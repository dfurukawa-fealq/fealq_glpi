<?php

require_once __DIR__ . '/event_alerts.php';
require_once __DIR__ . '/sla_monitor.php';
require_once __DIR__ . '/task_worker.php';
require_once __DIR__ . '/messaging/notification_dispatcher.php';

/**
 * Engine Dinâmica de Regras (PLAN-20260703-008): 1 regra -> N ações -> N destinatários,
 * lida do banco em vez de código fixo. Constrói sobre a infraestrutura já testada do
 * PLAN-20260702-007 (SLA/TaskWorker, dispatcher multi-canal, dedupe por evento) em vez
 * de substituí-la — os 3 workers fixos do orquestrador continuam rodando sem alteração.
 *
 * Escopo (ver ADENDO do plano):
 * - trigger_type é um enum FECHADO mapeado para handlers PHP revisados — nenhuma regra
 *   aceita SQL livre.
 * - 'solution_pending' fica FORA desta engine: seu valor é notificar o telefone do
 *   SOLICITANTE de cada chamado (resolvido dinamicamente por ticket), o que não se encaixa
 *   no modelo de destinatários ESTÁTICOS por regra (specific_webhook/specific_number/
 *   specific_chat) — continua exclusivamente como o worker fixo `inc/solution_worker.php`.
 * - recipient_type é derivado do action_type (1:1), não um campo livre: evita misturar
 *   URL/telefone/chat id na mesma coluna sem tipagem, e evita os tipos 'technical_team'/
 *   'client_vip' do prompt original, cuja fonte de resolução nunca foi definida.
 */

const DASHGLPI_RULES_TABLE = 'glpi_plugin_dashglpi_rules';
const DASHGLPI_RULE_ACTIONS_TABLE = 'glpi_plugin_dashglpi_rule_actions';
const DASHGLPI_RULE_RECIPIENTS_TABLE = 'glpi_plugin_dashglpi_rule_recipients';
/** PLAN-20260714-022 (Sub-fase 1): catálogo de credenciais de canal nomeadas/reaproveitáveis. */
const DASHGLPI_CHANNEL_CREDENTIALS_TABLE = 'glpi_plugin_dashglpi_channel_credentials';
/** PLAN-20260714-023: id externo do usuário GLPI por canal (Telegram user_id, futuramente AAD id do Teams). */
const DASHGLPI_USER_CHANNEL_IDS_TABLE = 'glpi_plugin_dashglpi_user_channel_ids';

// 'new_task_problem'/'new_task_change': PLAN-20260709-019 (Fase E) — mesma semântica
// de 'new_task', varrendo glpi_problemtasks/glpi_changetasks via registry ITIL.
// 'ticket_assigned': PLAN-20260713-020 — chamado atribuído a um técnico.
const DASHGLPI_RULE_TRIGGER_TYPES = ['sla_breach', 'new_task', 'new_task_problem', 'new_task_change', 'glpi_action_log', 'ticket_assigned'];

/**
 * PLAN-20260713-020: id_search_option/linked_action de glpi_logs para "Técnico
 * atribuído" em Ticket, confirmados por spike T0 contra a base real (GLPI 11.0.7) —
 * mesma ressalva de compatibilidade entre versões já registrada para glpi_action_log/
 * glpi_crontasklogs. linked_action=15 = Log::HISTORY_ADD_RELATION (vínculo de ator
 * adicionado); id_search_option=5 = campo "Técnico" do Ticket.
 */
const DASHGLPI_TICKET_ASSIGNED_SEARCH_OPTION = 5;
const DASHGLPI_TICKET_ASSIGNED_LINKED_ACTION = 15;

/** Gatilhos que compartilham a janela (e o teto) do new_task original. */
const DASHGLPI_RULE_NEW_TASK_TRIGGERS = [
    'new_task' => 'ticket',
    'new_task_problem' => 'problem',
    'new_task_change' => 'change',
];
const DASHGLPI_RULE_ACTION_TYPES = ['teams', 'whatsapp', 'telegram'];

/** action_type -> recipient_type (1:1, ver nota de escopo acima). */
const DASHGLPI_RULE_RECIPIENT_TYPE_BY_ACTION = [
    'teams' => 'specific_webhook',
    'whatsapp' => 'specific_number',
    'telegram' => 'specific_chat',
];

/**
 * Catálogo de variáveis de substituição disponíveis em message_template (PLAN-20260703-010).
 * Única fonte de verdade: usada tanto para renderizar (dashglpi_rule_render_message_template)
 * quanto para listar variáveis disponíveis na UI (catalog.template_variables do dataset).
 * 'field' é a chave correspondente no array $alert (ver channel_interface.php).
 */
const DASHGLPI_RULE_TEMPLATE_VARIABLES = [
    'ticket_id' => ['field' => 'ticket_id', 'label' => 'ID do chamado', 'example' => '1234'],
    'ticket_title' => ['field' => 'title', 'label' => 'Título do chamado', 'example' => 'Impressora não liga'],
    'category' => ['field' => 'category', 'label' => 'Categoria', 'example' => 'Hardware'],
    'entity_name' => ['field' => 'entity_name', 'label' => 'Entidade', 'example' => 'Fealq'],
    'minutes_overdue' => ['field' => 'minutes_overdue', 'label' => 'Minutos sem ação', 'example' => '20'],
    'threshold' => ['field' => 'threshold', 'label' => 'Limite configurado (min)', 'example' => '2'],
    'url_glpi' => ['field' => 'url', 'label' => 'Link do chamado no GLPI', 'example' => 'https://glpi.exemplo/front/ticket.form.php?id=1234'],
    'url_dash' => ['field' => 'dashboard_url', 'label' => 'Link do chamado no DashGLPI', 'example' => 'https://dashglpi.exemplo/front/dashboard.php?ticket_id=1234#tickets'],
    'event_at' => ['field' => 'created_at_formatted', 'label' => 'Data/hora do evento', 'example' => '04/07/2026 14:30'],
    // PLAN-20260713-020: preenchidos apenas em eventos com técnico atribuído (hoje,
    // só 'ticket_assigned') — vazio para os demais triggers, sem quebrar template.
    'assigned_to_name' => ['field' => 'assigned_to_name', 'label' => 'Técnico atribuído', 'example' => 'Maria Silva'],
    'assigned_to_email' => ['field' => 'assigned_to_email', 'label' => 'E-mail do técnico atribuído', 'example' => 'maria.silva@empresa.com'],
];

/**
 * Teto duro (defesa em profundidade, PLAN-20260704-014) para o `trigger_value` de regras
 * `new_task`: mesmo com a janela de dashglpi_task_worker_candidates() já limitada nos dois
 * lados, nada impedia o usuário de configurar uma janela de dias/semanas via UI de regras,
 * recriando o problema de "pegar tickets velhos" por configuração em vez de código.
 */
const DASHGLPI_RULE_NEW_TASK_MAX_WINDOW_MINUTES = 1440;

/** Limites de tamanho do template (mitiga payload rejeitado/truncado pelo Teams — ver Fase 3/Riscos do plano). */
const DASHGLPI_RULE_TEMPLATE_MAX_LINES = 12;
const DASHGLPI_RULE_TEMPLATE_MAX_LINE_LENGTH = 300;

/**
 * Allowlist de hosts para destinatários 'specific_webhook' (decisão D1 do ADENDO —
 * mitiga SSRF interno: sem isso, qualquer usuário com acesso à tela de regras poderia
 * fazer o worker enviar POST para qualquer URL arbitrária na fealq-network).
 * Validada tanto na escrita (ajax/rules_config.php) quanto na leitura (dispatch), para
 * que uma allowlist reduzida depois de uma regra já salva também seja respeitada.
 */
const DASHGLPI_WEBHOOK_HOST_ALLOWLIST = [
    'kawa_evolutionapi',
    '*.office.com',
    '*.webhook.office.com',
    '*.logic.azure.com',
    '*.environment.api.powerplatform.com',
];

function dashglpi_rules_ensure_tables(): void
{
    $pdo = dashglpi_db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_RULES_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            trigger_type VARCHAR(50) NOT NULL,
            trigger_value INT NOT NULL DEFAULT 15,
            trigger_config JSON NULL,
            message_template JSON NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_RULE_ACTIONS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_id INT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dashglpi_rule_actions_rule (rule_id),
            CONSTRAINT fk_dashglpi_rule_actions_rule FOREIGN KEY (rule_id)
                REFERENCES " . DASHGLPI_RULES_TABLE . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_RULE_RECIPIENTS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_id INT UNSIGNED NOT NULL,
            recipient_type VARCHAR(50) NOT NULL,
            recipient_value VARCHAR(1024) NOT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dashglpi_rule_recipients_action (action_id),
            CONSTRAINT fk_dashglpi_rule_recipients_action FOREIGN KEY (action_id)
                REFERENCES " . DASHGLPI_RULE_ACTIONS_TABLE . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Coluna informativa (não faz parte da chave de dedupe — ver nota em
    // dashglpi_rule_event_type_key()) para permitir filtrar/auditar alertas por regra.
    $hasRuleIdColumn = dashglpi_fetch_one(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'rule_id'",
        [DASHGLPI_EVENT_ALERTS_TABLE]
    );
    if (!$hasRuleIdColumn) {
        $pdo->exec(
            "ALTER TABLE " . DASHGLPI_EVENT_ALERTS_TABLE . "
             ADD COLUMN rule_id INT UNSIGNED NULL DEFAULT NULL AFTER event_id,
             ADD KEY idx_dashglpi_event_alert_rule (rule_id)"
        );
    }

    // recipient_value começou em VARCHAR(255); URLs de webhook assinadas (ex.: Power
    // Automate/Logic Apps com SAS token no querystring) passam disso — alarga para
    // instalações já existentes, sem perder dado (MODIFY não trunca ao aumentar).
    $recipientValueLength = dashglpi_fetch_one(
        "SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'recipient_value'",
        [DASHGLPI_RULE_RECIPIENTS_TABLE]
    );
    if ($recipientValueLength && (int) $recipientValueLength['len'] < 1024) {
        $pdo->exec(
            "ALTER TABLE " . DASHGLPI_RULE_RECIPIENTS_TABLE . "
             MODIFY COLUMN recipient_value VARCHAR(1024) NOT NULL"
        );
    }

    // message_template (PLAN-20260703-010): coluna nova para instalações já existentes.
    $hasMessageTemplateColumn = dashglpi_fetch_one(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'message_template'",
        [DASHGLPI_RULES_TABLE]
    );
    if (!$hasMessageTemplateColumn) {
        $pdo->exec(
            "ALTER TABLE " . DASHGLPI_RULES_TABLE . "
             ADD COLUMN message_template JSON NULL AFTER trigger_config"
        );
    }

    // PLAN-20260714-022 (Sub-fase 1): catálogo de credenciais nomeadas por canal +
    // vínculo opcional em rule_actions. Aditivo — credential_id nullable, ON DELETE
    // SET NULL (apagar credencial não derruba a ação, só volta ao default global).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_type VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            secret_data JSON NOT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_credential_name (action_type, name),
            KEY idx_dashglpi_credential_action (action_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $hasCredentialIdColumn = dashglpi_fetch_one(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'credential_id'",
        [DASHGLPI_RULE_ACTIONS_TABLE]
    );
    if (!$hasCredentialIdColumn) {
        $pdo->exec(
            "ALTER TABLE " . DASHGLPI_RULE_ACTIONS_TABLE . "
             ADD COLUMN credential_id INT UNSIGNED NULL AFTER action_type,
             ADD KEY idx_dashglpi_rule_actions_credential (credential_id),
             ADD CONSTRAINT fk_dashglpi_rule_actions_credential FOREIGN KEY (credential_id)
                 REFERENCES " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " (id) ON DELETE SET NULL"
        );
    }

    foreach (DASHGLPI_RULE_ACTION_TYPES as $actionType) {
        dashglpi_channel_credentials_ensure_default($actionType);
    }

    // PLAN-20260714-023: id externo do usuário GLPI por canal (hoje só Telegram),
    // para menção real do técnico atribuído. users_id SEM FOREIGN KEY real — mesmo
    // precedente já usado no log da SQL console (cross-schema com glpi_users não é
    // garantido entre upgrades do GLPI core).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_USER_CHANNEL_IDS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            users_id INT UNSIGNED NOT NULL,
            channel VARCHAR(50) NOT NULL,
            external_id VARCHAR(190) NOT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_user_channel (users_id, channel),
            KEY idx_dashglpi_user_channel_users (users_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * PLAN-20260714-022 (Sub-fase 1): migração automática e idempotente — se ainda não
 * existe nenhuma credencial nomeada para o canal e a config legada ('alerting', a
 * mesma lida por dashglpi_alerting_config()) tem valor não vazio, cria 1 credencial
 * "padrão (migrada)" a partir dela. Dá nome/gerenciamento ao que já existia sem exigir
 * que o usuário recadastre token algum. Idempotente: só roda se a tabela ainda não tem
 * nenhuma linha para o action_type (chamada a cada dashglpi_rules_ensure_tables()).
 */
function dashglpi_channel_credentials_ensure_default(string $actionType): void
{
    $exists = dashglpi_fetch_one(
        "SELECT id FROM " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " WHERE action_type = ? LIMIT 1",
        [$actionType]
    );
    if ($exists) {
        return;
    }

    $config = dashglpi_alerting_config();
    $secretData = match ($actionType) {
        'teams' => ['webhook_url' => (string) ($config['teams']['webhook_url'] ?? '')],
        'whatsapp' => [
            'api_url' => (string) ($config['whatsapp']['api_url'] ?? ''),
            'api_key' => (string) ($config['whatsapp']['api_key'] ?? ''),
            'instance' => (string) ($config['whatsapp']['instance'] ?? ''),
        ],
        'telegram' => ['bot_token' => (string) ($config['telegram']['bot_token'] ?? '')],
        default => [],
    };

    $hasValue = array_filter($secretData, static function ($value) {
        return trim((string) $value) !== '';
    });
    if (!$hasValue) {
        return;
    }

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " (action_type, name, is_default, secret_data)
         VALUES (?, ?, 1, ?)"
    );
    $stmt->execute([
        $actionType,
        'Credencial padrão (migrada)',
        json_encode($secretData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * PLAN-20260714-022 (Sub-fase 1): lê 1 credencial nomeada, decodificando secret_data.
 * Retorna null se não existir (chamador deve cair de volta pro default global).
 */
function dashglpi_channel_credential_get(int $id): ?array
{
    $row = dashglpi_fetch_one(
        "SELECT * FROM " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " WHERE id = ? LIMIT 1",
        [$id]
    );
    if (!$row) {
        return null;
    }

    $secretData = json_decode((string) ($row['secret_data'] ?? ''), true);
    $row['secret_data'] = is_array($secretData) ? $secretData : [];

    return $row;
}

// ---------------------------------------------------------------------------
// Validação de destinatários (D1 + tipagem por action_type)
// ---------------------------------------------------------------------------

function dashglpi_rule_webhook_host_allowed(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return false;
    }

    foreach (DASHGLPI_WEBHOOK_HOST_ALLOWLIST as $pattern) {
        $pattern = strtolower($pattern);
        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // ".office.com"
            if ($host === substr($suffix, 1) || str_ends_with($host, $suffix)) {
                return true;
            }
            continue;
        }
        if ($host === $pattern) {
            return true;
        }
    }

    return false;
}

/**
 * Valida e normaliza recipient_value conforme o recipient_type (derivado do
 * action_type). Lança RuntimeException com mensagem amigável em caso de erro.
 */
function dashglpi_rule_validate_recipient_value(string $recipientType, string $value): string
{
    switch ($recipientType) {
        case 'specific_webhook':
            $url = dashglpi_alerting_url($value);
            if ($url === '') {
                throw new RuntimeException('URL de webhook inválida (use http:// ou https://).');
            }
            $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
            if (!dashglpi_rule_webhook_host_allowed($host)) {
                throw new RuntimeException(
                    'Host do webhook não está na allowlist permitida: "' . $host . '". '
                    . 'Hosts aceitos: ' . implode(', ', DASHGLPI_WEBHOOK_HOST_ALLOWLIST) . '.'
                );
            }
            return $url;

        case 'specific_number':
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if ($digits === '' || strlen($digits) < 10) {
                throw new RuntimeException('Número de WhatsApp inválido — use DDI+DDD+número, só dígitos.');
            }
            return $digits;

        case 'specific_chat':
            $id = trim($value);
            if (!preg_match('/^-?\d+$/', $id)) {
                throw new RuntimeException('Chat ID do Telegram inválido.');
            }
            return $id;

        default:
            throw new RuntimeException('Tipo de destinatário desconhecido: ' . $recipientType);
    }
}

function dashglpi_rule_recipient_type_for_action(string $actionType): string
{
    $type = DASHGLPI_RULE_RECIPIENT_TYPE_BY_ACTION[$actionType] ?? null;
    if ($type === null) {
        throw new RuntimeException('action_type desconhecido: ' . $actionType);
    }
    return $type;
}

// ---------------------------------------------------------------------------
// CRUD
// ---------------------------------------------------------------------------

function dashglpi_rules_list(): array
{
    dashglpi_rules_ensure_tables();

    $rules = dashglpi_fetch_all(
        "SELECT * FROM " . DASHGLPI_RULES_TABLE . " ORDER BY id ASC"
    );

    foreach ($rules as &$rule) {
        $rule['trigger_config'] = dashglpi_rule_decode_config($rule['trigger_config'] ?? null);
        $rule['message_template'] = dashglpi_rule_decode_config($rule['message_template'] ?? null);
        $rule['actions'] = dashglpi_rule_actions_list((int) $rule['id']);
    }
    unset($rule);

    return $rules;
}

function dashglpi_rule_get(int $ruleId): ?array
{
    dashglpi_rules_ensure_tables();

    $rule = dashglpi_fetch_one(
        "SELECT * FROM " . DASHGLPI_RULES_TABLE . " WHERE id = ? LIMIT 1",
        [$ruleId]
    );
    if (!$rule) {
        return null;
    }

    $rule['trigger_config'] = dashglpi_rule_decode_config($rule['trigger_config'] ?? null);
    $rule['message_template'] = dashglpi_rule_decode_config($rule['message_template'] ?? null);
    $rule['actions'] = dashglpi_rule_actions_list($ruleId);

    return $rule;
}

function dashglpi_rule_decode_config($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}

// ---------------------------------------------------------------------------
// Template de mensagem por regra (PLAN-20260703-010)
// ---------------------------------------------------------------------------

/**
 * Valida o payload de message_template vindo do formulário. `null`/lista vazia é válido e
 * significa "sem template" (fallback para o texto hardcoded de cada canal). Falha cedo (mesma
 * filosofia da allowlist de webhook) em vez de deixar {var: x} desconhecido vazar pro canal.
 */
function dashglpi_rule_validate_message_template($data): ?array
{
    if ($data === null || $data === '') {
        return null;
    }

    $decoded = is_array($data) ? $data : json_decode((string) $data, true);
    if (!is_array($decoded) || !array_key_exists('lines', $decoded) || !is_array($decoded['lines'])) {
        throw new RuntimeException('message_template inválido — formato esperado: {"lines": ["..."]}.');
    }

    if (count($decoded['lines']) > DASHGLPI_RULE_TEMPLATE_MAX_LINES) {
        throw new RuntimeException(
            'message_template excede o limite de ' . DASHGLPI_RULE_TEMPLATE_MAX_LINES . ' linhas.'
        );
    }

    $cleanLines = [];
    foreach ($decoded['lines'] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strlen($line) > DASHGLPI_RULE_TEMPLATE_MAX_LINE_LENGTH) {
            throw new RuntimeException(
                'Uma linha do message_template excede o limite de '
                . DASHGLPI_RULE_TEMPLATE_MAX_LINE_LENGTH . ' caracteres.'
            );
        }
        if (preg_match_all('/\{var:\s*([a-zA-Z_]+)\s*\}/', $line, $matches)) {
            foreach ($matches[1] as $varName) {
                if (!isset(DASHGLPI_RULE_TEMPLATE_VARIABLES[strtolower($varName)])) {
                    throw new RuntimeException(
                        'Variável desconhecida no template: {var: ' . $varName . '}. Variáveis aceitas: '
                        . implode(', ', array_keys(DASHGLPI_RULE_TEMPLATE_VARIABLES)) . '.'
                    );
                }
            }
        }
        $cleanLines[] = $line;
    }

    return $cleanLines === [] ? null : ['lines' => $cleanLines];
}

/**
 * Escapa um valor de variável conforme as regras de formatação de cada canal — espelha
 * exatamente os escapeMd() privados já existentes em teams_channel.php/telegram_channel.php
 * (não alterados por este plano), para que a substituição de variáveis não quebre o
 * Markdown/MessageCard com caracteres especiais vindos de dado de terceiro (ex.: título de
 * chamado digitado pelo requerente).
 */
function dashglpi_rule_escape_for_channel(string $text, string $channelKey): string
{
    return match ($channelKey) {
        'teams' => str_replace(['*', '_', '#'], ['\\*', '\\_', '\\#'], $text),
        'telegram' => str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text),
        default => $text, // whatsapp: texto puro, sem escape (mesmo comportamento atual do canal)
    };
}

/**
 * Renderiza as linhas do template substituindo {var: nome} pelos valores do $alert, escapados
 * por canal. Retorna null quando não há template (sinaliza aos canais para usar o texto
 * hardcoded atual — fallback obrigatório, zero regressão para regras sem template).
 */
function dashglpi_rule_render_message_template(?array $template, array $alert, string $channelKey): ?array
{
    $lines = is_array($template['lines'] ?? null) ? $template['lines'] : null;
    if (!$lines) {
        return null;
    }

    return array_map(function (string $line) use ($alert, $channelKey) {
        return preg_replace_callback('/\{var:\s*([a-zA-Z_]+)\s*\}/', function ($m) use ($alert, $channelKey) {
            $varName = strtolower($m[1]);
            $meta = DASHGLPI_RULE_TEMPLATE_VARIABLES[$varName] ?? null;
            if ($meta === null) {
                return $m[0];
            }
            $value = (string) ($alert[$meta['field']] ?? '');
            return dashglpi_rule_escape_for_channel($value, $channelKey);
        }, $line);
    }, $lines);
}

function dashglpi_rule_actions_list(int $ruleId): array
{
    $actions = dashglpi_fetch_all(
        "SELECT * FROM " . DASHGLPI_RULE_ACTIONS_TABLE . " WHERE rule_id = ? ORDER BY id ASC",
        [$ruleId]
    );

    foreach ($actions as &$action) {
        $action['recipients'] = dashglpi_rule_recipients_list((int) $action['id']);
    }
    unset($action);

    return $actions;
}

function dashglpi_rule_recipients_list(int $actionId): array
{
    return dashglpi_fetch_all(
        "SELECT * FROM " . DASHGLPI_RULE_RECIPIENTS_TABLE . " WHERE action_id = ? ORDER BY id ASC",
        [$actionId]
    );
}

/** @return int id da regra criada */
function dashglpi_rule_create(array $data): int
{
    dashglpi_rules_ensure_tables();

    [$name, $triggerType, $triggerValue, $isActive, $triggerConfigJson, $messageTemplateJson] = dashglpi_rule_normalize_input($data);

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_RULES_TABLE . " (name, is_active, trigger_type, trigger_value, trigger_config, message_template)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $isActive, $triggerType, $triggerValue, $triggerConfigJson, $messageTemplateJson]);

    return (int) dashglpi_db()->lastInsertId();
}

function dashglpi_rule_update(int $ruleId, array $data): void
{
    dashglpi_rules_ensure_tables();

    if (!dashglpi_rule_get($ruleId)) {
        throw new RuntimeException('Regra não encontrada.');
    }

    [$name, $triggerType, $triggerValue, $isActive, $triggerConfigJson, $messageTemplateJson] = dashglpi_rule_normalize_input($data);

    $stmt = dashglpi_db()->prepare(
        "UPDATE " . DASHGLPI_RULES_TABLE . "
         SET name = ?, is_active = ?, trigger_type = ?, trigger_value = ?, trigger_config = ?, message_template = ?
         WHERE id = ?"
    );
    $stmt->execute([$name, $isActive, $triggerType, $triggerValue, $triggerConfigJson, $messageTemplateJson, $ruleId]);
}

function dashglpi_rule_set_active(int $ruleId, bool $active): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare("UPDATE " . DASHGLPI_RULES_TABLE . " SET is_active = ? WHERE id = ?");
    $stmt->execute([$active ? 1 : 0, $ruleId]);
}

function dashglpi_rule_delete(int $ruleId): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare("DELETE FROM " . DASHGLPI_RULES_TABLE . " WHERE id = ?");
    $stmt->execute([$ruleId]);
}

/** @return array{0:string,1:string,2:int,3:int,4:?string,5:?string} */
function dashglpi_rule_normalize_input(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Nome da regra é obrigatório.');
    }
    $name = substr($name, 0, 150);

    $triggerType = (string) ($data['trigger_type'] ?? '');
    if (!in_array($triggerType, DASHGLPI_RULE_TRIGGER_TYPES, true)) {
        throw new RuntimeException('trigger_type inválido. Valores aceitos: ' . implode(', ', DASHGLPI_RULE_TRIGGER_TYPES));
    }

    $triggerValue = max(1, (int) ($data['trigger_value'] ?? 15));
    if (isset(DASHGLPI_RULE_NEW_TASK_TRIGGERS[$triggerType])) {
        $triggerValue = min($triggerValue, DASHGLPI_RULE_NEW_TASK_MAX_WINDOW_MINUTES);
    }
    $isActive = !empty($data['is_active']) ? 1 : 0;

    $triggerConfig = null;
    if ($triggerType === 'glpi_action_log') {
        $triggerConfig = dashglpi_rule_validate_action_log_config(is_array($data['trigger_config'] ?? null) ? $data['trigger_config'] : []);
    }
    $triggerConfigJson = $triggerConfig !== null
        ? json_encode($triggerConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $messageTemplate = dashglpi_rule_validate_message_template($data['message_template'] ?? null);
    $messageTemplateJson = $messageTemplate !== null
        ? json_encode($messageTemplate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    return [$name, $triggerType, $triggerValue, $isActive, $triggerConfigJson, $messageTemplateJson];
}

function dashglpi_rule_validate_action_log_config(array $config): array
{
    $crontasksId = max(0, (int) ($config['crontasks_id'] ?? 0));
    if ($crontasksId <= 0) {
        throw new RuntimeException('trigger_config.crontasks_id é obrigatório para o gatilho glpi_action_log.');
    }

    $mode = (string) ($config['mode'] ?? 'failure');
    if (!in_array($mode, ['failure', 'every', 'duration_over'], true)) {
        throw new RuntimeException('trigger_config.mode inválido (use failure, every ou duration_over).');
    }

    $durationThreshold = max(1, (int) ($config['duration_threshold_s'] ?? 30));

    return [
        'crontasks_id' => $crontasksId,
        'mode' => $mode,
        'duration_threshold_s' => $durationThreshold,
    ];
}

function dashglpi_rule_action_create(int $ruleId, string $actionType): int
{
    dashglpi_rules_ensure_tables();

    if (!in_array($actionType, DASHGLPI_RULE_ACTION_TYPES, true)) {
        throw new RuntimeException('action_type inválido. Valores aceitos: ' . implode(', ', DASHGLPI_RULE_ACTION_TYPES));
    }
    if (!dashglpi_rule_get($ruleId)) {
        throw new RuntimeException('Regra não encontrada.');
    }

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_RULE_ACTIONS_TABLE . " (rule_id, action_type) VALUES (?, ?)"
    );
    $stmt->execute([$ruleId, $actionType]);

    return (int) dashglpi_db()->lastInsertId();
}

function dashglpi_rule_action_delete(int $actionId): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare("DELETE FROM " . DASHGLPI_RULE_ACTIONS_TABLE . " WHERE id = ?");
    $stmt->execute([$actionId]);
}

function dashglpi_rule_recipient_create(int $actionId, string $recipientValue): int
{
    dashglpi_rules_ensure_tables();

    $action = dashglpi_fetch_one(
        "SELECT * FROM " . DASHGLPI_RULE_ACTIONS_TABLE . " WHERE id = ? LIMIT 1",
        [$actionId]
    );
    if (!$action) {
        throw new RuntimeException('Ação não encontrada.');
    }

    $recipientType = dashglpi_rule_recipient_type_for_action((string) $action['action_type']);
    $normalizedValue = dashglpi_rule_validate_recipient_value($recipientType, $recipientValue);

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_RULE_RECIPIENTS_TABLE . " (action_id, recipient_type, recipient_value)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$actionId, $recipientType, $normalizedValue]);

    return (int) dashglpi_db()->lastInsertId();
}

function dashglpi_rule_recipient_delete(int $recipientId): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare("DELETE FROM " . DASHGLPI_RULE_RECIPIENTS_TABLE . " WHERE id = ?");
    $stmt->execute([$recipientId]);
}

function dashglpi_rule_action_set_credential(int $actionId, ?int $credentialId): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare(
        "UPDATE " . DASHGLPI_RULE_ACTIONS_TABLE . " SET credential_id = ? WHERE id = ?"
    );
    $stmt->execute([$credentialId, $actionId]);
}

// ---------------------------------------------------------------------------
// PLAN-20260714-022 (Sub-fase 2) — CRUD do catálogo de credenciais nomeadas.
// ---------------------------------------------------------------------------

/** Mascara segredo (mesmo contrato de dashglpi_messaging_public_config): nunca reenvia o valor. */
function dashglpi_channel_credential_public(array $credential): array
{
    $secret = is_array($credential['secret_data'] ?? null) ? $credential['secret_data'] : [];
    $actionType = (string) $credential['action_type'];

    $publicSecret = match ($actionType) {
        'teams' => ['webhook_url' => (string) ($secret['webhook_url'] ?? '')],
        'whatsapp' => [
            'api_url' => (string) ($secret['api_url'] ?? ''),
            'api_key_set' => trim((string) ($secret['api_key'] ?? '')) !== '',
            'instance' => (string) ($secret['instance'] ?? ''),
        ],
        'telegram' => ['bot_token_set' => trim((string) ($secret['bot_token'] ?? '')) !== ''],
        default => [],
    };

    return [
        'id' => (int) $credential['id'],
        'action_type' => $actionType,
        'name' => (string) $credential['name'],
        'is_default' => !empty($credential['is_default']),
        'usage_count' => (int) ($credential['usage_count'] ?? 0),
        'secret' => $publicSecret,
    ];
}

/** Lista o catálogo (opcionalmente filtrado por canal), com contagem de uso (Fase 3 §1 do plano). */
function dashglpi_channel_credentials_list(?string $actionType = null): array
{
    dashglpi_rules_ensure_tables();

    $sql = "SELECT c.*, (
                SELECT COUNT(*) FROM " . DASHGLPI_RULE_ACTIONS_TABLE . " a WHERE a.credential_id = c.id
            ) AS usage_count
            FROM " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " c";
    $params = [];
    if ($actionType !== null) {
        $sql .= " WHERE c.action_type = ?";
        $params[] = $actionType;
    }
    $sql .= " ORDER BY FIELD(c.action_type, 'teams', 'whatsapp', 'telegram'), c.is_default DESC, c.name ASC";

    $rows = dashglpi_fetch_all($sql, $params);

    return array_map(function (array $row): array {
        $secretData = json_decode((string) ($row['secret_data'] ?? ''), true);
        $row['secret_data'] = is_array($secretData) ? $secretData : [];
        return dashglpi_channel_credential_public($row);
    }, $rows);
}

/**
 * Cria/atualiza uma credencial nomeada. Segredo vazio no payload preserva o valor
 * atual (mesmo contrato de preservação já usado em ajax/messaging_config.php).
 */
function dashglpi_channel_credential_save(array $payload): int
{
    dashglpi_rules_ensure_tables();

    $id = (int) ($payload['id'] ?? 0);
    $actionType = (string) ($payload['action_type'] ?? '');
    $name = trim((string) ($payload['name'] ?? ''));
    $isDefault = !empty($payload['is_default']);

    if (!in_array($actionType, DASHGLPI_RULE_ACTION_TYPES, true)) {
        throw new RuntimeException('Tipo de ação inválido.');
    }
    if ($name === '') {
        throw new RuntimeException('Informe um nome para a credencial.');
    }

    $current = $id > 0 ? dashglpi_channel_credential_get($id) : null;
    if ($id > 0 && $current === null) {
        throw new RuntimeException('Credencial não encontrada.');
    }
    $currentSecret = $current['secret_data'] ?? [];

    $secretData = match ($actionType) {
        'teams' => [
            'webhook_url' => trim((string) ($payload['webhook_url'] ?? '')),
        ],
        'whatsapp' => [
            'api_url' => trim((string) ($payload['api_url'] ?? '')),
            'api_key' => trim((string) ($payload['api_key'] ?? '')) !== ''
                ? trim((string) $payload['api_key'])
                : (string) ($currentSecret['api_key'] ?? ''),
            'instance' => trim((string) ($payload['instance'] ?? '')),
        ],
        'telegram' => [
            'bot_token' => trim((string) ($payload['bot_token'] ?? '')) !== ''
                ? trim((string) $payload['bot_token'])
                : (string) ($currentSecret['bot_token'] ?? ''),
        ],
        default => [],
    };

    $json = json_encode($secretData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo = dashglpi_db();

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . "
                 SET action_type = ?, name = ?, secret_data = ? WHERE id = ?"
            );
            $stmt->execute([$actionType, $name, $json, $id]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " (action_type, name, is_default, secret_data)
                 VALUES (?, ?, 0, ?)"
            );
            $stmt->execute([$actionType, $name, $json]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            throw new RuntimeException('Já existe uma credencial com esse nome para este canal.');
        }
        throw $e;
    }

    if ($isDefault) {
        $pdo->prepare(
            "UPDATE " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " SET is_default = 0 WHERE action_type = ? AND id != ?"
        )->execute([$actionType, $id]);
        $pdo->prepare(
            "UPDATE " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " SET is_default = 1 WHERE id = ?"
        )->execute([$id]);
    }

    return $id;
}

/** Apagar não derruba ações que a usam (credential_id ON DELETE SET NULL) — só o vínculo. */
function dashglpi_channel_credential_delete(int $id): void
{
    dashglpi_rules_ensure_tables();
    $stmt = dashglpi_db()->prepare("DELETE FROM " . DASHGLPI_CHANNEL_CREDENTIALS_TABLE . " WHERE id = ?");
    $stmt->execute([$id]);
}

// ---------------------------------------------------------------------------
// PLAN-20260714-023 — id externo do usuário GLPI por canal (menção real no Telegram).
// ---------------------------------------------------------------------------

/** @return ?string external_id cadastrado, ou null se o usuário não tem id para esse canal. */
function dashglpi_user_channel_id_get(int $usersId, string $channel): ?string
{
    dashglpi_rules_ensure_tables();
    $row = dashglpi_fetch_one(
        "SELECT external_id FROM " . DASHGLPI_USER_CHANNEL_IDS_TABLE . "
         WHERE users_id = ? AND channel = ? LIMIT 1",
        [$usersId, $channel]
    );

    return $row ? (string) $row['external_id'] : null;
}

/** @return array<int,string> users_id => external_id, para pré-preencher listas/formulários. */
function dashglpi_user_channel_ids_map(string $channel): array
{
    dashglpi_rules_ensure_tables();
    $rows = dashglpi_fetch_all(
        "SELECT users_id, external_id FROM " . DASHGLPI_USER_CHANNEL_IDS_TABLE . " WHERE channel = ?",
        [$channel]
    );

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['users_id']] = (string) $row['external_id'];
    }

    return $map;
}

/** Upsert — 1 external_id por (usuário, canal). externalId vazio remove o vínculo. */
function dashglpi_user_channel_id_set(int $usersId, string $channel, string $externalId): void
{
    dashglpi_rules_ensure_tables();
    $externalId = trim($externalId);

    if ($externalId === '') {
        $stmt = dashglpi_db()->prepare(
            "DELETE FROM " . DASHGLPI_USER_CHANNEL_IDS_TABLE . " WHERE users_id = ? AND channel = ?"
        );
        $stmt->execute([$usersId, $channel]);

        return;
    }

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_USER_CHANNEL_IDS_TABLE . " (users_id, channel, external_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE external_id = VALUES(external_id)"
    );
    $stmt->execute([$usersId, $channel, $externalId]);
}

// ---------------------------------------------------------------------------
// Motor de execução (chamado pelo orquestrador CLI)
// ---------------------------------------------------------------------------

/**
 * Chave de dedupe namespaced por regra e tipo de gatilho. Evita alterar a UNIQUE KEY
 * já validada em produção de glpi_plugin_dashglpi_event_alerts (event_type,event_id,level):
 * ao invés disso, o rule_id entra codificado no próprio event_type, o que dá o mesmo
 * resultado funcional (duas regras diferentes sobre o mesmo evento não colidem) sem
 * risco de alterar uma constraint existente. A coluna rule_id (ver
 * dashglpi_rules_ensure_tables) é só para auditoria/filtro, não faz parte da dedupe.
 */
function dashglpi_rule_event_type_key(int $ruleId, string $triggerType): string
{
    return 'rule_' . $ruleId . '_' . $triggerType;
}

/**
 * @return array{scanned:int,dispatched:int,errors:string[]}
 */
function dashglpi_rule_engine_run(bool $dryRun = false): array
{
    dashglpi_rules_ensure_tables();
    dashglpi_event_alerts_ensure_table();

    $rules = dashglpi_fetch_all(
        "SELECT * FROM " . DASHGLPI_RULES_TABLE . " WHERE is_active = 1 ORDER BY id ASC LIMIT 200"
    );
    $dispatcher = new DashglpiNotificationDispatcher();
    $result = ['scanned' => 0, 'dispatched' => 0, 'errors' => []];

    foreach ($rules as $rule) {
        $ruleId = (int) $rule['id'];
        $triggerType = (string) $rule['trigger_type'];
        $rule['trigger_config'] = dashglpi_rule_decode_config($rule['trigger_config'] ?? null);
        $messageTemplate = dashglpi_rule_decode_config($rule['message_template'] ?? null) ?: null;

        if (!in_array($triggerType, DASHGLPI_RULE_TRIGGER_TYPES, true)) {
            $result['errors'][] = "Regra #$ruleId: trigger_type desconhecido ($triggerType).";
            continue;
        }

        try {
            $candidates = dashglpi_rule_detect_candidates($rule);
        } catch (Throwable $e) {
            $result['errors'][] = "Regra #$ruleId [$triggerType]: " . $e->getMessage();
            continue;
        }

        $result['scanned'] += count($candidates);
        $eventTypeKey = dashglpi_rule_event_type_key($ruleId, $triggerType);
        $actions = dashglpi_rule_actions_list($ruleId);

        foreach ($candidates as $candidate) {
            $eventId = (int) $candidate['event_id'];
            if (dashglpi_event_alert_already_sent($eventTypeKey, $eventId, 'info')) {
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $alert = $candidate['alert'];
            $okChannels = [];

            foreach ($actions as $action) {
                $actionType = (string) $action['action_type'];
                $alertForChannel = $alert;
                $templateLines = dashglpi_rule_render_message_template($messageTemplate, $alert, $actionType);
                if ($templateLines !== null) {
                    $alertForChannel['template_lines'] = $templateLines;
                }

                foreach ($action['recipients'] as $recipient) {
                    try {
                        $config = dashglpi_rule_recipient_dispatch_config(
                            $actionType,
                            (string) $recipient['recipient_value'],
                            isset($action['credential_id']) && $action['credential_id'] !== null
                                ? (int) $action['credential_id']
                                : null
                        );
                    } catch (Throwable $e) {
                        $result['errors'][] = "Regra #$ruleId, ação #{$action['id']}: " . $e->getMessage();
                        continue;
                    }

                    $dispatch = $dispatcher->sendTo($actionType, $alertForChannel, $config);
                    if (!empty($dispatch['ok'])) {
                        $okChannels[] = (string) $action['action_type'];
                    } elseif (empty($dispatch['skipped']) && !empty($dispatch['error'])) {
                        $result['errors'][] = "Regra #$ruleId, evento #$eventId: " . $dispatch['error'];
                    }
                }
            }

            if ($okChannels) {
                dashglpi_event_alert_record($eventTypeKey, $eventId, 'info', array_unique($okChannels));
                dashglpi_rule_event_alert_set_rule_id($eventTypeKey, $eventId, $ruleId);
                $result['dispatched']++;
            }
        }
    }

    return $result;
}

function dashglpi_rule_event_alert_set_rule_id(string $eventType, int $eventId, int $ruleId): void
{
    $stmt = dashglpi_db()->prepare(
        "UPDATE " . DASHGLPI_EVENT_ALERTS_TABLE . " SET rule_id = ? WHERE event_type = ? AND event_id = ? AND level = 'info'"
    );
    $stmt->execute([$ruleId, $eventType, $eventId]);
}

/**
 * Resolve a config de dispatch para um destinatário específico, herdando a config
 * global de canais (URL da Evolution API, instância, etc.) e sobrepondo apenas o
 * alvo (webhook/número/chat) — mesmo padrão já usado em inc/task_worker.php e
 * inc/solution_worker.php.
 *
 * PLAN-20260714-022 (Sub-fase 1): quando a ação tem credential_id, a credencial
 * nomeada sobrepõe o bloco de infra (bot_token/api_key/instance/webhook_url) do
 * canal ANTES da sobreposição do destinatário abaixo — sem credencial, comportamento
 * idêntico ao anterior (cai no default global via dashglpi_alerting_config()).
 */
function dashglpi_rule_recipient_dispatch_config(string $actionType, string $value, ?int $credentialId = null): array
{
    $config = dashglpi_alerting_config();

    if ($credentialId !== null) {
        $credential = dashglpi_channel_credential_get($credentialId);
        if ($credential !== null && (string) $credential['action_type'] === $actionType) {
            $config[$actionType] = array_merge($config[$actionType], $credential['secret_data']);
        }
    }

    switch ($actionType) {
        case 'teams':
            $host = (string) (parse_url($value, PHP_URL_HOST) ?? '');
            if (!dashglpi_rule_webhook_host_allowed($host)) {
                // Defesa em profundidade (D1): mesmo que a regra já esteja salva, uma
                // allowlist reduzida depois bloqueia o dispatch em vez de fazer o POST.
                throw new RuntimeException('Webhook bloqueado pela allowlist no momento do disparo: ' . $host);
            }
            $config['teams'] = ['enabled' => true, 'webhook_url' => $value];
            break;

        case 'whatsapp':
            $config['whatsapp']['enabled'] = true;
            $config['whatsapp']['recipients'] = [$value];
            break;

        case 'telegram':
            $config['telegram']['enabled'] = true;
            $config['telegram']['chat_ids'] = [$value];
            break;

        default:
            throw new RuntimeException('action_type desconhecido: ' . $actionType);
    }

    return $config;
}

function dashglpi_rule_detect_candidates(array $rule): array
{
    return match ($rule['trigger_type']) {
        'sla_breach' => dashglpi_rule_detect_sla_breach($rule),
        'new_task' => dashglpi_rule_detect_new_task($rule),
        'new_task_problem' => dashglpi_rule_detect_new_task($rule, 'problem'),
        'new_task_change' => dashglpi_rule_detect_new_task($rule, 'change'),
        'glpi_action_log' => dashglpi_rule_detect_glpi_action_log($rule),
        'ticket_assigned' => dashglpi_rule_detect_ticket_assigned($rule),
        default => [],
    };
}

function dashglpi_rule_detect_sla_breach(array $rule): array
{
    $threshold = max(1, (int) $rule['trigger_value']);
    $tickets = dashglpi_sla_monitor_candidates($threshold);

    $out = [];
    foreach ($tickets as $ticket) {
        $age = (int) ($ticket['age_minutes'] ?? 0);
        if ($age < $threshold) {
            continue;
        }
        $out[] = [
            'event_id' => (int) $ticket['id'],
            'alert' => dashglpi_sla_monitor_build_alert($ticket, 'breach', $threshold, $age),
        ];
    }

    return $out;
}

function dashglpi_rule_detect_new_task(array $rule, string $typeKey = 'ticket'): array
{
    $windowMinutes = max(1, (int) ($rule['trigger_value'] ?: 120));
    $tasks = dashglpi_task_worker_candidates($windowMinutes, $typeKey);

    $out = [];
    foreach ($tasks as $task) {
        $out[] = [
            'event_id' => (int) $task['task_id'],
            'alert' => dashglpi_task_worker_build_alert($task, $typeKey),
        ];
    }

    return $out;
}

/**
 * PLAN-20260713-020: chamado atribuído a um técnico. Detecta via glpi_logs (registro
 * nativo do GLPI para "vínculo de ator adicionado" no campo Técnico, ver constantes
 * DASHGLPI_TICKET_ASSIGNED_*), dedupe por glpi_logs.id (mesmo racional do
 * glpi_action_log — não depende de janela de tempo para não reenviar).
 *
 * new_id/new_value do próprio log NÃO trazem o id do técnico de forma confiável nesta
 * versão do GLPI (confirmado em spike T0: ambas colunas ficam NULL nesse tipo de
 * evento, só new_value carrega texto livre "Nome (id)") — resolve pelo vínculo atual
 * glpi_tickets_users type=2, mesmo padrão já usado em dashglpi_sla_monitor_candidates()
 * para detectar ausência de atribuição.
 */
function dashglpi_rule_detect_ticket_assigned(array $rule): array
{
    $windowMinutes = max(1, (int) ($rule['trigger_value'] ?: 15));

    $rows = dashglpi_fetch_all(
        "SELECT l.id AS log_id, l.items_id AS tickets_id, l.date_mod
         FROM glpi_logs l
         WHERE l.itemtype = 'Ticket'
           AND l.id_search_option = ?
           AND l.linked_action = ?
           AND l.date_mod >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
         ORDER BY l.id ASC
         LIMIT 200",
        [DASHGLPI_TICKET_ASSIGNED_SEARCH_OPTION, DASHGLPI_TICKET_ASSIGNED_LINKED_ACTION, $windowMinutes]
    );

    $out = [];
    foreach ($rows as $row) {
        $ticketId = (int) $row['tickets_id'];

        $assignee = dashglpi_fetch_one(
            "SELECT u.id, u.name, u.firstname, u.realname
             FROM glpi_tickets_users tu
             JOIN glpi_users u ON u.id = tu.users_id
             WHERE tu.tickets_id = ? AND tu.type = 2
             ORDER BY tu.id DESC
             LIMIT 1",
            [$ticketId]
        );
        // Ticket pode já ter sido desatribuído entre o log e este poll — sem técnico
        // atual, não há quem mencionar; ignora o candidato (não é reprocessado depois
        // porque o dedupe é por log_id, não por estado do ticket).
        if (!$assignee) {
            continue;
        }

        $ticket = dashglpi_fetch_one(
            "SELECT t.id, t.name, t.entities_id, t.priority, t.date AS created_at,
                    COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name,
                    COALESCE(c.completename, c.name, '') AS category_name
             FROM glpi_tickets t
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             WHERE t.id = ?
             LIMIT 1",
            [$ticketId]
        );
        if (!$ticket) {
            continue;
        }

        $emailRow = dashglpi_fetch_one(
            "SELECT email FROM glpi_useremails
             WHERE users_id = ? AND is_default = 1
             ORDER BY id ASC LIMIT 1",
            [(int) $assignee['id']]
        );

        $assignedName = trim(((string) ($assignee['realname'] ?? '')) . ' ' . ((string) ($assignee['firstname'] ?? '')));
        if ($assignedName === '') {
            $assignedName = (string) ($assignee['name'] ?? '');
        }

        $alert = dashglpi_sla_monitor_build_alert($ticket, 'info', 0, 0);
        $alert['created_at'] = (string) $row['date_mod'];
        $alert['created_at_formatted'] = dashglpi_format_event_datetime($row['date_mod'] ?? null);
        $alert['event_at_label'] = 'Atribuído em';
        $alert['assigned_to_name'] = $assignedName;
        $alert['assigned_to_email'] = $emailRow !== null ? (string) ($emailRow['email'] ?? '') : '';
        // PLAN-20260714-023: id do técnico no Telegram, se ele cadastrou o próprio no
        // perfil — usado por DashglpiTelegramChannel::send() para montar menção real
        // (text_mention). Vazio quando não cadastrado: canal degrada para texto simples.
        $telegramUserId = dashglpi_user_channel_id_get((int) $assignee['id'], 'telegram');
        $alert['assigned_to_telegram_user_id'] = $telegramUserId ?? '';

        $out[] = [
            'event_id' => (int) $row['log_id'],
            'alert' => $alert,
        ];
    }

    return $out;
}

/**
 * Trigger novo do prompt (glpi_action_log): lê glpi_crontasklogs (nome real da tabela
 * no core do GLPI — o prompt do usuário usa "glpi_crontaskslogs", com um "s" a mais;
 * detectamos os dois nomes por segurança). Ressalva de compatibilidade (Fase 3.5 do
 * plano): a tabela/colunas podem mudar entre versões maiores do GLPI — detectamos as
 * colunas disponíveis e falhamos com mensagem clara em vez de silenciosamente.
 */
function dashglpi_rule_detect_glpi_action_log(array $rule): array
{
    $table = dashglpi_rule_glpi_crontasklogs_table();
    if ($table === null) {
        throw new RuntimeException(
            'Tabela de logs de tarefas automáticas do GLPI não encontrada nesta versão '
            . '(trigger glpi_action_log indisponível).'
        );
    }

    $config = is_array($rule['trigger_config'] ?? null) ? $rule['trigger_config'] : [];
    $crontasksId = (int) ($config['crontasks_id'] ?? 0);
    if ($crontasksId <= 0) {
        throw new RuntimeException('trigger_config.crontasks_id não configurado para esta regra.');
    }
    $mode = (string) ($config['mode'] ?? 'failure');
    $durationThreshold = (int) ($config['duration_threshold_s'] ?? 30);

    $columns = dashglpi_rule_glpi_crontasklogs_columns($table);
    $hasState = in_array('state', $columns, true);
    $durationColumn = in_array('elapsed', $columns, true)
        ? 'elapsed'
        : (in_array('duration', $columns, true) ? 'duration' : null);

    if ($mode === 'failure' && !$hasState) {
        throw new RuntimeException("Coluna 'state' ausente em $table nesta versão do GLPI — modo 'failure' indisponível.");
    }
    if ($mode === 'duration_over' && $durationColumn === null) {
        throw new RuntimeException("Coluna de duração ausente em $table nesta versão do GLPI — modo 'duration_over' indisponível.");
    }

    $selectExtra = ($hasState ? ', state' : '') . ($durationColumn ? ", $durationColumn AS duration_value" : '');
    $rows = dashglpi_fetch_all(
        "SELECT id, crontasks_id, date" . $selectExtra . "
         FROM $table
         WHERE crontasks_id = ?
           AND date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY id ASC
         LIMIT 200",
        [$crontasksId]
    );

    $out = [];
    foreach ($rows as $row) {
        // Heurística GLPI (state = 0 quando a execução falhou) — não validada ainda
        // contra a instância real de produção; revisar após o primeiro teste e2e.
        $matches = match ($mode) {
            'every' => true,
            'failure' => ((int) ($row['state'] ?? 1)) === 0,
            'duration_over' => ((int) ($row['duration_value'] ?? 0)) > $durationThreshold,
            default => false,
        };
        if (!$matches) {
            continue;
        }

        $out[] = [
            'event_id' => (int) $row['id'],
            'alert' => [
                'ticket_id' => 0,
                'title' => 'Tarefa automática GLPI #' . $crontasksId . ' — log #' . $row['id'],
                'category' => 'Automação GLPI',
                'entity_name' => '',
                'entities_id' => 0,
                'minutes_overdue' => 0,
                'threshold' => 0,
                'level' => $mode === 'failure' ? 'breach' : 'info',
                'note' => 'Modo: ' . $mode . ($durationColumn ? (' | duração: ' . ($row['duration_value'] ?? '-') . 's') : ''),
                'created_at' => (string) ($row['date'] ?? ''),
                'created_at_formatted' => dashglpi_format_event_datetime($row['date'] ?? null),
                'event_at_label' => 'Executado em',
                'url' => '',
            ],
        ];
    }

    return $out;
}

function dashglpi_rule_glpi_crontasklogs_table(): ?string
{
    static $table = false;
    if ($table !== false) {
        return $table;
    }

    foreach (['glpi_crontasklogs', 'glpi_crontaskslogs'] as $candidate) {
        $row = dashglpi_fetch_one(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$candidate]
        );
        if ($row) {
            $table = $candidate;
            return $table;
        }
    }

    $table = null;
    return null;
}

function dashglpi_rule_glpi_crontasklogs_columns(string $table): array
{
    $rows = dashglpi_fetch_all(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        [$table]
    );

    return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows);
}

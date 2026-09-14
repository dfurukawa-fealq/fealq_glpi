<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sla_monitor.php';
require_once __DIR__ . '/../inc/rules_engine.php';

dashglpi_require_admin();

/**
 * Mascara segredos antes de devolver a config para o frontend. O valor real
 * nunca trafega de volta; o form envia vazio para "manter" o segredo atual.
 */
function dashglpi_messaging_public_config(array $settings): array
{
    $whatsappKey = (string) ($settings['whatsapp']['api_key'] ?? '');
    $botToken = (string) ($settings['telegram']['bot_token'] ?? '');

    return [
        'sla_threshold_minutes' => (int) $settings['sla_threshold_minutes'],
        'warn_threshold_minutes' => (int) $settings['warn_threshold_minutes'],
        'max_age_hours' => (int) ($settings['max_age_hours'] ?? 0),
        'since_date' => (string) ($settings['since_date'] ?? ''),
        'teams' => [
            'enabled' => !empty($settings['teams']['enabled']),
            'webhook_url' => (string) ($settings['teams']['webhook_url'] ?? ''),
        ],
        'whatsapp' => [
            'enabled' => !empty($settings['whatsapp']['enabled']),
            'api_url' => (string) ($settings['whatsapp']['api_url'] ?? ''),
            'api_key_set' => $whatsappKey !== '',
            'instance' => (string) ($settings['whatsapp']['instance'] ?? ''),
            'recipients' => array_values($settings['whatsapp']['recipients'] ?? []),
        ],
        'telegram' => [
            'enabled' => !empty($settings['telegram']['enabled']),
            'bot_token_set' => $botToken !== '',
            'chat_ids' => array_values($settings['telegram']['chat_ids'] ?? []),
        ],
        'n8n' => [
            'enabled' => !empty($settings['n8n']['enabled']),
            'webhook_url' => (string) ($settings['n8n']['webhook_url'] ?? ''),
        ],
        'entities' => is_array($settings['entities'] ?? null) ? $settings['entities'] : [],
    ];
}

function dashglpi_messaging_dataset(): array
{
    $settings = dashglpi_get_settings('alerting');

    return [
        'config' => dashglpi_messaging_public_config($settings),
        'channels' => [
            ['key' => 'teams', 'label' => 'Microsoft Teams'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp (Evolution)'],
            ['key' => 'telegram', 'label' => 'Telegram'],
            ['key' => 'n8n', 'label' => 'n8n'],
        ],
        'snapshot' => dashglpi_sla_monitor_snapshot(),
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json(['ok' => true] + dashglpi_messaging_dataset());
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Método não permitido.'], 405);
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF inválido.'], 403);
    }

    $action = (string) ($_POST['messaging_action'] ?? '');

    if ($action === 'save') {
        $raw = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($raw)) {
            dashglpi_json(['error' => 'Payload inválido.'], 400);
        }

        // Preserva segredos quando o form envia campo vazio (não reexibimos o valor).
        $current = dashglpi_get_settings('alerting');
        if (trim((string) ($raw['whatsapp']['api_key'] ?? '')) === '') {
            $raw['whatsapp']['api_key'] = (string) ($current['whatsapp']['api_key'] ?? '');
        }
        if (trim((string) ($raw['telegram']['bot_token'] ?? '')) === '') {
            $raw['telegram']['bot_token'] = (string) ($current['telegram']['bot_token'] ?? '');
        }

        dashglpi_save_settings('alerting', $raw);
        dashglpi_json(['ok' => true, 'action' => 'save'] + dashglpi_messaging_dataset());
    }

    if ($action === 'test') {
        $channel = (string) ($_POST['channel'] ?? '');
        if (!in_array($channel, ['teams', 'whatsapp', 'telegram', 'n8n'], true)) {
            dashglpi_json(['error' => 'Canal inválido.'], 400);
        }

        $dispatcher = new DashglpiNotificationDispatcher();
        $alert = [
            'ticket_id' => 0,
            'title' => 'Chamado de teste — DashGLPI',
            'category' => 'Teste / Integração',
            'entity_name' => 'Fealq',
            'entities_id' => 0,
            'priority' => 5,
            'minutes_overdue' => 20,
            'threshold' => 15,
            'level' => 'breach',
            'url' => dashglpi_sla_monitor_ticket_url(0),
        ];
        $result = $dispatcher->sendTo($channel, $alert);

        dashglpi_json([
            'ok' => !empty($result['ok']),
            'action' => 'test',
            'channel' => $channel,
            'result' => $result,
        ]);
    }

    /**
     * PLAN-20260714-021 (Fase 4 §3): testa 1 destinatário isolado de uma ação de regra
     * (não os destinatários salvos em Canais de Alerta). Não persiste nada.
     *
     * PLAN-20260714-022: reaproveita dashglpi_rule_recipient_dispatch_config() — o
     * mesmo resolver do dispatch real da engine — em vez de montar o $config na mão.
     * Isso corrige um bug real: antes, testar um destinatário cuja ação tinha uma
     * credencial nomeada (bot_token/api_key) sempre caía na config global de Canais
     * de Alerta (frequentemente vazia), ignorando a credencial da ação.
     *
     * Correção adicional: quando a ação pertence a uma regra com message_template
     * configurado, o teste agora renderiza e usa ESSA mensagem (mesma função do
     * dispatch real, dashglpi_rule_render_message_template) em vez do texto genérico
     * fixo — o teste passa a refletir o que a regra realmente vai enviar.
     */
    if ($action === 'test_recipient') {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $actionType = (string) ($_POST['action_type'] ?? '');
        $recipientValue = (string) ($_POST['recipient_value'] ?? '');

        if (!in_array($actionType, DASHGLPI_RULE_ACTION_TYPES, true)) {
            dashglpi_json(['error' => 'Tipo de ação inválido.'], 400);
        }

        try {
            $recipientType = DASHGLPI_RULE_RECIPIENT_TYPE_BY_ACTION[$actionType];
            $validatedValue = dashglpi_rule_validate_recipient_value($recipientType, $recipientValue);
        } catch (RuntimeException $e) {
            dashglpi_json(['error' => $e->getMessage()], 400);
        }

        $credentialId = null;
        $messageTemplate = null;
        if ($actionId > 0) {
            $actionRow = dashglpi_fetch_one(
                "SELECT a.credential_id, r.message_template
                 FROM " . DASHGLPI_RULE_ACTIONS_TABLE . " a
                 JOIN " . DASHGLPI_RULES_TABLE . " r ON r.id = a.rule_id
                 WHERE a.id = ? LIMIT 1",
                [$actionId]
            );
            if ($actionRow) {
                $credentialId = $actionRow['credential_id'] !== null ? (int) $actionRow['credential_id'] : null;
                $messageTemplate = dashglpi_rule_decode_config($actionRow['message_template'] ?? null) ?: null;
            }
        }

        try {
            $config = dashglpi_rule_recipient_dispatch_config($actionType, $validatedValue, $credentialId);
        } catch (Throwable $e) {
            dashglpi_json(['error' => $e->getMessage()], 400);
        }

        $dispatcher = new DashglpiNotificationDispatcher();
        $alert = [
            'ticket_id' => 0,
            'title' => 'Chamado de teste — DashGLPI',
            'category' => 'Teste / Integração',
            'entity_name' => 'Fealq',
            'entities_id' => 0,
            'priority' => 5,
            'minutes_overdue' => 20,
            'threshold' => 15,
            'level' => 'breach',
            'url' => dashglpi_sla_monitor_ticket_url(0),
        ];
        $templateLines = dashglpi_rule_render_message_template($messageTemplate, $alert, $actionType);
        if ($templateLines !== null) {
            $alert['template_lines'] = $templateLines;
        }
        $result = $dispatcher->sendTo($actionType, $alert, $config);

        dashglpi_json([
            'ok' => !empty($result['ok']),
            'action' => 'test_recipient',
            'channel' => $actionType,
            'result' => $result,
        ]);
    }

    dashglpi_json(['error' => 'Ação de mensageria inválida.'], 400);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log(sprintf(
        '[DashGLPI] messaging_config error: class=%s message=%s file=%s line=%d',
        get_class($e),
        $message !== '' ? $message : '(empty)',
        $e->getFile(),
        $e->getLine()
    ));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no configurador de mensageria.'], 500);
}

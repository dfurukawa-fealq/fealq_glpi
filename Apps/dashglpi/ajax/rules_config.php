<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/rules_engine.php';

dashglpi_require_admin();

function dashglpi_rules_dataset(): array
{
    return [
        'rules' => dashglpi_rules_list(),
        'catalog' => [
            'trigger_types' => DASHGLPI_RULE_TRIGGER_TYPES,
            'action_types' => DASHGLPI_RULE_ACTION_TYPES,
            'recipient_type_by_action' => DASHGLPI_RULE_RECIPIENT_TYPE_BY_ACTION,
            'webhook_host_allowlist' => DASHGLPI_WEBHOOK_HOST_ALLOWLIST,
            'template_variables' => DASHGLPI_RULE_TEMPLATE_VARIABLES,
            'template_max_lines' => DASHGLPI_RULE_TEMPLATE_MAX_LINES,
            'template_max_line_length' => DASHGLPI_RULE_TEMPLATE_MAX_LINE_LENGTH,
            // PLAN-20260714-022 (Sub-fase 2): catálogo de credenciais nomeadas por canal.
            'credentials' => dashglpi_channel_credentials_list(),
        ],
    ];
}

/**
 * Alerta de exemplo para o preview de template — mesmo shape/valores do
 * worker/send_test.php, para o preview bater com o que um teste manual real mostraria.
 */
function dashglpi_rule_template_preview_sample_alert(): array
{
    return [
        'ticket_id' => 1234,
        'title' => 'Impressora não liga',
        'category' => 'Hardware',
        'entity_name' => 'Fealq',
        'entities_id' => 0,
        'priority' => 5,
        'minutes_overdue' => 20,
        'threshold' => 2,
        'level' => 'breach',
        'url' => 'https://glpi.exemplo/front/ticket.form.php?id=1234',
        'dashboard_url' => 'https://dashglpi.exemplo/front/dashboard.php?ticket_id=1234#tickets',
        'assigned_to_name' => 'Maria Silva',
        'assigned_to_email' => 'maria.silva@empresa.com',
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json(['ok' => true] + dashglpi_rules_dataset());
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Método não permitido.'], 405);
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF inválido.'], 403);
    }

    $action = (string) ($_POST['rules_action'] ?? '');

    if ($action === 'rule_save') {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($payload)) {
            dashglpi_json(['error' => 'Payload inválido.'], 400);
        }

        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        if ($ruleId > 0) {
            dashglpi_rule_update($ruleId, $payload);
        } else {
            $ruleId = dashglpi_rule_create($payload);
        }

        dashglpi_json(['ok' => true, 'action' => 'rule_save', 'rule_id' => $ruleId] + dashglpi_rules_dataset());
    }

    if ($action === 'rule_toggle') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $active = !empty($_POST['is_active']);
        dashglpi_rule_set_active($ruleId, $active);
        dashglpi_json(['ok' => true, 'action' => 'rule_toggle'] + dashglpi_rules_dataset());
    }

    if ($action === 'rule_delete') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        dashglpi_rule_delete($ruleId);
        dashglpi_json(['ok' => true, 'action' => 'rule_delete'] + dashglpi_rules_dataset());
    }

    if ($action === 'action_save') {
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $actionType = (string) ($_POST['action_type'] ?? '');
        dashglpi_rule_action_create($ruleId, $actionType);
        dashglpi_json(['ok' => true, 'action' => 'action_save'] + dashglpi_rules_dataset());
    }

    if ($action === 'action_delete') {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        dashglpi_rule_action_delete($actionId);
        dashglpi_json(['ok' => true, 'action' => 'action_delete'] + dashglpi_rules_dataset());
    }

    if ($action === 'action_set_credential') {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $credentialIdRaw = $_POST['credential_id'] ?? '';
        $credentialId = $credentialIdRaw === '' ? null : (int) $credentialIdRaw;
        dashglpi_rule_action_set_credential($actionId, $credentialId);
        dashglpi_json(['ok' => true, 'action' => 'action_set_credential'] + dashglpi_rules_dataset());
    }

    if ($action === 'recipient_save') {
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $recipientValue = (string) ($_POST['recipient_value'] ?? '');
        dashglpi_rule_recipient_create($actionId, $recipientValue);
        dashglpi_json(['ok' => true, 'action' => 'recipient_save'] + dashglpi_rules_dataset());
    }

    if ($action === 'recipient_delete') {
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        dashglpi_rule_recipient_delete($recipientId);
        dashglpi_json(['ok' => true, 'action' => 'recipient_delete'] + dashglpi_rules_dataset());
    }

    if ($action === 'template_preview') {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        $template = dashglpi_rule_validate_message_template(is_array($payload) ? ($payload['message_template'] ?? null) : null);

        $sampleAlert = dashglpi_rule_template_preview_sample_alert();
        $preview = [];
        foreach (DASHGLPI_RULE_ACTION_TYPES as $channelKey) {
            $lines = dashglpi_rule_render_message_template($template, $sampleAlert, $channelKey);
            $preview[$channelKey] = $lines ?? null; // null = sem template, canal usaria o texto padrão
        }

        dashglpi_json(['ok' => true, 'action' => 'template_preview', 'preview' => $preview]);
    }

    dashglpi_json(['error' => 'Ação de regras inválida.'], 400);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log(sprintf(
        '[DashGLPI] rules_config error: class=%s message=%s file=%s line=%d',
        get_class($e),
        $message !== '' ? $message : '(empty)',
        $e->getFile(),
        $e->getLine()
    ));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no configurador de regras.'], 500);
}

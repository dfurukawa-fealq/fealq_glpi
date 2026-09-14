<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/notification_admin.php';
require_once __DIR__ . '/../inc/sla_simple.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json(['ok' => true] + dashglpi_sla_dataset());
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Metodo nao permitido.'], 405);
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF invalido.'], 403);
    }

    $action = (string) ($_POST['sla_action'] ?? '');
    if (!in_array($action, [
        'validate',
        'apply',
        'reapply_open_tickets',
        'apply_reminders',
        'save_sla_notification',
        'update_sla_automation',
    ], true)) {
        dashglpi_json(['error' => 'Acao de SLA invalida.'], 400);
    }

    $currentSettings = dashglpi_get_settings('sla_simple');
    $result = [];

    if (in_array($action, ['validate', 'apply', 'reapply_open_tickets'], true)) {
        $payload = $action === 'reapply_open_tickets'
            ? dashglpi_sla_build_payload_from_settings($currentSettings)
            : dashglpi_sla_build_payload_from_post($_POST);
        $result = dashglpi_sla_bridge_request($action, $payload);

        if ($action === 'apply') {
            dashglpi_save_settings('sla_simple', dashglpi_sla_settings_from_post_and_result($_POST, $result));
        }
    } elseif ($action === 'apply_reminders') {
        $reminders = dashglpi_sla_reminders_from_post($_POST, $currentSettings);
        $result = dashglpi_sla_bridge_request('apply_reminders', dashglpi_sla_build_reminders_payload($currentSettings, $reminders));
        dashglpi_save_settings('sla_simple', dashglpi_sla_settings_with_reminders($currentSettings, $reminders, $result));
    } elseif ($action === 'save_sla_notification') {
        $catalog = dashglpi_notification_catalog_data();
        $specialEvent = $catalog['special_events']['ticket']['sla_reminder'] ?? ['available' => false, 'event_key' => ''];
        if (empty($specialEvent['available']) || trim((string) ($specialEvent['event_key'] ?? '')) === '') {
            throw new RuntimeException('O evento de lembrete automático de SLA não está disponível no catálogo do GLPI.');
        }

        $notification = dashglpi_sla_notification_from_post($_POST, $currentSettings);
        $templateResult = dashglpi_admin_bridge_request(
            'notification_config.php',
            dashglpi_sla_template_bundle_payload($notification)
        );
        $templateBundle = is_array($templateResult['template_bundle'] ?? null) ? $templateResult['template_bundle'] : [];
        $templateId = max(0, (int) ($templateBundle['template_id'] ?? 0));
        if ($templateId <= 0) {
            throw new RuntimeException('Falha ao obter o template compartilhado de SLA.');
        }

        $notificationId = max(0, (int) ($currentSettings['ids']['sla_notification']['notification_id'] ?? 0));
        $notificationResult = dashglpi_admin_bridge_request(
            'notification_config.php',
            dashglpi_sla_notification_bridge_payload(
                $notification,
                (string) $specialEvent['event_key'],
                $templateId,
                $notificationId
            )
        );

        dashglpi_save_settings(
            'sla_simple',
            dashglpi_sla_settings_with_notification($currentSettings, $notification, $templateResult, $notificationResult)
        );

        $result = [
            'event' => $specialEvent,
            'template_bundle' => $templateBundle,
            'notification' => $notificationResult['notification'] ?? [],
        ];
    } elseif ($action === 'update_sla_automation') {
        $result = dashglpi_admin_bridge_request('notification_config.php', ['action' => 'update_sla_automation']);
    }

    dashglpi_json([
        'ok' => true,
        'action' => $action,
        'result' => $result,
    ] + dashglpi_sla_dataset());
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log(sprintf(
        '[DashGLPI] SLA simple error: class=%s code=%s message=%s file=%s line=%d',
        get_class($e),
        (string) $e->getCode(),
        $message !== '' ? $message : '(empty)',
        $e->getFile(),
        $e->getLine()
    ));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no configurador de SLA.'], 500);
}

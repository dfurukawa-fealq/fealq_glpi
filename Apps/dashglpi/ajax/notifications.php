<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/notification_admin.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json(['ok' => true] + dashglpi_notification_dataset());
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Metodo nao permitido.'], 405);
        exit;
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF invalido.'], 403);
        exit;
    }

    $payload = dashglpi_notification_payload($_POST);
    $result = dashglpi_admin_bridge_request('notification_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'result' => $result,
    ] + dashglpi_notification_dataset());
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] notification admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de notificacoes.'], 500);
}

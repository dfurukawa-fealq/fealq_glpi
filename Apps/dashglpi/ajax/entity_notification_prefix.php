<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $response = [
            'ok' => true,
            'entities' => dashglpi_admin_entities(),
        ];

        if (isset($_GET['entities_id'])) {
            $entitiesId = max(0, (int) $_GET['entities_id']);
            $prefix = dashglpi_admin_bridge_request('entity_notification_prefix_config.php', [
                'action' => 'load',
                'entities_id' => $entitiesId,
            ]);
            $response['prefix'] = is_array($prefix['prefix'] ?? null) ? $prefix['prefix'] : null;
        }

        dashglpi_json($response);
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

    $payload = dashglpi_admin_entity_notification_prefix_payload($_POST);
    $result = dashglpi_admin_bridge_request('entity_notification_prefix_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'prefix' => is_array($result['prefix'] ?? null) ? $result['prefix'] : null,
        'entities' => dashglpi_admin_entities(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] entity notification prefix error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno ao atualizar prefixo da entidade.'], 500);
}

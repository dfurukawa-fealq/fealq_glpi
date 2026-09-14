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

        $status = dashglpi_admin_bridge_request('entitysmtp_config.php', ['action' => 'status']);
        $response['owner'] = is_array($status['owner'] ?? null) ? $status['owner'] : null;
        $response['summary'] = is_array($status['summary'] ?? null) ? $status['summary'] : null;

        if (isset($_GET['entities_id'])) {
            $entityId = max(0, (int) $_GET['entities_id']);
            $state = dashglpi_admin_bridge_request('entitysmtp_config.php', [
                'action' => 'load',
                'entities_id' => $entityId,
            ]);
            $response['entitysmtp'] = is_array($state['entitysmtp'] ?? null) ? $state['entitysmtp'] : null;
            $response['smtp'] = is_array($state['smtp'] ?? null) ? $state['smtp'] : null;
            $response['effective_smtp'] = is_array($state['effective_smtp'] ?? null) ? $state['effective_smtp'] : null;
            $response['recent_logs'] = is_array($state['recent_logs'] ?? null) ? $state['recent_logs'] : [];
            $response['owner'] = is_array($state['owner'] ?? null) ? $state['owner'] : $response['owner'];
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

    $payload = dashglpi_admin_entitysmtp_payload($_POST);
    $result = dashglpi_admin_bridge_request('entitysmtp_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'entitysmtp' => is_array($result['entitysmtp'] ?? null) ? $result['entitysmtp'] : null,
        'smtp' => is_array($result['smtp'] ?? null) ? $result['smtp'] : null,
        'effective_smtp' => is_array($result['effective_smtp'] ?? null) ? $result['effective_smtp'] : null,
        'owner' => is_array($result['owner'] ?? null) ? $result['owner'] : null,
        'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : null,
        'recent_logs' => is_array($result['recent_logs'] ?? null) ? $result['recent_logs'] : [],
        'test_email' => is_array($result['test_email'] ?? null) ? $result['test_email'] : null,
        'entities' => dashglpi_admin_entities(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] entity SMTP error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no SMTP por entidade.'], 500);
}

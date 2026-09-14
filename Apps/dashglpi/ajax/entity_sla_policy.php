<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sla_simple.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/entity_sla_policy.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $entitiesId = isset($_GET['entities_id']) ? max(0, (int) $_GET['entities_id']) : 0;
        $response = [
            'ok' => true,
            'entities' => dashglpi_admin_entities(),
            'policies' => dashglpi_sla_policy_all(),
        ];

        if (isset($_GET['entities_id'])) {
            $response['policy'] = dashglpi_sla_policy_get($entitiesId);
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

    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['save_apply', 'reapply_open_tickets'], true)) {
        dashglpi_json(['error' => 'Acao de politica SLA invalida.'], 400);
        exit;
    }

    $policy = dashglpi_sla_policy_from_post($_POST);
    if ($action === 'reapply_open_tickets') {
        $savedPolicy = dashglpi_sla_policy_get((int) $policy['entities_id']);
        $policy['managed_ids'] = $savedPolicy['managed_ids'] ?? $policy['managed_ids'] ?? [];
    }
    $payload = dashglpi_sla_policy_build_payload($policy);
    $bridgeAction = $action === 'save_apply'
        ? 'apply_client_policy'
        : 'reapply_client_policy_open_tickets';

    $result = dashglpi_sla_bridge_request($bridgeAction, $payload);

    if ($action === 'save_apply') {
        $policy['managed_ids'] = dashglpi_sla_policy_managed_ids_from_result($result['result'] ?? $result, $policy['managed_ids'] ?? []);
        $policy = dashglpi_sla_policy_save($policy);
    }

    dashglpi_json([
        'ok' => true,
        'action' => $action,
        'result' => $result['result'] ?? $result,
        'policy' => dashglpi_sla_policy_get((int) $policy['entities_id']),
        'policies' => dashglpi_sla_policy_all(),
        'entities' => dashglpi_admin_entities(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] entity SLA policy error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno na politica SLA do cliente.'], 500);
}

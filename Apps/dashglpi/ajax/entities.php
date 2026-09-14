<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sla_simple.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/entity_sla_policy.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json([
            'ok' => true,
            'entities' => dashglpi_admin_entities(),
            'policies' => dashglpi_sla_policy_all(),
        ]);
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

    if ($action === 'preview_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $preview = dashglpi_admin_entity_import_preview($_FILES['csv_file'] ?? []);
        $_SESSION['dashglpi_entity_import_preview'] = $preview;

        dashglpi_json([
            'ok' => true,
            'preview' => dashglpi_admin_import_preview_response($preview),
        ]);
        exit;
    }

    if ($action === 'confirm_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $items = dashglpi_admin_import_confirm_items((string) ($_POST['preview_token'] ?? ''), 'dashglpi_entity_import_preview');
        $result = dashglpi_admin_import_dispatch('entity_config.php', $items);
        unset($_SESSION['dashglpi_entity_import_preview']);

        dashglpi_json([
            'ok' => true,
            'result' => $result,
            'entities' => dashglpi_admin_entities(),
            'groups' => dashglpi_admin_groups(),
            'policies' => dashglpi_sla_policy_all(),
        ]);
        exit;
    }

    $payload = dashglpi_admin_entity_payload($_POST);
    $result = dashglpi_admin_bridge_request('entity_config.php', $payload);
    $policyResult = null;
    $solutionClosureResult = null;
    $isNewEntityCreate = ($payload['action'] ?? 'save') === 'save' && empty($payload['id']);

    if ($isNewEntityCreate && !empty($payload['create_sla_policy'])) {
        $entitiesId = (int) ($result['entity']['id'] ?? 0);
        if ($entitiesId <= 0) {
            throw new RuntimeException('Nao foi possivel identificar a entidade criada para gerar a politica SLA.');
        }

        $policy = dashglpi_sla_policy_get($entitiesId);
        $policy['is_active'] = 1;
        $policy['is_recursive'] = 1;
        $policy['tto_mode'] = 'fixed';
        $policy['tto_fixed_key'] = 'TTO-P1';
        $policy['ttr_mode'] = 'priority';
        $policy['ttr_fixed_key'] = 'TTR-P1';

        $policyPayload = dashglpi_sla_policy_build_payload($policy);
        $policyBridge = dashglpi_sla_bridge_request('apply_client_policy', $policyPayload);
        $policy['managed_ids'] = dashglpi_sla_policy_managed_ids_from_result($policyBridge['result'] ?? $policyBridge, $policy['managed_ids'] ?? []);
        $policyResult = dashglpi_sla_policy_save($policy);
    }

    $solutionClosure = is_array($payload['solution_closure'] ?? null) ? $payload['solution_closure'] : null;
    if ($isNewEntityCreate && $solutionClosure && ($solutionClosure['mode'] ?? 'inherit') !== 'inherit') {
        $entitiesId = (int) ($result['entity']['id'] ?? 0);
        if ($entitiesId <= 0) {
            throw new RuntimeException('Nao foi possivel identificar a entidade criada para configurar o fechamento pós-solucao.');
        }

        $solutionClosure['entities_id'] = $entitiesId;
        $solutionClosureBridge = dashglpi_admin_bridge_request('entity_solution_closure_config.php', $solutionClosure);
        $solutionClosureResult = is_array($solutionClosureBridge['closure'] ?? null) ? $solutionClosureBridge['closure'] : null;
    }

    dashglpi_json([
        'ok' => true,
        'result' => $result,
        'policy' => $policyResult,
        'solution_closure' => $solutionClosureResult,
        'entities' => dashglpi_admin_entities(),
        'groups' => dashglpi_admin_groups(),
        'policies' => dashglpi_sla_policy_all(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] entity admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de entidade.'], 500);
}

<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json([
            'ok' => true,
            'entities' => dashglpi_admin_entities(),
            'groups' => dashglpi_admin_groups(),
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
        $preview = dashglpi_admin_group_import_preview($_FILES['csv_file'] ?? []);
        $_SESSION['dashglpi_group_import_preview'] = $preview;

        dashglpi_json([
            'ok' => true,
            'preview' => dashglpi_admin_import_preview_response($preview),
        ]);
        exit;
    }

    if ($action === 'confirm_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $items = dashglpi_admin_import_confirm_items((string) ($_POST['preview_token'] ?? ''), 'dashglpi_group_import_preview');
        $result = dashglpi_admin_import_dispatch('group_config.php', $items);
        unset($_SESSION['dashglpi_group_import_preview']);

        dashglpi_json([
            'ok' => true,
            'result' => $result,
            'entities' => dashglpi_admin_entities(),
            'groups' => dashglpi_admin_groups(),
        ]);
        exit;
    }

    $payload = dashglpi_admin_group_payload($_POST);
    $result = dashglpi_admin_bridge_request('group_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'result' => $result,
        'entities' => dashglpi_admin_entities(),
        'groups' => dashglpi_admin_groups(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] group admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de grupo.'], 500);
}

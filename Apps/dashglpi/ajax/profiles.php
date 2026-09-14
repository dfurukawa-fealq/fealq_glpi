<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json([
            'ok' => true,
            'profiles_full' => dashglpi_admin_profiles_full(),
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
        $preview = dashglpi_admin_profile_import_preview($_FILES['csv_file'] ?? []);
        $_SESSION['dashglpi_profile_import_preview'] = $preview;

        dashglpi_json([
            'ok' => true,
            'preview' => dashglpi_admin_import_preview_response($preview),
        ]);
        exit;
    }

    if ($action === 'confirm_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $items = dashglpi_admin_import_confirm_items((string) ($_POST['preview_token'] ?? ''), 'dashglpi_profile_import_preview');
        $result = dashglpi_admin_import_dispatch('profile_config.php', $items);
        unset($_SESSION['dashglpi_profile_import_preview']);

        dashglpi_json([
            'ok' => true,
            'result' => $result,
            'profiles_full' => dashglpi_admin_profiles_full(),
        ]);
        exit;
    }

    $payload = dashglpi_admin_profile_payload($_POST);
    $result = dashglpi_admin_bridge_request('profile_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'result' => $result,
        'profiles_full' => dashglpi_admin_profiles_full(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] profile admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de perfil.'], 500);
}

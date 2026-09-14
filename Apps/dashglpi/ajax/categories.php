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
            'categories' => dashglpi_admin_categories(),
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

    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'preview_import') {
        $preview = dashglpi_admin_category_import_preview($_FILES['csv_file'] ?? []);
        $_SESSION['dashglpi_category_import_preview'] = $preview;

        dashglpi_json([
            'ok' => true,
            'preview' => [
                'token' => $preview['token'],
                'summary' => $preview['summary'],
                'rows' => $preview['rows'],
                'can_confirm' => $preview['can_confirm'],
                'filename' => $preview['filename'],
            ],
        ]);
        exit;
    }

    if ($action === 'confirm_import') {
        $token = (string) ($_POST['preview_token'] ?? '');
        $preview = $_SESSION['dashglpi_category_import_preview'] ?? null;

        if (!is_array($preview) || !hash_equals((string) ($preview['token'] ?? ''), $token)) {
            throw new RuntimeException('Previa de importacao expirada. Envie o CSV novamente.');
        }

        if (empty($preview['items']) || !is_array($preview['items'])) {
            throw new RuntimeException('Nao ha categorias validas para importar.');
        }

        $result = dashglpi_admin_bridge_request('group_config.php', [
            'bridge_scope' => 'itil_category',
            'items' => $preview['items'],
        ]);
        unset($_SESSION['dashglpi_category_import_preview']);

        dashglpi_json([
            'ok' => true,
            'result' => $result,
            'entities' => dashglpi_admin_entities(),
            'categories' => dashglpi_admin_categories(),
        ]);
        exit;
    }

    if ($action !== 'save') {
        dashglpi_json(['error' => 'Acao invalida.'], 400);
        exit;
    }

    $payload = dashglpi_admin_category_payload($_POST);
    $payload['bridge_scope'] = 'itil_category';
    $result = dashglpi_admin_bridge_request('group_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'result' => $result,
        'entities' => dashglpi_admin_entities(),
        'categories' => dashglpi_admin_categories(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] category admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de categoria.'], 500);
}

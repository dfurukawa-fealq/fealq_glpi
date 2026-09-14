<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';

dashglpi_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dashglpi_json(['error' => 'Metodo nao permitido.'], 405);
}

if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
    dashglpi_json(['error' => 'Token CSRF invalido.'], 403);
}

try {
    $settings = dashglpi_get_settings('reports');
    foreach (['brand', 'pdf', 'attachments', 'visibility', 'app_name', 'logo_url', 'logo_light_url', 'logo_dark_url'] as $field) {
        if (array_key_exists($field, $_POST)) {
            $settings[$field] = (string) $_POST[$field];
        }
    }
    if (array_key_exists('logo_light_url', $_POST)) {
        $settings['logo_url'] = (string) $_POST['logo_light_url'];
    }

    dashglpi_save_settings('reports', $settings);

    dashglpi_json([
        'ok' => true,
        'settings' => dashglpi_get_settings('reports'),
    ]);
} catch (Throwable $e) {
    error_log('[DashGLPI] Settings save error: ' . $e->getMessage());
    dashglpi_json(['error' => 'Erro interno ao salvar configuracoes.'], 500);
}

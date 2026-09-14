<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';

dashglpi_require_admin();

function dashglpi_profile_access_response_payload(): array
{
    return [
        'ok' => true,
        'profiles' => dashglpi_admin_profiles_full(),
        'settings' => dashglpi_get_settings('profile_access'),
        'page_catalog' => dashglpi_profile_access_page_catalog(),
    ];
}

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        dashglpi_json(dashglpi_profile_access_response_payload());
    }

    if ($method !== 'POST') {
        dashglpi_json(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
    }

    $rawPayload = file_get_contents('php://input');
    if (!is_string($rawPayload) || trim($rawPayload) === '') {
        dashglpi_json(['ok' => false, 'error' => 'Payload vazio.'], 400);
    }

    $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        dashglpi_json(['ok' => false, 'error' => 'Payload invalido.'], 400);
    }

    if (!dashglpi_validate_csrf($payload['csrf_token'] ?? null)) {
        dashglpi_json(['ok' => false, 'error' => 'Token CSRF invalido.'], 403);
    }

    dashglpi_save_settings('profile_access', [
        'rules' => $payload['rules'] ?? [],
    ]);

    dashglpi_json(dashglpi_profile_access_response_payload());
} catch (InvalidArgumentException $e) {
    dashglpi_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (JsonException $e) {
    dashglpi_json(['ok' => false, 'error' => 'Payload JSON invalido.'], 400);
} catch (Throwable $e) {
    error_log('[DashGLPI] Profile access error: ' . $e->getMessage());
    dashglpi_json(['ok' => false, 'error' => 'Erro interno ao processar acesso por perfil.'], 500);
}

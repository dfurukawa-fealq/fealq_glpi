<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/rules_engine.php';

dashglpi_require_admin();

/**
 * PLAN-20260714-022 (Sub-fase 2): CRUD do catálogo de credenciais nomeadas de canal.
 * Endpoint fino — regra de negócio (validação, mascaramento, contagem de uso) vive em
 * inc/rules_engine.php, mesmo padrão de ajax/rules_config.php.
 */
function dashglpi_channel_credentials_dataset(?string $actionType = null): array
{
    return [
        'credentials' => dashglpi_channel_credentials_list($actionType),
        'action_types' => DASHGLPI_RULE_ACTION_TYPES,
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $actionType = isset($_GET['action_type']) && $_GET['action_type'] !== '' ? (string) $_GET['action_type'] : null;
        dashglpi_json(['ok' => true] + dashglpi_channel_credentials_dataset($actionType));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Método não permitido.'], 405);
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF inválido.'], 403);
    }

    $credentialAction = (string) ($_POST['credential_action'] ?? '');

    if ($credentialAction === 'save') {
        $id = dashglpi_channel_credential_save($_POST);
        dashglpi_json(['ok' => true, 'action' => 'save', 'id' => $id] + dashglpi_channel_credentials_dataset());
    }

    if ($credentialAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            dashglpi_json(['error' => 'Credencial inválida.'], 400);
        }
        dashglpi_channel_credential_delete($id);
        dashglpi_json(['ok' => true, 'action' => 'delete'] + dashglpi_channel_credentials_dataset());
    }

    dashglpi_json(['error' => 'Ação de credencial inválida.'], 400);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log(sprintf(
        '[DashGLPI] channel_credentials error: class=%s message=%s file=%s line=%d',
        get_class($e),
        $message !== '' ? $message : '(empty)',
        $e->getFile(),
        $e->getLine()
    ));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no gerenciador de credenciais.'], 500);
}

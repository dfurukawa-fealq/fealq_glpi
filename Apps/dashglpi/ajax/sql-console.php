<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sql_console.php';

dashglpi_require_admin();

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        dashglpi_json(dashglpi_sql_console_dataset());
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

    $action = strtolower(trim((string) ($payload['action'] ?? 'execute')));
    $sql = (string) ($payload['sql'] ?? '');
    $limit = (int) ($payload['limit'] ?? DASHGLPI_SQL_CONSOLE_DEFAULT_LIMIT);

    if ($action === 'preview_write') {
        dashglpi_json(dashglpi_sql_console_prepare_write_preview($sql));
    }

    if ($action === 'clear_glpi_cache') {
        dashglpi_json(dashglpi_sql_console_clear_glpi_cache());
    }

    if ($action === 'optimize_heavy_tables') {
        dashglpi_json(dashglpi_sql_console_optimize_heavy_tables());
    }

    if ($action === 'check_diagnostics') {
        dashglpi_json(['ok' => true, 'diagnostics' => dashglpi_sql_console_diagnostics()]);
    }

    if ($action !== 'execute') {
        dashglpi_json(['ok' => false, 'error' => 'Acao invalida para o console SQL.'], 400);
    }

    dashglpi_json(dashglpi_sql_console_execute($sql, $limit, [
        'confirm_write' => !empty($payload['confirm_write']),
        'preview_token' => (string) ($payload['preview_token'] ?? ''),
        'confirmation_text' => (string) ($payload['confirmation_text'] ?? ''),
    ]));
} catch (InvalidArgumentException $e) {
    dashglpi_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (PDOException $e) {
    error_log('[DashGLPI] SQL console PDO error: ' . $e->getMessage());
    dashglpi_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (JsonException $e) {
    dashglpi_json(['ok' => false, 'error' => 'Payload JSON invalido.'], 400);
} catch (Throwable $e) {
    error_log('[DashGLPI] SQL console error: ' . $e->getMessage());
    dashglpi_json(['ok' => false, 'error' => 'Erro interno ao processar o console SQL.'], 500);
}

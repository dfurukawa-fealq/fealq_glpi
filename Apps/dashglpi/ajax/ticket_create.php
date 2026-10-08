<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/ticket_create.php';

dashglpi_require_auth();

function dashglpi_ticket_create_uploaded_files(array $files, string $field = 'attachments'): array
{
    if (!isset($files[$field])) {
        return [];
    }

    $entry = $files[$field];
    $names = $entry['name'] ?? [];
    $tmpNames = $entry['tmp_name'] ?? [];
    $types = $entry['type'] ?? [];
    $errors = $entry['error'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $types = [$types];
        $errors = [$errors];
    }

    $normalized = [];
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao enviar um dos anexos do chamado.');
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Arquivo temporario do anexo nao encontrado.');
        }

        $normalized[] = [
            'field_name' => 'attachments[]',
            'client_name' => (string) $name,
            'tmp_name' => $tmpName,
            'mime_type' => (string) ($types[$index] ?? 'application/octet-stream'),
        ];
    }

    return $normalized;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = (string) ($_GET['action'] ?? 'catalog');
        if ($action === 'catalog') {
            dashglpi_json(dashglpi_ticket_create_catalog_response($_GET));
        }
        if ($action === 'requester_search') {
            dashglpi_json(dashglpi_ticket_create_requester_search_response($_GET));
        }

        dashglpi_json(['ok' => false, 'error' => 'Acao invalida.'], 400);
    }
} catch (Throwable $e) {
    dashglpi_ajax_error_response('ticket_create_get', $e, 'Erro interno ao criar chamado.');
}

dashglpi_ajax_bridge_endpoint('ticket create', function () {
    $action = (string) ($_POST['action'] ?? 'create');
    if ($action !== 'create') {
        dashglpi_json(['ok' => false, 'error' => 'Acao invalida.'], 400);
    }

    $payload = dashglpi_ticket_create_payload_from_request($_POST);
    $files = dashglpi_ticket_create_uploaded_files($_FILES);
    $result = dashglpi_admin_bridge_request('ticket_create_config.php', $payload, $files);

    return [
        'ticket' => $result['ticket'] ?? null,
        'sla' => $result['sla'] ?? null,
        'message' => (string) ($result['message'] ?? 'Chamado criado com sucesso.'),
    ];
}, 'Erro interno ao criar chamado.');

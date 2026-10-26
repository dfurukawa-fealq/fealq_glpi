<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/ticket_create.php';

dashglpi_require_auth();

// PHP leaves POST/FILES empty when post_max_size was exceeded. Keep the
// browser's correlation key so the error can still be tied to this attempt.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && dashglpi_ticket_create_ini_bytes((string) ini_get('post_max_size')) > 0
    && (int) $_SERVER['CONTENT_LENGTH'] > dashglpi_ticket_create_ini_bytes((string) ini_get('post_max_size'))
) {
    $requestId = strtolower(trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{16,64}$/', $requestId)) {
        $requestId = dashglpi_ajax_request_id();
    }
    $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
    header('X-Request-ID: ' . $requestId);
    dashglpi_json(['ok' => false, 'error' => 'O envio excede post_max_size configurado no DASHGLPI.', 'request_id' => $requestId], 413);
}

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
    $sizes = $entry['size'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $types = [$types];
        $errors = [$errors];
        $sizes = [$sizes];
    }
    if (count($names) > 10) {
        throw new RuntimeException('O chamado aceita no maximo 10 anexos.', 422);
    }

    $normalized = [];
    $totalBytes = 0;
    $maxFileBytes = dashglpi_ticket_create_ini_bytes((string) ini_get('upload_max_filesize'));
    $maxPostBytes = dashglpi_ticket_create_ini_bytes((string) ini_get('post_max_size'));
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $status = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
            throw new RuntimeException('Falha ao enviar um dos anexos do chamado.', $status);
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Arquivo temporario do anexo nao encontrado.', 400);
        }

        $size = (int) ($sizes[$index] ?? 0);
        if ($size <= 0 || ($maxFileBytes > 0 && $size > $maxFileBytes)) {
            throw new RuntimeException('Anexo vazio ou acima do limite por arquivo.', 422);
        }
        $totalBytes += $size;
        if ($maxPostBytes > 0 && $totalBytes > $maxPostBytes) {
            throw new RuntimeException('O tamanho total dos anexos excede o limite deste envio.', 422);
        }

        $clientName = basename(str_replace('\\', '/', (string) $name));
        if ($clientName === '' || strlen($clientName) > 240 || preg_match('/[\x00-\x1F\x7F]/', $clientName)) {
            throw new RuntimeException('Nome de anexo invalido ou muito longo.', 422);
        }
        if (preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|shtml)$/i', $clientName)) {
            throw new RuntimeException('Este tipo de arquivo nao pode ser anexado.', 422);
        }

        $mimeType = (string) ($types[$index] ?? 'application/octet-stream');
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            }
        }

        $normalized[] = [
            'field_name' => 'attachments[]',
            'client_name' => $clientName,
            'tmp_name' => $tmpName,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    return $normalized;
}

function dashglpi_ticket_create_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $number = (float) $value;
    $unit = strtolower(substr($value, -1));
    return (int) ($number * match ($unit) {
        'g' => 1024 * 1024 * 1024,
        'm' => 1024 * 1024,
        'k' => 1024,
        default => 1,
    });
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
    $requestId = strtolower(trim((string) ($_POST['request_id'] ?? $_SERVER['HTTP_X_REQUEST_ID'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{16,64}$/', $requestId)) {
        $requestId = dashglpi_ajax_request_id();
    }
    $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;

    $action = (string) ($_POST['action'] ?? 'create');
    if ($action !== 'create') {
        dashglpi_json(['ok' => false, 'error' => 'Acao invalida.'], 400);
    }

    $payload = dashglpi_ticket_create_payload_from_request($_POST);
    $payload['request_id'] = $requestId;
    $files = dashglpi_ticket_create_uploaded_files($_FILES);
    $result = dashglpi_admin_bridge_request('ticket_create_config.php', $payload, $files, $requestId);

    return [
        'ticket' => $result['ticket'] ?? null,
        'sla' => $result['sla'] ?? null,
        'message' => (string) ($result['message'] ?? 'Chamado criado com sucesso.'),
        'request_id' => $requestId,
        'idempotent_replay' => !empty($result['idempotent_replay']),
        'notification_queued' => (bool) ($result['notification_queued'] ?? false),
    ];
}, 'Erro interno ao criar chamado.');

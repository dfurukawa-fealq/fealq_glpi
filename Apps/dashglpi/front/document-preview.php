<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/ticket_attendance.php'; // PLAN-20260905-001

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

$ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$documentId = filter_input(INPUT_GET, 'document_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$ticketId || !$documentId) {
    http_response_code(400);
    echo 'Parametros invalidos.';
    exit;
}

// Whitelist de objeto ITIL (PLAN-20260709-019, Fase D); default 'ticket'.
$itemtypeKey = dashglpi_itil_normalize_type($_GET['itemtype'] ?? 'ticket');
dashglpi_assert_itil_object_access($itemtypeKey, (int) $ticketId);

if ($itemtypeKey === 'ticket') {
    try {
        dashglpi_attendance_request('ticket_attendance_config.php', (int) $ticketId,
            ['action' => 'document', 'document_id' => (int) $documentId]);
    } catch (Throwable $e) {
        http_response_code(403);
        exit('Documento indisponível.');
    }
}

$document = dashglpi_itil_related_document(dashglpi_itil_type($itemtypeKey), (int) $ticketId, (int) $documentId);

$download = $itemtypeKey === 'ticket' && ($_GET['download'] ?? '') === '1';
if (!$document || (!$download && !in_array(strtolower((string) $document['mime']), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true))) {
    http_response_code(404);
    echo 'Imagem nao encontrada.';
    exit;
}

$path = dashglpi_document_path($document['filepath'] ?? '');
if (!$path || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$mime = (string) $document['mime'];
$filename = (string) ($document['filename'] ?: $document['name'] ?: 'documento');

header('Content-Type: ' . ($download ? 'application/octet-stream' : $mime));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace(["\r", "\n", '"'], '', basename($filename)) . '"');
header('Cache-Control: private, no-store');

readfile($path);

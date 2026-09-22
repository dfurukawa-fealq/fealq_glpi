<?php

require_once '/var/www/html/inc/bootstrap.php';
require_once '/var/www/html/inc/glpi_admin_import.php';

$csv = '/tmp/glpi_computador.csv';
$preview = dashglpi_admin_computer_import_preview([
    'name' => basename($csv),
    'type' => 'text/csv',
    'tmp_name' => $csv,
    'error' => UPLOAD_ERR_OK,
    'size' => is_file($csv) ? filesize($csv) : 0,
]);

$items = array_slice($preview['items'] ?? [], 0, 1);
$payload = ['items' => $items];

$slaUrl = dashglpi_env('DASHGLPI_BRIDGE_URL', 'http://glpi/plugins/dashglpi/ajax/sla_config.php');
$baseUrl = preg_replace('#/ajax/sla_config\.php$#', '', $slaUrl) ?: 'http://glpi/plugins/dashglpi';
$url = rtrim((string) $baseUrl, '/') . '/ajax/computer_config.php';
$token = dashglpi_env('DASHGLPI_BRIDGE_TOKEN', '');
$body = json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

$headers = [
    'Accept: application/json',
    'X-DashGLPI-Bridge-Token: ' . $token,
    'Content-Type: application/json',
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 20,
    ],
]);

$response = file_get_contents($url, false, $context);

echo "URL: {$url}\n";
echo "TOKEN_LEN: " . strlen($token) . "\n";
echo "PREVIEW_READY: " . (int) ($preview['summary']['ready'] ?? 0) . "\n";
echo "REQUEST_BODY: {$body}\n";
echo "RESPONSE_HEADERS:\n" . implode("\n", $http_response_header ?? []) . "\n";
echo "RESPONSE_BODY:\n" . (is_string($response) ? $response : '[false]') . "\n";

<?php

require_once '/var/www/html/inc/bootstrap.php';
require_once '/var/www/html/inc/glpi_admin.php';

$tests = [
    ['endpoint' => 'sla_config.php', 'payload' => ['action' => 'load']],
    ['endpoint' => 'computer_config.php', 'payload' => ['items' => [['name' => 'DASHGLPI_TEST_COMPUTER']]]],
    ['endpoint' => 'monitor_config.php', 'payload' => ['items' => [['name' => 'DASHGLPI_TEST_MONITOR']]]],
    ['endpoint' => 'ticket_create_config.php', 'payload' => ['items' => [['title' => 'DASHGLPI_TEST_TICKET']]]],
];

echo json_encode([
    'bridge_url' => getenv('DASHGLPI_BRIDGE_URL') ?: '',
    'bridge_base_url' => getenv('DASHGLPI_BRIDGE_BASE_URL') ?: '',
    'allow_url_fopen' => ini_get('allow_url_fopen'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

foreach ($tests as $test) {
    try {
        $result = dashglpi_admin_bridge_request($test['endpoint'], $test['payload']);
        echo json_encode([
            'endpoint' => $test['endpoint'],
            'ok' => true,
            'keys' => array_keys($result),
            'summary' => $result['summary'] ?? null,
            'message' => $result['message'] ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } catch (Throwable $e) {
        echo json_encode([
            'endpoint' => $test['endpoint'],
            'ok' => false,
            'class' => get_class($e),
            'message' => $e->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

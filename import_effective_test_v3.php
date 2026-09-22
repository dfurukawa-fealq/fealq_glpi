<?php

require_once '/var/www/html/inc/bootstrap.php';
require_once '/var/www/html/inc/glpi_admin_import.php';

$tests = [
    'computadores' => [
        'csv' => '/tmp/glpi_computador.csv',
        'preview' => 'dashglpi_admin_computer_import_preview',
        'endpoint' => 'computer_config.php',
        'table' => 'glpi_computers',
    ],
    'monitores' => [
        'csv' => '/tmp/glpi_monitores.csv',
        'preview' => 'dashglpi_admin_monitor_import_preview',
        'endpoint' => 'monitor_config.php',
        'table' => 'glpi_monitors',
    ],
    'tickets' => [
        'csv' => '/tmp/glpi_tickets.csv',
        'preview' => 'dashglpi_admin_ticket_import_preview',
        'endpoint' => 'ticket_import_config.php',
        'table' => 'glpi_tickets',
    ],
];

function test_file_array(string $path): array
{
    return [
        'name' => basename($path),
        'type' => 'text/csv',
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => is_file($path) ? filesize($path) : 0,
    ];
}

function table_count(string $table): int
{
    $stmt = dashglpi_db()->query("SELECT COUNT(*) AS total FROM {$table}");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return (int) ($row['total'] ?? 0);
}

$report = [];

foreach ($tests as $name => $config) {
    $entry = [
        'csv' => $config['csv'],
        'endpoint' => $config['endpoint'],
        'table' => $config['table'],
        'before' => null,
        'preview' => null,
        'dispatch' => null,
        'after' => null,
        'delta' => null,
        'error' => null,
    ];

    try {
        $entry['before'] = table_count($config['table']);
        $preview = $config['preview'](test_file_array($config['csv']));
        $entry['preview'] = [
            'filename' => $preview['filename'] ?? '',
            'summary' => $preview['summary'] ?? [],
            'can_confirm' => !empty($preview['can_confirm']),
            'items_count' => is_array($preview['items'] ?? null) ? count($preview['items']) : 0,
            'first_item' => $preview['items'][0] ?? null,
        ];

        if (!empty($preview['items']) && is_array($preview['items'])) {
            $entry['dispatch'] = dashglpi_admin_import_dispatch($config['endpoint'], $preview['items']);
        }

        $entry['after'] = table_count($config['table']);
        $entry['delta'] = $entry['after'] - $entry['before'];
    } catch (Throwable $e) {
        try {
            $entry['after'] = table_count($config['table']);
            if ($entry['before'] !== null) {
                $entry['delta'] = $entry['after'] - $entry['before'];
            }
        } catch (Throwable $ignored) {
        }
        $entry['error'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
        ];
    }

    $report[$name] = $entry;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

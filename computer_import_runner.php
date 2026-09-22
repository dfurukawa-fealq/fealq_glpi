<?php

require_once '/var/www/html/inc/bootstrap.php';
require_once '/var/www/html/inc/glpi_admin_import.php';

function count_computers(): int
{
    return (int) dashglpi_db()->query('SELECT COUNT(*) FROM glpi_computers')->fetchColumn();
}

$file = [
    'name' => 'glpi_computador.csv',
    'type' => 'text/csv',
    'tmp_name' => '/tmp/glpi_computador.csv',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize('/tmp/glpi_computador.csv'),
];

$out = ['before' => count_computers()];

try {
    $preview = dashglpi_admin_computer_import_preview($file);
    $out['preview'] = [
        'summary' => $preview['summary'],
        'items_count' => count($preview['items'] ?? []),
        'can_confirm' => !empty($preview['can_confirm']),
        'first_item' => $preview['items'][0] ?? null,
    ];
    $out['result'] = dashglpi_admin_import_dispatch('computer_config.php', $preview['items']);
    $out['after'] = count_computers();
    $out['delta'] = $out['after'] - $out['before'];
} catch (Throwable $e) {
    $out['after'] = count_computers();
    $out['delta'] = $out['after'] - $out['before'];
    $out['error'] = ['class' => get_class($e), 'message' => $e->getMessage()];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

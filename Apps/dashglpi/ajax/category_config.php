<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/admin_tree_lib.php';

plugin_dashglpi_admin_bridge_handle('category', function (array $payload): array {
    $result = plugin_dashglpi_tree_category_process($payload);

    return [
        'summary' => $result['summary'],
        'items' => $result['items'],
        'category' => $result['category'],
        'message' => 'Categorias ITIL processadas no GLPI.',
    ];
}, 'Erro interno no bridge de categoria.');

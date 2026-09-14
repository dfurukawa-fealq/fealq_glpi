<?php

function plugin_dashglpi_tree_category_process(array $payload): array
{
    $items = [];
    if (isset($payload['items']) && is_array($payload['items'])) {
        $items = $payload['items'];
    } else {
        $items = [$payload];
    }

    $summary = [
        'created' => 0,
        'existing' => 0,
        'errors' => 0,
    ];
    $results = [];

    foreach ($items as $index => $item) {
        try {
            if (!is_array($item)) {
                throw new RuntimeException('Item de categoria invalido.');
            }
            $row = plugin_dashglpi_tree_category_save($item);
            $summary['created'] += (int) ($row['summary']['created'] ?? 0);
            $summary['existing'] += (int) ($row['summary']['existing'] ?? 0);
            $results[] = $row;
        } catch (Throwable $e) {
            $summary['errors']++;
            $results[] = [
                'status' => 'error',
                'source_line' => (int) ($item['source_line'] ?? ($index + 1)),
                'name' => (string) ($item['full_name'] ?? $item['name'] ?? ''),
                'error' => trim((string) $e->getMessage()) ?: 'Erro ao criar categoria.',
            ];
        }
    }

    return [
        'summary' => $summary,
        'items' => $results,
        'category' => count($results) === 1 ? ($results[0]['category'] ?? null) : null,
    ];
}

function plugin_dashglpi_tree_category_save(array $item): array
{
    $categoryClass = plugin_dashglpi_tree_category_class();
    $entitiesId = max(0, (int) ($item['entities_id'] ?? 0));
    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');

    $flags = plugin_dashglpi_tree_category_flags($item);
    $comment = trim((string) ($item['comment'] ?? ''));
    $summary = ['created' => 0, 'existing' => 0];
    $nodes = [];

    $segments = [];
    if (isset($item['path_segments']) && is_array($item['path_segments'])) {
        foreach ($item['path_segments'] as $segment) {
            $segment = plugin_dashglpi_admin_bridge_name((string) $segment, 'Informe o nome da categoria.');
            $segments[] = $segment;
        }
    }

    if ($segments) {
        $parentId = 0;
        foreach ($segments as $segment) {
            $node = plugin_dashglpi_tree_category_save_node($categoryClass, $segment, $entitiesId, $parentId, $flags, $comment);
            $summary[$node['status']]++;
            $nodes[] = $node;
            $parentId = (int) $node['id'];
        }
    } else {
        $name = plugin_dashglpi_admin_bridge_name((string) ($item['name'] ?? ''), 'Informe o nome da categoria.');
        $parentId = max(0, (int) ($item['parent_id'] ?? 0));
        if ($parentId > 0) {
            plugin_dashglpi_admin_bridge_require_item($categoryClass, $parentId, 'Categoria pai nao encontrada.');
        }
        $node = plugin_dashglpi_tree_category_save_node($categoryClass, $name, $entitiesId, $parentId, $flags, $comment);
        $summary[$node['status']]++;
        $nodes[] = $node;
    }

    $last = $nodes ? $nodes[count($nodes) - 1] : null;

    return [
        'status' => ($summary['created'] > 0) ? 'created' : 'existing',
        'source_line' => (int) ($item['source_line'] ?? 0),
        'full_name' => (string) ($item['full_name'] ?? ($last['name'] ?? '')),
        'summary' => $summary,
        'nodes' => $nodes,
        'category' => $last,
    ];
}

function plugin_dashglpi_tree_category_class(): string
{
    if (!class_exists('ITILCategory')) {
        $path = __DIR__ . '/../../../src/ITILCategory.php';
        if (is_file($path) && class_exists('CommonTreeDropdown')) {
            require_once $path;
        }
    }

    if (!class_exists('ITILCategory')) {
        throw new RuntimeException('Classe ITILCategory nao encontrada no GLPI.');
    }

    return 'ITILCategory';
}

function plugin_dashglpi_tree_category_save_node(
    string $categoryClass,
    string $name,
    int $entitiesId,
    int $parentId,
    array $flags,
    string $comment
): array {
    $existing = plugin_dashglpi_admin_bridge_find_one($categoryClass, [
        'name' => $name,
        'entities_id' => $entitiesId,
        'itilcategories_id' => $parentId,
    ]);

    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'name' => $name,
            'entities_id' => $entitiesId,
            'parent_id' => $parentId,
            'status' => 'existing',
        ];
    }

    $category = new $categoryClass();
    $id = (int) $category->add([
        'name' => $name,
        'entities_id' => $entitiesId,
        'itilcategories_id' => $parentId,
        'is_recursive' => $flags['is_recursive'],
        'is_helpdeskvisible' => $flags['is_helpdeskvisible'],
        'is_incident' => $flags['is_incident'],
        'is_request' => $flags['is_request'],
        'is_problem' => $flags['is_problem'],
        'is_change' => $flags['is_change'],
        'comment' => $comment !== '' ? $comment : 'Criado pelo cadastro de Categorias ITIL do DashGLPI.',
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao criar categoria "' . $name . '".');
    }

    return [
        'id' => $id,
        'name' => $name,
        'entities_id' => $entitiesId,
        'parent_id' => $parentId,
        'status' => 'created',
    ];
}

function plugin_dashglpi_tree_category_flags(array $item): array
{
    return [
        'is_recursive' => !empty($item['is_recursive']) ? 1 : 0,
        'is_helpdeskvisible' => !empty($item['is_helpdeskvisible']) ? 1 : 0,
        'is_incident' => !empty($item['is_incident']) ? 1 : 0,
        'is_request' => !empty($item['is_request']) ? 1 : 0,
        'is_problem' => !empty($item['is_problem']) ? 1 : 0,
        'is_change' => !empty($item['is_change']) ? 1 : 0,
    ];
}

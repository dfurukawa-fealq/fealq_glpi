<?php

// Processamento em lote (items[]) para os bridges de cadastro (PLAN-20260708-017).
// Mesmo contrato de resposta do lote de Categorias (admin_tree_lib.php):
// summary {created, existing, errors} + items[] com source_line/full_name.

function plugin_dashglpi_batch_process(array $payload, callable $saveOne, string $errorLabel): array
{
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [$payload];

    $summary = ['created' => 0, 'existing' => 0, 'errors' => 0];
    $results = [];

    foreach ($items as $index => $item) {
        try {
            if (!is_array($item)) {
                throw new RuntimeException('Item de ' . $errorLabel . ' invalido.');
            }
            $row = $saveOne($item);
            if (isset($row['summary'])) {
                $summary['created'] += (int) ($row['summary']['created'] ?? 0);
                $summary['existing'] += (int) ($row['summary']['existing'] ?? 0);
            } elseif (($row['status'] ?? '') === 'existing') {
                $summary['existing']++;
            } else {
                $summary['created']++;
            }
            $results[] = $row;
        } catch (Throwable $e) {
            $summary['errors']++;
            $results[] = [
                'status' => 'error',
                'source_line' => (int) ($item['source_line'] ?? ($index + 1)),
                'full_name' => (string) ($item['full_name'] ?? $item['name'] ?? $item['login'] ?? ''),
                'error' => trim((string) $e->getMessage()) ?: ('Erro ao processar ' . $errorLabel . '.'),
            ];
        }
    }

    return [
        'summary' => $summary,
        'items' => $results,
    ];
}

/**
 * Criação idempotente de um caminho hierárquico (mesma lógica nó a nó do
 * import de Categorias). $config:
 * - class: classe GLPI (CommonTreeDropdown, ex.: Entity, Group)
 * - parent_field: FK do pai (ex.: entities_id, groups_id)
 * - extra_criteria: campos adicionais na busca de existentes (ex.: entities_id do grupo)
 * - input: campos extras aplicados ao add() de cada nó novo
 * - error_label: rótulo em mensagens de erro
 */
function plugin_dashglpi_batch_tree_save(array $config, array $item): array
{
    $class = (string) $config['class'];
    $parentField = (string) $config['parent_field'];
    $extraCriteria = is_array($config['extra_criteria'] ?? null) ? $config['extra_criteria'] : [];
    $input = is_array($config['input'] ?? null) ? $config['input'] : [];
    $errorLabel = (string) ($config['error_label'] ?? 'registro');

    $segments = [];
    if (isset($item['path_segments']) && is_array($item['path_segments'])) {
        foreach ($item['path_segments'] as $segment) {
            $segments[] = plugin_dashglpi_admin_bridge_name((string) $segment, 'Informe o nome de ' . $errorLabel . '.');
        }
    }
    if (!$segments) {
        throw new RuntimeException('Caminho de ' . $errorLabel . ' vazio.');
    }

    $summary = ['created' => 0, 'existing' => 0];
    $nodes = [];
    $parentId = 0;

    foreach ($segments as $segment) {
        $existing = plugin_dashglpi_admin_bridge_find_one($class, [
            'name' => $segment,
            $parentField => $parentId,
        ] + $extraCriteria);

        if ($existing) {
            $node = [
                'id' => (int) $existing['id'],
                'name' => $segment,
                'parent_id' => $parentId,
                'status' => 'existing',
            ];
        } else {
            $object = new $class();
            $id = (int) $object->add([
                'name' => $segment,
                $parentField => $parentId,
            ] + $extraCriteria + $input);

            if ($id <= 0) {
                throw new RuntimeException('Falha ao criar ' . $errorLabel . ' "' . $segment . '".');
            }

            $node = [
                'id' => $id,
                'name' => $segment,
                'parent_id' => $parentId,
                'status' => 'created',
            ];
        }

        $summary[$node['status']]++;
        $nodes[] = $node;
        $parentId = (int) $node['id'];
    }

    return [
        'status' => ($summary['created'] > 0) ? 'created' : 'existing',
        'source_line' => (int) ($item['source_line'] ?? 0),
        'full_name' => (string) ($item['full_name'] ?? implode(' > ', $segments)),
        'entities_id' => isset($extraCriteria['entities_id']) ? (int) $extraCriteria['entities_id'] : null,
        'summary' => $summary,
        'nodes' => $nodes,
    ];
}

// Senha temporária forte para usuários importados sem coluna "Senha"
// (decisão aprovada no PLAN-20260708-017). Nunca logar/retornar o valor.
function plugin_dashglpi_batch_random_password(): string
{
    $sets = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%*+-?',
    ];

    $password = '';
    foreach ($sets as $set) {
        for ($i = 0; $i < 4; $i++) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }
    }

    return str_shuffle($password);
}

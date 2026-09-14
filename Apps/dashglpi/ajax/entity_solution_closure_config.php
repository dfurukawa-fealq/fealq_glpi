<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('entity_solution_closure', function (array $payload): array {
    $action = trim((string) ($payload['action'] ?? 'load'));

    return match ($action) {
        'load' => plugin_dashglpi_entity_solution_closure_load($payload),
        'save' => plugin_dashglpi_entity_solution_closure_save($payload),
        default => throw new RuntimeException('Acao de fechamento pós-solucao invalida.'),
    };
}, 'Erro interno no bridge de fechamento pós-solucao.');

function plugin_dashglpi_entity_solution_closure_load(array $payload): array
{
    $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
    $entity = plugin_dashglpi_entity_solution_closure_find_entity($entityId);

    return [
        'closure' => plugin_dashglpi_entity_solution_closure_state($entity),
    ];
}

function plugin_dashglpi_entity_solution_closure_save(array $payload): array
{
    $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
    $entity = plugin_dashglpi_entity_solution_closure_find_entity($entityId);
    $mode = plugin_dashglpi_entity_solution_closure_mode((string) ($payload['mode'] ?? 'inherit'));
    $isRoot = (int) ($entity['id'] ?? 0) === 0;

    if ($isRoot && $mode === 'inherit') {
        throw new RuntimeException('A entidade raiz nao pode herdar o fechamento pós-solucao.');
    }

    $currentLocalValue = plugin_dashglpi_entity_solution_closure_local_value($entity);
    $nextLocalValue = plugin_dashglpi_entity_solution_closure_value_from_payload($mode, $payload, $isRoot);

    if ($currentLocalValue !== $nextLocalValue) {
        $entityItem = new Entity();
        if (!$entityItem->getFromDB($entityId)) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        if (!$entityItem->update([
            'id' => $entityId,
            'autoclose_delay' => $nextLocalValue,
        ])) {
            throw new RuntimeException('Falha ao atualizar o fechamento pós-solucao da entidade.');
        }
    }

    $updatedEntity = plugin_dashglpi_entity_solution_closure_find_entity($entityId);

    return [
        'closure' => plugin_dashglpi_entity_solution_closure_state($updatedEntity),
    ];
}

function plugin_dashglpi_entity_solution_closure_state(array $entity): array
{
    $entityId = (int) ($entity['id'] ?? 0);
    $isRoot = $entityId === 0;
    $entityLabel = plugin_dashglpi_entity_solution_closure_entity_label($entity);
    $localValue = plugin_dashglpi_entity_solution_closure_local_value($entity);
    $inherited = plugin_dashglpi_entity_solution_closure_inherited_state($entity);
    $isInherited = !$isRoot && $localValue === Entity::CONFIG_PARENT;
    $effectiveValue = $isInherited ? $inherited['value'] : $localValue;
    $effectiveOriginLabel = $isInherited ? $inherited['origin_label'] : 'Valor proprio da entidade';
    $mode = $isInherited ? 'inherit' : plugin_dashglpi_entity_solution_closure_value_mode($localValue, $isRoot);
    $effectiveMode = plugin_dashglpi_entity_solution_closure_value_mode($effectiveValue, false);

    return [
        'entity' => [
            'id' => $entityId,
            'name' => (string) ($entity['name'] ?? $entityLabel),
            'completename' => $entityLabel,
            'parent_id' => max(0, (int) ($entity['entities_id'] ?? 0)),
            'is_root' => $isRoot,
        ],
        'local_value' => $localValue,
        'mode' => $mode,
        'days' => $mode === 'days' ? $localValue : 0,
        'effective_value' => $effectiveValue,
        'effective_mode' => $effectiveMode,
        'effective_days' => $effectiveMode === 'days' ? $effectiveValue : 0,
        'effective_label' => plugin_dashglpi_entity_solution_closure_value_label($effectiveValue, false),
        'effective_origin_label' => $effectiveOriginLabel,
        'inherited_value' => $inherited['value'],
        'inherited_mode' => $inherited['mode'],
        'inherited_days' => $inherited['days'],
        'inherited_label' => plugin_dashglpi_entity_solution_closure_value_label($inherited['value'], false),
        'inherited_origin_label' => $inherited['origin_label'],
    ];
}

function plugin_dashglpi_entity_solution_closure_inherited_state(array $entity): array
{
    $parentId = $entity['entities_id'] ?? null;
    $visited = [];

    while ($parentId !== null) {
        $parentId = (int) $parentId;
        if (isset($visited[$parentId])) {
            break;
        }
        $visited[$parentId] = true;

        $parent = plugin_dashglpi_entity_solution_closure_find_entity($parentId);
        $parentValue = plugin_dashglpi_entity_solution_closure_local_value($parent);
        $parentIsRoot = (int) ($parent['id'] ?? 0) === 0;
        if ($parentValue !== Entity::CONFIG_PARENT || $parentIsRoot) {
            $mode = plugin_dashglpi_entity_solution_closure_value_mode($parentValue, $parentIsRoot);
            return [
                'value' => $parentValue,
                'mode' => $mode,
                'days' => $mode === 'days' ? $parentValue : 0,
                'origin_label' => 'Herdado de ' . plugin_dashglpi_entity_solution_closure_entity_label($parent),
            ];
        }

        $parentId = $parent['entities_id'] ?? null;
    }

    return [
        'value' => Entity::CONFIG_NEVER,
        'mode' => 'never',
        'days' => 0,
        'origin_label' => 'Padrao GLPI',
    ];
}

function plugin_dashglpi_entity_solution_closure_find_entity(int $entityId): array
{
    global $DB;

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'entities_id', 'autoclose_delay'],
        'FROM' => Entity::getTable(),
        'WHERE' => ['id' => $entityId],
        'LIMIT' => 1,
    ]) as $row) {
        if (is_array($row)) {
            return $row;
        }
    }

    throw new RuntimeException('Entidade nao encontrada.');
}

function plugin_dashglpi_entity_solution_closure_entity_label(array $entity): string
{
    $label = trim((string) ($entity['completename'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    $label = trim((string) ($entity['name'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return (int) ($entity['id'] ?? 0) === 0 ? 'Entidade raiz' : 'Entidade';
}

function plugin_dashglpi_entity_solution_closure_mode(string $mode): string
{
    $mode = trim($mode);
    if (!in_array($mode, ['inherit', 'immediate', 'days', 'never'], true)) {
        throw new RuntimeException('Modo do fechamento pós-solucao invalido.');
    }

    return $mode;
}

function plugin_dashglpi_entity_solution_closure_local_value(array $entity): int
{
    $entityId = (int) ($entity['id'] ?? 0);
    $value = (int) ($entity['autoclose_delay'] ?? Entity::CONFIG_NEVER);
    if ($entityId === 0 && $value === Entity::CONFIG_PARENT) {
        return Entity::CONFIG_NEVER;
    }

    return $value;
}

function plugin_dashglpi_entity_solution_closure_value_from_payload(string $mode, array $payload, bool $isRoot): int
{
    return match ($mode) {
        'inherit' => $isRoot
            ? throw new RuntimeException('A entidade raiz nao pode herdar o fechamento pós-solucao.')
            : Entity::CONFIG_PARENT,
        'immediate' => 0,
        'never' => Entity::CONFIG_NEVER,
        'days' => plugin_dashglpi_entity_solution_closure_required_days($payload),
    };
}

function plugin_dashglpi_entity_solution_closure_required_days(array $payload): int
{
    $days = trim((string) ($payload['days'] ?? ''));
    if ($days === '' || !ctype_digit($days)) {
        throw new RuntimeException('Informe a quantidade de dias para o fechamento pós-solucao.');
    }

    $value = (int) $days;
    if ($value < 1 || $value > 99) {
        throw new RuntimeException('Fechamento pós-solucao deve estar entre 1 e 99 dias.');
    }

    return $value;
}

function plugin_dashglpi_entity_solution_closure_value_mode(int $value, bool $isRoot): string
{
    if (!$isRoot && $value === Entity::CONFIG_PARENT) {
        return 'inherit';
    }
    if ($value === 0) {
        return 'immediate';
    }
    if ($value === Entity::CONFIG_NEVER) {
        return 'never';
    }
    if ($value > 0) {
        return 'days';
    }

    return $isRoot ? 'never' : 'inherit';
}

function plugin_dashglpi_entity_solution_closure_value_label(int $value, bool $isRoot): string
{
    return match (plugin_dashglpi_entity_solution_closure_value_mode($value, $isRoot)) {
        'inherit' => 'Herdado',
        'immediate' => 'Imediato',
        'never' => 'Nunca',
        'days' => sprintf('%d dia%s', $value, $value === 1 ? '' : 's'),
        default => 'Herdado',
    };
}

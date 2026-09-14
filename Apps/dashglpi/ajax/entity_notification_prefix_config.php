<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('entity_notification_prefix', function (array $payload): array {
    $action = trim((string) ($payload['action'] ?? 'load'));

    return match ($action) {
        'load' => plugin_dashglpi_entity_notification_prefix_load($payload),
        'save' => plugin_dashglpi_entity_notification_prefix_save($payload),
        default => throw new RuntimeException('Acao de prefixo de notificacoes invalida.'),
    };
}, 'Erro interno no bridge de prefixo de notificacoes.');

function plugin_dashglpi_entity_notification_prefix_load(array $payload): array
{
    $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
    $entity = plugin_dashglpi_entity_notification_prefix_find_entity($entityId);

    return [
        'prefix' => plugin_dashglpi_entity_notification_prefix_state($entity),
    ];
}

function plugin_dashglpi_entity_notification_prefix_save(array $payload): array
{
    $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
    $mode = plugin_dashglpi_entity_notification_prefix_mode((string) ($payload['mode'] ?? 'inherit'));
    $entity = plugin_dashglpi_entity_notification_prefix_find_entity($entityId);
    $currentLocalValue = plugin_dashglpi_entity_notification_prefix_normalize((string) ($entity['notification_subject_tag'] ?? ''));
    $nextLocalValue = $mode === 'custom'
        ? plugin_dashglpi_entity_notification_prefix_required_value((string) ($payload['notification_subject_tag'] ?? ''))
        : '';

    if ($currentLocalValue !== $nextLocalValue) {
        $entityItem = new Entity();
        if (!$entityItem->getFromDB($entityId)) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        if (!$entityItem->update([
            'id' => $entityId,
            'notification_subject_tag' => $nextLocalValue,
        ])) {
            throw new RuntimeException('Falha ao atualizar o prefixo de notificacoes da entidade.');
        }
    }

    $updatedEntity = plugin_dashglpi_entity_notification_prefix_find_entity($entityId);

    return [
        'prefix' => plugin_dashglpi_entity_notification_prefix_state($updatedEntity),
    ];
}

function plugin_dashglpi_entity_notification_prefix_state(array $entity): array
{
    $entityId = (int) ($entity['id'] ?? 0);
    $localValue = plugin_dashglpi_entity_notification_prefix_normalize((string) ($entity['notification_subject_tag'] ?? ''));
    $entityLabel = plugin_dashglpi_entity_notification_prefix_entity_label($entity);
    $inherited = plugin_dashglpi_entity_notification_prefix_inherited_state($entity);
    $effectiveValue = $localValue !== '' ? $localValue : $inherited['value'];
    $effectiveOriginLabel = $localValue !== '' ? 'Valor proprio da entidade' : $inherited['origin_label'];

    return [
        'entity' => [
            'id' => $entityId,
            'name' => (string) ($entity['name'] ?? $entityLabel),
            'completename' => $entityLabel,
            'parent_id' => max(0, (int) ($entity['entities_id'] ?? 0)),
        ],
        'local_value' => $localValue,
        'mode' => $localValue !== '' ? 'custom' : 'inherit',
        'effective_value' => $effectiveValue,
        'effective_origin_label' => $effectiveOriginLabel,
        'subject_preview' => plugin_dashglpi_entity_notification_prefix_subject_preview($effectiveValue),
        'inherited_value' => $inherited['value'],
        'inherited_origin_label' => $inherited['origin_label'],
    ];
}

function plugin_dashglpi_entity_notification_prefix_inherited_state(array $entity): array
{
    $parentId = $entity['entities_id'] ?? null;
    $visited = [];

    while ($parentId !== null) {
        $parentId = (int) $parentId;
        if (isset($visited[$parentId])) {
            break;
        }
        $visited[$parentId] = true;

        $parent = plugin_dashglpi_entity_notification_prefix_find_entity($parentId);
        $value = plugin_dashglpi_entity_notification_prefix_normalize((string) ($parent['notification_subject_tag'] ?? ''));
        if ($value !== '') {
            return [
                'value' => $value,
                'origin_label' => 'Herdado de ' . plugin_dashglpi_entity_notification_prefix_entity_label($parent),
            ];
        }

        if ((int) ($parent['id'] ?? 0) === 0) {
            break;
        }

        $parentId = $parent['entities_id'] ?? null;
    }

    return [
        'value' => 'GLPI',
        'origin_label' => 'Padrao GLPI',
    ];
}

function plugin_dashglpi_entity_notification_prefix_find_entity(int $entityId): array
{
    global $DB;

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'entities_id', 'notification_subject_tag'],
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

function plugin_dashglpi_entity_notification_prefix_entity_label(array $entity): string
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

function plugin_dashglpi_entity_notification_prefix_mode(string $mode): string
{
    $mode = trim($mode);
    if (!in_array($mode, ['inherit', 'custom'], true)) {
        throw new RuntimeException('Modo do prefixo de notificacoes invalido.');
    }

    return $mode;
}

function plugin_dashglpi_entity_notification_prefix_normalize(string $value): string
{
    return substr(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 0, 255);
}

function plugin_dashglpi_entity_notification_prefix_required_value(string $value): string
{
    $value = plugin_dashglpi_entity_notification_prefix_normalize($value);
    if ($value === '') {
        throw new RuntimeException('Informe o prefixo de notificacoes.');
    }

    return $value;
}

function plugin_dashglpi_entity_notification_prefix_subject_preview(string $prefix): string
{
    return sprintf('[%s #0000025] <assunto do template>', $prefix !== '' ? $prefix : 'GLPI');
}

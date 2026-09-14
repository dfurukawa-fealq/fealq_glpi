<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('entity', function (array $payload): array {
    if (isset($payload['items']) && is_array($payload['items'])) {
        require_once __DIR__ . '/admin_batch_lib.php';

        $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_entity_batch_save', 'entidade');

        return [
            'summary' => $result['summary'],
            'items' => $result['items'],
            'message' => 'Entidades processadas no GLPI.',
        ];
    }

    $action = (string) ($payload['action'] ?? 'save');

    $result = match ($action) {
        'clone' => ['entity' => plugin_dashglpi_entity_clone($payload), 'group' => null],
        'delete' => ['entity' => plugin_dashglpi_entity_delete($payload), 'group' => null],
        default => plugin_dashglpi_entity_save($payload),
    };

    return [
        'entity' => $result['entity'],
        'group' => $result['group'],
        'message' => plugin_dashglpi_entity_status_message($result['entity']['status'] ?? ''),
    ];
}, 'Erro interno no bridge de entidade.');

function plugin_dashglpi_entity_status_message(string $status): string
{
    return match ($status) {
        'updated' => 'Entidade atualizada no GLPI.',
        'cloned' => 'Entidade clonada no GLPI.',
        'deleted' => 'Entidade excluida no GLPI.',
        'existing' => 'Entidade ja existia no GLPI.',
        default => 'Entidade criada no GLPI.',
    };
}

function plugin_dashglpi_entity_save(array $payload): array
{
    $entityId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome da entidade.');
    $parentId = max(0, (int) ($payload['parent_id'] ?? 0));
    $createGroup = !empty($payload['create_group']);

    if ($parentId > 0) {
        plugin_dashglpi_admin_bridge_require_item(Entity::class, $parentId, 'Entidade pai nao encontrada.');
    }
    if ($parentId === $entityId && $entityId > 0) {
        throw new RuntimeException('Uma entidade nao pode ser pai dela mesma.');
    }

    if ($entityId > 0) {
        $entity = new Entity();
        if (!$entity->getFromDB($entityId)) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        $existing = plugin_dashglpi_admin_bridge_find_one(Entity::class, [
            'name' => $name,
            'entities_id' => $parentId,
        ]);
        if ($existing && (int) ($existing['id'] ?? 0) !== $entityId) {
            throw new RuntimeException('Ja existe uma entidade com este nome nesta entidade pai.');
        }

        if (!$entity->update([
            'id' => $entityId,
            'name' => $name,
            'entities_id' => $parentId,
        ])) {
            throw new RuntimeException('Falha ao atualizar entidade.');
        }

        return [
            'entity' => [
                'id' => $entityId,
                'name' => $name,
                'parent_id' => $parentId,
                'status' => 'updated',
            ],
            'group' => null,
        ];
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Entity::class, [
        'name' => $name,
        'entities_id' => $parentId,
    ]);

    $entityStatus = 'existing';
    if ($existing) {
        $entityId = (int) $existing['id'];
    } else {
        $entity = new Entity();
        $entityId = (int) $entity->add([
            'name' => $name,
            'entities_id' => $parentId,
            'comment' => 'Criado pelo cadastro Cliente/Entidade do DashGLPI.',
        ]);

        if ($entityId <= 0) {
            throw new RuntimeException('Falha ao criar entidade.');
        }
        $entityStatus = 'created';
    }

    $groupResult = null;
    if ($createGroup) {
        $groupResult = plugin_dashglpi_admin_bridge_create_group($name, $entityId, '', 0);
    }

    return [
        'entity' => [
            'id' => $entityId,
            'name' => $name,
            'parent_id' => $parentId,
            'status' => $entityStatus,
        ],
        'group' => $groupResult,
    ];
}

// Import CSV: cria o caminho hierárquico nó a nó, sem os side-effects do
// cadastro unitário (create_group/create_sla_policy) — PLAN-20260708-017 §4.4.
function plugin_dashglpi_entity_batch_save(array $item): array
{
    return plugin_dashglpi_batch_tree_save([
        'class' => 'Entity',
        'parent_field' => 'entities_id',
        'extra_criteria' => [],
        'input' => ['comment' => 'Importado pelo CSV de Entidades do DashGLPI.'],
        'error_label' => 'entidade',
    ], $item);
}

function plugin_dashglpi_entity_clone(array $payload): array
{
    $sourceId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome da nova entidade.');

    if ($sourceId <= 0) {
        throw new RuntimeException('Entidade de origem nao encontrada.');
    }

    $entity = new Entity();
    if (!$entity->getFromDB($sourceId)) {
        throw new RuntimeException('Entidade de origem nao encontrada.');
    }

    $parentId = max(0, (int) ($entity->fields['entities_id'] ?? 0));
    $existing = plugin_dashglpi_admin_bridge_find_one(Entity::class, [
        'name' => $name,
        'entities_id' => $parentId,
    ]);
    if ($existing) {
        throw new RuntimeException('Ja existe uma entidade com este nome nesta entidade pai.');
    }

    $newId = $entity->clone(['name' => $name]);
    if ($newId === false || (int) $newId <= 0) {
        throw new RuntimeException('Falha ao clonar entidade.');
    }

    return [
        'id' => (int) $newId,
        'source_id' => $sourceId,
        'name' => $name,
        'parent_id' => $parentId,
        'status' => 'cloned',
    ];
}

function plugin_dashglpi_entity_delete(array $payload): array
{
    $entityId = max(0, (int) ($payload['id'] ?? 0));
    if ($entityId <= 0) {
        throw new RuntimeException('Nao e permitido excluir a entidade raiz.');
    }

    $entity = new Entity();
    if (!$entity->getFromDB($entityId)) {
        throw new RuntimeException('Entidade nao encontrada.');
    }

    global $DB;

    $childCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_entities', 'WHERE' => ['entities_id' => $entityId]])->current()['total'] ?? 0);
    if ($childCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: esta entidade possui entidades-filhas vinculadas.');
    }

    $groupCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_groups', 'WHERE' => ['entities_id' => $entityId]])->current()['total'] ?? 0);
    if ($groupCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: esta entidade possui grupos vinculados.');
    }

    $userCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_profiles_users', 'WHERE' => ['entities_id' => $entityId]])->current()['total'] ?? 0);
    if ($userCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: esta entidade possui usuarios vinculados.');
    }

    $ticketCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_tickets', 'WHERE' => ['entities_id' => $entityId]])->current()['total'] ?? 0);
    if ($ticketCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: esta entidade possui chamados vinculados.');
    }

    if (!$entity->delete(['id' => $entityId], true)) {
        throw new RuntimeException('Falha ao excluir entidade.');
    }

    return [
        'id' => $entityId,
        'status' => 'deleted',
    ];
}

function plugin_dashglpi_admin_bridge_create_group(string $name, int $entitiesId, string $comment, int $isRecursive): array
{
    $existing = plugin_dashglpi_admin_bridge_find_one(Group::class, [
        'name' => $name,
        'entities_id' => $entitiesId,
    ]);

    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'name' => $name,
            'entities_id' => $entitiesId,
            'status' => 'existing',
        ];
    }

    $group = new Group();
    $id = (int) $group->add([
        'name' => $name,
        'entities_id' => $entitiesId,
        'is_recursive' => $isRecursive,
        'comment' => $comment !== '' ? $comment : 'Criado pelo cadastro do DashGLPI.',
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao criar grupo.');
    }

    return [
        'id' => $id,
        'name' => $name,
        'entities_id' => $entitiesId,
        'status' => 'created',
    ];
}

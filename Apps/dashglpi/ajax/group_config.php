<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('group', function (array $payload): array {
    if (($payload['bridge_scope'] ?? '') === 'itil_category') {
        require_once __DIR__ . '/admin_tree_lib.php';

        $result = plugin_dashglpi_tree_category_process($payload);

        return [
            'summary' => $result['summary'],
            'items' => $result['items'],
            'category' => $result['category'],
            'message' => 'Categorias ITIL processadas no GLPI.',
        ];
    }

    if (isset($payload['items']) && is_array($payload['items'])) {
        require_once __DIR__ . '/admin_batch_lib.php';

        $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_group_batch_save', 'grupo');

        return [
            'summary' => $result['summary'],
            'items' => $result['items'],
            'message' => 'Grupos processados no GLPI.',
        ];
    }

    $action = (string) ($payload['action'] ?? 'save');
    $result = match ($action) {
        'update' => plugin_dashglpi_group_update($payload),
        'clone' => plugin_dashglpi_group_clone($payload),
        'delete' => plugin_dashglpi_group_delete($payload),
        default => plugin_dashglpi_group_save($payload),
    };

    return [
        'group' => $result,
        'message' => plugin_dashglpi_group_status_message($result['status'] ?? ''),
    ];
}, 'Erro interno no bridge de grupo.');

function plugin_dashglpi_group_status_message(string $status): string
{
    return match ($status) {
        'updated' => 'Grupo atualizado no GLPI.',
        'cloned' => 'Grupo clonado no GLPI.',
        'deleted' => 'Grupo excluido no GLPI.',
        'existing' => 'Grupo ja existia no GLPI.',
        default => 'Grupo criado no GLPI.',
    };
}

// Import CSV: suporta hierarquia de grupos ("A > B") — a exportação do GLPI
// traz o completename, então o import recria o caminho nó a nó (PLAN-20260708-017).
function plugin_dashglpi_group_batch_save(array $item): array
{
    $entitiesId = max(0, (int) ($item['entities_id'] ?? 0));
    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');

    $comment = trim((string) ($item['comment'] ?? ''));

    return plugin_dashglpi_batch_tree_save([
        'class' => 'Group',
        'parent_field' => 'groups_id',
        'extra_criteria' => ['entities_id' => $entitiesId],
        'input' => [
            'is_recursive' => !empty($item['is_recursive']) ? 1 : 0,
            'comment' => $comment !== '' ? $comment : 'Importado pelo CSV de Grupos do DashGLPI.',
        ],
        'error_label' => 'grupo',
    ], $item);
}

function plugin_dashglpi_group_save(array $payload): array
{
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do grupo.');
    $entitiesId = max(0, (int) ($payload['entities_id'] ?? 0));
    $comment = trim((string) ($payload['comment'] ?? ''));
    $isRecursive = !empty($payload['is_recursive']) ? 1 : 0;

    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');

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
        'comment' => $comment !== '' ? $comment : 'Criado pelo cadastro de Grupo do DashGLPI.',
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

function plugin_dashglpi_group_update(array $payload): array
{
    $groupId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do grupo.');
    $entitiesId = max(0, (int) ($payload['entities_id'] ?? 0));
    $comment = trim((string) ($payload['comment'] ?? ''));
    $isRecursive = !empty($payload['is_recursive']) ? 1 : 0;

    if ($groupId <= 0) {
        throw new RuntimeException('Grupo nao encontrado.');
    }

    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');

    $group = new Group();
    if (!$group->getFromDB($groupId)) {
        throw new RuntimeException('Grupo nao encontrado.');
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Group::class, [
        'name' => $name,
        'entities_id' => $entitiesId,
    ]);
    if ($existing && (int) ($existing['id'] ?? 0) !== $groupId) {
        throw new RuntimeException('Ja existe um grupo com este nome nesta entidade.');
    }

    if (!$group->update([
        'id' => $groupId,
        'name' => $name,
        'entities_id' => $entitiesId,
        'is_recursive' => $isRecursive,
        'comment' => $comment,
    ])) {
        throw new RuntimeException('Falha ao atualizar grupo.');
    }

    return [
        'id' => $groupId,
        'name' => $name,
        'entities_id' => $entitiesId,
        'status' => 'updated',
    ];
}

function plugin_dashglpi_group_clone(array $payload): array
{
    $sourceId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do novo grupo.');

    if ($sourceId <= 0) {
        throw new RuntimeException('Grupo de origem nao encontrado.');
    }

    $group = new Group();
    if (!$group->getFromDB($sourceId)) {
        throw new RuntimeException('Grupo de origem nao encontrado.');
    }

    $entitiesId = (int) ($group->fields['entities_id'] ?? 0);
    $existing = plugin_dashglpi_admin_bridge_find_one(Group::class, [
        'name' => $name,
        'entities_id' => $entitiesId,
    ]);
    if ($existing) {
        throw new RuntimeException('Ja existe um grupo com este nome nesta entidade.');
    }

    $newId = $group->clone(['name' => $name]);
    if ($newId === false || (int) $newId <= 0) {
        throw new RuntimeException('Falha ao clonar grupo.');
    }

    return [
        'id' => (int) $newId,
        'source_id' => $sourceId,
        'name' => $name,
        'entities_id' => $entitiesId,
        'status' => 'cloned',
    ];
}

function plugin_dashglpi_group_delete(array $payload): array
{
    $groupId = max(0, (int) ($payload['id'] ?? 0));
    if ($groupId <= 0) {
        throw new RuntimeException('Grupo nao encontrado.');
    }

    $group = new Group();
    if (!$group->getFromDB($groupId)) {
        throw new RuntimeException('Grupo nao encontrado.');
    }

    global $DB;

    $memberCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_groups_users', 'WHERE' => ['groups_id' => $groupId]])->current()['total'] ?? 0);
    if ($memberCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: este grupo possui usuarios vinculados.');
    }

    $ticketCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_groups_tickets', 'WHERE' => ['groups_id' => $groupId]])->current()['total'] ?? 0);
    if ($ticketCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: este grupo possui chamados vinculados.');
    }

    if (!$group->delete(['id' => $groupId], true)) {
        throw new RuntimeException('Falha ao excluir grupo.');
    }

    return [
        'id' => $groupId,
        'status' => 'deleted',
    ];
}

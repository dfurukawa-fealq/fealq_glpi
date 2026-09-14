<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('ticket_assignment', function (array $payload): array {
    $ticketId = max(0, (int) ($payload['ticket_id'] ?? 0));
    $usersId = max(0, (int) ($payload['users_id'] ?? 0));
    $groupsId = max(0, (int) ($payload['groups_id'] ?? 0));

    if ($ticketId <= 0) {
        throw new RuntimeException('Chamado invalido.');
    }
    if ($usersId <= 0 && $groupsId <= 0) {
        throw new RuntimeException('Selecione um tecnico, um grupo ou ambos.');
    }

    $ticketFields = plugin_dashglpi_admin_bridge_require_ticket($ticketId, 'Chamado nao encontrado.', 'Chamado removido nao pode ser atribuido.');
    if (in_array((int) ($ticketFields['status'] ?? 0), [5, 6], true)) {
        throw new RuntimeException('Chamado encerrado nao pode ser atribuido pelo Dash.');
    }

    $assignment = [
        'ticket_id' => $ticketId,
        'user' => null,
        'group' => null,
    ];

    if ($usersId > 0) {
        plugin_dashglpi_admin_bridge_require_item(User::class, $usersId, 'Tecnico nao encontrado.');
        $assignment['user'] = plugin_dashglpi_assign_ticket_user($ticketId, $usersId);
    }

    if ($groupsId > 0) {
        plugin_dashglpi_admin_bridge_require_item(Group::class, $groupsId, 'Grupo nao encontrado.');
        $assignment['group'] = plugin_dashglpi_assign_ticket_group($ticketId, $groupsId);
    }

    return [
        'assignment' => $assignment,
        'message' => 'Chamado atribuido no GLPI.',
    ];
}, 'Erro interno no bridge de atribuicao.');

function plugin_dashglpi_assign_ticket_user(int $ticketId, int $usersId): array
{
    $existing = plugin_dashglpi_admin_bridge_find_one(Ticket_User::class, [
        'tickets_id' => $ticketId,
        'users_id' => $usersId,
        'type' => CommonITILActor::ASSIGN,
    ]);

    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'users_id' => $usersId,
            'status' => 'existing',
        ];
    }

    $relation = new Ticket_User();
    $id = (int) $relation->add([
        'tickets_id' => $ticketId,
        'users_id' => $usersId,
        'type' => CommonITILActor::ASSIGN,
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao atribuir tecnico ao chamado.');
    }

    return [
        'id' => $id,
        'users_id' => $usersId,
        'status' => 'created',
    ];
}

function plugin_dashglpi_assign_ticket_group(int $ticketId, int $groupsId): array
{
    $existing = plugin_dashglpi_admin_bridge_find_one(Group_Ticket::class, [
        'tickets_id' => $ticketId,
        'groups_id' => $groupsId,
        'type' => CommonITILActor::ASSIGN,
    ]);

    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'groups_id' => $groupsId,
            'status' => 'existing',
        ];
    }

    $relation = new Group_Ticket();
    $id = (int) $relation->add([
        'tickets_id' => $ticketId,
        'groups_id' => $groupsId,
        'type' => CommonITILActor::ASSIGN,
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao atribuir grupo ao chamado.');
    }

    return [
        'id' => $id,
        'groups_id' => $groupsId,
        'status' => 'created',
    ];
}

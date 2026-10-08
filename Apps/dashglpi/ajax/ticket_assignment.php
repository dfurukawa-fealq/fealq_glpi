<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/glpi_admin.php';

dashglpi_require_auth();
dashglpi_assert_page_access('sla');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json([
            'ok' => true,
            'users' => dashglpi_assignment_users(),
            'groups' => dashglpi_assignment_groups(),
        ]);
    }
} catch (Throwable $e) {
    dashglpi_ajax_error_response('ticket_assignment_get', $e, 'Erro interno na atribuicao do chamado.');
}

dashglpi_ajax_bridge_endpoint('ticket assignment', function () {
    $payload = dashglpi_assignment_payload($_POST);
    $result = dashglpi_admin_bridge_request('ticket_assignment_config.php', $payload);

    return [
        'result' => $result,
        'message' => 'Atribuicao processada no GLPI.',
    ];
}, 'Erro interno na atribuicao do chamado.');

function dashglpi_assignment_payload(array $post): array
{
    $ticketId = max(0, (int) ($post['ticket_id'] ?? 0));
    $usersId = max(0, (int) ($post['users_id'] ?? 0));
    $groupsId = max(0, (int) ($post['groups_id'] ?? 0));

    if ($ticketId <= 0) {
        throw new RuntimeException('Chamado invalido.', 400);
    }
    if ($usersId <= 0 && $groupsId <= 0) {
        throw new RuntimeException('Selecione um tecnico, um grupo ou ambos.', 422);
    }

    return [
        'ticket_id' => $ticketId,
        'users_id' => $usersId,
        'groups_id' => $groupsId,
    ];
}

function dashglpi_assignment_users(): array
{
    return dashglpi_fetch_all(
        "SELECT u.id, u.name, u.firstname, u.realname,
                TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) AS display_name
         FROM glpi_users u
         WHERE u.is_deleted = 0
           AND u.is_active = 1
         ORDER BY display_name ASC, u.name ASC"
    );
}

function dashglpi_assignment_groups(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, completename, entities_id, is_recursive
         FROM glpi_groups
         ORDER BY completename ASC, name ASC"
    );
}

<?php

// Autenticação por sessão Dash; permissões nativas no bridge (PLAN-20260905-001).
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/ticket_attendance.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

dashglpi_ajax_bridge_endpoint('ticket attendance', function (): array {
    $ticketId = dashglpi_ajax_require_ticket_id($_POST);
    $payload = array_intersect_key($_POST, array_flip([
        'action', 'revision', 'content', 'is_private', 'status', 'user_id', 'reason',
        'role', 'itemtype', 'items_id', 'operation', 'pendingreasons_id', 'solutiontypes_id',
        'duration_minutes', 'state', 'users_id_tech', 'groups_id_tech', 'taskcategories_id', 'begin', 'end',
    ]));

    $payload['changes'] = json_decode((string) ($_POST['changes'] ?? '{}'), true);
    return dashglpi_attendance_request('ticket_update_config.php', $ticketId, $payload, dashglpi_attendance_uploads($_FILES));
});

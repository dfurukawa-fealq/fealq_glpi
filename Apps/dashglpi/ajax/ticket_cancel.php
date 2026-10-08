<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/ticket_attendance.php'; // PLAN-20260905-001

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

dashglpi_ajax_bridge_endpoint('ticket cancel', function () {
    $ticketId = dashglpi_ajax_require_ticket_id($_POST);
    dashglpi_assert_ticket_access($ticketId);

    $reason = trim((string) ($_POST['reason'] ?? ''));

    $result = dashglpi_attendance_request('ticket_cancel_config.php', $ticketId, [
        'action' => 'cancel',
        'ticket_id' => $ticketId,
        'reason' => $reason,
        'revision' => (string) ($_POST['revision'] ?? ''),
    ]);

    return ['result' => $result];
}, 'Erro interno ao cancelar chamado.');

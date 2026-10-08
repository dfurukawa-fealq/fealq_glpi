<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/ticket_attendance.php'; // PLAN-20260905-001

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    dashglpi_ajax_get_endpoint('ticket_satisfaction_get', static function (): array {
        $ticketId = dashglpi_ajax_require_ticket_id($_GET);
        dashglpi_assert_ticket_access($ticketId);

        $result = dashglpi_attendance_request('ticket_satisfaction_config.php', $ticketId, [
            'action' => 'catalog',
            'ticket_id' => $ticketId,
        ]);

        return ['ok' => true, 'satisfaction' => $result['satisfaction'] ?? null];
    }, 'Erro interno ao carregar pesquisa de satisfacao.');
}

dashglpi_ajax_bridge_endpoint('ticket satisfaction', function () {
    $ticketId = dashglpi_ajax_require_ticket_id($_POST);
    dashglpi_assert_ticket_access($ticketId);

    $rate = (int) ($_POST['satisfaction'] ?? -1);
    if ($rate < 0) {
        throw new RuntimeException('Informe uma nota para a pesquisa de satisfacao.', 422);
    }

    $result = dashglpi_attendance_request('ticket_satisfaction_config.php', $ticketId, [
        'action' => 'submit',
        'ticket_id' => $ticketId,
        'satisfaction' => $rate,
        'comment' => trim((string) ($_POST['comment'] ?? '')),
        'revision' => (string) ($_POST['revision'] ?? ''),
    ]);

    return ['satisfaction' => $result['satisfaction'] ?? null];
}, 'Erro interno ao registrar pesquisa de satisfacao.');

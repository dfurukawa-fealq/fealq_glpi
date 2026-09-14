<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ticket_attendance.php'; // PLAN-20260905-001

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $ticketId = dashglpi_ajax_require_ticket_id($_GET);
        dashglpi_assert_ticket_access($ticketId);

        $result = dashglpi_attendance_request('ticket_satisfaction_config.php', $ticketId, [
            'action' => 'catalog',
            'ticket_id' => $ticketId,
        ]);

        dashglpi_json(['ok' => true, 'satisfaction' => $result['satisfaction'] ?? null]);
    }
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] ticket satisfaction error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['ok' => false, 'error' => $message !== '' ? $message : 'Erro interno ao registrar pesquisa de satisfacao.'], 500);
}

dashglpi_ajax_bridge_endpoint('ticket satisfaction', function () {
    $ticketId = dashglpi_ajax_require_ticket_id($_POST);
    dashglpi_assert_ticket_access($ticketId);

    $rate = (int) ($_POST['satisfaction'] ?? -1);
    if ($rate < 0) {
        throw new RuntimeException('Informe uma nota para a pesquisa de satisfacao.');
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

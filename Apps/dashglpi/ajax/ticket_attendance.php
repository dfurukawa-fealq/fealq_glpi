<?php

// Regime A: sessão Dash; catálogo do ticket autorizado (PLAN-20260905-001).
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ticket_attendance.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        dashglpi_json(['ok' => false, 'error' => DASHGLPI_AJAX_MSG_METHOD_NOT_ALLOWED], 405);
    }
    $ticketId = dashglpi_ajax_require_ticket_id($_GET);
    dashglpi_json(dashglpi_attendance_request('ticket_attendance_config.php', $ticketId,
        ['action' => 'catalog', 'q' => (string) ($_GET['q'] ?? '')]));
} catch (Throwable $e) {
    error_log('[DashGLPI] attendance catalog: ' . $e->getMessage());
    dashglpi_json(['ok' => false, 'error' => $e->getMessage()], 403);
}

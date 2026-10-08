<?php

// Regime A: sessão Dash; catálogo do ticket autorizado (PLAN-20260905-001).
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/ticket_attendance.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');
dashglpi_ajax_get_endpoint('attendance_catalog', static function (): array {
    $ticketId = dashglpi_ajax_require_ticket_id($_GET);
    return dashglpi_attendance_request('ticket_attendance_config.php', $ticketId, [
        'action' => 'catalog',
        'q' => (string) ($_GET['q'] ?? ''),
    ]);
}, 'Erro ao carregar catalogo do atendimento.');

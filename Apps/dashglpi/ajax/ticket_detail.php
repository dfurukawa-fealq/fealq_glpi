<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/dashboard.class.php';
require_once __DIR__ . '/../inc/ticket_attendance.php'; // PLAN-20260905-001

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

try {
    $ticketId = max(0, (int) ($_GET['ticket_id'] ?? 0));
    if ($ticketId <= 0) {
        dashglpi_json(['ok' => false, 'error' => 'Chamado invalido.'], 400);
    }

    // Whitelist de objeto ITIL (PLAN-20260709-019, Fase D); default 'ticket'
    // mantém o contrato atual do endpoint.
    $itemtype = dashglpi_itil_normalize_type($_GET['itemtype'] ?? 'ticket');
    dashglpi_assert_itil_object_access($itemtype, $ticketId);

    if ($itemtype === 'ticket') {
        $result = dashglpi_attendance_request('ticket_attendance_config.php', $ticketId, ['action' => 'detail']);
        // Mantém o contrato de satisfação já consumido pelo Self-Service.
        $legacy = PluginDashglpiDashboard::getTicketDetail($ticketId);
        $result['ticket']['satisfaction_pending'] = (int) ($legacy['satisfaction_pending'] ?? 0);
        dashglpi_json($result);
    }

    $ticket = PluginDashglpiDashboard::getItilObjectDetail($itemtype, $ticketId);
    if (!$ticket) {
        dashglpi_json(['ok' => false, 'error' => 'Registro nao encontrado.'], 404);
    }

    dashglpi_json(['ok' => true, 'ticket' => $ticket]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] ticket detail error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['ok' => false, 'error' => $message !== '' ? $message : 'Erro interno ao carregar chamado.'], 500);
}

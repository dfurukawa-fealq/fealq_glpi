<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/../inc/ticket_attendance_bridge.php'; // PLAN-20260905-001

dashglpi_attendance_bridge_handle(function (array $payload, Ticket $ticket): array {
    if (($payload['action'] ?? '') === 'submit') {
        return dashglpi_attendance_mutate($ticket, $payload,
            fn(Ticket $locked): array => plugin_dashglpi_ticket_satisfaction_handle($payload, $locked));
    }
    return plugin_dashglpi_ticket_satisfaction_handle($payload, $ticket);
});

function plugin_dashglpi_ticket_satisfaction_handle(array $payload, Ticket $ticket): array
{
    if (!$ticket->canApprove()) {
        throw new RuntimeException('Somente o solicitante autorizado pode responder à pesquisa.', 403);
    }
    $action = (string) ($payload['action'] ?? '');
    $ticketId = max(0, (int) ($payload['ticket_id'] ?? 0));
    if ($ticketId <= 0) {
        throw new RuntimeException('Chamado invalido.');
    }

    $ticketFields = plugin_dashglpi_admin_bridge_require_ticket($ticketId, 'Chamado nao encontrado.', 'Chamado removido nao possui pesquisa de satisfacao.');

    $satisfaction = plugin_dashglpi_admin_bridge_find_one(TicketSatisfaction::class, ['tickets_id' => $ticketId]);
    if (!$satisfaction) {
        throw new RuntimeException('Nao existe pesquisa de satisfacao pendente para este chamado.');
    }

    $maxRate = (int) Entity::getUsedConfig(
        'inquest_config',
        (int) ($ticketFields['entities_id'] ?? 0),
        'inquest_max_rate'
    );

    if ($action === 'catalog') {
        return ['satisfaction' => plugin_dashglpi_ticket_satisfaction_view($satisfaction, $maxRate)];
    }

    if ($action === 'submit') {
        if (!empty($satisfaction['date_answered'])) {
            throw new RuntimeException('A pesquisa de satisfacao deste chamado ja foi respondida.');
        }

        $rate = max(0, min($maxRate, (int) ($payload['satisfaction'] ?? -1)));
        $comment = trim((string) ($payload['comment'] ?? ''));

        $item = new TicketSatisfaction();
        // TicketSatisfaction usa tickets_id como chave de domínio no GLPI 11.
        if (!$item->can($ticketId, UPDATE)) {
            throw new RuntimeException('Resposta da pesquisa não permitida.', 403);
        }
        if (!$item->update([
            'tickets_id' => $ticketId,
            'satisfaction' => $rate,
            'comment' => $comment,
        ])) {
            throw new RuntimeException('Falha ao registrar a pesquisa de satisfacao.');
        }

        $item->getFromDB($ticketId);

        return ['satisfaction' => plugin_dashglpi_ticket_satisfaction_view($item->fields, $maxRate)];
    }

    throw new RuntimeException('Acao desconhecida.');
}

function plugin_dashglpi_ticket_satisfaction_view(array $satisfaction, int $maxRate): array
{
    return [
        'id' => (int) ($satisfaction['id'] ?? 0),
        'tickets_id' => (int) ($satisfaction['tickets_id'] ?? 0),
        'max_rate' => $maxRate,
        'satisfaction' => $satisfaction['satisfaction'] !== null ? (int) $satisfaction['satisfaction'] : null,
        'comment' => (string) ($satisfaction['comment'] ?? ''),
        'date_answered' => $satisfaction['date_answered'] ?? null,
        'is_answered' => !empty($satisfaction['date_answered']),
    ];
}

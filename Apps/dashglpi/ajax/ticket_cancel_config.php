<?php

// Contexto do solicitante e transição nativa (PLAN-20260905-001).
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/../inc/ticket_attendance_bridge.php';

dashglpi_attendance_bridge_handle(function (array $payload, Ticket $ticket): array {
    return dashglpi_attendance_mutate($ticket, $payload, function (Ticket $locked) use ($payload): array {
        dashglpi_attendance_require_capability($locked, 'cancel');
        $reason = trim((string) ($payload['reason'] ?? ''));
        dashglpi_attendance_add_event($locked, ['content' => 'Cancelamento solicitado.' . ($reason !== '' ? ' Motivo: ' . $reason : '')], 'reply');
        if (!$locked->update(['id' => $locked->getID(), 'status' => Ticket::CLOSED])) {
            throw new RuntimeException('GLPI não permitiu o cancelamento.', 422);
        }
        return ['result' => ['ticket_id' => $locked->getID(), 'status' => Ticket::CLOSED]];
    });
});

<?php

// Autenticação por token de bridge + contexto nativo do ator (PLAN-20260905-001).
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/../inc/ticket_attendance_bridge.php';

dashglpi_attendance_bridge_handle(function (array $payload, Ticket $ticket): array {
    if (($payload['action'] ?? '') === 'list') {
        return dashglpi_attendance_timeline($ticket, $payload);
    }
    return dashglpi_attendance_mutate($ticket, $payload, function (Ticket $locked) use ($payload): array {
        return dashglpi_attendance_add_event($locked, $payload, !empty($payload['is_private']) ? 'private' : 'reply');
    });
});

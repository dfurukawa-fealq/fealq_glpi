<?php

// Regime B: token de bridge; leitura sob direitos do ator (PLAN-20260905-001).
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/../inc/ticket_attendance_bridge.php';

dashglpi_attendance_bridge_handle(function (array $payload, Ticket $ticket): array {
    switch ($payload['action'] ?? '') {
        case 'detail':
            return ['ticket' => dashglpi_attendance_detail($ticket)];
        case 'catalog':
            return dashglpi_attendance_catalog($ticket, $payload);
        case 'document':
            $docs = dashglpi_attendance_documents($ticket, null, (int) ($payload['document_id'] ?? 0));
            if ((int) ($payload['document_id'] ?? 0) <= 0 || !$docs) {
                throw new RuntimeException('Documento não disponível para este usuário.', 403);
            }
            return ['document' => $docs[0]];
        default:
            throw new RuntimeException('Consulta de atendimento inválida.', 400);
    }
});

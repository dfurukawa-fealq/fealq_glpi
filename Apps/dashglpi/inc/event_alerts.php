<?php

/**
 * Deduplicação genérica de alertas por tipo de evento (Task/Solution workers).
 *
 * Tabela independente de glpi_plugin_dashglpi_sla_alerts (que continua intocada,
 * conforme PLAN-20260702-007 §4 "Impacto na Camada de Dados" — evita migração em
 * tabela já validada em produção). event_type namespacea a chave para permitir
 * múltiplos workers sem colisão (ex.: 'task_assigned', 'solution_pending').
 */

const DASHGLPI_EVENT_ALERTS_TABLE = 'glpi_plugin_dashglpi_event_alerts';

function dashglpi_event_alerts_ensure_table(): void
{
    dashglpi_db()->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_EVENT_ALERTS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(30) NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            channels VARCHAR(191) NOT NULL DEFAULT '',
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_event_alert (event_type, event_id, level),
            KEY idx_dashglpi_event_alert_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dashglpi_event_alert_already_sent(string $eventType, int $eventId, string $level = 'info'): bool
{
    $row = dashglpi_fetch_one(
        "SELECT id FROM " . DASHGLPI_EVENT_ALERTS_TABLE . "
         WHERE event_type = ? AND event_id = ? AND level = ? LIMIT 1",
        [$eventType, $eventId, $level]
    );

    return $row !== null;
}

function dashglpi_event_alert_record(string $eventType, int $eventId, string $level, array $channels): void
{
    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_EVENT_ALERTS_TABLE . " (event_type, event_id, level, channels)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            channels = VALUES(channels),
            sent_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$eventType, $eventId, $level, substr(implode(',', $channels), 0, 191)]);
}

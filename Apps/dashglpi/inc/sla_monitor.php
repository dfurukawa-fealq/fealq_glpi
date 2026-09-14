<?php

require_once __DIR__ . '/messaging/notification_dispatcher.php';
require_once __DIR__ . '/event_alerts.php';

/**
 * Motor de monitoramento ativo de SLA (Step 5 do .Stack.md).
 *
 * Varre chamados em status "Novo" (status = 1) que ultrapassaram a janela limite
 * (em minutos) sem qualquer ciência/andamento (sem técnico atribuído e sem
 * acompanhamento), deduplica contra a tabela de alertas já enviados e despacha
 * para os canais habilitados. Cada passada é idempotente — o loop de execução
 * fica a cargo do container worker.
 */

const DASHGLPI_SLA_ALERTS_TABLE = 'glpi_plugin_dashglpi_sla_alerts';

/** Status GLPI "Novo"/Incoming. */
const DASHGLPI_TICKET_STATUS_NEW = 1;

function dashglpi_sla_alerts_ensure_table(): void
{
    dashglpi_db()->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_SLA_ALERTS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tickets_id INT UNSIGNED NOT NULL,
            level VARCHAR(10) NOT NULL DEFAULT 'breach',
            minutes_overdue INT NOT NULL DEFAULT 0,
            channels VARCHAR(191) NOT NULL DEFAULT '',
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_sla_alert_ticket_level (tickets_id, level),
            KEY idx_dashglpi_sla_alert_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Executa uma passada de monitoramento.
 *
 * @param bool $dryRun quando true, não despacha nem grava (apenas retorna candidatos)
 * @return array{scanned:int,alerts:array<int,array>,dispatched:int,errors:string[]}
 */
function dashglpi_sla_monitor_run(bool $dryRun = false): array
{
    dashglpi_sla_alerts_ensure_table();

    $config = dashglpi_alerting_config();
    $breachThreshold = max(1, (int) ($config['sla_threshold_minutes'] ?? 15));
    $warnThreshold = (int) ($config['warn_threshold_minutes'] ?? 0);
    $minThreshold = $warnThreshold > 0 ? min($warnThreshold, $breachThreshold) : $breachThreshold;

    // Trava de idade (PLAN-20260704-014): endurece dashglpi_sla_monitor_candidates() contra
    // chamados "Novo" muito antigos, sem afetar o widget do dashboard (snapshot separado).
    $maxAgeHours = (int) ($config['max_age_hours'] ?? 0);
    $maxAgeMinutes = $maxAgeHours > 0 ? $maxAgeHours * 60 : null;
    $sinceDate = trim((string) ($config['since_date'] ?? ''));
    $sinceDate = $sinceDate !== '' ? $sinceDate : null;

    $candidates = dashglpi_sla_monitor_candidates($minThreshold, $maxAgeMinutes, $sinceDate);
    $dispatcher = new DashglpiNotificationDispatcher();

    $result = ['scanned' => count($candidates), 'alerts' => [], 'dispatched' => 0, 'errors' => []];

    foreach ($candidates as $ticket) {
        $entitiesId = (int) ($ticket['entities_id'] ?? 0);
        $entityConfig = dashglpi_alerting_apply_entity_override($config, $entitiesId);
        $entityBreach = max(1, (int) ($entityConfig['sla_threshold_minutes'] ?? $breachThreshold));
        $age = (int) ($ticket['age_minutes'] ?? 0);

        $level = null;
        if ($age >= $entityBreach) {
            $level = 'breach';
        } elseif ($warnThreshold > 0 && $age >= $warnThreshold) {
            $level = 'warn';
        }
        if ($level === null) {
            continue;
        }

        $alert = dashglpi_sla_monitor_build_alert($ticket, $level, $entityBreach, $age);

        if ($dryRun) {
            $result['alerts'][] = $alert + ['would_dispatch' => true];
            continue;
        }

        if (dashglpi_sla_alert_already_sent((int) $ticket['id'], $level)) {
            continue;
        }

        $dispatch = $dispatcher->dispatch($alert, $entityConfig);
        $okChannels = [];
        foreach ($dispatch as $channelKey => $res) {
            if (!empty($res['ok'])) {
                $okChannels[] = $channelKey;
            } elseif (empty($res['skipped']) && !empty($res['error'])) {
                $result['errors'][] = 'Ticket #' . $ticket['id'] . ' [' . $channelKey . ']: ' . $res['error'];
            }
        }

        // Só marca como enviado se ao menos um canal aceitou — assim uma falha
        // transitória é reprocessada no próximo ciclo em vez de silenciar o alerta.
        if ($okChannels) {
            dashglpi_sla_alert_record((int) $ticket['id'], $level, $age, $okChannels);
            $result['dispatched']++;
        }

        $result['alerts'][] = $alert + ['channels' => $okChannels, 'results' => $dispatch];
    }

    // PLAN-20260709-019 (Fase E, Decisão 1 ≠ c): réplica do monitor "sem atribuição"
    // para Problema/Manutenção. O fluxo de chamados acima fica intocado (dedupe na
    // tabela glpi_plugin_dashglpi_sla_alerts, já validada em produção); os objetos
    // novos deduplicam em glpi_plugin_dashglpi_event_alerts com event_type
    // namespaceado ('sla_unattended:problem'/':change'), evitando colisão de ids.
    dashglpi_event_alerts_ensure_table();
    foreach (dashglpi_itil_type_keys() as $typeKey) {
        if ($typeKey === 'ticket') {
            continue;
        }

        $type = dashglpi_itil_type($typeKey);
        $eventType = 'sla_unattended:' . $typeKey;
        $candidates = dashglpi_itil_unattended_candidates($type, $minThreshold, $maxAgeMinutes, $sinceDate);
        $result['scanned'] += count($candidates);

        foreach ($candidates as $object) {
            $entitiesId = (int) ($object['entities_id'] ?? 0);
            $entityConfig = dashglpi_alerting_apply_entity_override($config, $entitiesId);
            $entityBreach = max(1, (int) ($entityConfig['sla_threshold_minutes'] ?? $breachThreshold));
            $age = (int) ($object['age_minutes'] ?? 0);

            $level = null;
            if ($age >= $entityBreach) {
                $level = 'breach';
            } elseif ($warnThreshold > 0 && $age >= $warnThreshold) {
                $level = 'warn';
            }
            if ($level === null) {
                continue;
            }

            $alert = dashglpi_sla_monitor_build_alert($object, $level, $entityBreach, $age);
            $alert['title'] = $type['label'] . ' #' . (int) $object['id'] . ' — ' . (string) ($object['name'] ?? '');
            $alert['url'] = dashglpi_itil_glpi_url($type, (int) $object['id']);
            $alert['dashboard_url'] = '';

            if ($dryRun) {
                $result['alerts'][] = $alert + ['would_dispatch' => true, 'itemtype' => $typeKey];
                continue;
            }

            if (dashglpi_event_alert_already_sent($eventType, (int) $object['id'], $level)) {
                continue;
            }

            $dispatch = $dispatcher->dispatch($alert, $entityConfig);
            $okChannels = [];
            foreach ($dispatch as $channelKey => $res) {
                if (!empty($res['ok'])) {
                    $okChannels[] = $channelKey;
                } elseif (empty($res['skipped']) && !empty($res['error'])) {
                    $result['errors'][] = ucfirst($typeKey) . ' #' . $object['id'] . ' [' . $channelKey . ']: ' . $res['error'];
                }
            }

            if ($okChannels) {
                dashglpi_event_alert_record($eventType, (int) $object['id'], $level, $okChannels);
                $result['dispatched']++;
            }

            $result['alerts'][] = $alert + ['channels' => $okChannels, 'results' => $dispatch, 'itemtype' => $typeKey];
        }
    }

    return $result;
}

/**
 * Problemas/Manutenções em status "Novo" sem técnico/grupo e sem acompanhamento,
 * mais antigos que o limite — mesma semântica de dashglpi_sla_monitor_candidates(),
 * parametrizada pelo registry ITIL (PLAN-20260709-019).
 */
function dashglpi_itil_unattended_candidates(array $type, int $thresholdMinutes, ?int $maxAgeMinutes = null, ?string $sinceDate = null): array
{
    $thresholdMinutes = max(1, $thresholdMinutes);
    $maxAgeMinutes = ($maxAgeMinutes !== null && $maxAgeMinutes > 0) ? $maxAgeMinutes : null;
    $sinceDate = ($sinceDate !== null && $sinceDate !== '') ? $sinceDate : null;
    $fk = $type['fk'];

    return dashglpi_fetch_all(
        "SELECT t.id,
                t.name,
                t.entities_id,
                t.priority,
                t.date AS created_at,
                TIMESTAMPDIFF(MINUTE, t.date, NOW()) AS age_minutes,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name,
                COALESCE(c.completename, c.name, '') AS category_name
         FROM {$type['table']} t
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
         WHERE t.is_deleted = 0
           AND t.status = ?
           AND t.date IS NOT NULL
           AND TIMESTAMPDIFF(MINUTE, t.date, NOW()) >= ?
           AND (? IS NULL OR TIMESTAMPDIFF(MINUTE, t.date, NOW()) <= ?)
           AND (? IS NULL OR t.date >= ?)
           AND NOT EXISTS (
               SELECT 1 FROM {$type['user_link_table']} tu
               WHERE tu.$fk = t.id AND tu.type = 2
           )
           AND NOT EXISTS (
               SELECT 1 FROM {$type['group_link_table']} gt
               WHERE gt.$fk = t.id AND gt.type = 2
           )
           AND NOT EXISTS (
               SELECT 1 FROM glpi_itilfollowups f
               WHERE f.itemtype = ? AND f.items_id = t.id
           )
         ORDER BY t.date ASC
         LIMIT 200",
        [
            DASHGLPI_TICKET_STATUS_NEW, $thresholdMinutes,
            $maxAgeMinutes, $maxAgeMinutes,
            $sinceDate, $sinceDate,
            (string) $type['glpi_itemtype'],
        ]
    );
}

/**
 * Chamados "Novo" mais antigos que o limite, sem técnico atribuído e sem
 * acompanhamento (nenhuma ciência/andamento).
 *
 * @param int|null $maxAgeMinutes teto de idade relativo (PLAN-20260704-014); null/0 = sem teto.
 * @param string|null $sinceDate piso absoluto ("rodar desde de"); null/'' = sem piso.
 */
function dashglpi_sla_monitor_candidates(int $thresholdMinutes, ?int $maxAgeMinutes = null, ?string $sinceDate = null): array
{
    $thresholdMinutes = max(1, $thresholdMinutes);
    $maxAgeMinutes = ($maxAgeMinutes !== null && $maxAgeMinutes > 0) ? $maxAgeMinutes : null;
    $sinceDate = ($sinceDate !== null && $sinceDate !== '') ? $sinceDate : null;

    return dashglpi_fetch_all(
        "SELECT t.id,
                t.name,
                t.entities_id,
                t.priority,
                t.date AS created_at,
                TIMESTAMPDIFF(MINUTE, t.date, NOW()) AS age_minutes,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name,
                COALESCE(c.completename, c.name, '') AS category_name
         FROM glpi_tickets t
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
         WHERE t.is_deleted = 0
           AND t.status = ?
           AND t.date IS NOT NULL
           AND TIMESTAMPDIFF(MINUTE, t.date, NOW()) >= ?
           AND (? IS NULL OR TIMESTAMPDIFF(MINUTE, t.date, NOW()) <= ?)
           AND (? IS NULL OR t.date >= ?)
           AND NOT EXISTS (
               SELECT 1 FROM glpi_tickets_users tu
               WHERE tu.tickets_id = t.id AND tu.type = 2
           )
           AND NOT EXISTS (
               SELECT 1 FROM glpi_groups_tickets gt
               WHERE gt.tickets_id = t.id AND gt.type = 2
           )
           AND NOT EXISTS (
               SELECT 1 FROM glpi_itilfollowups f
               WHERE f.itemtype = 'Ticket' AND f.items_id = t.id
           )
         ORDER BY t.date ASC
         LIMIT 200",
        [
            DASHGLPI_TICKET_STATUS_NEW, $thresholdMinutes,
            $maxAgeMinutes, $maxAgeMinutes,
            $sinceDate, $sinceDate,
        ]
    );
}

function dashglpi_sla_monitor_build_alert(array $ticket, string $level, int $threshold, int $age): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);

    return [
        'ticket_id' => $ticketId,
        'title' => (string) ($ticket['name'] ?? ('Chamado #' . $ticketId)),
        'category' => (string) ($ticket['category_name'] ?? ''),
        'entity_name' => (string) ($ticket['entity_name'] ?? ''),
        'entities_id' => (int) ($ticket['entities_id'] ?? 0),
        'priority' => (int) ($ticket['priority'] ?? 0),
        'minutes_overdue' => $age,
        'threshold' => $threshold,
        'level' => $level,
        'created_at' => (string) ($ticket['created_at'] ?? ''),
        'created_at_formatted' => dashglpi_format_event_datetime($ticket['created_at'] ?? null),
        'url' => dashglpi_sla_monitor_ticket_url($ticketId),
        'dashboard_url' => dashglpi_dashboard_ticket_url($ticketId),
    ];
}

/** Formata uma data/hora bruta do banco para exibição no alerta (d/m/Y H:i), com fallback seguro. */
function dashglpi_format_event_datetime($rawDate): string
{
    $rawDate = trim((string) ($rawDate ?? ''));
    if ($rawDate === '') {
        return '';
    }

    $timestamp = strtotime($rawDate);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '';
}

function dashglpi_sla_monitor_ticket_url(int $ticketId): string
{
    $template = (string) dashglpi_env('DASHGLPI_TICKET_URL_TEMPLATE', '');
    if ($template !== '' && str_contains($template, '{id}')) {
        return str_replace('{id}', (string) $ticketId, $template);
    }

    $base = rtrim((string) dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
    if ($base === '') {
        return '';
    }

    return $base . '/front/ticket.form.php?id=' . $ticketId;
}

/**
 * Link para abrir o chamado direto no DashGLPI (não no GLPI) — deep-link lido por
 * public/js/script.js no carregamento da página (?ticket_id={id}), que abre o modal
 * de detalhe automaticamente. Distinto de dashglpi_sla_monitor_ticket_url(), que
 * aponta para o GLPI.
 */
function dashglpi_dashboard_ticket_url(int $ticketId): string
{
    if ($ticketId <= 0) {
        return '';
    }

    $base = rtrim((string) dashglpi_env('DASHGLPI_PUBLIC_URL', ''), '/');
    if ($base === '') {
        return '';
    }

    return $base . '/front/dashboard.php?ticket_id=' . $ticketId . '#tickets';
}

function dashglpi_sla_alert_already_sent(int $ticketId, string $level): bool
{
    $row = dashglpi_fetch_one(
        "SELECT id FROM " . DASHGLPI_SLA_ALERTS_TABLE . "
         WHERE tickets_id = ? AND level = ? LIMIT 1",
        [$ticketId, $level]
    );

    return $row !== null;
}

function dashglpi_sla_alert_record(int $ticketId, string $level, int $minutesOverdue, array $channels): void
{
    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_SLA_ALERTS_TABLE . " (tickets_id, level, minutes_overdue, channels)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            minutes_overdue = VALUES(minutes_overdue),
            channels = VALUES(channels),
            sent_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        $ticketId,
        $level,
        max(0, $minutesOverdue),
        substr(implode(',', $channels), 0, 191),
    ]);
}

/**
 * Contadores para os widgets reais do dashboard (Fase 4).
 * @return array{critical:int,warning:int,on_track:int,recent:array}
 */
function dashglpi_sla_monitor_snapshot(): array
{
    dashglpi_sla_alerts_ensure_table();
    $config = dashglpi_alerting_config();
    $breach = max(1, (int) ($config['sla_threshold_minutes'] ?? 15));
    $warn = (int) ($config['warn_threshold_minutes'] ?? 0);
    if ($warn <= 0 || $warn >= $breach) {
        $warn = (int) floor($breach / 2);
    }

    $rows = dashglpi_fetch_all(
        "SELECT t.id, t.name, t.entities_id,
                TIMESTAMPDIFF(MINUTE, t.date, NOW()) AS age_minutes,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name
         FROM glpi_tickets t
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         WHERE t.is_deleted = 0
           AND t.status = ?
           AND t.date IS NOT NULL
           AND NOT EXISTS (
               SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.type = 2
           )
           AND NOT EXISTS (
               SELECT 1 FROM glpi_itilfollowups f WHERE f.itemtype = 'Ticket' AND f.items_id = t.id
           )
         ORDER BY t.date ASC
         LIMIT 300",
        [DASHGLPI_TICKET_STATUS_NEW]
    );

    $snapshot = ['critical' => 0, 'warning' => 0, 'on_track' => 0, 'threshold' => $breach, 'warn_threshold' => $warn, 'recent' => []];
    foreach ($rows as $row) {
        $age = (int) $row['age_minutes'];
        if ($age >= $breach) {
            $bucket = 'critical';
            $snapshot['critical']++;
        } elseif ($age >= $warn) {
            $bucket = 'warning';
            $snapshot['warning']++;
        } else {
            $bucket = 'on_track';
            $snapshot['on_track']++;
        }

        if (count($snapshot['recent']) < 20 && $bucket !== 'on_track') {
            $snapshot['recent'][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'entity_name' => (string) $row['entity_name'],
                'age_minutes' => $age,
                'remaining_minutes' => $breach - $age,
                'bucket' => $bucket,
            ];
        }
    }

    return $snapshot;
}

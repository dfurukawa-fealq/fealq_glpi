<?php

/**
 * Standalone DashGLPI data queries.
 * Reads GLPI native tables directly through PDO/MySQL.
 */
class PluginDashglpiDashboard
{
    private const TICKETS_LIST_MAX = 5000;

    public static function getDashboardData(array $filters = []): array
    {
        $period = self::dashboardPeriod($filters);
        $start = $period['start'];
        $scope = self::dashboardTicketScope($start, self::filterMyTasks($filters));
        $createdTicketsChart = self::createdTicketsChart($filters, $scope);
        $notificationQueue = self::notificationQueueData($filters);

        $total       = self::countTickets('AND t.date >= ?', [$start], $scope);
        $abertos     = self::countTickets('AND t.status = ? AND t.date >= ?', [1, $start], $scope);
        $andamento   = self::countTickets('AND t.status IN (2, 3) AND t.date >= ?', [$start], $scope);
        $pendentes   = self::countTickets('AND t.status = ? AND t.date >= ?', [4, $start], $scope);
        $finalizados = self::countTickets('AND t.status IN (5, 6) AND t.date >= ?', [$start], $scope);
        $taxa        = ($total > 0) ? round(($finalizados / $total) * 100) : 0;
        $atribuidos  = self::countAssignedTickets($start, $scope);
        $reabertos   = self::countReopenedTickets($start, $scope);
        $slaRows     = self::getDashboardSlaRows($start, $scope);
        $slaVencido  = count(array_filter($slaRows, static fn(array $row): bool => !empty($row['active_sla_overdue'])));

        $avg = dashglpi_fetch_one(
            "SELECT AVG(
                    CASE
                        WHEN t.status IN (5, 6) AND COALESCE(t.solvedate, t.closedate) IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, t.date, COALESCE(t.solvedate, t.closedate))
                        ELSE TIMESTAMPDIFF(SECOND, t.date, NOW())
                    END
                ) AS avg_sec
             FROM glpi_tickets t
             WHERE t.is_deleted = 0
               AND t.date IS NOT NULL
               AND t.date >= ?
               " . $scope['sql'],
            array_merge([$start], $scope['params'])
        );
        $tempoMedioHoras = !empty($avg['avg_sec']) ? round(((float) $avg['avg_sec']) / 3600) : 0;

        $trendData = dashglpi_fetch_all(
            "SELECT DATE_FORMAT(t.date, '%d/%m') AS dia, COUNT(t.id) AS total
             FROM glpi_tickets t
             WHERE t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             GROUP BY DATE(t.date), DATE_FORMAT(t.date, '%d/%m')
             ORDER BY DATE(t.date) ASC",
            array_merge([$start], $scope['params'])
        );

        $catData = dashglpi_fetch_all(
            "SELECT COALESCE(c.completename, 'Sem Categoria') AS nome, COUNT(t.id) AS total
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             WHERE t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             GROUP BY c.id, c.completename
             ORDER BY total DESC
             LIMIT 5",
            array_merge([$start], $scope['params'])
        );

        $openedData = dashglpi_fetch_all(
            "SELECT DATE_FORMAT(t.date, '%Y-%m') AS mes_ano, COUNT(t.id) AS total
             FROM glpi_tickets t
             WHERE t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             GROUP BY DATE_FORMAT(t.date, '%Y-%m')
             ORDER BY mes_ano ASC",
            array_merge([$start], $scope['params'])
        );

        $solvedData = dashglpi_fetch_all(
            "SELECT DATE_FORMAT(t.solvedate, '%Y-%m') AS mes_ano, COUNT(t.id) AS total
             FROM glpi_tickets t
             WHERE t.status IN (5, 6)
               AND t.is_deleted = 0
               AND t.solvedate IS NOT NULL
               AND t.solvedate >= ?
               " . $scope['sql'] . "
             GROUP BY DATE_FORMAT(t.solvedate, '%Y-%m')
             ORDER BY mes_ano ASC",
            array_merge([$start], $scope['params'])
        );

        return [
            'period' => [
                'days' => $period['days'],
                'label' => $period['label'],
                'start' => $period['start'],
                'available' => self::dashboardPeriods(),
            ],
            // KPIs segmentados de Problema/Manutenção (PLAN-20260709-019, Decisão 5:
            // contadores próprios; os números de chamados acima ficam intactos).
            'itil_objects' => self::itilObjectsSummary($start),
            'cards_top' => [
                'total'       => (int) $total,
                'andamento'   => (int) $andamento,
                'taxa'        => $taxa,
                'sla'         => (int) $slaVencido,
                'tempo_medio' => $tempoMedioHoras,
                'reabertos'   => (int) $reabertos,
            ],
            'cards_bottom' => [
                'abertos'     => (int) $abertos,
                'atribuidos'  => (int) $atribuidos,
                'pendentes'   => (int) $pendentes,
                'finalizados' => (int) $finalizados,
            ],
            'charts' => [
                'created_tickets' => $createdTicketsChart,
                'trend_line'     => self::intRows($trendData, ['total']),
                'cat_bar'        => self::intRows($catData, ['total']),
                'monthly_opened' => self::intRows($openedData, ['total']),
                'monthly_solved' => self::intRows($solvedData, ['total']),
            ],
            'recent_tickets' => [],
            'notifications' => self::dashboardNotifications($slaRows, $period, $scope),
            'notification_queue' => $notificationQueue,
        ];
    }

    /**
     * Contadores segmentados por objeto ITIL (Problema/Manutenção) — agregação por
     * tabela via registry (PLAN-20260709-019 §5.2), gated pelo direito GLPI do perfil.
     * "abertos"/"vencidos" são snapshot do backlog atual; "total"/"finalizados"
     * respeitam o período selecionado.
     */
    public static function itilObjectsSummary(string $start): array
    {
        $summary = [];

        foreach (dashglpi_current_user_visible_itil_types() as $typeKey) {
            if ($typeKey === 'ticket') {
                continue;
            }

            $type = dashglpi_itil_type($typeKey);
            if (!$type) {
                continue;
            }

            $scope = self::entityScope('t.entities_id');
            $row = dashglpi_fetch_one(
                "SELECT
                        SUM(CASE WHEN t.date >= ? THEN 1 ELSE 0 END) AS total_period,
                        SUM(CASE WHEN t.status NOT IN (5, 6) THEN 1 ELSE 0 END) AS abertos,
                        SUM(CASE WHEN t.status NOT IN (5, 6)
                                  AND t.time_to_resolve IS NOT NULL
                                  AND t.time_to_resolve < NOW() THEN 1 ELSE 0 END) AS vencidos,
                        SUM(CASE WHEN t.status IN (5, 6)
                                  AND COALESCE(t.solvedate, t.closedate, t.date) >= ? THEN 1 ELSE 0 END) AS finalizados
                 FROM {$type['table']} t
                 WHERE t.is_deleted = 0" . $scope['sql'],
                array_merge([$start, $start], $scope['params'])
            ) ?? [];

            $monthly = dashglpi_fetch_all(
                "SELECT DATE_FORMAT(t.date, '%Y-%m') AS mes_ano, COUNT(t.id) AS total
                 FROM {$type['table']} t
                 WHERE t.is_deleted = 0
                   AND t.date >= ?
                   " . $scope['sql'] . "
                 GROUP BY DATE_FORMAT(t.date, '%Y-%m')
                 ORDER BY mes_ano ASC",
                array_merge([$start], $scope['params'])
            );

            $summary[$typeKey] = [
                'key' => $typeKey,
                'label' => (string) $type['label'],
                'label_plural' => (string) $type['label_plural'],
                'total_period' => (int) ($row['total_period'] ?? 0),
                'abertos' => (int) ($row['abertos'] ?? 0),
                'vencidos' => (int) ($row['vencidos'] ?? 0),
                'finalizados' => (int) ($row['finalizados'] ?? 0),
                'monthly_opened' => self::intRows($monthly, ['total']),
            ];
        }

        return $summary;
    }

    public static function getRanking(): array
    {
        $scope = self::entityScope('t.entities_id');
        $rows = dashglpi_fetch_all(
            "SELECT TRIM(CONCAT(COALESCE(u.firstname, u.name), ' ', COALESCE(u.realname, ''))) AS name,
                    UPPER(CONCAT(LEFT(COALESCE(u.firstname, u.name), 1), LEFT(COALESCE(u.realname, ''), 1))) AS avatar,
                    COUNT(t.id) AS tickets,
                    COUNT(t.id) * 10 AS points
             FROM glpi_tickets t
             INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id
             INNER JOIN glpi_users u ON u.id = tu.users_id
             WHERE tu.type = 2
               AND t.status IN (5, 6)
               AND t.is_deleted = 0
               AND t.solvedate IS NOT NULL
               AND MONTH(t.solvedate) = MONTH(CURRENT_DATE())
               AND YEAR(t.solvedate) = YEAR(CURRENT_DATE())
               " . $scope['sql'] . "
             GROUP BY u.id, u.firstname, u.name, u.realname
             ORDER BY points DESC
             LIMIT 20",
            $scope['params']
        );

        $colors = ['#3b82f6', '#a855f7', '#22c55e', '#f59e0b', '#06b6d4'];
        $ranking = [];
        foreach ($rows as $index => $row) {
            $ranking[] = [
                'name'    => $row['name'] ?: 'Tecnico',
                'avatar'  => $row['avatar'] ?: 'T',
                'tickets' => (int) $row['tickets'],
                'points'  => (int) $row['points'],
                'color'   => $colors[$index % count($colors)],
            ];
        }

        return $ranking;
    }

    public static function getTicketsList(array $filters = []): array
    {
        // Filtro por objeto ITIL (PLAN-20260709-019, Fase C). Default 'ticket'
        // preserva o contrato atual do endpoint.
        $typeKey = dashglpi_itil_normalize_type($filters['itemtype'] ?? 'ticket');
        if ($typeKey !== 'ticket') {
            return self::getItilObjectsList($typeKey, $filters);
        }

        $scope = self::ticketsListScope('1970-01-01 00:00:00', self::filterMyTasks($filters));
        $rows = self::ticketRows('1970-01-01 00:00:00', self::TICKETS_LIST_MAX, $scope);

        $rows = self::intRows($rows, [
            'id', 'status', 'priority', 'entities_id', 'global_validation', 'notification_failed',
            'technician_count', 'group_count', 'takeintoaccount_delay_stat', 'solve_delay_stat',
            'satisfaction_pending', 'attachments_count', 'followups_count',
        ]);
        $now = time();
        foreach ($rows as &$row) {
            $row['stage'] = self::ticketStage((int) $row['status']);
            $row['stage_step'] = self::ticketStageStep((int) $row['status']);
            $row['status_label'] = self::statusLabel((int) $row['status']);
            $row['itemtype'] = 'ticket';
            $row['row_key'] = 'ticket:' . (int) $row['id'];
            $row['kanban_status'] = (int) $row['status'];
            $row['readonly'] = 0;
            // View-model mobile de tickets (PLAN-20260831-001): dados resumidos, sem HTML.
            $row['description_excerpt'] = self::ticketDescriptionExcerpt((string) ($row['content'] ?? ''));
            $row['priority_label'] = self::priorityLabel((int) ($row['priority'] ?? 0));
            $row['can_take'] = (int) ($row['technician_count'] ?? 0) === 0 ? 1 : 0;
            $row = array_merge($row, self::activeSlaSnapshot($row, $now));
        }
        unset($row);

        return $rows;
    }

    private static function ticketDescriptionExcerpt(string $content): string
    {
        $text = trim(preg_replace('/\\s+/', ' ', strip_tags($content)) ?? '');
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, 180, '...', 'UTF-8');
        }
        return strlen($text) > 180 ? substr($text, 0, 177) . '...' : $text;
    }

    /**
     * Lista de Problemas/Manutenções para a mesma tela de atendimento (Fase C).
     * Somente leitura (Decisão 3): linhas marcadas readonly, sem ações de escrita.
     * Prazo (Decisão 1): usa time_to_resolve nativo quando preenchido, sem escalonamento.
     */
    private static function getItilObjectsList(string $typeKey, array $filters): array
    {
        if (!dashglpi_current_user_can_view_itil_type($typeKey)) {
            return [];
        }

        $type = dashglpi_itil_type($typeKey);
        if (!$type) {
            return [];
        }

        $period = self::dashboardPeriod($filters);
        $scope = self::filterMyTasks($filters)
            ? self::currentUserItilScope($type, 't')
            : self::entityScope('t.entities_id');
        $fk = $type['fk'];

        $rows = dashglpi_fetch_all(
            "SELECT t.id, t.name, t.status, t.date, t.date_mod, t.priority, t.time_to_resolve,
                    t.solvedate, t.closedate, t.solve_delay_stat, t.entities_id,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(e.completename, e.name, 'Entidade raiz') AS entity_name,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(tech.technician_count, 0) AS technician_count,
                    COALESCE(grp.group_count, 0) AS group_count,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name
             FROM {$type['table']} t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.$fk AS object_id,
                       COUNT(*) AS technician_count,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM {$type['user_link_table']} tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.$fk
             ) tech ON tech.object_id = t.id
             LEFT JOIN (
                SELECT gt.$fk AS object_id, COUNT(*) AS group_count
                FROM {$type['group_link_table']} gt
                WHERE gt.type = 2
                GROUP BY gt.$fk
             ) grp ON grp.object_id = t.id
             LEFT JOIN (
                SELECT tu.$fk AS object_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM {$type['user_link_table']} tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.$fk
             ) req ON req.object_id = t.id
             WHERE t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             ORDER BY t.date DESC
             LIMIT 100",
            array_merge([$period['start']], $scope['params'])
        );

        $rows = self::intRows($rows, [
            'id', 'status', 'priority', 'entities_id', 'technician_count', 'group_count', 'solve_delay_stat',
        ]);
        $now = time();
        foreach ($rows as &$row) {
            $status = (int) $row['status'];
            $kanbanStatus = dashglpi_itil_kanban_status($type, $status);
            $row['status_label'] = dashglpi_itil_status_label($type, $status);
            $row['stage'] = self::ticketStage($kanbanStatus);
            $row['stage_step'] = self::ticketStageStep($kanbanStatus);
            $row['itemtype'] = $typeKey;
            $row['row_key'] = $typeKey . ':' . (int) $row['id'];
            $row['kanban_status'] = $kanbanStatus;
            $row['readonly'] = 1;
            $row['notification_failed'] = 0;
            $row['satisfaction_pending'] = 0;
            $row['global_validation'] = 0;

            // Prazo via time_to_resolve nativo (sem SLA/escalonamento — Decisão 1).
            $ttr = self::slaTiming('TTR', $row, 'time_to_resolve', 'solvedate', 'solve_delay_stat', $now, 'closedate');
            if ($ttr['total_seconds'] !== null && !in_array($status, [5, 6], true)) {
                $row['active_sla_kind'] = 'TTR';
                $row['active_sla_percent'] = (float) ($ttr['percent'] ?? 0);
                $row['seconds_left'] = $ttr['seconds_left'];
                $row['active_sla_overdue'] = !empty($ttr['overdue']) ? 1 : 0;
                $row['sla_status'] = !empty($ttr['overdue'])
                    ? 'critical'
                    : ((float) ($ttr['percent'] ?? 0) >= 80 ? 'warning' : 'ok');
            } else {
                $row['active_sla_kind'] = null;
                $row['active_sla_percent'] = 0;
                $row['seconds_left'] = null;
                $row['active_sla_overdue'] = 0;
                $row['sla_status'] = 'ok';
            }
        }
        unset($row);

        return $rows;
    }

    public static function getTicketDetail(int $ticketId): ?array
    {
        $rows = dashglpi_fetch_all(
            "SELECT t.id, t.name, t.content, t.status, t.global_validation, t.date, t.date_mod, t.priority,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name,
                    CASE WHEN ts.id IS NOT NULL AND ts.date_answered IS NULL THEN 1 ELSE 0 END AS satisfaction_pending
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.tickets_id
             ) tech ON tech.tickets_id = t.id
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.tickets_id
             ) req ON req.tickets_id = t.id
             LEFT JOIN glpi_ticketsatisfactions ts ON ts.tickets_id = t.id
             WHERE t.id = ?
               AND t.is_deleted = 0
             LIMIT 1",
            [$ticketId]
        );

        if (!$rows) {
            return null;
        }

        $row = self::intRows($rows, ['id', 'status', 'priority', 'global_validation', 'satisfaction_pending'])[0];
        $row['status_label'] = self::statusLabel((int) $row['status']);
        $row['content'] = trim((string) ($row['content'] ?? ''));

        return $row;
    }

    /**
     * Detalhe de Problema/Manutenção (PLAN-20260709-019, Fase D — somente leitura).
     * Mesmo shape do getTicketDetail + followups lidos direto de glpi_itilfollowups
     * (leitura SQL pura; o bridge continua exclusivo dos chamados).
     */
    public static function getItilObjectDetail(string $typeKey, int $objectId): ?array
    {
        if ($typeKey === 'ticket') {
            return self::getTicketDetail($objectId);
        }

        $type = dashglpi_itil_type($typeKey);
        if (!$type) {
            return null;
        }

        $fk = $type['fk'];
        $row = dashglpi_fetch_one(
            "SELECT t.id, t.name, t.content, t.status, t.date, t.date_mod, t.priority,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name
             FROM {$type['table']} t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.$fk AS object_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM {$type['user_link_table']} tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.$fk
             ) tech ON tech.object_id = t.id
             LEFT JOIN (
                SELECT tu.$fk AS object_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM {$type['user_link_table']} tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.$fk
             ) req ON req.object_id = t.id
             WHERE t.id = ?
               AND t.is_deleted = 0
             LIMIT 1",
            [$objectId]
        );

        if (!$row) {
            return null;
        }

        $row = self::intRows([$row], ['id', 'status', 'priority'])[0];
        $row['status_label'] = dashglpi_itil_status_label($type, (int) $row['status']);
        $row['content'] = trim(strip_tags((string) ($row['content'] ?? '')));
        $row['itemtype'] = $typeKey;
        $row['type_label'] = (string) $type['label'];
        $row['glpi_url'] = dashglpi_itil_glpi_url($type, $objectId);
        $row['satisfaction_pending'] = 0;
        $row['global_validation'] = 0;
        $row['followups'] = self::itilObjectFollowups($type, $objectId);

        return $row;
    }

    private static function itilObjectFollowups(array $type, int $objectId): array
    {
        $rows = dashglpi_fetch_all(
            "SELECT f.content,
                    COALESCE(f.date, f.date_creation, f.date_mod) AS date,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))), ''), 'Sistema') AS author
             FROM glpi_itilfollowups f
             LEFT JOIN glpi_users u ON u.id = f.users_id
             WHERE f.itemtype = ?
               AND f.items_id = ?
               AND f.is_private = 0
             ORDER BY COALESCE(f.date, f.date_creation, f.date_mod) ASC
             LIMIT 100",
            [(string) $type['glpi_itemtype'], $objectId]
        );

        $followups = [];
        foreach ($rows as $row) {
            $followups[] = [
                'author' => (string) ($row['author'] ?? 'Sistema'),
                'date' => (string) ($row['date'] ?? ''),
                'content' => trim(strip_tags((string) ($row['content'] ?? ''))),
            ];
        }

        return $followups;
    }

    private static function activeSlaSnapshot(array $row, int $now): array
    {
        $hasTechnician = (int) ($row['technician_count'] ?? 0) > 0;
        $hasGroup = (int) ($row['group_count'] ?? 0) > 0;
        $isUnassigned = !$hasTechnician && !$hasGroup;
        $activeKind = $isUnassigned ? 'TTO' : 'TTR';

        $ttoRow = $row;
        if ($isUnassigned) {
            $ttoRow['takeintoaccountdate'] = null;
            $ttoRow['takeintoaccount_delay_stat'] = 0;
        }
        $tto = self::slaTiming('TTO', $ttoRow, 'time_to_own', 'takeintoaccountdate', 'takeintoaccount_delay_stat', $now);
        $ttr = self::slaTiming('TTR', $row, 'time_to_resolve', 'solvedate', 'solve_delay_stat', $now, 'closedate');
        $active = $activeKind === 'TTO' ? $tto : $ttr;

        if ($active['total_seconds'] === null) {
            return [
                'active_sla_kind' => null,
                'active_sla_percent' => 0,
                'seconds_left' => null,
                'active_sla_overdue' => 0,
                'sla_status' => 'ok',
            ];
        }

        $activePercent = (float) ($active['percent'] ?? 0);
        $isOverdue = !empty($active['overdue']);

        $riskScore = 0;
        if ($isUnassigned) {
            $riskScore += 50;
        }
        if ($activePercent >= 80 && !$isOverdue) {
            $riskScore += 30;
        }
        if ($isOverdue) {
            $riskScore += 50;
        }

        $slaStatus = 'ok';
        if ($riskScore >= 100) {
            $slaStatus = 'critical';
        } elseif ($riskScore >= 50) {
            $slaStatus = 'warning';
        }

        return [
            'active_sla_kind' => $activeKind,
            'active_sla_percent' => $activePercent,
            'seconds_left' => $active['seconds_left'],
            'active_sla_overdue' => $isOverdue ? 1 : 0,
            'sla_status' => $slaStatus,
        ];
    }

    public static function getTechniciansList(): array
    {
        $rows = dashglpi_fetch_all(
            "SELECT DISTINCT u.id,
                    TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) AS name
             FROM glpi_users u
             INNER JOIN glpi_profiles_users pu ON pu.users_id = u.id
             INNER JOIN glpi_profiles p ON p.id = pu.profiles_id
             WHERE u.is_deleted = 0
               AND u.is_active = 1
               AND p.interface = 'central'
             ORDER BY name ASC
             LIMIT 200",
            []
        );
        return self::intRows($rows, ['id']);
    }

    public static function getSlaList(array $filters = []): array
    {
        $filter = self::slaListFilters($filters);
        $queryFilter = self::slaTicketQueryFilter($filter);
        $scope = self::slaListScope($queryFilter['where'], $queryFilter['params']);
        $where = $queryFilter['where'];
        $params = $queryFilter['params'];

        if ($scope['where'] !== '') {
            $where[] = $scope['where'];
            $params = array_merge($params, $scope['params']);
        }

        $rows = dashglpi_fetch_all(
            "SELECT t.id, t.name, t.status, t.date, t.priority,
                    t.time_to_own, t.time_to_resolve, t.takeintoaccountdate, t.solvedate, t.closedate,
                    t.slas_id_tto, t.slas_id_ttr, t.takeintoaccount_delay_stat, t.solve_delay_stat,
                    CASE
                        WHEN t.status IN (5, 6) AND COALESCE(t.solvedate, t.closedate) IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, t.date, COALESCE(t.solvedate, t.closedate))
                        ELSE TIMESTAMPDIFF(SECOND, t.date, NOW())
                    END AS open_seconds,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(e.completename, 'Entidade raiz') AS entity_name,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(tech.technician_count, 0) AS technician_count,
                    COALESCE(NULLIF(grp.group_name, ''), '-') AS group_name,
                    COALESCE(grp.group_count, 0) AS group_count,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.tickets_id,
                       COUNT(*) AS technician_count,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.tickets_id
             ) tech ON tech.tickets_id = t.id
             LEFT JOIN (
                SELECT gt.tickets_id,
                       COUNT(*) AS group_count,
                       GROUP_CONCAT(COALESCE(NULLIF(g.completename, ''), g.name) ORDER BY g.name SEPARATOR ', ') AS group_name
                FROM glpi_groups_tickets gt
                INNER JOIN glpi_groups g ON g.id = gt.groups_id
                WHERE gt.type = 2
                GROUP BY gt.tickets_id
             ) grp ON grp.tickets_id = t.id
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.tickets_id
             ) req ON req.tickets_id = t.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(t.time_to_own, t.time_to_resolve) IS NULL ASC,
                      COALESCE(t.time_to_own, t.time_to_resolve) ASC,
                      t.date ASC
             LIMIT 100",
            $params
        );

        $slaData = self::enrichSlaRows($rows);
        $slaData['summary']['avg_open_seconds'] = self::slaAverageOpenSeconds($filter, $scope);

        return [
            'items' => $slaData['items'],
            'summary' => $slaData['summary'],
            'filters' => $filter,
        ];
    }

    public static function getAssetsList(): array
    {
        $scope = self::entityScope('c.entities_id');
        $assets = dashglpi_fetch_all(
            "SELECT c.id, c.name, c.serial,
                    loc.completename AS location,
                    cm.name AS model,
                    man.name AS manufacturer,
                    st.completename AS status
             FROM glpi_computers c
             LEFT JOIN glpi_locations loc ON loc.id = c.locations_id
             LEFT JOIN glpi_computermodels cm ON cm.id = c.computermodels_id
             LEFT JOIN glpi_manufacturers man ON man.id = c.manufacturers_id
             LEFT JOIN glpi_states st ON st.id = c.states_id
             WHERE c.is_deleted = 0
               AND c.is_template = 0
               " . $scope['sql'] . "
             ORDER BY c.name ASC
             LIMIT 100",
            $scope['params']
        );

        if (!$assets) {
            return [];
        }

        $ids = array_map(static fn($row) => (int) $row['id'], $assets);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $osMap = [];
        foreach (dashglpi_fetch_all(
            "SELECT ios.items_id, os.name
             FROM glpi_items_operatingsystems ios
             LEFT JOIN glpi_operatingsystems os ON os.id = ios.operatingsystems_id
             WHERE ios.items_id IN ($placeholders)
               AND ios.itemtype = 'Computer'
               AND ios.is_deleted = 0",
            $ids
        ) as $row) {
            if (!empty($row['name'])) {
                $osMap[(int) $row['items_id']][] = $row['name'];
            }
        }

        $cpuMap = [];
        foreach (dashglpi_fetch_all(
            "SELECT idp.items_id, dp.designation
             FROM glpi_items_deviceprocessors idp
             INNER JOIN glpi_deviceprocessors dp ON dp.id = idp.deviceprocessors_id
             WHERE idp.items_id IN ($placeholders)
               AND idp.itemtype = 'Computer'",
            $ids
        ) as $row) {
            $itemId = (int) $row['items_id'];
            if (!isset($cpuMap[$itemId]) && !empty($row['designation'])) {
                $cpuMap[$itemId] = $row['designation'];
            }
        }

        $ramMap = [];
        foreach (dashglpi_fetch_all(
            "SELECT items_id, SUM(size) AS total
             FROM glpi_items_devicememories
             WHERE items_id IN ($placeholders)
               AND itemtype = 'Computer'
               AND is_deleted = 0
             GROUP BY items_id",
            $ids
        ) as $row) {
            $ramMap[(int) $row['items_id']] = (int) ($row['total'] ?? 0);
        }

        $diskMap = [];
        foreach (dashglpi_fetch_all(
            "SELECT items_id, SUM(totalsize) AS disk_total, SUM(freesize) AS disk_free
             FROM glpi_items_disks
             WHERE items_id IN ($placeholders)
               AND itemtype = 'Computer'
               AND is_deleted = 0
             GROUP BY items_id",
            $ids
        ) as $row) {
            $diskMap[(int) $row['items_id']] = [
                'total' => (int) ($row['disk_total'] ?? 0),
                'free'  => (int) ($row['disk_free'] ?? 0),
            ];
        }

        $result = [];
        foreach ($assets as $asset) {
            $id = (int) $asset['id'];
            $asset['id'] = $id;
            $asset['os_name'] = implode(', ', array_unique($osMap[$id] ?? []));
            $asset['cpu'] = $cpuMap[$id] ?? '';
            $asset['ram_total'] = $ramMap[$id] ?? 0;
            $asset['disk_total'] = $diskMap[$id]['total'] ?? 0;
            $asset['disk_free'] = $diskMap[$id]['free'] ?? 0;
            $result[] = $asset;
        }

        return $result;
    }

    public static function getGlpiHealthData(array $filters = []): array
    {
        $queue = self::notificationQueueData($filters);
        $entitysmtpOwner = self::healthEntitySmtpOwnerData();
        $crontasks = [
            'queuednotification' => self::healthCrontaskSnapshot('queuednotification', true),
            'dashglpi_entitysmtp_queuednotification' => self::healthCrontaskSnapshot('dashglpi_entitysmtp_queuednotification', false),
            'slaticket' => self::healthCrontaskSnapshot('slaticket', false),
        ];
        $crontasks = self::healthApplyEntitySmtpOwner($crontasks, $entitysmtpOwner);

        $mailSettings = function_exists('dashglpi_notification_mail_settings')
            ? dashglpi_notification_mail_settings()
            : [];
        $mail = self::healthMailData($mailSettings);

        $notificationSummary = function_exists('dashglpi_notification_summary')
            ? dashglpi_notification_summary()
            : ['active' => 0, 'templates' => 0, 'queued' => 0];
        $notificationCatalog = function_exists('dashglpi_notification_catalog_data')
            ? dashglpi_notification_catalog_data()
            : [];
        $notificationConfig = self::healthNotificationConfigData($notificationSummary, $notificationCatalog);

        $collectorSummary = function_exists('dashglpi_mailcollector_summary')
            ? dashglpi_mailcollector_summary()
            : ['active' => 0, 'errors' => 0, 'total' => 0];
        $collectorItems = function_exists('dashglpi_mailcollectors_list')
            ? dashglpi_mailcollectors_list()
            : [];
        $collectors = self::healthCollectorsData($collectorSummary, $collectorItems);

        $tickets = self::healthTicketsData();
        $recentEvents = self::healthRecentCrontaskEvents();
        $phpConfig = self::healthPhpConfigData();
        $mailgateLoop = self::healthMailgateLoopData();

        $issuesPayload = self::healthIssues(
            $queue,
            $crontasks,
            $mail,
            $notificationConfig,
            $collectors,
            $tickets,
            $recentEvents,
            $entitysmtpOwner,
            $phpConfig,
            $mailgateLoop
        );
        $counts = $issuesPayload['counts'];
        $status = $counts['critical'] > 0
            ? 'critical'
            : ($counts['warning'] > 0 ? 'warning' : 'ok');

        return [
            'overall' => [
                'status' => $status,
                'critical_count' => $counts['critical'],
                'warning_count' => $counts['warning'],
                'info_count' => $counts['info'],
                'refreshed_at' => date('Y-m-d H:i:s'),
            ],
            'queue' => $queue,
            'crontasks' => $crontasks,
            'entitysmtp_owner' => $entitysmtpOwner,
            'mail' => $mail,
            'notification_config' => $notificationConfig,
            'collectors' => $collectors,
            'tickets' => $tickets,
            'recent_events' => $recentEvents,
            'php_config' => $phpConfig,
            'mailgate_loop' => $mailgateLoop,
            'issues' => $issuesPayload['issues'],
            'links' => self::healthLinks(),
        ];
    }

    private static function healthPhpConfigData(): array
    {
        try {
            $result = dashglpi_admin_bridge_request('health_diagnostics_config.php', []);
        } catch (Throwable $e) {
            return ['available' => false];
        }

        $config = is_array($result['php_config'] ?? null) ? $result['php_config'] : [];
        if ($config === []) {
            return ['available' => false];
        }

        $uploadBytes = (int) ($config['upload_max_filesize_bytes'] ?? 0);
        $memoryBytes = (int) ($config['memory_limit_bytes'] ?? 0);

        return [
            'available' => true,
            'memory_limit' => (string) ($config['memory_limit'] ?? ''),
            'memory_limit_bytes' => $memoryBytes,
            'upload_max_filesize' => (string) ($config['upload_max_filesize'] ?? ''),
            'upload_max_filesize_bytes' => $uploadBytes,
            'post_max_size' => (string) ($config['post_max_size'] ?? ''),
            'max_execution_time' => (int) ($config['max_execution_time'] ?? 0),
            'memory_limit_low' => $memoryBytes > 0 && $memoryBytes < (256 * 1024 * 1024),
            'upload_max_filesize_low' => $uploadBytes > 0 && $uploadBytes < (8 * 1024 * 1024),
        ];
    }

    private static function healthMailgateLoopData(): array
    {
        $rows = dashglpi_fetch_all(
            "SELECT t1.id AS ticket_id, t2.id AS duplicate_id, t1.name, t1.date
             FROM glpi_tickets t1
             JOIN glpi_tickets t2
               ON t1.name = t2.name
              AND t1.users_id_recipient = t2.users_id_recipient
              AND t1.id < t2.id
              AND ABS(TIMESTAMPDIFF(SECOND, t1.date, t2.date)) <= 60
             WHERE t1.date >= NOW() - INTERVAL 1 DAY
             ORDER BY t1.date DESC
             LIMIT 50"
        );

        return [
            'count' => count($rows),
            'items' => $rows,
        ];
    }

    private static function dashboardPeriods(): array
    {
        return [
            ['days' => 7, 'label' => '7 dias'],
            ['days' => 30, 'label' => '30 dias'],
            ['days' => 90, 'label' => '90 dias'],
            ['days' => 180, 'label' => '6 meses'],
        ];
    }

    private static function healthLinks(): array
    {
        return [
            'crontask' => self::glpiUrl('/front/crontask.php'),
            'email' => self::glpiUrl('/front/notificationmailingsetting.form.php'),
            'notifications' => self::glpiUrl('/front/notification.php'),
            'templates' => self::glpiUrl('/front/notificationtemplate.php'),
            'collectors' => self::glpiUrl('/front/mailcollector.php'),
            'settings_notifications' => '/front/settings.php?section=notifications',
            'settings_sla' => '/front/settings.php?section=sla',
        ];
    }

    private static function glpiUrl(string $path): string
    {
        $root = rtrim((string) dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
        if ($root === '') {
            return '#';
        }

        return $root . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    private static function healthCrontaskSnapshot(string $name, bool $withQueueMetrics): array
    {
        $base = $withQueueMetrics && function_exists('dashglpi_notification_delivery_status')
            ? dashglpi_notification_delivery_status()
            : self::crontaskSnapshotByName($name);

        $taskId = max(0, (int) ($base['id'] ?? 0));
        $frequency = max(0, (int) ($base['frequency'] ?? 0));
        $lastrun = trim((string) ($base['lastrun'] ?? ''));
        $lastRunAge = self::secondsSince($lastrun);
        $executedFrequency = self::healthCrontaskExecutedFrequencySeconds($taskId);
        $staleThreshold = max(180, $frequency * 3);
        $stale = !empty($base['available']) && ($lastrun === '' || $lastRunAge > $staleThreshold);
        $stuckRunning = !empty($base['available']) && (int) ($base['state'] ?? 0) === 2 && $stale;
        $recommendationOk = !empty($base['available'])
            && (int) ($base['state'] ?? 0) === 1
            && (int) ($base['mode'] ?? 0) === 2
            && $frequency === 60;
        $driftWarning = false;

        if ($frequency > 0 && $executedFrequency !== null) {
            $ratio = $executedFrequency / $frequency;
            $driftWarning = $ratio > 1.5 || $ratio < 0.5;
        }

        return [
            'available' => !empty($base['available']),
            'id' => $taskId,
            'itemtype' => (string) ($base['itemtype'] ?? ''),
            'name' => (string) ($base['name'] ?? $name),
            'state' => (int) ($base['state'] ?? 0),
            'state_label' => (string) ($base['state_label'] ?? self::crontaskStateLabel((int) ($base['state'] ?? 0))),
            'mode' => (int) ($base['mode'] ?? 0),
            'mode_label' => (string) ($base['mode_label'] ?? self::crontaskModeLabel((int) ($base['mode'] ?? 0))),
            'allowmode' => (int) ($base['allowmode'] ?? 0),
            'frequency' => $frequency,
            'lastrun' => $lastrun,
            'lastcode' => (string) ($base['lastcode'] ?? ''),
            'last_run_age_seconds' => $lastRunAge,
            'pending_count' => $withQueueMetrics ? max(0, (int) ($base['pending_count'] ?? 0)) : 0,
            'delay_seconds' => $withQueueMetrics ? max(0, (int) ($base['delay_seconds'] ?? 0)) : 0,
            'recommendation_ok' => $recommendationOk,
            'configured_frequency_seconds' => $frequency,
            'executed_frequency_seconds' => $executedFrequency,
            'stale' => $stale,
            'stuck_running' => $stuckRunning,
            'drift_warning' => $driftWarning,
            'monitored' => true,
        ];
    }

    private static function healthEntitySmtpOwnerData(): array
    {
        $native = self::crontaskSnapshotByName('queuednotification');
        $dash = self::crontaskSnapshotByName('dashglpi_entitysmtp_queuednotification');
        $nativeActive = !empty($native['available']) && (int) ($native['state'] ?? 0) === 1;
        $dashActive = !empty($dash['available']) && (int) ($dash['state'] ?? 0) === 1;
        $settings = self::entitySmtpOwnerSettings();

        return [
            'owner' => $dashActive ? 'dash' : 'glpi',
            'active' => $dashActive,
            'conflict' => $dashActive && $nativeActive,
            'snapshot_available' => is_array($settings['snapshot'] ?? null) && $settings['snapshot'] !== [],
            'activated_at' => (string) ($settings['activated_at'] ?? ''),
            'restored_at' => (string) ($settings['restored_at'] ?? ''),
        ];
    }

    private static function healthApplyEntitySmtpOwner(array $crontasks, array $owner): array
    {
        $dashOwnsQueue = ($owner['owner'] ?? 'glpi') === 'dash';
        $conflict = !empty($owner['conflict']);
        $monitoredMap = [
            'queuednotification' => !$dashOwnsQueue || $conflict,
            'dashglpi_entitysmtp_queuednotification' => $dashOwnsQueue || $conflict,
            'slaticket' => true,
        ];

        foreach ($crontasks as $name => &$task) {
            $monitored = $monitoredMap[$name] ?? true;
            $task['monitored'] = $monitored;
            if (!$monitored) {
                $task['recommendation_ok'] = true;
                $task['stale'] = false;
                $task['stuck_running'] = false;
                $task['drift_warning'] = false;
                $task['expected_state_label'] = $name === 'queuednotification'
                    ? 'Pausada enquanto Dash é dono'
                    : 'Inativa enquanto GLPI é dono';
            }
        }
        unset($task);

        return $crontasks;
    }

    private static function entitySmtpOwnerSettings(): array
    {
        if (!self::tableExists('glpi_plugin_dashglpi_settings')) {
            return [];
        }

        $row = dashglpi_fetch_one(
            "SELECT data
             FROM glpi_plugin_dashglpi_settings
             WHERE namespace = 'entitysmtp_owner'
             LIMIT 1"
        );
        $data = json_decode((string) ($row['data'] ?? '{}'), true);

        return is_array($data) ? $data : [];
    }

    private static function crontaskSnapshotByName(string $name): array
    {
        $row = dashglpi_fetch_one(
            "SELECT id, itemtype, name, frequency, state, mode, allowmode, lastrun, lastcode
             FROM glpi_crontasks
             WHERE name = ?
             LIMIT 1",
            [$name]
        );

        if (!$row) {
            return [
                'available' => false,
                'itemtype' => '',
                'name' => $name,
                'state' => 0,
                'mode' => 0,
                'allowmode' => 0,
                'frequency' => 0,
                'lastrun' => '',
                'lastcode' => '',
            ];
        }

        return [
            'available' => true,
            'id' => (int) ($row['id'] ?? 0),
            'itemtype' => (string) ($row['itemtype'] ?? ''),
            'name' => (string) ($row['name'] ?? $name),
            'state' => (int) ($row['state'] ?? 0),
            'mode' => (int) ($row['mode'] ?? 0),
            'allowmode' => (int) ($row['allowmode'] ?? 0),
            'frequency' => (int) ($row['frequency'] ?? 0),
            'lastrun' => (string) ($row['lastrun'] ?? ''),
            'lastcode' => (string) ($row['lastcode'] ?? ''),
        ];
    }

    private static function healthCrontaskExecutedFrequencySeconds(int $taskId): ?int
    {
        if ($taskId <= 0 || !self::tableExists('glpi_crontasklogs')) {
            return null;
        }

        $rows = dashglpi_fetch_all(
            "SELECT date
             FROM glpi_crontasklogs
             WHERE crontasks_id = ?
               AND state = 2
             ORDER BY date DESC
             LIMIT 5",
            [$taskId]
        );

        if (count($rows) < 2) {
            return null;
        }

        $intervals = [];
        $previousTimestamp = null;
        foreach ($rows as $row) {
            $timestamp = self::timestamp((string) ($row['date'] ?? ''));
            if ($timestamp === null) {
                continue;
            }
            if ($previousTimestamp !== null) {
                $intervals[] = abs($previousTimestamp - $timestamp);
            }
            $previousTimestamp = $timestamp;
        }

        if ($intervals === []) {
            return null;
        }

        return (int) round(self::median($intervals));
    }

    private static function healthMailData(array $settings): array
    {
        $mode = (string) ($settings['smtp_mode'] ?? '0');
        $smtpHost = trim((string) ($settings['smtp_host'] ?? ''));
        $smtpPort = trim((string) ($settings['smtp_port'] ?? ''));
        $smtpUsername = trim((string) ($settings['smtp_username'] ?? ''));
        $fromEmail = trim((string) ($settings['from_email'] ?? ''));
        $isSmtp = $mode === '1';
        $isOauth = $mode === '4';
        $missingParts = [];

        if ($isSmtp || $isOauth) {
            if ($smtpHost === '') {
                $missingParts[] = 'host SMTP';
            }
            if ($smtpPort === '' || $smtpPort === '0') {
                $missingParts[] = 'porta SMTP';
            }
            if ($smtpUsername === '') {
                $missingParts[] = 'login SMTP';
            }
        }

        if ($isSmtp && empty($settings['smtp_password_configured'])) {
            $missingParts[] = 'senha SMTP';
        }

        if ($isOauth) {
            if (trim((string) ($settings['smtp_oauth_provider'] ?? '')) === '') {
                $missingParts[] = 'provedor OAuth';
            }
            if (trim((string) ($settings['smtp_oauth_client_id'] ?? '')) === '') {
                $missingParts[] = 'client id OAuth';
            }
            if (empty($settings['smtp_oauth_client_secret_configured'])) {
                $missingParts[] = 'client secret OAuth';
            }
            if (empty($settings['smtp_oauth_refresh_token_configured'])) {
                $missingParts[] = 'refresh token OAuth';
            }
        }

        $senderLoginRuleOk = true;
        if ($isSmtp && str_contains(strtolower($smtpUsername), '@')) {
            $senderLoginRuleOk = strtolower($fromEmail) === strtolower($smtpUsername);
        }

        return [
            'smtp_mode' => $mode,
            'smtp_mode_label' => match ($mode) {
                '1' => 'SMTP',
                '4' => 'SMTP+OAuth',
                default => 'PHP',
            },
            'admin_email' => trim((string) ($settings['admin_email'] ?? '')),
            'from_email' => $fromEmail,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'sender_login_rule_ok' => $senderLoginRuleOk,
            'smtp_password_configured' => !empty($settings['smtp_password_configured']),
            'smtp_oauth_client_secret_configured' => !empty($settings['smtp_oauth_client_secret_configured']),
            'smtp_oauth_refresh_token_configured' => !empty($settings['smtp_oauth_refresh_token_configured']),
            'credentials_ok' => $missingParts === [],
            'missing_parts' => $missingParts,
        ];
    }

    private static function healthNotificationConfigData(array $summary, array $catalog): array
    {
        $specialEvent = $catalog['special_events']['ticket']['sla_reminder'] ?? [
            'available' => false,
            'event_key' => '',
            'event_label' => '',
        ];

        return [
            'active_notifications' => max(0, (int) ($summary['active'] ?? 0)),
            'templates' => max(0, (int) ($summary['templates'] ?? 0)),
            'queued' => max(0, (int) ($summary['queued'] ?? 0)),
            'sla_reminder_event_available' => !empty($specialEvent['available']),
            'sla_reminder_event_key' => (string) ($specialEvent['event_key'] ?? ''),
            'sla_reminder_event_label' => (string) ($specialEvent['event_label'] ?? ''),
        ];
    }

    private static function healthCollectorsData(array $summary, array $items): array
    {
        $rows = self::intRows($items, ['id', 'is_active', 'errors', 'filesize_max', 'collect_only_unread']);
        usort($rows, static function (array $left, array $right): int {
            $errorCompare = ((int) ($right['errors'] ?? 0)) <=> ((int) ($left['errors'] ?? 0));
            if ($errorCompare !== 0) {
                return $errorCompare;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return [
            'summary' => [
                'active' => max(0, (int) ($summary['active'] ?? 0)),
                'errors' => max(0, (int) ($summary['errors'] ?? 0)),
                'total' => max(0, (int) ($summary['total'] ?? 0)),
            ],
            'items' => $rows,
        ];
    }

    private static function healthTicketsData(): array
    {
        $rows = dashglpi_fetch_all(
            "SELECT t.id, t.name, t.status, t.date, t.priority,
                    t.time_to_own, t.time_to_resolve, t.takeintoaccountdate, t.solvedate, t.closedate,
                    t.slas_id_tto, t.slas_id_ttr, t.takeintoaccount_delay_stat, t.solve_delay_stat,
                    CASE
                        WHEN t.status IN (5, 6) AND COALESCE(t.solvedate, t.closedate) IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, t.date, COALESCE(t.solvedate, t.closedate))
                        ELSE TIMESTAMPDIFF(SECOND, t.date, NOW())
                    END AS open_seconds,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(e.completename, 'Entidade raiz') AS entity_name,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(tech.technician_count, 0) AS technician_count,
                    COALESCE(NULLIF(grp.group_name, ''), '-') AS group_name,
                    COALESCE(grp.group_count, 0) AS group_count,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.tickets_id,
                       COUNT(*) AS technician_count,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.tickets_id
             ) tech ON tech.tickets_id = t.id
             LEFT JOIN (
                SELECT gt.tickets_id,
                       COUNT(*) AS group_count,
                       GROUP_CONCAT(COALESCE(NULLIF(g.completename, ''), g.name) ORDER BY g.name SEPARATOR ', ') AS group_name
                FROM glpi_groups_tickets gt
                INNER JOIN glpi_groups g ON g.id = gt.groups_id
                WHERE gt.type = 2
                GROUP BY gt.tickets_id
             ) grp ON grp.tickets_id = t.id
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.tickets_id
             ) req ON req.tickets_id = t.id
             WHERE t.status IN (1, 2, 3, 4)
               AND t.is_deleted = 0
             ORDER BY COALESCE(t.time_to_own, t.time_to_resolve) IS NULL ASC,
                      COALESCE(t.time_to_own, t.time_to_resolve) ASC,
                      t.date ASC"
        );

        $slaData = self::enrichSlaRows($rows);
        $topCritical = array_slice($slaData['items'], 0, 10);

        foreach ($topCritical as &$row) {
            $row['ticket_url'] = self::glpiUrl('/front/ticket.form.php?id=' . (int) ($row['id'] ?? 0));
        }
        unset($row);

        return [
            'summary' => $slaData['summary'],
            'top_critical' => $topCritical,
        ];
    }

    private static function healthRecentCrontaskEvents(): array
    {
        if (!self::tableExists('glpi_crontasklogs')) {
            return [
                'available' => false,
                'items' => [],
            ];
        }

        $rows = dashglpi_fetch_all(
            "SELECT ctl.id, ct.name AS task_name, ctl.date, ctl.state, ctl.elapsed, ctl.volume, ctl.content
             FROM glpi_crontasklogs ctl
             INNER JOIN glpi_crontasks ct ON ct.id = ctl.crontasks_id
             WHERE ct.name IN ('queuednotification', 'dashglpi_entitysmtp_queuednotification', 'slaticket')
             ORDER BY ctl.date DESC, ctl.id DESC
             LIMIT 20"
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'task_name' => (string) ($row['task_name'] ?? ''),
                'date' => (string) ($row['date'] ?? ''),
                'state' => (int) ($row['state'] ?? 0),
                'state_label' => self::crontaskLogStateLabel((int) ($row['state'] ?? 0)),
                'elapsed' => isset($row['elapsed']) ? (float) $row['elapsed'] : 0.0,
                'volume' => (int) ($row['volume'] ?? 0),
                'content' => trim((string) ($row['content'] ?? '')),
            ];
        }

        return [
            'available' => true,
            'items' => $items,
        ];
    }

    private static function healthIssues(
        array $queue,
        array $crontasks,
        array $mail,
        array $notificationConfig,
        array $collectors,
        array $tickets,
        array $recentEvents,
        array $entitysmtpOwner,
        array $phpConfig = [],
        array $mailgateLoop = []
    ): array {
        $issues = [];
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];

        $queuednotificationTask = $crontasks['queuednotification'] ?? [];
        $queuednotificationMonitored = !isset($queuednotificationTask['monitored']) || $queuednotificationTask['monitored'] !== false;
        if (
            $queuednotificationMonitored
            && !empty($queuednotificationTask['available'])
            && (int) ($queuednotificationTask['last_run_age_seconds'] ?? 0) > 300
            && (int) ($queuednotificationTask['mode'] ?? 0) !== 2
        ) {
            self::addHealthIssue(
                $issues,
                $counts,
                'critical',
                'Fila de E-mails Parada (Modo CLI não detectado)',
                'A tarefa queuednotification está há mais de 5 minutos sem rodar e não está configurada em modo CLI.',
                'crontasks'
            );
        }

        if (!empty($queue['attention_active'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'critical',
                'Fila de notificações atrasada',
                sprintf(
                    '%d pendência(s) com atraso de %s.',
                    max(0, (int) ($queue['pending_count'] ?? 0)),
                    self::durationLabel(max(0, (int) ($queue['delay_seconds'] ?? 0)))
                ),
                'queue'
            );
        } elseif ((int) ($queue['pending_count'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Fila de notificações com pendências',
                sprintf('%d envio(s) aguardando processamento.', max(0, (int) ($queue['pending_count'] ?? 0))),
                'queue'
            );
        }

        if (!empty($entitysmtpOwner['conflict'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'critical',
                'Conflito no dono da fila',
                'queuednotification e dashglpi_entitysmtp_queuednotification estao ativas ao mesmo tempo.',
                'crontasks'
            );
        }

        $queueTaskNames = ($entitysmtpOwner['owner'] ?? 'glpi') === 'dash'
            ? ['dashglpi_entitysmtp_queuednotification']
            : ['queuednotification'];
        if (!empty($entitysmtpOwner['conflict'])) {
            $queueTaskNames = ['queuednotification', 'dashglpi_entitysmtp_queuednotification'];
        }
        $taskNames = array_values(array_unique(array_merge($queueTaskNames, ['slaticket'])));

        foreach ($taskNames as $taskName) {
            $task = $crontasks[$taskName] ?? [];
            if (isset($task['monitored']) && $task['monitored'] === false) {
                continue;
            }
            if (empty($task['available'])) {
                self::addHealthIssue(
                    $issues,
                    $counts,
                    'critical',
                    'Ação automática ausente',
                    sprintf('A tarefa %s não foi encontrada no GLPI.', $taskName),
                    'crontasks'
                );
                continue;
            }

            if (!empty($task['stuck_running'])) {
                self::addHealthIssue(
                    $issues,
                    $counts,
                    'critical',
                    'Ação automática possivelmente travada',
                    sprintf('%s está em execução e sem atualização recente.', $taskName),
                    'crontasks'
                );
            } elseif (!empty($task['stale'])) {
                self::addHealthIssue(
                    $issues,
                    $counts,
                    'critical',
                    'Ação automática sem execução recente',
                    sprintf('%s não executa dentro da janela esperada.', $taskName),
                    'crontasks'
                );
            }

            if (empty($task['recommendation_ok'])) {
                self::addHealthIssue(
                    $issues,
                    $counts,
                    'warning',
                    'Configuração fora do recomendado',
                    sprintf('%s deveria estar em Ativa + CLI + 60s.', $taskName),
                    'crontasks'
                );
            }

            if (!empty($task['drift_warning'])) {
                self::addHealthIssue(
                    $issues,
                    $counts,
                    'warning',
                    'Frequência executada fora do esperado',
                    sprintf('%s está executando em ritmo diferente da frequência configurada.', $taskName),
                    'crontasks'
                );
            }
        }

        if (($mail['smtp_mode'] ?? '0') !== '0' && empty($mail['credentials_ok'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Configuração SMTP incompleta',
                'Faltam itens obrigatórios: ' . implode(', ', (array) ($mail['missing_parts'] ?? [])) . '.',
                'mail'
            );
        }

        if (empty($mail['sender_login_rule_ok'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Remetente diferente do login SMTP',
                'O endereço do remetente deve ser igual ao login SMTP neste provedor.',
                'mail'
            );
        }

        if ((int) ($notificationConfig['active_notifications'] ?? 0) === 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Nenhuma notificação ativa',
                'O GLPI não possui notificações ativas no momento.',
                'notifications'
            );
        }

        if ((int) ($notificationConfig['templates'] ?? 0) === 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Nenhum template de notificação',
                'Não foram encontrados templates de notificação cadastrados.',
                'notifications'
            );
        }

        if (empty($notificationConfig['sla_reminder_event_available'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Evento de lembrete SLA indisponível',
                'O evento especial de lembrete automático de SLA não foi identificado no catálogo.',
                'notifications'
            );
        }

        if ((int) ($collectors['summary']['errors'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Coletores com erro',
                sprintf('%d coletor(es) apresentam falha.', max(0, (int) ($collectors['summary']['errors'] ?? 0))),
                'collectors'
            );
        }

        if ((int) ($collectors['summary']['total'] ?? 0) > 0 && (int) ($collectors['summary']['active'] ?? 0) === 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Coletores sem ativação',
                'Existem coletores cadastrados, mas nenhum está ativo.',
                'collectors'
            );
        }

        $ticketSummary = is_array($tickets['summary'] ?? null) ? $tickets['summary'] : [];
        if ((int) ($ticketSummary['critical'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'critical',
                'Chamados com SLA vencido',
                sprintf('%d chamado(s) crítico(s) no monitor de SLA.', max(0, (int) ($ticketSummary['critical'] ?? 0))),
                'tickets'
            );
        }

        if ((int) ($ticketSummary['warning'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Chamados em risco de SLA',
                sprintf('%d chamado(s) em atenção no monitor de SLA.', max(0, (int) ($ticketSummary['warning'] ?? 0))),
                'tickets'
            );
        }

        if ((int) ($ticketSummary['unassigned'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Chamados sem atribuição',
                sprintf('%d chamado(s) sem técnico ou grupo responsável.', max(0, (int) ($ticketSummary['unassigned'] ?? 0))),
                'tickets'
            );
        }

        if (empty($recentEvents['available'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'info',
                'Histórico de crontasks indisponível',
                'A instalação não expõe a tabela glpi_crontasklogs.',
                'recent_events'
            );
        }

        if (!empty($phpConfig['upload_max_filesize_low'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Upload de anexos limitado',
                sprintf(
                    'upload_max_filesize do GLPI esta em %s. Anexos maiores serao rejeitados.',
                    (string) ($phpConfig['upload_max_filesize'] ?? '?')
                ),
                'php_config'
            );
        }

        if (!empty($phpConfig['memory_limit_low'])) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'memory_limit do GLPI baixo',
                sprintf(
                    'memory_limit esta em %s, abaixo do recomendado (256M).',
                    (string) ($phpConfig['memory_limit'] ?? '?')
                ),
                'php_config'
            );
        }

        if ((int) ($mailgateLoop['count'] ?? 0) > 0) {
            self::addHealthIssue(
                $issues,
                $counts,
                'warning',
                'Possivel loop de e-mail no Mailgate',
                sprintf('%d par(es) de chamados duplicados criados em menos de 60s.', (int) ($mailgateLoop['count'] ?? 0)),
                'mailgate_loop'
            );
        }

        if ($issues === []) {
            self::addHealthIssue(
                $issues,
                $counts,
                'info',
                'Sem alertas relevantes',
                'O diagnóstico atual não encontrou sinais críticos nem warnings operacionais.',
                'overall'
            );
        }

        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, static function (array $left, array $right) use ($severityOrder): int {
            $leftWeight = $severityOrder[$left['severity'] ?? 'info'] ?? 9;
            $rightWeight = $severityOrder[$right['severity'] ?? 'info'] ?? 9;
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        foreach ($issues as $index => &$issue) {
            $issue['sort_order'] = $index + 1;
        }
        unset($issue);

        return [
            'issues' => $issues,
            'counts' => $counts,
        ];
    }

    private static function addHealthIssue(
        array &$issues,
        array &$counts,
        string $severity,
        string $title,
        string $detail,
        string $section
    ): void {
        $severity = in_array($severity, ['critical', 'warning', 'info'], true) ? $severity : 'info';
        $counts[$severity] = max(0, (int) ($counts[$severity] ?? 0)) + 1;
        $issues[] = [
            'id' => sprintf('%s-%d', $section, count($issues) + 1),
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'section' => $section,
        ];
    }

    private static function tableExists(string $tableName): bool
    {
        $row = dashglpi_fetch_one(
            "SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$tableName]
        );

        return !empty($row['total']);
    }

    private static function secondsSince(string $value): int
    {
        $timestamp = self::timestamp($value);
        if ($timestamp === null) {
            return 0;
        }

        return max(0, time() - $timestamp);
    }

    private static function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private static function dashboardPeriod(array $input): array
    {
        $allowed = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses'];
        $days = (int) ($input['period_days'] ?? 30);
        if (!array_key_exists($days, $allowed)) {
            $days = 30;
        }

        return [
            'days' => $days,
            'label' => $allowed[$days],
            'start' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days')),
        ];
    }

    private static function hourlyChartRanges(): array
    {
        return [
            1 => ['hours' => 1, 'label' => '1H', 'bucket_minutes' => 5, 'points' => 12],
            3 => ['hours' => 3, 'label' => '3H', 'bucket_minutes' => 15, 'points' => 12],
            6 => ['hours' => 6, 'label' => '6H', 'bucket_minutes' => 30, 'points' => 12],
            12 => ['hours' => 12, 'label' => '12H', 'bucket_minutes' => 60, 'points' => 12],
            24 => ['hours' => 24, 'label' => '24H', 'bucket_minutes' => 120, 'points' => 12],
            48 => ['hours' => 48, 'label' => '48H', 'bucket_minutes' => 240, 'points' => 12],
        ];
    }

    private static function hourlyChartRange(array $input, string $field): array
    {
        $ranges = self::hourlyChartRanges();
        $hours = (int) ($input[$field] ?? 1);

        if (!isset($ranges[$hours])) {
            $hours = 1;
        }

        return $ranges[$hours];
    }

    private static function createdTicketsRange(array $input): array
    {
        return self::hourlyChartRange($input, 'created_tickets_range_hours');
    }

    private static function notificationRange(array $input): array
    {
        return self::hourlyChartRange($input, 'notification_range_hours');
    }

    private static function createdTicketsChart(array $filters, array $scope): array
    {
        $range = self::createdTicketsRange($filters);
        $window = self::hourlyChartWindow($range);
        $rows = dashglpi_fetch_all(
            "SELECT t.date AS created_at
             FROM glpi_tickets t
             WHERE t.is_deleted = 0
               AND t.date IS NOT NULL
               AND t.date >= ?
               AND t.date < ?
               " . $scope['sql'] . "
             ORDER BY t.date ASC",
            array_merge([$window['query_start'], $window['query_end']], $scope['params'])
        );

        return self::buildHourlyChart($range, $window, $rows, 'created_at');
    }

    private static function notificationQueueData(array $filters): array
    {
        $range = self::notificationRange($filters);
        if (dashglpi_context_has_entity_scope()) {
            return self::restrictedNotificationQueueData($range);
        }

        $pending = dashglpi_fetch_one(
            "SELECT COUNT(*) AS pending_count, MIN(send_time) AS oldest_send_time
             FROM glpi_queuednotifications
             WHERE mode = 'mailing'
               AND is_deleted = 0
               AND sent_time IS NULL
               AND send_time <= NOW()"
        ) ?? [];

        $lastSent = dashglpi_fetch_one(
            "SELECT MAX(sent_time) AS last_sent_at
             FROM glpi_queuednotifications
             WHERE mode = 'mailing'
               AND is_deleted = 1
               AND sent_time IS NOT NULL"
        ) ?? [];

        $pendingCount = (int) ($pending['pending_count'] ?? 0);
        $oldestSendTime = trim((string) ($pending['oldest_send_time'] ?? ''));
        $lastSentAt = trim((string) ($lastSent['last_sent_at'] ?? ''));
        $thresholdSeconds = self::queuedNotificationThresholdSeconds();
        $delaySeconds = 0;

        if ($pendingCount > 0 && $oldestSendTime !== '') {
            $oldestTimestamp = self::timestamp($oldestSendTime);
            if ($oldestTimestamp !== null) {
                $delaySeconds = max(0, time() - $oldestTimestamp);
            }
        }

        $attentionActive = $pendingCount > 0 && $delaySeconds > $thresholdSeconds;
        $attentionReason = '';

        if ($attentionActive) {
            $attentionReason = 'Fila atrasada há ' . self::durationLabel($delaySeconds);
        } elseif ($pendingCount > 0) {
            $attentionReason = 'Pendências dentro da janela normal';
        }

        return [
            'pending_count' => $pendingCount,
            'oldest_send_time' => $oldestSendTime !== '' ? $oldestSendTime : null,
            'delay_seconds' => $delaySeconds,
            'last_sent_at' => $lastSentAt !== '' ? $lastSentAt : null,
            'last_sent_label' => $lastSentAt !== ''
                ? self::formatDateTimeLabel($lastSentAt)
                : 'Sem notificações enviadas',
            'attention_active' => $attentionActive,
            'attention_reason' => $attentionReason,
            'attention_threshold_seconds' => $thresholdSeconds,
            'chart' => self::notificationQueueChart($range),
        ];
    }

    private static function notificationQueueChart(array $range): array
    {
        $window = self::hourlyChartWindow($range);

        $rows = dashglpi_fetch_all(
            "SELECT sent_time
             FROM glpi_queuednotifications
             WHERE mode = 'mailing'
               AND is_deleted = 1
               AND sent_time IS NOT NULL
               AND sent_time >= ?
               AND sent_time < ?
             ORDER BY sent_time ASC",
            [$window['query_start'], $window['query_end']]
        );

        return self::buildHourlyChart($range, $window, $rows, 'sent_time');
    }

    private static function hourlyChartWindow(array $range): array
    {
        $intervalSeconds = max(60, (int) ($range['bucket_minutes'] ?? 5) * 60);
        $points = max(1, (int) ($range['points'] ?? 12));
        $now = time();
        $lastBucketStart = $now - ($now % $intervalSeconds);
        $firstBucketStart = $lastBucketStart - (($points - 1) * $intervalSeconds);

        return [
            'interval_seconds' => $intervalSeconds,
            'points' => $points,
            'first_bucket_start' => $firstBucketStart,
            'last_bucket_start' => $lastBucketStart,
            'query_start' => date('Y-m-d H:i:s', $firstBucketStart),
            'query_end' => date('Y-m-d H:i:s', $lastBucketStart + $intervalSeconds),
        ];
    }

    private static function buildHourlyChart(array $range, array $window, array $rows, string $timestampField): array
    {
        $intervalSeconds = (int) ($window['interval_seconds'] ?? 300);
        $points = max(1, (int) ($window['points'] ?? 12));
        $firstBucketStart = (int) ($window['first_bucket_start'] ?? time());
        $lastBucketStart = (int) ($window['last_bucket_start'] ?? $firstBucketStart);
        $bucketMap = [];

        for ($i = 0; $i < $points; $i++) {
            $bucketStart = $firstBucketStart + ($i * $intervalSeconds);
            $key = date('Y-m-d H:i:s', $bucketStart);
            $bucketMap[$key] = [
                'bucket_start' => $key,
                'label' => self::hourlyChartLabel($bucketStart, (int) ($range['hours'] ?? 1)),
                'total' => 0,
            ];
        }

        $windowEnd = $lastBucketStart + $intervalSeconds;
        foreach ($rows as $row) {
            $timestamp = self::timestamp((string) ($row[$timestampField] ?? ''));
            if ($timestamp === null || $timestamp < $firstBucketStart || $timestamp >= $windowEnd) {
                continue;
            }

            $bucketStart = $firstBucketStart
                + ((int) floor(($timestamp - $firstBucketStart) / $intervalSeconds) * $intervalSeconds);
            $key = date('Y-m-d H:i:s', $bucketStart);
            if (isset($bucketMap[$key])) {
                $bucketMap[$key]['total']++;
            }
        }

        return [
            'range_hours' => (int) ($range['hours'] ?? 1),
            'available_ranges' => array_map('intval', array_keys(self::hourlyChartRanges())),
            'points' => array_values($bucketMap),
        ];
    }

    private static function hourlyChartLabel(int $bucketStart, int $hours): string
    {
        return $hours >= 24 ? date('d/m H:i', $bucketStart) : date('H:i', $bucketStart);
    }

    private static function queuedNotificationThresholdSeconds(): int
    {
        $row = dashglpi_fetch_one(
            "SELECT frequency
             FROM glpi_crontasks
             WHERE name IN ('queuednotification', 'dashglpi_entitysmtp_queuednotification')
             ORDER BY CASE
                        WHEN name = 'dashglpi_entitysmtp_queuednotification' AND state = 1 THEN 0
                        WHEN name = 'queuednotification' THEN 1
                        ELSE 2
                      END ASC
             LIMIT 1"
        );

        $frequency = (int) ($row['frequency'] ?? 0);
        return $frequency > 0 ? $frequency * 2 : 120;
    }

    private static function countAssignedTickets(string $start, array $scope): int
    {
        $row = dashglpi_fetch_one(
            "SELECT COUNT(DISTINCT t.id) AS cpt
             FROM glpi_tickets t
             INNER JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
             WHERE t.is_deleted = 0
               AND t.status IN (1, 2, 3, 4)
               AND t.date >= ?
               " . $scope['sql'],
            array_merge([$start], $scope['params'])
        );

        return (int) ($row['cpt'] ?? 0);
    }

    private static function countReopenedTickets(string $start, array $scope): int
    {
        $row = dashglpi_fetch_one(
            "SELECT COUNT(DISTINCT l.items_id) AS cpt
             FROM glpi_logs l
             INNER JOIN glpi_tickets t ON t.id = l.items_id AND t.is_deleted = 0
             WHERE l.itemtype = 'Ticket'
               AND l.date_mod >= ?
               AND l.id_search_option = 12
               AND (
                    (CAST(l.old_id AS UNSIGNED) IN (5, 6) AND CAST(l.new_id AS UNSIGNED) IN (1, 2, 3, 4))
                    OR (
                        l.old_value IN ('5', '6', 'Solucionado', 'Fechado')
                        AND l.new_value IN ('1', '2', '3', '4', 'Novo', 'Em Atendimento', 'Planejado', 'Pendente')
                    )
               )
               " . $scope['sql'],
            array_merge([$start], $scope['params'])
        );

        return (int) ($row['cpt'] ?? 0);
    }

    private static function getDashboardSlaRows(string $start, array $scope): array
    {
        $rows = dashglpi_fetch_all(
            "SELECT t.id, t.name, t.status, t.date, t.priority,
                    t.time_to_own, t.time_to_resolve, t.takeintoaccountdate, t.solvedate, t.closedate,
                    t.slas_id_tto, t.slas_id_ttr, t.takeintoaccount_delay_stat, t.solve_delay_stat,
                    CASE
                        WHEN t.status IN (5, 6) AND COALESCE(t.solvedate, t.closedate) IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, t.date, COALESCE(t.solvedate, t.closedate))
                        ELSE TIMESTAMPDIFF(SECOND, t.date, NOW())
                    END AS open_seconds,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(e.completename, 'Entidade raiz') AS entity_name,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(tech.technician_count, 0) AS technician_count,
                    COALESCE(NULLIF(grp.group_name, ''), '-') AS group_name,
                    COALESCE(grp.group_count, 0) AS group_count,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.tickets_id,
                       COUNT(*) AS technician_count,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.tickets_id
             ) tech ON tech.tickets_id = t.id
             LEFT JOIN (
                SELECT gt.tickets_id,
                       COUNT(*) AS group_count,
                       GROUP_CONCAT(COALESCE(NULLIF(g.completename, ''), g.name) ORDER BY g.name SEPARATOR ', ') AS group_name
                FROM glpi_groups_tickets gt
                INNER JOIN glpi_groups g ON g.id = gt.groups_id
                WHERE gt.type = 2
                GROUP BY gt.tickets_id
             ) grp ON grp.tickets_id = t.id
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.tickets_id
             ) req ON req.tickets_id = t.id
             WHERE t.status IN (1, 2, 3, 4)
               AND t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             ORDER BY COALESCE(t.time_to_own, t.time_to_resolve) IS NULL ASC,
                      COALESCE(t.time_to_own, t.time_to_resolve) ASC,
                      t.date ASC",
            array_merge([$start], $scope['params'])
        );

        return self::enrichSlaRows($rows)['items'];
    }

    private static function enrichSlaRows(array $rows): array
    {
        $rows = self::intRows($rows, [
            'id',
            'status',
            'priority',
            'technician_count',
            'group_count',
            'open_seconds',
            'slas_id_tto',
            'slas_id_ttr',
            'takeintoaccount_delay_stat',
            'solve_delay_stat',
        ]);
        $now = time();
        $summary = [
            'critical' => 0,
            'warning' => 0,
            'ok' => 0,
            'unassigned' => 0,
            'avg_open_seconds' => 0,
        ];
        $elapsedTotal = 0;

        foreach ($rows as &$row) {
            $hasTechnician = (int) ($row['technician_count'] ?? 0) > 0;
            $hasGroup = (int) ($row['group_count'] ?? 0) > 0;
            $isUnassigned = !$hasTechnician && !$hasGroup;
            $activeKind = $isUnassigned ? 'TTO' : 'TTR';
            $ttoRow = $row;
            if ($isUnassigned) {
                $ttoRow['takeintoaccountdate'] = null;
                $ttoRow['takeintoaccount_delay_stat'] = 0;
            }
            $tto = self::slaTiming(
                'TTO',
                $ttoRow,
                'time_to_own',
                'takeintoaccountdate',
                'takeintoaccount_delay_stat',
                $now
            );
            $ttr = self::slaTiming(
                'TTR',
                $row,
                'time_to_resolve',
                'solvedate',
                'solve_delay_stat',
                $now,
                'closedate'
            );
            $active = $activeKind === 'TTO' ? $tto : $ttr;
            $activePercent = (float) ($active['percent'] ?? 0);
            $isOverdue = !empty($active['overdue']);
            $activeElapsed = max(0, (int) ($active['elapsed_seconds'] ?? 0));
            $elapsedTotal += $activeElapsed;

            $riskScore = 0;
            if ($isUnassigned) {
                $riskScore += 50;
            }
            if ($activePercent >= 80 && !$isOverdue) {
                $riskScore += 30;
            }
            if ($isOverdue) {
                $riskScore += 50;
            }
            if ((int) ($row['priority'] ?? 0) === 4) {
                $riskScore += 20;
            }
            if ((int) ($row['priority'] ?? 0) >= 5) {
                $riskScore += 50;
            }

            if ($riskScore >= 100) {
                $row['sla_status'] = 'critical';
            } elseif ($riskScore >= 50) {
                $row['sla_status'] = 'warning';
            } else {
                $row['sla_status'] = 'ok';
            }

            $row['active_sla_kind'] = $activeKind;
            $row['active_sla_deadline'] = $active['deadline'];
            $row['active_sla_completion'] = $active['completion'];
            $row['active_sla_elapsed_seconds'] = $activeElapsed;
            $row['active_sla_percent'] = $activePercent;
            $row['active_sla_overdue'] = $isOverdue ? 1 : 0;
            $row['seconds_left'] = $active['seconds_left'];
            $row['sla_initial_percent'] = $activePercent;
            $row['sla_initial_overdue'] = $isOverdue ? 1 : 0;
            $row['is_unassigned'] = $isUnassigned ? 1 : 0;
            $row['is_closed'] = in_array((int) ($row['status'] ?? 0), [5, 6], true) ? 1 : 0;
            $row['risk_score'] = $riskScore;
            $row['risk_label'] = self::riskLabel($riskScore);
            $row['priority_label'] = self::priorityLabel((int) ($row['priority'] ?? 0));
            $row['status_label'] = self::statusLabel((int) ($row['status'] ?? 0));
            $row['tto'] = $tto;
            $row['ttr'] = $ttr;
            $summary[$row['sla_status']]++;
            if ($isUnassigned) {
                $summary['unassigned']++;
            }
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            $risk = ((int) ($b['risk_score'] ?? 0)) <=> ((int) ($a['risk_score'] ?? 0));
            if ($risk !== 0) {
                return $risk;
            }
            return ((int) ($a['seconds_left'] ?? PHP_INT_MAX)) <=> ((int) ($b['seconds_left'] ?? PHP_INT_MAX));
        });

        if (count($rows) > 0) {
            $summary['avg_open_seconds'] = (int) round($elapsedTotal / count($rows));
        }

        return [
            'items' => $rows,
            'summary' => $summary,
        ];
    }

    private static function dashboardNotifications(array $slaRows, array $period, array $scope): array
    {
        $notifications = [];
        $seen = [];
        $profileRestricted = !empty(dashglpi_current_user_context()['has_profile_rule']);

        if (!$profileRestricted) {
            foreach ($slaRows as $row) {
                if (!empty($row['active_sla_overdue'])) {
                    self::addNotification($notifications, $seen, [
                        'id' => 'sla-overdue-' . (int) $row['id'],
                        'type' => 'danger',
                        'category' => 'sla',
                        'text' => 'SLA vencido: Chamado #' . (int) $row['id'] . ' (' . (string) $row['active_sla_kind'] . ')',
                        'time' => self::deadlineLabel((string) ($row['active_sla_deadline'] ?? '')),
                        'unread' => true,
                    ]);
                }
                if (count($notifications) >= 4) {
                    break;
                }
            }

            foreach ($slaRows as $row) {
                if (($row['sla_status'] ?? '') === 'warning' && empty($row['active_sla_overdue'])) {
                    self::addNotification($notifications, $seen, [
                        'id' => 'sla-risk-' . (int) $row['id'],
                        'type' => 'warning',
                        'category' => 'sla',
                        'text' => 'SLA em risco: Chamado #' . (int) $row['id'] . ' em ' . (float) ($row['active_sla_percent'] ?? 0) . '%',
                        'time' => self::deadlineLabel((string) ($row['active_sla_deadline'] ?? '')),
                        'unread' => true,
                    ]);
                }
                if (count($notifications) >= 6) {
                    break;
                }
            }

            foreach ($slaRows as $row) {
                if (!empty($row['is_unassigned'])) {
                    self::addNotification($notifications, $seen, [
                        'id' => 'unassigned-' . (int) $row['id'],
                        'type' => 'info',
                        'category' => 'assignment',
                        'text' => 'Chamado #' . (int) $row['id'] . ' sem técnico atribuído',
                        'time' => self::timeAgo((string) ($row['date'] ?? '')),
                        'unread' => true,
                    ]);
                }
                if (count($notifications) >= 8) {
                    break;
                }
            }
        }

        foreach (self::notificationTicketRows($period['start'], [1], 'date', 3, $scope) as $row) {
            self::addNotification($notifications, $seen, [
                'id' => 'new-' . (int) $row['id'],
                'type' => 'info',
                'category' => 'ticket',
                'text' => 'Novo chamado #' . (int) $row['id'] . ': ' . self::shortText((string) $row['name'], 54),
                'time' => self::timeAgo((string) ($row['date'] ?? '')),
                'unread' => true,
            ]);
        }

        foreach (self::notificationTicketRows($period['start'], [5, 6], 'solvedate', 3, $scope) as $row) {
            self::addNotification($notifications, $seen, [
                'id' => 'resolved-' . (int) $row['id'],
                'type' => 'success',
                'category' => 'ticket',
                'text' => 'Chamado #' . (int) $row['id'] . ' foi resolvido',
                'time' => self::timeAgo((string) ($row['solvedate'] ?? '')),
                'unread' => true,
            ]);
        }

        return array_slice($notifications, 0, 8);
    }

    private static function addNotification(array &$notifications, array &$seen, array $notification): void
    {
        $id = (string) ($notification['id'] ?? '');
        if ($id === '' || isset($seen[$id])) {
            return;
        }

        $seen[$id] = true;
        $notifications[] = $notification;
    }

    private static function notificationTicketRows(string $start, array $statuses, string $dateField, int $limit, array $scope): array
    {
        $dateField = in_array($dateField, ['date', 'solvedate'], true) ? $dateField : 'date';
        $limit = min(10, max(1, $limit));
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge($statuses, [$start]);

        return dashglpi_fetch_all(
            "SELECT t.id, t.name, t.status, t.date, t.solvedate
             FROM glpi_tickets t
             WHERE t.is_deleted = 0
               AND t.status IN ($placeholders)
               AND t.$dateField IS NOT NULL
               AND t.$dateField >= ?
               " . $scope['sql'] . "
             ORDER BY t.$dateField DESC
             LIMIT $limit",
            array_merge($params, $scope['params'])
        );
    }

    private static function shortText(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, max(0, $max - 3)) . '...';
    }

    private static function timeAgo(string $value): string
    {
        $timestamp = self::timestamp($value);
        if (!$timestamp) {
            return 'Agora';
        }

        $seconds = time() - $timestamp;
        if ($seconds <= 60) {
            return 'Agora';
        }

        return self::durationLabel($seconds) . ' atrás';
    }

    private static function deadlineLabel(string $value): string
    {
        $timestamp = self::timestamp($value);
        if (!$timestamp) {
            return 'Sem prazo';
        }

        $seconds = $timestamp - time();
        if ($seconds >= 0) {
            return 'vence em ' . self::durationLabel($seconds);
        }

        return 'vencido há ' . self::durationLabel(abs($seconds));
    }

    private static function durationLabel(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, (int) ceil($seconds / 60)) . ' min';
        }

        if ($seconds < 86400) {
            return max(1, (int) round($seconds / 3600)) . ' h';
        }

        return max(1, (int) round($seconds / 86400)) . ' d';
    }

    private static function countTickets(string $extraWhere = '', array $params = [], ?array $scope = null): int
    {
        $scope = $scope ?? ['sql' => '', 'params' => []];
        $row = dashglpi_fetch_one(
            "SELECT COUNT(t.id) AS cpt
             FROM glpi_tickets t
             WHERE t.is_deleted = 0 $extraWhere" . $scope['sql'],
            array_merge($params, $scope['params'])
        );
        return (int) ($row['cpt'] ?? 0);
    }

    private static function ticketRows(string $start, int $limit, array $scope): array
    {
        $limit = min(self::TICKETS_LIST_MAX, max(1, $limit));

        return dashglpi_fetch_all(
            "SELECT t.id, t.name, t.content, t.status, t.global_validation, t.date, t.date_mod, t.priority, t.time_to_resolve,
                    t.time_to_own, t.takeintoaccountdate, t.solvedate, t.closedate, t.entities_id,
                    t.takeintoaccount_delay_stat, t.solve_delay_stat,
                    COALESCE(c.completename, 'Sem Categoria') AS category,
                    COALESCE(e.completename, e.name, 'Entidade raiz') AS entity_name,
                    COALESCE(NULLIF(tech.technician_name, ''), '-') AS technician_name,
                    COALESCE(tech.technician_count, 0) AS technician_count,
                    COALESCE(grp.group_count, 0) AS group_count,
                    COALESCE(NULLIF(req.requester_name, ''), NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS requester_name,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(NULLIF(ur.firstname, ''), ur.name, ''), ' ', COALESCE(ur.realname, ''))), ''), '-') AS user_name,
                    COALESCE(qn.has_failure, 0) AS notification_failed,
                    COALESCE(doc.documents_count, 0) AS attachments_count,
                    COALESCE(fu.followups_count, 0) AS followups_count,
                    CASE WHEN ts.id IS NOT NULL AND ts.date_answered IS NULL THEN 1 ELSE 0 END AS satisfaction_pending
             FROM glpi_tickets t
             LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
             LEFT JOIN glpi_entities e ON e.id = t.entities_id
             LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
             LEFT JOIN (
                SELECT tu.tickets_id,
                       COUNT(*) AS technician_count,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS technician_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 2
                GROUP BY tu.tickets_id
             ) tech ON tech.tickets_id = t.id
             LEFT JOIN (
                SELECT gt.tickets_id,
                       COUNT(*) AS group_count
                FROM glpi_groups_tickets gt
                WHERE gt.type = 2
                GROUP BY gt.tickets_id
             ) grp ON grp.tickets_id = t.id
             LEFT JOIN (
                SELECT tu.tickets_id,
                       GROUP_CONCAT(TRIM(CONCAT(COALESCE(NULLIF(u.firstname, ''), u.name, ''), ' ', COALESCE(u.realname, ''))) ORDER BY u.name SEPARATOR ', ') AS requester_name
                FROM glpi_tickets_users tu
                INNER JOIN glpi_users u ON u.id = tu.users_id
                WHERE tu.type = 1
                GROUP BY tu.tickets_id
             ) req ON req.tickets_id = t.id
             LEFT JOIN (
                SELECT items_id, 1 AS has_failure
                FROM glpi_queuednotifications
                WHERE itemtype = 'Ticket' AND mode = 'mailing' AND is_deleted = 1 AND sent_time IS NULL
                GROUP BY items_id
             ) qn ON qn.items_id = t.id
             LEFT JOIN glpi_ticketsatisfactions ts ON ts.tickets_id = t.id
             LEFT JOIN (
                SELECT items_id, COUNT(*) AS documents_count
                FROM glpi_documents_items
                WHERE itemtype = 'Ticket'
                GROUP BY items_id
             ) doc ON doc.items_id = t.id
             LEFT JOIN (
                SELECT items_id, COUNT(*) AS followups_count
                FROM glpi_itilfollowups
                WHERE itemtype = 'Ticket' AND is_private = 0
                GROUP BY items_id
             ) fu ON fu.items_id = t.id
             WHERE t.status IN (1, 2, 3, 4, 5, 6)
               AND t.is_deleted = 0
               AND t.date >= ?
               " . $scope['sql'] . "
             ORDER BY t.date DESC
             LIMIT $limit",
            array_merge([$start], $scope['params'])
        );
    }

    private static function recentTickets(string $start, int $limit, array $scope): array
    {
        $rows = self::ticketRows($start, $limit, $scope);
        $rows = self::intRows($rows, ['id', 'status', 'priority']);

        foreach ($rows as &$row) {
            $row['stage'] = self::ticketStage((int) ($row['status'] ?? 0));
            $row['stage_step'] = self::ticketStageStep((int) ($row['status'] ?? 0));
            $row['status_label'] = self::statusLabel((int) ($row['status'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    private static function entityScope(string $column, bool $prependAnd = true): array
    {
        return dashglpi_scoped_entity_sql($column, $prependAnd);
    }

    private static function dashboardTicketScope(string $start, bool $myTasks): array
    {
        if ($myTasks) {
            return self::currentUserTicketScope('t');
        }

        return self::entityScope('t.entities_id');
    }

    private static function ticketsListScope(string $start, bool $myTasks): array
    {
        if ($myTasks) {
            return self::currentUserTicketScope('t');
        }

        return self::entityScope('t.entities_id');
    }

    private static function slaListScope(array $where, array $params): array
    {
        return self::requesterThenEntityScope(
            implode(' AND ', $where),
            $params
        );
    }

    private static function requesterThenEntityScope(string $baseWhereSql, array $baseParams, string $ticketAlias = 't'): array
    {
        $context = dashglpi_current_user_context();
        if (!empty($context['is_admin_bypass'])) {
            return [
                'mode' => 'entity',
                'sql' => '',
                'where' => '',
                'params' => [],
            ];
        }

        $requesterScope = self::requesterScope($ticketAlias, false);
        $requesterWhere = trim($baseWhereSql);
        if ($requesterWhere === '') {
            $requesterWhere = $requesterScope['where'];
        } else {
            $requesterWhere .= ' AND ' . $requesterScope['where'];
        }

        $hasRequesterRows = (bool) dashglpi_fetch_one(
            "SELECT $ticketAlias.id
             FROM glpi_tickets $ticketAlias
             WHERE $requesterWhere
             LIMIT 1",
            array_merge($baseParams, $requesterScope['params'])
        );

        if ($hasRequesterRows) {
            return [
                'mode' => 'requester',
                'sql' => ' AND ' . $requesterScope['where'],
                'where' => $requesterScope['where'],
                'params' => $requesterScope['params'],
            ];
        }

        $entityScope = self::entityScope($ticketAlias . '.entities_id', false);

        return [
            'mode' => 'entity',
            'sql' => $entityScope['sql'] !== '' ? ' AND ' . $entityScope['sql'] : '',
            'where' => $entityScope['sql'],
            'params' => $entityScope['params'],
        ];
    }


    private static function filterMyTasks(array $filters): bool
    {
        $value = $filters['my_tasks'] ?? '1';
        return !in_array((string) $value, ['0', 'false', 'off', 'no'], true);
    }

    private static function currentUserTicketScope(string $ticketAlias = 't'): array
    {
        $context = dashglpi_current_user_context();
        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['mode' => 'my_tasks', 'sql' => ' AND 1 = 0', 'where' => '1 = 0', 'params' => []];
        }

        $where = "($ticketAlias.users_id_recipient = ? OR EXISTS (
            SELECT 1
            FROM glpi_tickets_users tu_scope
            WHERE tu_scope.tickets_id = $ticketAlias.id
              AND tu_scope.users_id = ?
              AND tu_scope.type IN (1, 2)
        ))";

        return [
            'mode' => 'my_tasks',
            'sql' => ' AND ' . $where,
            'where' => $where,
            'params' => [$userId, $userId],
        ];
    }

    private static function currentUserItilScope(array $type, string $objectAlias = 't'): array
    {
        $context = dashglpi_current_user_context();
        $userId = (int) ($context['user_id'] ?? 0);
        $fk = (string) ($type['fk'] ?? '');
        $userLinkTable = (string) ($type['user_link_table'] ?? '');
        if ($userId <= 0 || $fk === '' || $userLinkTable === '') {
            return ['mode' => 'my_tasks', 'sql' => ' AND 1 = 0', 'where' => '1 = 0', 'params' => []];
        }

        $where = "($objectAlias.users_id_recipient = ? OR EXISTS (
            SELECT 1
            FROM $userLinkTable tu_scope
            WHERE tu_scope.$fk = $objectAlias.id
              AND tu_scope.users_id = ?
              AND tu_scope.type IN (1, 2)
        ))";

        return [
            'mode' => 'my_tasks',
            'sql' => ' AND ' . $where,
            'where' => $where,
            'params' => [$userId, $userId],
        ];
    }
    private static function requesterScope(string $ticketAlias = 't', bool $prependAnd = true): array
    {
        $context = dashglpi_current_user_context();
        $userId = (int) ($context['user_id'] ?? 0);
        $prefix = $prependAnd ? ' AND ' : '';
        $where = "EXISTS (
            SELECT 1
            FROM glpi_tickets_users tu_scope
            WHERE tu_scope.tickets_id = $ticketAlias.id
              AND tu_scope.users_id = ?
              AND tu_scope.type = 1
        )";

        return [
            'sql' => $prefix . $where,
            'where' => $where,
            'params' => [$userId],
        ];
    }

    private static function restrictedNotificationQueueData(array $range): array
    {
        $window = self::hourlyChartWindow($range);

        return [
            'pending_count' => 0,
            'oldest_send_time' => null,
            'delay_seconds' => 0,
            'last_sent_at' => null,
            'last_sent_label' => 'Indisponivel para este perfil',
            'attention_active' => false,
            'attention_reason' => 'Dados operacionais da fila ficam restritos a perfis administrativos.',
            'attention_threshold_seconds' => 0,
            'chart' => self::buildHourlyChart($range, $window, [], 'sent_time'),
        ];
    }

    private static function ticketStage(int $status): string
    {
        return match ($status) {
            1 => 'Aberto',
            3 => 'Planejado',
            2 => 'Em Atendimento',
            4 => 'Pendente',
            5 => 'Solucionado',
            6 => 'Fechado',
            default => 'Outro',
        };
    }

    private static function ticketStageStep(int $status): int
    {
        return match ($status) {
            1 => 1,
            3 => 2,
            2 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            default => 0,
        };
    }

    private static function slaListFilters(array $input): array
    {
        $statuses = [];
        $rawStatuses = (string) ($input['statuses'] ?? '');
        if ($rawStatuses !== '') {
            foreach (explode(',', $rawStatuses) as $status) {
                $value = (int) trim($status);
                if ($value >= 1 && $value <= 6) {
                    $statuses[] = $value;
                }
            }
        }

        $statuses = array_values(array_unique($statuses));
        if (!$statuses) {
            $statuses = [1, 2, 3, 4];
        }

        sort($statuses);

        return [
            'statuses' => $statuses,
            'date_from' => self::dateFilter((string) ($input['date_from'] ?? '')),
            'date_to' => self::dateFilter((string) ($input['date_to'] ?? '')),
        ];
    }

    private static function dateFilter(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : null;
    }

    private static function slaTiming(
        string $kind,
        array $row,
        string $deadlineField,
        string $completionField,
        string $statField,
        int $now,
        ?string $fallbackCompletionField = null
    ): array {
        $start = self::timestamp((string) ($row['date'] ?? ''));
        $deadline = self::timestamp((string) ($row[$deadlineField] ?? ''));
        $completion = self::timestamp((string) ($row[$completionField] ?? ''));
        if (!$completion && $fallbackCompletionField !== null) {
            $completion = self::timestamp((string) ($row[$fallbackCompletionField] ?? ''));
        }

        $end = $completion ?: $now;
        $statSeconds = max(0, (int) ($row[$statField] ?? 0));
        $elapsedSeconds = $statSeconds > 0
            ? $statSeconds
            : (($start && $end > $start) ? max(0, $end - $start) : 0);
        $totalSeconds = ($start && $deadline && $deadline > $start)
            ? max(1, $deadline - $start)
            : null;
        $percent = $totalSeconds !== null
            ? min(999, max(0, round(($elapsedSeconds / $totalSeconds) * 100, 1)))
            : 0;
        $secondsLeft = $deadline ? $deadline - $end : null;

        return [
            'kind' => $kind,
            'start' => self::dateValue($start),
            'deadline' => self::dateValue($deadline),
            'completion' => self::dateValue($completion),
            'elapsed_seconds' => $elapsedSeconds,
            'total_seconds' => $totalSeconds,
            'seconds_left' => $secondsLeft,
            'percent' => $percent,
            'overdue' => $secondsLeft !== null && $secondsLeft < 0 ? 1 : 0,
        ];
    }

    private static function timestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    private static function dateValue(?int $timestamp): ?string
    {
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function formatDateTimeLabel(string $value): string
    {
        $timestamp = self::timestamp($value);
        return $timestamp ? date('d/m/Y - H:i:s', $timestamp) : 'Sem notificações enviadas';
    }

    private static function averageResolutionSeconds(): int
    {
        $scope = self::entityScope('entities_id');
        $row = dashglpi_fetch_one(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, date, solvedate)) AS avg_sec
             FROM glpi_tickets
             WHERE status IN (5, 6)
               AND is_deleted = 0
               AND solvedate IS NOT NULL
               AND date IS NOT NULL
               AND solvedate >= DATE(NOW()) - INTERVAL 30 DAY
               " . $scope['sql'],
            $scope['params']
        );

        return !empty($row['avg_sec']) ? (int) round((float) $row['avg_sec']) : 0;
    }

    private static function slaAverageOpenSeconds(array $filter, array $scope): int
    {
        $queryFilter = self::slaTicketQueryFilter($filter);
        $where = $queryFilter['where'];
        $params = $queryFilter['params'];

        if ($scope['where'] !== '') {
            $where[] = $scope['where'];
            $params = array_merge($params, $scope['params']);
        }

        $row = dashglpi_fetch_one(
            "SELECT AVG(
                    CASE
                        WHEN t.status IN (5, 6) AND COALESCE(t.solvedate, t.closedate) IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, t.date, COALESCE(t.solvedate, t.closedate))
                        ELSE TIMESTAMPDIFF(SECOND, t.date, NOW())
                    END
                ) AS avg_sec
             FROM glpi_tickets t
             WHERE " . implode(' AND ', $where),
            $params
        );

        return !empty($row['avg_sec']) ? (int) round((float) $row['avg_sec']) : 0;
    }

    private static function slaTicketQueryFilter(array $filter): array
    {
        $statuses = $filter['statuses'] ?? [1, 2, 3, 4];
        if (!is_array($statuses) || !$statuses) {
            $statuses = [1, 2, 3, 4];
        }

        $statuses = array_values(array_filter(array_map('intval', $statuses), static fn(int $status): bool => $status >= 1 && $status <= 6));
        if (!$statuses) {
            $statuses = [1, 2, 3, 4];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = $statuses;
        $where = [
            "t.status IN ($placeholders)",
            't.is_deleted = 0',
            't.date IS NOT NULL',
        ];

        if (($filter['date_from'] ?? null) !== null) {
            $where[] = 't.date >= ?';
            $params[] = $filter['date_from'] . ' 00:00:00';
        }

        if (($filter['date_to'] ?? null) !== null) {
            $where[] = 't.date <= ?';
            $params[] = $filter['date_to'] . ' 23:59:59';
        }

        return [
            'where' => $where,
            'params' => $params,
        ];
    }

    private static function riskLabel(int $score): string
    {
        if ($score >= 100) {
            return 'Crítico';
        }
        if ($score >= 50) {
            return 'Atenção';
        }
        return 'No prazo';
    }

    private static function statusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Novo',
            2 => 'Em Atendimento',
            3 => 'Planejado',
            4 => 'Pendente',
            5 => 'Solucionado',
            6 => 'Fechado',
            default => 'Outro',
        };
    }

    private static function priorityLabel(int $priority): string
    {
        return match ($priority) {
            1 => 'Muito baixa',
            2 => 'Baixa',
            3 => 'Média',
            4 => 'Alta',
            5 => 'Muito alta',
            6 => 'Maior',
            default => 'Não definida',
        };
    }

    private static function crontaskStateLabel(int $state): string
    {
        return match ($state) {
            1 => 'Ativa',
            2 => 'Em execução',
            default => 'Desativada',
        };
    }

    private static function crontaskModeLabel(int $mode): string
    {
        return match ($mode) {
            2 => 'CLI',
            1 => 'GLPI',
            default => 'Desconhecido',
        };
    }

    private static function crontaskLogStateLabel(int $state): string
    {
        return match ($state) {
            2 => 'Concluída',
            1 => 'Erro',
            default => 'Info',
        };
    }

    private static function intRows(array $rows, array $columns): array
    {
        foreach ($rows as &$row) {
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = (int) $row[$column];
                }
            }
        }
        return $rows;
    }
}

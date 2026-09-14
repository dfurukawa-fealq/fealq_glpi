<?php

require_once __DIR__ . '/event_alerts.php';
require_once __DIR__ . '/sla_monitor.php';
require_once __DIR__ . '/messaging/notification_dispatcher.php';

/**
 * TaskWorker (PLAN-20260702-007): notifica o grupo/fila responsável quando uma
 * nova tarefa é criada em um chamado, via Teams (webhook por grupo — Incoming
 * Webhook não suporta DM por técnico individual, decisão registrada no plano).
 *
 * PLAN-20260709-019 (Fase E): generalizado para Problema/Manutenção via registry
 * ITIL. O event_type de dedupe é namespaceado por objeto ('task_assigned' para
 * chamados — registros antigos continuam válidos; 'task_assigned:problem' /
 * 'task_assigned:change' para os novos), evitando colisão de ids entre tabelas
 * (Chamado #500 vs Problema #500 — risco §4.5 do plano).
 */

const DASHGLPI_TASK_EVENT_TYPE = 'task_assigned';

function dashglpi_task_worker_event_type(string $typeKey): string
{
    return $typeKey === 'ticket'
        ? DASHGLPI_TASK_EVENT_TYPE
        : DASHGLPI_TASK_EVENT_TYPE . ':' . $typeKey;
}

/**
 * @return array{scanned:int,dispatched:int,errors:string[]}
 */
function dashglpi_task_worker_run(bool $dryRun = false): array
{
    dashglpi_event_alerts_ensure_table();

    $config = dashglpi_alerting_config();
    $dispatcher = new DashglpiNotificationDispatcher();

    $result = ['scanned' => 0, 'dispatched' => 0, 'errors' => []];

    foreach (dashglpi_itil_type_keys() as $typeKey) {
        $eventType = dashglpi_task_worker_event_type($typeKey);
        $candidates = dashglpi_task_worker_candidates(120, $typeKey);
        $result['scanned'] += count($candidates);

        foreach ($candidates as $task) {
            $taskId = (int) $task['task_id'];

            if (dashglpi_event_alert_already_sent($eventType, $taskId, 'info')) {
                continue;
            }

            $groupId = (int) ($task['groups_id'] ?? 0);
            if ($groupId <= 0) {
                // Sem grupo/fila responsável atribuído — nada para rotear.
                // Não registra em dedupe: se um grupo for atribuído depois, a tarefa
                // ainda está na janela de candidatos e será reavaliada.
                continue;
            }

            $webhook = dashglpi_alerting_group_teams_webhook($config, $groupId);
            if ($webhook === '') {
                // Grupo sem webhook Teams configurado — mesmo raciocínio acima.
                continue;
            }

            $alert = dashglpi_task_worker_build_alert($task, $typeKey);

            if ($dryRun) {
                continue;
            }

            $groupConfig = $config;
            $groupConfig['teams'] = ['enabled' => true, 'webhook_url' => $webhook];

            $dispatch = $dispatcher->sendTo('teams', $alert, $groupConfig);
            if (!empty($dispatch['ok'])) {
                dashglpi_event_alert_record($eventType, $taskId, 'info', ['teams']);
                $result['dispatched']++;
            } elseif (empty($dispatch['skipped']) && !empty($dispatch['error'])) {
                $result['errors'][] = 'Task #' . $taskId . ' [' . $typeKey . ']: ' . $dispatch['error'];
            }
        }
    }

    return $result;
}

/**
 * @param int $windowMinutes janela de varredura (default 120 = 2h, comportamento
 *        original do worker fixo). A engine de regras (PLAN-20260703-008) chama esta
 *        mesma função com a janela vinda de `dashglpi_rules.trigger_value` por regra.
 * @param string $typeKey objeto ITIL ('ticket' default preserva o contrato original;
 *        aliases tickets_id/ticket_name mantidos para o build_alert e a rules engine).
 */
function dashglpi_task_worker_candidates(int $windowMinutes = 120, string $typeKey = 'ticket'): array
{
    $windowMinutes = max(1, $windowMinutes);
    $type = dashglpi_itil_type($typeKey) ?? dashglpi_itil_type('ticket');
    $fk = $type['fk'];
    $eventType = dashglpi_task_worker_event_type((string) $type['key']);

    return dashglpi_fetch_all(
        "SELECT tk.id AS task_id, tk.$fk AS tickets_id, tk.content AS task_content, tk.date_creation,
                t.name AS ticket_name, t.entities_id,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name,
                COALESCE(c.completename, c.name, '') AS category_name,
                (SELECT MIN(gt.groups_id)
                   FROM {$type['group_link_table']} gt
                  WHERE gt.$fk = t.id AND gt.type = 2) AS groups_id
         FROM {$type['task_table']} tk
         INNER JOIN {$type['table']} t ON t.id = tk.$fk AND t.is_deleted = 0
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
         WHERE tk.date_creation >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
           AND NOT EXISTS (
               SELECT 1 FROM glpi_plugin_dashglpi_event_alerts ea
               WHERE ea.event_type = ? AND ea.event_id = tk.id AND ea.level = 'info'
           )
         ORDER BY tk.date_creation ASC
         LIMIT 200",
        [$windowMinutes, $eventType]
    );
}

function dashglpi_task_worker_build_alert(array $task, string $typeKey = 'ticket'): array
{
    $type = dashglpi_itil_type($typeKey) ?? dashglpi_itil_type('ticket');
    $objectId = (int) ($task['tickets_id'] ?? 0);
    $content = trim(strip_tags((string) ($task['task_content'] ?? '')));
    if (strlen($content) > 200) {
        $content = substr($content, 0, 200) . '…';
    }

    $isTicket = (string) $type['key'] === 'ticket';
    $reference = $isTicket ? '#' . $objectId : $type['label'] . ' #' . $objectId;

    return [
        'ticket_id' => $objectId,
        'title' => 'Nova tarefa em ' . $reference . ' — ' . (string) ($task['ticket_name'] ?? ''),
        'category' => (string) ($task['category_name'] ?? ''),
        'entity_name' => (string) ($task['entity_name'] ?? ''),
        'entities_id' => (int) ($task['entities_id'] ?? 0),
        'minutes_overdue' => max(0, (int) ((strtotime('now') - strtotime((string) $task['date_creation'])) / 60)),
        'threshold' => 0,
        'level' => 'info',
        'note' => $content,
        'created_at' => (string) ($task['date_creation'] ?? ''),
        'created_at_formatted' => dashglpi_format_event_datetime($task['date_creation'] ?? null),
        'url' => $isTicket ? dashglpi_sla_monitor_ticket_url($objectId) : dashglpi_itil_glpi_url($type, $objectId),
        'dashboard_url' => $isTicket ? dashglpi_dashboard_ticket_url($objectId) : '',
    ];
}

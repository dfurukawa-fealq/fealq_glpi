<?php

require_once __DIR__ . '/event_alerts.php';
require_once __DIR__ . '/sla_monitor.php';
require_once __DIR__ . '/messaging/notification_dispatcher.php';

/**
 * SolutionWorker (PLAN-20260702-007): notifica o solicitante via WhatsApp quando
 * um chamado é movido para "Solucionado" (status = 5), pedindo confirmação.
 *
 * Fonte de telefone (decisão registrada no plano): glpi_users.mobile/phone/phone2,
 * nessa ordem de preferência. Sem telefone disponível → evento não é despachado
 * nem marcado como enviado (permanece candidato até sair da janela de tempo).
 */

const DASHGLPI_SOLUTION_EVENT_TYPE = 'solution_pending';

/**
 * PLAN-20260709-019 (Fase E): event_type namespaceado por objeto — 'solution_pending'
 * para chamados (dedupe antigo preservado), ':problem'/':change' para os demais,
 * evitando colisão de ids entre tabelas (§4.5 do plano).
 */
function dashglpi_solution_worker_event_type(string $typeKey): string
{
    return $typeKey === 'ticket'
        ? DASHGLPI_SOLUTION_EVENT_TYPE
        : DASHGLPI_SOLUTION_EVENT_TYPE . ':' . $typeKey;
}

/**
 * @return array{scanned:int,dispatched:int,errors:string[]}
 */
function dashglpi_solution_worker_run(bool $dryRun = false): array
{
    dashglpi_event_alerts_ensure_table();

    $config = dashglpi_alerting_config();
    $dispatcher = new DashglpiNotificationDispatcher();

    $result = ['scanned' => 0, 'dispatched' => 0, 'errors' => []];

    foreach (dashglpi_itil_type_keys() as $typeKey) {
        $eventType = dashglpi_solution_worker_event_type($typeKey);
        $candidates = dashglpi_solution_worker_candidates($typeKey);
        $result['scanned'] += count($candidates);

        foreach ($candidates as $ticket) {
            $ticketId = (int) $ticket['ticket_id'];

            if (dashglpi_event_alert_already_sent($eventType, $ticketId, 'info')) {
                continue;
            }

            $phone = dashglpi_solution_worker_requester_phone($ticket);
            if ($phone === '') {
                // Sem telefone confiável para o solicitante — não despacha nem registra
                // (decisão do plano: sem fallback além de mobile/phone/phone2).
                continue;
            }

            $alert = dashglpi_solution_worker_build_alert($ticket, $typeKey);

            if ($dryRun) {
                continue;
            }

            $customConfig = $config;
            $customConfig['whatsapp']['enabled'] = true;
            $customConfig['whatsapp']['recipients'] = [$phone];

            $dispatch = $dispatcher->sendTo('whatsapp', $alert, $customConfig);
            if (!empty($dispatch['ok'])) {
                dashglpi_event_alert_record($eventType, $ticketId, 'info', ['whatsapp']);
                $result['dispatched']++;
            } elseif (empty($dispatch['skipped']) && !empty($dispatch['error'])) {
                $result['errors'][] = 'Ticket #' . $ticketId . ' [' . $typeKey . ']: ' . $dispatch['error'];
            }
        }
    }

    return $result;
}

function dashglpi_solution_worker_candidates(string $typeKey = 'ticket'): array
{
    $type = dashglpi_itil_type($typeKey) ?? dashglpi_itil_type('ticket');
    $eventType = dashglpi_solution_worker_event_type((string) $type['key']);

    return dashglpi_fetch_all(
        "SELECT t.id AS ticket_id, t.name AS ticket_name, t.entities_id, t.date_mod,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name,
                COALESCE(c.completename, c.name, '') AS category_name,
                u.mobile, u.phone, u.phone2
         FROM {$type['table']} t
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
         LEFT JOIN glpi_users u ON u.id = t.users_id_recipient
         WHERE t.status = 5
           AND t.is_deleted = 0
           AND t.date_mod >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
           AND NOT EXISTS (
               SELECT 1 FROM glpi_plugin_dashglpi_event_alerts ea
               WHERE ea.event_type = ? AND ea.event_id = t.id AND ea.level = 'info'
           )
         ORDER BY t.date_mod ASC
         LIMIT 200",
        [$eventType]
    );
}

/** Preferência: mobile (WhatsApp) > phone > phone2. Retorna só dígitos, ou ''. */
function dashglpi_solution_worker_requester_phone(array $ticket): string
{
    foreach (['mobile', 'phone', 'phone2'] as $field) {
        $digits = preg_replace('/\D+/', '', (string) ($ticket[$field] ?? ''));
        if ($digits !== '' && strlen($digits) >= 10) {
            return $digits;
        }
    }

    return '';
}

function dashglpi_solution_worker_build_alert(array $ticket, string $typeKey = 'ticket'): array
{
    $type = dashglpi_itil_type($typeKey) ?? dashglpi_itil_type('ticket');
    $ticketId = (int) ($ticket['ticket_id'] ?? 0);
    $isTicket = (string) $type['key'] === 'ticket';
    $solvedLabel = $isTicket
        ? 'Chamado solucionado'
        : $type['label'] . ' solucionad' . ($type['article'] ?? 'o');

    return [
        'ticket_id' => $ticketId,
        'title' => $solvedLabel . ': #' . $ticketId . ' — ' . (string) ($ticket['ticket_name'] ?? ''),
        'category' => (string) ($ticket['category_name'] ?? ''),
        'entity_name' => (string) ($ticket['entity_name'] ?? ''),
        'entities_id' => (int) ($ticket['entities_id'] ?? 0),
        'minutes_overdue' => max(0, (int) ((strtotime('now') - strtotime((string) $ticket['date_mod'])) / 60)),
        'threshold' => 0,
        'level' => 'info',
        'note' => 'Por favor, confirme se o problema foi resolvido.',
        // Rotulado no canal como "Solucionado em" — date_mod é o evento de solução, não a
        // criação do chamado (ver PLAN-20260704-014).
        'created_at' => (string) ($ticket['date_mod'] ?? ''),
        'created_at_formatted' => dashglpi_format_event_datetime($ticket['date_mod'] ?? null),
        'event_at_label' => 'Solucionado em',
        'url' => $isTicket ? dashglpi_sla_monitor_ticket_url($ticketId) : dashglpi_itil_glpi_url($type, $ticketId),
    ];
}

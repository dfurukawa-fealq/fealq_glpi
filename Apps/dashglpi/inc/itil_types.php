<?php

/**
 * Registry central dos objetos ITIL suportados (PLAN-20260709-019, Fase A).
 *
 * Única fonte de verdade para tabelas, atores, status e capacidades de
 * Chamado/Problema/Mudança — nenhuma query nova fora daqui pode hardcodear
 * nomes de tabela desses objetos. "Mudança" é exibida como "Manutenção"
 * (Decisão 4 do plano: termo usado pela operação; o GLPI nativo mantém o seu).
 *
 * Schema validado contra o banco real (GLPI 11.0.7) em 2026-07-09:
 * - atores têm nomenclatura assimétrica (glpi_groups_problems vs glpi_changes_groups);
 * - Problema/Mudança NÃO têm SLA nativo (sem slas_id_tto/ttr nem takeintoaccountdate) —
 *   apenas time_to_resolve (Decisão 1: usar quando preenchido, sem escalonamento).
 */

function dashglpi_itil_types(): array
{
    static $types = null;
    if ($types !== null) {
        return $types;
    }

    $types = [
        'ticket' => [
            'key' => 'ticket',
            'glpi_itemtype' => 'Ticket',
            'task_itemtype' => 'TicketTask',
            'label' => 'Chamado',
            'label_plural' => 'Chamados',
            'article' => 'o',
            'table' => 'glpi_tickets',
            'fk' => 'tickets_id',
            'user_link_table' => 'glpi_tickets_users',
            'group_link_table' => 'glpi_groups_tickets',
            'task_table' => 'glpi_tickettasks',
            'status_labels' => [
                1 => 'Novo',
                2 => 'Em Atendimento',
                3 => 'Planejado',
                4 => 'Pendente',
                5 => 'Solucionado',
                6 => 'Fechado',
            ],
            // Status fora de 1-6 não existem em chamado; mapa vazio = usa o próprio status.
            'kanban_status_map' => [],
            'has_sla' => true,
            'has_satisfaction' => true,
            'has_validation' => true,
            'has_ticket_type' => true,
            'form_path' => '/front/ticket.form.php',
            'right' => 'ticket',
        ],
        'problem' => [
            'key' => 'problem',
            'glpi_itemtype' => 'Problem',
            'task_itemtype' => 'ProblemTask',
            'label' => 'Problema',
            'label_plural' => 'Problemas',
            'article' => 'o',
            'table' => 'glpi_problems',
            'fk' => 'problems_id',
            'user_link_table' => 'glpi_problems_users',
            'group_link_table' => 'glpi_groups_problems',
            'task_table' => 'glpi_problemtasks',
            'status_labels' => [
                1 => 'Novo',
                7 => 'Aceito',
                2 => 'Em Atendimento',
                3 => 'Planejado',
                4 => 'Pendente',
                8 => 'Sob observação',
                5 => 'Solucionado',
                6 => 'Fechado',
            ],
            // Status próprios do objeto → coluna kanban mais próxima (risco §9 do plano:
            // nunca reutilizar cegamente o mapa de chamado).
            'kanban_status_map' => [7 => 1, 8 => 4],
            'has_sla' => false,
            'has_satisfaction' => false,
            'has_validation' => false,
            'has_ticket_type' => false,
            'form_path' => '/front/problem.form.php',
            'right' => 'problem',
        ],
        'change' => [
            'key' => 'change',
            'glpi_itemtype' => 'Change',
            'task_itemtype' => 'ChangeTask',
            // Decisão 4: rótulo da operação; o objeto GLPI continua sendo "Mudança".
            'label' => 'Mudança',
            'label_plural' => 'Mudanças',
            'article' => 'a',
            'table' => 'glpi_changes',
            'fk' => 'changes_id',
            'user_link_table' => 'glpi_changes_users',
            'group_link_table' => 'glpi_changes_groups',
            'task_table' => 'glpi_changetasks',
            'status_labels' => [
                1 => 'Novo',
                9 => 'Avaliação',
                10 => 'Aprovação',
                7 => 'Aceito',
                2 => 'Em Atendimento',
                3 => 'Planejado',
                4 => 'Pendente',
                11 => 'Teste',
                12 => 'Qualificação',
                8 => 'Sob observação',
                5 => 'Solucionado',
                6 => 'Fechado',
            ],
            'kanban_status_map' => [7 => 1, 8 => 4, 9 => 3, 10 => 3, 11 => 2, 12 => 2],
            'has_sla' => false,
            'has_satisfaction' => false,
            'has_validation' => true,
            'has_ticket_type' => false,
            'form_path' => '/front/change.form.php',
            'right' => 'change',
        ],
    ];

    return $types;
}

function dashglpi_itil_type_keys(): array
{
    return array_keys(dashglpi_itil_types());
}

function dashglpi_itil_type(string $key): ?array
{
    return dashglpi_itil_types()[$key] ?? null;
}

/** Whitelist estrita de itemtype vindo de request; default 'ticket' (comportamento atual). */
function dashglpi_itil_normalize_type($value): string
{
    $key = strtolower(trim((string) $value));
    return isset(dashglpi_itil_types()[$key]) ? $key : 'ticket';
}

function dashglpi_itil_status_label(array $type, int $status): string
{
    return $type['status_labels'][$status] ?? 'Outro';
}

/** Status "aberto" = tudo que não está solucionado/fechado (vale para os 3 objetos). */
function dashglpi_itil_open_statuses(array $type): array
{
    return array_values(array_filter(
        array_keys($type['status_labels']),
        static fn(int $status): bool => !in_array($status, [5, 6], true)
    ));
}

/** Coluna kanban (1-6) para um status do objeto; status extras mapeiam pelo registry. */
function dashglpi_itil_kanban_status(array $type, int $status): int
{
    $mapped = $type['kanban_status_map'][$status] ?? $status;
    return in_array($mapped, [1, 2, 3, 4, 5, 6], true) ? $mapped : 1;
}

/** URL do objeto no GLPI nativo (problem.form.php / change.form.php / ticket.form.php). */
function dashglpi_itil_glpi_url(array $type, int $id): string
{
    $base = rtrim((string) dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
    if ($base === '') {
        return '';
    }

    return $base . $type['form_path'] . '?id=' . $id;
}

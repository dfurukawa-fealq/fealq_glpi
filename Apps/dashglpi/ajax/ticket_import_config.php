<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/admin_batch_lib.php';

plugin_dashglpi_admin_bridge_handle('ticket_import', function (array $payload): array {
    $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_ticket_import_save', 'ticket');

    return [
        'summary' => $result['summary'],
        'items' => $result['items'],
        'message' => 'Tickets processados no GLPI.',
    ];
}, 'Erro interno no bridge de importacao de tickets.');

function plugin_dashglpi_ticket_import_save(array $item): array
{
    $title = plugin_dashglpi_admin_bridge_name((string) ($item['title'] ?? ''), 'Informe o titulo do ticket.');
    $externalId = plugin_dashglpi_ticket_import_clean((string) ($item['external_id'] ?? ''));

    $existing = null;
    if ($externalId !== '') {
        $existing = plugin_dashglpi_admin_bridge_find_one(Ticket::class, ['externalid' => $externalId, 'is_deleted' => 0]);
    }
    if (!$existing) {
        $existing = plugin_dashglpi_admin_bridge_find_one(Ticket::class, ['name' => $title, 'is_deleted' => 0]);
    }

    $entitiesId = plugin_dashglpi_ticket_import_entity_id((string) ($item['entity'] ?? ''));
    $requesterId = plugin_dashglpi_ticket_import_user_id((string) ($item['requester'] ?? ''), true);
    $technicianId = plugin_dashglpi_ticket_import_user_id((string) ($item['technician'] ?? ''), false);
    $input = plugin_dashglpi_ticket_import_input($item, $title, $externalId, $entitiesId, $requesterId, $technicianId);

    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);

    $ticket = new Ticket();
    if ($existing) {
        $id = (int) $existing['id'];
        if (!$ticket->update(['id' => $id] + $input)) {
            throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao atualizar ticket "' . $title . '".'));
        }
        plugin_dashglpi_ticket_import_after_save($id, $item, $requesterId, $technicianId);

        return [
            'id' => $id,
            'title' => $title,
            'source_line' => (int) ($item['source_line'] ?? 0),
            'status' => 'updated',
            'entities_id' => $entitiesId,
        ];
    }

    $id = (int) $ticket->add($input);
    if ($id <= 0) {
        throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao criar ticket "' . $title . '".'));
    }
    plugin_dashglpi_ticket_import_after_save($id, $item, $requesterId, $technicianId);

    return [
        'id' => $id,
        'title' => $title,
        'source_line' => (int) ($item['source_line'] ?? 0),
        'status' => 'created',
        'entities_id' => $entitiesId,
    ];
}

function plugin_dashglpi_ticket_import_input(array $item, string $title, string $externalId, int $entitiesId, int $requesterId, int $technicianId): array
{
    $urgency = plugin_dashglpi_ticket_import_level((string) ($item['urgency'] ?? $item['priority'] ?? ''), 3);
    $impact = plugin_dashglpi_ticket_import_level((string) ($item['impact'] ?? ''), 3);
    $priority = plugin_dashglpi_ticket_import_level((string) ($item['priority'] ?? ''), Ticket::computePriority($urgency, $impact));

    $input = [
        'name' => $title,
        'content' => plugin_dashglpi_ticket_import_content($item),
        'entities_id' => $entitiesId,
        'type' => plugin_dashglpi_ticket_import_type((string) ($item['type'] ?? '')),
        'urgency' => $urgency,
        'impact' => $impact,
        'priority' => $priority,
        'status' => plugin_dashglpi_ticket_import_status((string) ($item['status'] ?? '')),
        'itilcategories_id' => plugin_dashglpi_ticket_import_category_id((string) ($item['category'] ?? ''), $entitiesId),
        'users_id_recipient' => $requesterId,
        'users_id_lastupdater' => $requesterId,
        '_users_id_requester' => $requesterId,
        'users_id' => $requesterId,
    ];

    if ($externalId !== '') {
        $input['externalid'] = $externalId;
    }
    if ($technicianId > 0) {
        $input['_users_id_assign'] = $technicianId;
    }

    $openedAt = plugin_dashglpi_ticket_import_date((string) ($item['opened_at'] ?? ''), true);
    if ($openedAt !== '') {
        $input['date'] = $openedAt;
    }

    $lastUpdate = plugin_dashglpi_ticket_import_date((string) ($item['last_update'] ?? ''), true);
    if ($lastUpdate !== '') {
        $input['date_mod'] = $lastUpdate;
    }

    return $input;
}

function plugin_dashglpi_ticket_import_after_save(int $ticketId, array $item, int $requesterId, int $technicianId): void
{
    plugin_dashglpi_ticket_import_sync_user($ticketId, $requesterId, Ticket_User::REQUESTER);
    plugin_dashglpi_ticket_import_sync_user($ticketId, $technicianId, Ticket_User::ASSIGN);

    $fields = [];
    $openedAt = plugin_dashglpi_ticket_import_date((string) ($item['opened_at'] ?? ''), true);
    if ($openedAt !== '') {
        $fields['date'] = $openedAt;
    }

    $lastUpdate = plugin_dashglpi_ticket_import_date((string) ($item['last_update'] ?? ''), true);
    if ($lastUpdate !== '') {
        $fields['date_mod'] = $lastUpdate;
    }

    if (!$fields) {
        return;
    }

    global $DB;
    if (isset($DB) && method_exists($DB, 'update')) {
        $DB->update('glpi_tickets', $fields, ['id' => $ticketId]);
    }
}
function plugin_dashglpi_ticket_import_content(array $item): string
{
    $content = plugin_dashglpi_ticket_import_clean((string) ($item['content'] ?? ''));
    $lines = [];
    if ($content !== '') {
        $lines[] = $content;
    } else {
        $lines[] = 'Ticket importado via CSV pelo DashGLPI.';
    }

    $labels = [
        'external_id' => 'ID origem',
        'status' => 'Status',
        'last_update' => 'Ultima atualizacao',
        'opened_at' => 'Data de abertura',
        'priority' => 'Prioridade',
        'urgency' => 'Urgencia',
        'impact' => 'Impacto',
        'type' => 'Tipo',
        'entity' => 'Entidade',
        'requester' => 'Requerente',
        'technician' => 'Tecnico',
        'category' => 'Categoria',
    ];

    foreach ($labels as $field => $label) {
        $value = plugin_dashglpi_ticket_import_clean((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    return implode("\n", array_unique($lines));
}

function plugin_dashglpi_ticket_import_type(string $value): int
{
    $key = plugin_dashglpi_ticket_import_key($value);
    return in_array($key, ['requisicao', 'request', 'demand', 'demanda'], true)
        ? Ticket::DEMAND_TYPE
        : Ticket::INCIDENT_TYPE;
}

function plugin_dashglpi_ticket_import_status(string $value): int
{
    $key = plugin_dashglpi_ticket_import_key($value);
    return match ($key) {
        'novo', 'new', 'aberto', 'incoming' => Ticket::INCOMING,
        'processando atribuido', 'processando (atribuido)', 'atribuido', 'atribuído', 'assigned', 'em atendimento' => Ticket::ASSIGNED,
        'processando planejado', 'processando (planejado)', 'planejado', 'planned' => Ticket::PLANNED,
        'pendente', 'em espera', 'waiting' => Ticket::WAITING,
        'solucionado', 'resolvido', 'solved' => Ticket::SOLVED,
        'fechado', 'closed' => Ticket::CLOSED,
        default => Ticket::INCOMING,
    };
}

function plugin_dashglpi_ticket_import_level(string $value, int $default): int
{
    $key = plugin_dashglpi_ticket_import_key($value);
    if ($key !== '' && ctype_digit($key)) {
        $number = (int) $key;
        return ($number >= 1 && $number <= 5) ? $number : $default;
    }

    return match ($key) {
        'muito alta', 'very high' => 5,
        'alta', 'high' => 4,
        'media', 'medio', 'normal', 'medium' => 3,
        'baixa', 'low' => 2,
        'muito baixa', 'very low' => 1,
        default => $default,
    };
}

function plugin_dashglpi_ticket_import_entity_id(string $value): int
{
    $value = plugin_dashglpi_ticket_import_clean($value);
    if ($value === '') {
        return 0;
    }

    foreach (['completename', 'name'] as $field) {
        $entity = plugin_dashglpi_admin_bridge_find_one(Entity::class, [$field => $value]);
        if ($entity) {
            return (int) $entity['id'];
        }
    }

    return 0;
}

function plugin_dashglpi_ticket_import_user_id(string $value, bool $required): int
{
    $value = plugin_dashglpi_ticket_import_clean($value);
    if ($value !== '') {
        foreach (['name', 'firstname', 'realname'] as $field) {
            $user = plugin_dashglpi_admin_bridge_find_one(User::class, [$field => $value, 'is_deleted' => 0]);
            if ($user) {
                return (int) $user['id'];
            }
        }
    }

    if (!$required) {
        return 0;
    }

    $fallback = plugin_dashglpi_admin_bridge_find_one(User::class, ['is_active' => 1, 'is_deleted' => 0]);
    $id = (int) ($fallback['id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Solicitante do ticket nao encontrado. Informe a coluna Requerente ou cadastre um usuario ativo.');
    }

    return $id;
}

function plugin_dashglpi_ticket_import_category_id(string $value, int $entitiesId): int
{
    $value = plugin_dashglpi_ticket_import_clean($value);
    if ($value === '') {
        return 0;
    }

    foreach (['completename', 'name'] as $field) {
        $category = plugin_dashglpi_admin_bridge_find_one(ITILCategory::class, [$field => $value]);
        if ($category) {
            return (int) $category['id'];
        }
    }

    $category = new ITILCategory();
    $id = (int) $category->add([
        'name' => $value,
        'entities_id' => $entitiesId,
        'is_recursive' => 1,
    ]);

    return $id > 0 ? $id : 0;
}

function plugin_dashglpi_ticket_import_sync_user(int $ticketId, int $userId, int $type): void
{
    if ($ticketId <= 0 || $userId <= 0) {
        return;
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Ticket_User::class, [
        'tickets_id' => $ticketId,
        'users_id' => $userId,
        'type' => $type,
    ]);
    if ($existing) {
        return;
    }

    $relation = new Ticket_User();
    $relation->add([
        'tickets_id' => $ticketId,
        'users_id' => $userId,
        'type' => $type,
        'use_notification' => 0,
    ]);
}

function plugin_dashglpi_ticket_import_date(string $value, bool $withTime): string
{
    $value = plugin_dashglpi_ticket_import_clean($value);
    if ($value === '') {
        return '';
    }

    $formats = $withTime
        ? ['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y']
        : ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return $withTime ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d', $timestamp);
}

function plugin_dashglpi_ticket_import_clean(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function plugin_dashglpi_ticket_import_key(string $value): string
{
    $value = plugin_dashglpi_ticket_import_clean($value);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $ascii !== false ? $ascii : $value;
    return strtolower($value);
}
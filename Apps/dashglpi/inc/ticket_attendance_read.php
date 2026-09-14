<?php

// Leitura limitada e contratos de apresentação do atendimento (PLAN-20260905-001).
const DASHGLPI_ATTENDANCE_PAGE_SIZE = 30;
const DASHGLPI_ATTENDANCE_PAGE_MAX = 100;
const DASHGLPI_ATTENDANCE_CATALOG_MAX = 100;

function dashglpi_attendance_revision(Ticket $ticket): string
{
    global $DB;
    // Inclui relações para detectar alterações que ocorreram no mesmo segundo.
    $data = $ticket->fields;
    foreach ([CommonITILActor::REQUESTER, CommonITILActor::OBSERVER, CommonITILActor::ASSIGN] as $role) {
        $data['actors_' . $role] = $ticket->getActorsForType($role);
    }
    foreach ([ITILFollowup::class, TicketTask::class, ITILSolution::class] as $class) {
        $where = $class === TicketTask::class ? ['tickets_id' => $ticket->getID()] : ['itemtype' => 'Ticket', 'items_id' => $ticket->getID()];
        $data['last_' . $class] = $DB->request(['SELECT' => ['id', 'date_mod'], 'FROM' => $class::getTable(),
            'WHERE' => $where, 'ORDERBY' => ['id DESC'], 'LIMIT' => 1])->current() ?: null;
    }
    return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dashglpi_attendance_documents(Ticket $ticket, ?array $references = null, int $documentId = 0): array
{
    global $DB;
    $where = [$ticket->getAssociatedDocumentsCriteria(), 'glpi_documents.is_deleted' => 0];
    if ($documentId > 0) {
        $where['glpi_documents.id'] = $documentId;
    }
    if ($references !== null) {
        $where[] = ['OR' => $references ?: [['glpi_documents_items.id' => -1]]];
    }
    $rows = $DB->request([
        'SELECT' => ['glpi_documents.id', 'glpi_documents.name', 'glpi_documents.filename', 'glpi_documents.mime',
            'glpi_documents_items.itemtype', 'glpi_documents_items.items_id'],
        'FROM' => 'glpi_documents_items',
        'INNER JOIN' => ['glpi_documents' => ['FKEY' => ['glpi_documents_items' => 'documents_id', 'glpi_documents' => 'id']]],
        'WHERE' => $where, 'ORDERBY' => ['glpi_documents.id', 'glpi_documents_items.id'], 'LIMIT' => 101,
    ]);
    $documents = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $documents[] = ['id' => $id, 'name' => $row['filename'] ?: $row['name'],
            'is_image' => in_array(strtolower($row['mime']), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true),
            'itemtype' => $row['itemtype'], 'items_id' => (int) $row['items_id']];
    }
    return $documents;
}

function dashglpi_attendance_detail(Ticket $ticket): array
{
    $fields = array_intersect_key($ticket->fields, array_flip(['id', 'name', 'status', 'date', 'date_mod', 'solvedate',
        'closedate', 'time_to_resolve', 'time_to_own', 'entities_id', 'type', 'itilcategories_id', 'urgency', 'impact', 'priority', 'requesttypes_id']));
    $fields['status_label'] = Ticket::getStatus((int) $ticket->fields['status']);
    $fields['itemtype'] = 'ticket';
    $fields['capabilities'] = dashglpi_attendance_capabilities($ticket);
    $fields['revision'] = dashglpi_attendance_revision($ticket);
    $fields['current_user_id'] = (int) Session::getLoginUserID();
    $fields['category'] = Dropdown::getDropdownName('glpi_itilcategories', (int) $ticket->fields['itilcategories_id']) ?: 'Sem categoria';
    $fields['entity_name'] = Dropdown::getDropdownName('glpi_entities', (int) $ticket->fields['entities_id']);
    $fields['origin_name'] = Dropdown::getDropdownName('glpi_requesttypes', (int) $ticket->fields['requesttypes_id']);
    foreach (['requester' => CommonITILActor::REQUESTER, 'observer' => CommonITILActor::OBSERVER, 'assign' => CommonITILActor::ASSIGN] as $name => $role) {
        $fields['actors'][$name] = $ticket->getActorsForType($role);
    }
    $fields['allowed_statuses'] = [];
    if ($fields['capabilities']['status']) {
        foreach (Ticket::getAllowedStatusArray($ticket->fields['status']) as $id => $label) {
            // Solução e aprovação têm ações próprias, preservando seus efeitos nativos.
            if (in_array((int) $id, [Ticket::SOLVED, Ticket::CLOSED], true) && (int) $id !== (int) $ticket->fields['status']) {
                continue;
            }
            $fields['allowed_statuses'][] = ['id' => (int) $id, 'name' => $label];
        }
    }
    $docs = dashglpi_attendance_documents($ticket, [['glpi_documents_items.itemtype' => 'Ticket', 'glpi_documents_items.items_id' => $ticket->getID()]]);
    $fields['documents_truncated'] = count($docs) > 100;
    $fields['documents'] = array_slice($docs, 0, 100);
    $fields += dashglpi_attendance_content((string) $ticket->fields['content'], $ticket->getID(), array_column($docs, null, 'id'));
    // Campo legado permanece texto; somente o contrato sanitizado pode entrar no DOM HTML.
    $fields['content'] = $fields['content_text'];
    $fields['content_edit'] = $fields['capabilities']['edit'] && !$fields['content_truncated'] ? (string) $ticket->fields['content'] : null;
    $fields['upload_max_label'] = Document::getMaxUploadSize();
    return $fields;
}

/** Filtro equivalente à visibilidade nativa, aplicado antes da paginação. */
function dashglpi_attendance_event_scope(string $class): array
{
    if ($class === ITILSolution::class) {
        return [];
    }
    $userId = (int) Session::getLoginUserID();
    $central = Session::getCurrentInterface() === 'central';
    if ($central && Session::haveRight($class::$rightname, $class::SEEPRIVATE)) {
        return [];
    }
    $or = [];
    if (Session::haveRight($class::$rightname, $class::SEEPUBLIC)) {
        $or[] = ['e.is_private' => 0];
    }
    if ($central) {
        $or[] = ['e.users_id' => $userId];
        if ($class === TicketTask::class) {
            $or[] = ['e.users_id_tech' => $userId];
            if (Session::haveRight($class::$rightname, CommonITILTask::SEEPRIVATEGROUPS) && !empty($_SESSION['glpigroups'])) {
                $or[] = ['e.groups_id_tech' => $_SESSION['glpigroups']];
            }
        }
    }
    return ['OR' => $or ?: [['e.id' => -1]]];
}

function dashglpi_attendance_timeline(Ticket $ticket, array $payload): array
{
    global $DB;
    $limit = min(DASHGLPI_ATTENDANCE_PAGE_MAX, max(1, (int) ($payload['limit'] ?? DASHGLPI_ATTENDANCE_PAGE_SIZE)));
    $cursor = null;
    if (!empty($payload['cursor'])) {
        $cursor = json_decode(base64_decode((string) $payload['cursor'], true) ?: '', true);
        if (!is_array($cursor) || count($cursor) !== 3 || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($cursor[0] ?? ''))
            || !in_array($cursor[1], ['ITILFollowup', 'TicketTask', 'ITILSolution'], true) || !is_int($cursor[2]) || $cursor[2] <= 0) {
            throw new RuntimeException('Página de histórico inválida.', 400);
        }
    }
    $events = [];
    foreach ([ITILFollowup::class, TicketTask::class, ITILSolution::class] as $class) {
        if (!$class::canView()) {
            continue;
        }
        $where = $class === TicketTask::class ? ['e.tickets_id' => $ticket->getID()] : ['e.itemtype' => 'Ticket', 'e.items_id' => $ticket->getID()];
        $eventScope = dashglpi_attendance_event_scope($class);
        if ($eventScope) {
            $where[] = $eventScope;
        }
        if ($cursor) {
            $sameDate = ['e.date_creation' => $cursor[0]];
            if ($class === $cursor[1]) {
                $sameDate['e.id'] = ['<', $cursor[2]];
            } elseif (strcmp($class, $cursor[1]) > 0) {
                $sameDate['e.id'] = -1;
            }
            $where[] = ['OR' => [['e.date_creation' => ['<', $cursor[0]]], $sameDate]];
        }
        $rows = $DB->request(['SELECT' => ['e.*', 'u.name AS author_login', 'u.firstname AS author_firstname', 'u.realname AS author_realname'],
            'FROM' => $class::getTable() . ' AS e', 'LEFT JOIN' => ['glpi_users AS u' => ['FKEY' => ['e' => 'users_id', 'u' => 'id']]],
            'WHERE' => $where, 'ORDERBY' => ['e.date_creation DESC', 'e.id DESC'], 'LIMIT' => $limit + 1]);
        foreach ($rows as $row) {
            $events[] = ['id' => (int) $row['id'], 'itemtype' => $class, 'date' => (string) $row['date_creation'],
                'is_private' => (int) ($row['is_private'] ?? 0), 'content' => (string) $row['content'],
                'author' => trim(($row['author_firstname'] ?? '') . ' ' . ($row['author_realname'] ?? '')) ?: $row['author_login'],
                'duration' => (int) ($row['actiontime'] ?? 0), 'state' => (int) ($row['state'] ?? 0)];
        }
    }
    usort($events, static fn(array $a, array $b): int => [$b['date'], $b['itemtype'], $b['id']] <=> [$a['date'], $a['itemtype'], $a['id']]);
    $more = count($events) > $limit;
    $events = array_slice($events, 0, $limit);
    $references = array_map(static fn(array $e): array => ['glpi_documents_items.itemtype' => $e['itemtype'], 'glpi_documents_items.items_id' => $e['id']], $events);
    $docs = $events ? dashglpi_attendance_documents($ticket, $references) : [];
    foreach ($events as &$event) {
        $event['documents'] = array_values(array_filter($docs, static fn(array $d): bool => $d['itemtype'] === $event['itemtype'] && $d['items_id'] === $event['id']));
        $event += dashglpi_attendance_content($event['content'], $ticket->getID(), array_column($event['documents'], null, 'id'));
        $event['content'] = $event['content_text'];
    }
    unset($event);
    $last = $events ? $events[count($events) - 1] : null;
    return ['followups' => $events, 'next_cursor' => $more && $last ? base64_encode(json_encode([$last['date'], $last['itemtype'], $last['id']])) : null,
        'documents_truncated' => count($docs) > 100, 'upload_max_label' => Document::getMaxUploadSize()];
}

function dashglpi_attendance_catalog(Ticket $ticket, array $payload): array
{
    global $DB;
    if (dashglpi_attendance_capabilities($ticket)['mode'] !== 'operator') {
        throw new RuntimeException('Catálogo restrito ao atendimento.', 403);
    }
    $entityId = (int) $ticket->fields['entities_id'];
    $query = mb_substr(trim((string) ($payload['q'] ?? '')), 0, 100);
    $catalog = [];
    foreach (['users' => 'all', 'technicians' => 'own_ticket'] as $key => $right) {
        $catalog[$key] = [];
        foreach (User::getSqlSearchResult(false, $right, $entityId, 0, [], $query, 0, DASHGLPI_ATTENDANCE_CATALOG_MAX + 1) as $row) {
            $catalog[$key][] = ['id' => (int) $row['id'], 'name' => trim($row['firstname'] . ' ' . $row['realname']) ?: $row['name']];
        }
    }
    foreach (['categories' => ITILCategory::class, 'groups' => Group::class, 'solutiontypes' => SolutionType::class,
        'taskcategories' => TaskCategory::class, 'pendingreasons' => PendingReason::class] as $key => $class) {
        $item = new $class();
        $where = $item->isEntityAssign() ? getEntitiesRestrictCriteria($class::getTable(), '', $entityId, $item->maybeRecursive()) : [];
        if ($key === 'groups') {
            $where['is_assign'] = 1;
        }
        if ($query !== '') {
            $where['name'] = ['LIKE', '%' . $query . '%'];
        }
        $catalog[$key] = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $class::getTable(), 'WHERE' => $where,
            'ORDERBY' => 'name', 'LIMIT' => DASHGLPI_ATTENDANCE_CATALOG_MAX + 1]) as $row) {
            $catalog[$key][] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }
    }
    $catalog['has_more'] = [];
    foreach (array_keys($catalog) as $key) {
        if ($key === 'has_more') {
            continue;
        }
        $catalog['has_more'][$key] = count($catalog[$key]) > DASHGLPI_ATTENDANCE_CATALOG_MAX;
        $catalog[$key] = array_slice($catalog[$key], 0, DASHGLPI_ATTENDANCE_CATALOG_MAX);
    }
    return ['catalog' => $catalog];
}

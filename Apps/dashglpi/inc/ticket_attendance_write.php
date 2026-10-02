<?php

// Escritas pelas classes GLPI, com ator real e controle de concorrência (PLAN-20260905-001).

/** Bloqueio de linha também serializa atualizações feitas pelo GLPI nativo. */
function dashglpi_attendance_mutate(Ticket $ticket, array $payload, callable $handler): array
{
    global $DB;
    $DB->beginTransaction();
    try {
        $id = (int) $ticket->getID();
        $statement = $DB->prepare('SELECT id FROM glpi_tickets WHERE id = ? FOR UPDATE');
        $statement->bind_param('i', $id);
        $statement->execute();
        $statement->get_result()->free();
        $statement->close();
        $ticket = new Ticket();
        if (!$ticket->getFromDB($id) || $ticket->isDeleted() || !$ticket->can($id, READ)) {
            throw new RuntimeException('Chamado indisponível para atendimento.', 403);
        }
        if (isset($payload['revision']) && !hash_equals(dashglpi_attendance_revision($ticket), (string) $payload['revision'])) {
            throw new RuntimeException('O chamado foi alterado. Recarregue os dados antes de salvar; seu texto foi preservado.', 409);
        }
        $result = $handler($ticket);
        $DB->commit();
        return $result;
    } catch (Throwable $e) {
        $DB->rollBack();
        throw $e;
    }
}

function dashglpi_attendance_validate_item(string $class, int $id, Ticket $ticket, bool $technician = false): void
{
    global $DB;
    if ($id <= 0) {
        throw new RuntimeException('Selecione um valor válido.', 422);
    }
    $item = new $class();
    if (!$item->getFromDB($id) || !empty($item->fields['is_deleted'])) {
        throw new RuntimeException('Valor selecionado não está disponível.', 422);
    }
    $entityId = (int) $ticket->fields['entities_id'];
    if ($class === User::class) {
        $now = date('Y-m-d H:i:s');
        if (!$item->fields['is_active'] || (!empty($item->fields['begin_date']) && $item->fields['begin_date'] > $now)
            || (!empty($item->fields['end_date']) && $item->fields['end_date'] < $now)) {
            throw new RuntimeException('Usuário indisponível para atribuição.', 422);
        }
        $scope = getEntitiesRestrictCriteria('glpi_profiles_users', '', $entityId, true);
        $rows = $DB->request(['COUNT' => 'cpt', 'FROM' => 'glpi_profiles_users', 'WHERE' => ['users_id' => $id] + $scope]);
        if (!(int) ($rows->current()['cpt'] ?? 0) || ($technician && !$item->hasRight('ticket', Ticket::OWN, $entityId))) {
            throw new RuntimeException('Usuário sem atribuição permitida nesta entidade.', 403);
        }
    } elseif ($item->isEntityAssign()) {
        $scope = getEntitiesRestrictCriteria($class::getTable(), '', $entityId, $item->maybeRecursive());
        $rows = $DB->request(['COUNT' => 'cpt', 'FROM' => $class::getTable(), 'WHERE' => ['id' => $id] + $scope]);
        if (!(int) ($rows->current()['cpt'] ?? 0)) {
            throw new RuntimeException('Valor fora da entidade do chamado.', 403);
        }
    }
    if ($class === Group::class && empty($item->fields['is_assign'])) {
        throw new RuntimeException('Grupo não recebe atribuições.', 422);
    }
}

function dashglpi_attendance_text(string $text): string
{
    $text = trim($text);
    if ($text === '' || strlen($text) > DASHGLPI_ATTENDANCE_CONTENT_MAX) {
        throw new RuntimeException('Preencha a descrição, com até 200 KB de texto.', 422);
    }
    return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
}

function dashglpi_attendance_upload_native(array &$input): void
{
    $files = plugin_dashglpi_admin_bridge_uploaded_files('attachments');
    if (!$files) {
        return;
    }
    plugin_dashglpi_admin_bridge_ticket_document_category_id();

    foreach ($files as $index => $file) {
        $name = substr(bin2hex(random_bytes(12)), 0, 23) . basename($file['name']);
        if (is_array($_FILES['attachments']['name'])) {
            $_FILES['attachments']['name'][$index] = $name;
        } else {
            $_FILES['attachments']['name'] = $name;
        }
    }
    foreach (GLPIUploadHandler::uploadFiles(['name' => 'attachments', 'print_response' => false]) as $group) {
        foreach ((array) $group as $file) {
            if (!empty($file->error)) {
                throw new RuntimeException((string) $file->error, 422);
            }
            if (isset($file->name)) {
                $input['_filename'][] = (string) $file->name;
                $input['_prefix_filename'][] = (string) ($file->prefix ?? '');
            }
        }
    }
    if (count($input['_filename'] ?? []) !== count($files)) {
        throw new RuntimeException('Nem todos os anexos foram recebidos pelo GLPI.', 422);
    }
}

function dashglpi_attendance_add_event(Ticket $ticket, array $payload, string $kind): array
{
    $capability = ['reply' => 'reply', 'private' => 'private', 'task' => 'task', 'solution' => 'solution'][$kind] ?? '';
    dashglpi_attendance_require_capability($ticket, $capability);
    $class = ['reply' => ITILFollowup::class, 'private' => ITILFollowup::class, 'task' => TicketTask::class, 'solution' => ITILSolution::class][$kind];
    $input = ['content' => dashglpi_attendance_text((string) ($payload['content'] ?? '')),
        'users_id' => (int) Session::getLoginUserID(), 'is_private' => $kind === 'private' ? 1 : 0];
    if ($kind === 'task') {
        $input['tickets_id'] = $ticket->getID();
        $minutes = filter_var($payload['duration_minutes'] ?? 0, FILTER_VALIDATE_INT);
        $state = filter_var($payload['state'] ?? 1, FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 0 || $minutes > 525600 || !in_array($state, [0, 1, 2], true)) {
            throw new RuntimeException('Duração ou estado da tarefa inválido.', 422);
        }
        $input['actiontime'] = $minutes * 60;
        $input['state'] = $state;
        foreach (['users_id_tech' => User::class, 'groups_id_tech' => Group::class, 'taskcategories_id' => TaskCategory::class] as $key => $targetClass) {
            $value = (int) ($payload[$key] ?? 0);
            if ($value > 0) {
                dashglpi_attendance_validate_item($targetClass, $value, $ticket, $targetClass === User::class);
            }
            $input[$key] = $value;
        }
        $begin = trim((string) ($payload['begin'] ?? ''));
        $end = trim((string) ($payload['end'] ?? ''));
        if ($begin !== '' || $end !== '') {
            $startDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $begin);
            $endDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $end);
            if (!$startDate || !$endDate || $startDate->format('Y-m-d H:i:s') !== $begin || $endDate->format('Y-m-d H:i:s') !== $end || $endDate <= $startDate
                || ($input['users_id_tech'] <= 0 && $input['groups_id_tech'] <= 0)) {
                throw new RuntimeException('Planejamento exige responsável, início e fim válidos.', 422);
            }
            $input['plan'] = ['begin' => $begin, 'end' => $end];
        }
    } else {
        $input['itemtype'] = 'Ticket';
        $input['items_id'] = $ticket->getID();
    }
    if ($kind === 'solution') {
        unset($input['is_private']);
        $input['solutiontypes_id'] = (int) ($payload['solutiontypes_id'] ?? 0);
        if ($input['solutiontypes_id'] > 0) {
            dashglpi_attendance_validate_item(SolutionType::class, $input['solutiontypes_id'], $ticket);
        }
    }
    $event = new $class();
    if (!$event->can(-1, CREATE, $input)) {
        throw new RuntimeException('Permissão insuficiente para registrar o atendimento.', 403);
    }
    dashglpi_attendance_upload_native($input);
    $id = (int) $event->add($input);
    if ($id <= 0) {
        throw new RuntimeException('GLPI não registrou o atendimento. Confira os campos obrigatórios.', 422);
    }
    return ['event' => ['id' => $id, 'itemtype' => $class], 'result' => ['ticket_id' => $ticket->getID()]];
}

function dashglpi_attendance_update(Ticket $ticket, array $payload): array
{
    $action = (string) ($payload['action'] ?? '');
    $input = ['id' => $ticket->getID()];
    if ($action === 'update_properties') {
        $changes = $payload['changes'] ?? [];
        $allowed = ['name', 'content', 'type', 'itilcategories_id', 'urgency', 'impact', 'priority'];
        if (!is_array($changes) || array_diff(array_keys($changes), $allowed)) {
            throw new RuntimeException('Propriedade não permitida.', 422);
        }
        foreach ($changes as $key => $value) {
            dashglpi_attendance_require_capability($ticket, $key === 'priority' ? 'priority' : 'edit');
            if ($key === 'name') {
                $value = trim((string) $value);
                if ($value === '' || mb_strlen($value) > 255) {
                    throw new RuntimeException('Título deve ter entre 1 e 255 caracteres.', 422);
                }
            } elseif ($key === 'content') {
                $value = dashglpi_attendance_text((string) $value);
            } else {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false || ($key === 'type' && !in_array($value, [1, 2], true))
                    || (in_array($key, ['urgency', 'impact', 'priority'], true) && ($value < 1 || $value > ($key === 'priority' ? 6 : 5)))
                    || ($key === 'itilcategories_id' && $value < 0)) {
                    throw new RuntimeException('Valor de propriedade inválido.', 422);
                }
                if ($key === 'itilcategories_id' && $value > 0) {
                    dashglpi_attendance_validate_item(ITILCategory::class, $value, $ticket);
                }
            }
            $input[$key] = $value;
        }
        if ((isset($input['urgency']) || isset($input['impact'])) && !isset($input['priority'])) {
            $input['priority'] = Ticket::computePriority($input['urgency'] ?? $ticket->fields['urgency'], $input['impact'] ?? $ticket->fields['impact']);
        }
    } elseif ($action === 'update_status') {
        dashglpi_attendance_require_capability($ticket, 'status');
        $status = (int) ($payload['status'] ?? 0);
        $knownStatuses = method_exists(Ticket::class, 'getAllStatusArray')
            ? array_map('intval', array_keys(Ticket::getAllStatusArray()))
            : [Ticket::INCOMING, Ticket::ASSIGNED, Ticket::PLANNED, Ticket::WAITING, Ticket::SOLVED, Ticket::CLOSED];
        if (!in_array($status, $knownStatuses, true) || !Ticket::isAllowedStatus($ticket->fields['status'], $status)) {
            throw new RuntimeException('Transição não permitida. Para solucionar, registre uma solução.', 422);
        }
        $input['status'] = $status;
        if ($status === Ticket::WAITING) {
            $reason = trim((string) ($payload['reason'] ?? ''));
            if ($reason === '') {
                throw new RuntimeException('Informe o motivo da pendência.', 422);
            }
            $pendingId = (int) ($payload['pendingreasons_id'] ?? 0);
            if ($pendingId > 0) {
                dashglpi_attendance_validate_item(PendingReason::class, $pendingId, $ticket);
            }
            dashglpi_attendance_require_capability($ticket, 'reply');
            $followup = new ITILFollowup();
            $followupInput = ['itemtype' => 'Ticket', 'items_id' => $ticket->getID(), 'content' => dashglpi_attendance_text($reason),
                'users_id' => (int) Session::getLoginUserID(), 'is_private' => 0];
            if ($pendingId > 0) {
                $followupInput['pending'] = 1;
                $followupInput['pendingreasons_id'] = $pendingId;
                $pending = new PendingReason();
                $pending->getFromDB($pendingId);
                $followupInput['followup_frequency'] = $pending->fields['followup_frequency'];
                $followupInput['followups_before_resolution'] = $pending->fields['followups_before_resolution'];
            }
            if (!$followup->can(-1, CREATE, $followupInput) || !$followup->add($followupInput)) {
                throw new RuntimeException('Não foi possível registrar a pendência.', 422);
            }
        }
    } elseif (in_array($action, ['assign', 'take', 'actor'], true)) {
        if ($action === 'actor' && !in_array($payload['operation'] ?? '', ['add', 'remove'], true)) {
            throw new RuntimeException('Operação de ator inválida.', 422);
        }
        $userId = (int) ($payload['user_id'] ?? 0);
        $take = $action === 'take' || ($action === 'assign' && $userId === (int) Session::getLoginUserID() && !$ticket->canAssign());
        $role = $action === 'actor' ? (string) ($payload['role'] ?? '') : 'assign';
        if (!in_array($role, ['assign', 'requester', 'observer'], true)) {
            throw new RuntimeException('Tipo de ator inválido.', 422);
        }
        dashglpi_attendance_require_capability($ticket, $take ? 'take' : ($role === 'assign' ? 'assign' : 'actors'));
        $targetClass = $action === 'actor' ? (string) ($payload['itemtype'] ?? '') : User::class;
        $targetId = $take ? (int) Session::getLoginUserID() : ($action === 'actor' ? (int) ($payload['items_id'] ?? 0) : $userId);
        if (!in_array($targetClass, [User::class, Group::class], true) || ($role !== 'assign' && $targetClass !== User::class)) {
            throw new RuntimeException('Ator não permitido.', 422);
        }
        $remove = $action === 'actor' && ($payload['operation'] ?? '') === 'remove';
        if (!$remove) {
            dashglpi_attendance_validate_item($targetClass, $targetId, $ticket, $role === 'assign' && $targetClass === User::class);
        }
        $actors = [];
        foreach (['requester' => 1, 'assign' => 2, 'observer' => 3] as $key => $nativeRole) {
            $actors[$key] = $ticket->getActorsForType($nativeRole);
        }
        $exists = false;
        foreach ($actors[$role] as $index => $actor) {
            if ($actor['itemtype'] === $targetClass && (int) $actor['items_id'] === $targetId) {
                $exists = true;
                if ($remove) {
                    unset($actors[$role][$index]);
                }
            }
        }
        if (!$exists && !$remove) {
            $actors[$role][] = ['itemtype' => $targetClass, 'items_id' => $targetId, 'use_notification' => 1];
        }
        $actors[$role] = array_values($actors[$role]);
        // O GLPI ignora _actors.assign quando o perfil só pode assumir.
        // O campo nativo incremental respeita OWN/STEAL sem remover terceiros.
        if ($take) {
            $input['_users_id_assign'] = $targetId;
        } else {
            $input['_actors'] = $actors;
        }
    } else {
        throw new RuntimeException('Ação de atendimento desconhecida.', 400);
    }
    if (count($input) > 1 && !$ticket->update($input)) {
        throw new RuntimeException('GLPI não salvou as alterações. Confira os campos obrigatórios.', 422);
    }
    return ['result' => ['ticket_id' => $ticket->getID()]];
}

function dashglpi_attendance_solution_decision(Ticket $ticket, array $payload): array
{
    dashglpi_attendance_require_capability($ticket, 'approve');
    $action = $payload['action'] ?? '';
    if (!in_array($action, ['approve', 'refuse'], true)) {
        throw new RuntimeException('Decisão inválida.', 422);
    }
    $status = $action === 'approve' ? Ticket::CLOSED : Ticket::ASSIGNED;
    if ($action === 'refuse') {
        if (trim((string) ($payload['reason'] ?? '')) === '') {
            throw new RuntimeException('Informe o motivo da recusa.', 422);
        }
        dashglpi_attendance_add_event($ticket, ['content' => 'Solução recusada. Motivo: ' . trim((string) ($payload['reason'] ?? ''))], 'reply');
    }
    $input = ['id' => $ticket->getID(), 'status' => $status];
    if ($action === 'approve') {
        $input['_accepted'] = true;
    }
    if (!$ticket->update($input)) {
        throw new RuntimeException('GLPI não registrou a decisão.', 422);
    }
    return ['result' => ['ticket_id' => $ticket->getID(), 'status' => $status]];
}

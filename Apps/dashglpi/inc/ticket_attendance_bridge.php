<?php

// Biblioteca nativa GLPI; não é endpoint (PLAN-20260905-001).
require_once __DIR__ . '/../ajax/admin_bridge_common.php';
require_once __DIR__ . '/ticket_attendance_content.php';
require_once __DIR__ . '/ticket_attendance_read.php';
require_once __DIR__ . '/ticket_attendance_write.php';

/** Executa somente após autenticação do bridge, sem callAsSystem(). */
function dashglpi_attendance_bridge_handle(callable $handler): void
{
    try {
        $payload = plugin_dashglpi_admin_bridge_payload();
        $result = dashglpi_attendance_as_actor($payload, $handler);
        plugin_dashglpi_admin_bridge_json(['ok' => true] + $result);
    } catch (Throwable $e) {
        plugin_dashglpi_admin_bridge_log($e, 'ticket attendance');
        $code = in_array($e->getCode(), [400, 403, 404, 409, 422], true) ? $e->getCode() : 500;
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => $e->getMessage() ?: 'Falha no atendimento.'], $code);
    }
}

/** Reconstitui direitos nativos a partir de usuário/perfil autenticados pelo Dash. */
function dashglpi_attendance_as_actor(array $payload, callable $handler): array
{
    $saved = $_SESSION ?? [];
    try {
        $userId = (int) ($payload['actor']['user_id'] ?? 0);
        $requestedProfileId = (int) ($payload['actor']['profile_id'] ?? 0);
        $user = new User();
        if ($userId <= 0 || !$user->getFromDB($userId) || !$user->fields['is_active'] || $user->fields['is_deleted']) {
            throw new RuntimeException('Usuário de atendimento inválido.', 403);
        }
        $now = date('Y-m-d H:i:s');
        if ((!empty($user->fields['begin_date']) && $user->fields['begin_date'] > $now)
            || (!empty($user->fields['end_date']) && $user->fields['end_date'] < $now)) {
            throw new RuntimeException('Usuário fora do período de validade.', 403);
        }
        $_SESSION = [];
        Session::initVars();
        $_SESSION['glpiID'] = $userId;
        $_SESSION['glpiname'] = $user->fields['name'];
        $_SESSION['glpidefault_entity'] = (int) $user->fields['entities_id'];
        $user->loadPreferencesInSession();
        Session::initEntityProfiles($userId);
        $availableProfiles = array_map('intval', array_keys($_SESSION['glpiprofiles'] ?? []));
        if ($requestedProfileId > 0 && !in_array($requestedProfileId, $availableProfiles, true)) {
            throw new RuntimeException('Perfil não pertence ao usuário.', 403);
        }
        // O Dash pode ter escolhido um perfil padrão diferente daquele que o
        // usuário tinha ativo no GLPI. Tente primeiro o perfil informado e, se
        // ele não enxergar este chamado, deixe o próprio GLPI encontrar outro
        // vínculo real do mesmo usuário. Isso preserva READMY para o requerente
        // sem transformar a entidade em uma permissão criada pelo Dash.
        $candidateProfiles = $requestedProfileId > 0
            ? array_values(array_unique(array_merge([$requestedProfileId], $availableProfiles)))
            : $availableProfiles;
        $ticket = new Ticket();
        if (!$ticket->getFromDB((int) ($payload['ticket_id'] ?? 0)) || $ticket->isDeleted()) {
            throw new RuntimeException('Chamado não encontrado.', 404);
        }
        foreach ($candidateProfiles as $candidateProfileId) {
            Session::changeProfile($candidateProfileId);
            if (!isset($_SESSION['glpiactiveprofile'])) {
                continue;
            }
            Session::changeActiveEntities('all');
            Session::loadGroups();
            if ($ticket->can($ticket->getID(), READ)) {
                return $handler($payload, $ticket);
            }
        }
        throw new RuntimeException('Acesso restrito ao chamado pelo GLPI.', 403);
    } finally {
        $_SESSION = $saved;
    }
}

function dashglpi_attendance_capabilities(Ticket $ticket): array
{
    $operator = Session::getCurrentInterface() === 'central';
    $open = !$ticket->isClosed();
    $followup = $open && $ticket->canAddFollowups();
    $edit = $operator && $open && Session::haveRight(Ticket::$rightname, UPDATE) && $ticket->can($ticket->getID(), UPDATE);
    return [
        'mode' => $operator ? 'operator' : 'self_service',
        'edit' => $edit,
        'priority' => $operator && $open && Session::haveRight(Ticket::$rightname, Ticket::CHANGEPRIORITY),
        'reply' => $followup,
        'private' => $operator && $followup && Session::haveRight(ITILFollowup::$rightname, ITILFollowup::SEEPRIVATE),
        'task' => $operator && $open && $ticket->canAddTasks(),
        'solution' => $operator && !$ticket->isSolved() && $ticket->canSolve(),
        'assign' => $operator && $ticket->canAssign(),
        'take' => $operator && $ticket->canAssignToMe(),
        'actors' => $operator && $open && $ticket->canAdminActors(),
        'status' => $edit || ($operator && $ticket->canReopen()),
        'approve' => $ticket->isSolved() && $ticket->canApprove(),
        'cancel' => !$operator && $open && $ticket->canApprove() && $ticket->canRequesterUpdateItem()
            && Ticket::isAllowedStatus($ticket->fields['status'], Ticket::CLOSED),
    ];
}

function dashglpi_attendance_require_capability(Ticket $ticket, string $key): void
{
    if (empty(dashglpi_attendance_capabilities($ticket)[$key])) {
        throw new RuntimeException('Seu perfil não permite esta ação no estado atual do chamado.', 403);
    }
}

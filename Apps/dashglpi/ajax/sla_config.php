<?php

require_once __DIR__ . '/../../../inc/includes.php';

plugin_dashglpi_sla_bridge_require_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => 'JSON invalido.'], 400);
}

$action = (string) ($input['action'] ?? '');
$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

try {
    if (!in_array($action, [
        'validate',
        'apply',
        'reapply_open_tickets',
        'apply_reminders',
        'apply_client_policy',
        'reapply_client_policy_open_tickets',
        'clear_glpi_cache',
    ], true)) {
        plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => 'Acao invalida.'], 400);
    }

    if ($action === 'clear_glpi_cache') {
        if (empty($_SESSION['glpi_currenttime'])) {
            $_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
        }

        $result = Session::callAsSystem(static fn(): array => plugin_dashglpi_sla_bridge_clear_glpi_cache());

        plugin_dashglpi_sla_bridge_json(array_merge([
            'ok' => true,
            'action' => $action,
            'message' => 'Cache do GLPI limpo com sucesso.',
        ], $result));
    }

    $isClientPolicyAction = in_array($action, ['apply_client_policy', 'reapply_client_policy_open_tickets'], true);
    $validated = $isClientPolicyAction
        ? plugin_dashglpi_sla_bridge_validate_client_policy_payload($payload)
        : ($action === 'apply_reminders'
            ? plugin_dashglpi_sla_bridge_validate_reminders_payload($payload)
            : plugin_dashglpi_sla_bridge_validate_payload($payload));
    if (empty($_SESSION['glpi_currenttime'])) {
        $_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
    }

    $result = Session::callAsSystem(static function () use ($validated, $action): array {
        if ($action === 'apply_client_policy') {
            return plugin_dashglpi_sla_bridge_process_client_policy($validated, true);
        }

        if ($action === 'reapply_client_policy_open_tickets') {
            return plugin_dashglpi_sla_bridge_reapply_client_policy_open_tickets($validated);
        }

        if ($action === 'reapply_open_tickets') {
            return plugin_dashglpi_sla_bridge_reapply_open_tickets($validated);
        }

        if ($action === 'apply_reminders') {
            return plugin_dashglpi_sla_bridge_apply_reminders($validated);
        }

        return plugin_dashglpi_sla_bridge_process($validated, $action === 'apply');
    });

    if (in_array($action, ['reapply_open_tickets', 'reapply_client_policy_open_tickets'], true)) {
        plugin_dashglpi_sla_bridge_json([
            'ok' => true,
            'action' => $action,
            'result' => $result,
            'message' => 'SLA reaplicado nos chamados abertos elegiveis.',
        ]);
    }

    if ($action === 'apply_client_policy') {
        plugin_dashglpi_sla_bridge_json([
            'ok' => true,
            'action' => $action,
            'result' => $result,
            'message' => 'Politica SLA do cliente criada/atualizada no GLPI.',
        ]);
    }

    if ($action === 'apply_reminders') {
        plugin_dashglpi_sla_bridge_json([
            'ok' => true,
            'action' => $action,
            'entity' => $result['entity'] ?? ['id' => 0, 'status' => 'existing'],
            'reminders' => $result['reminders'] ?? [],
            'message' => 'Alertas automáticos de SLA atualizados no GLPI.',
        ]);
    }

    plugin_dashglpi_sla_bridge_json([
        'ok' => true,
        'action' => $action,
        'entity' => $result['entity'],
        'calendar' => $result['calendar'],
        'slm' => $result['slm'],
        'slas' => $result['slas'],
        'rules' => $result['rules'] ?? [],
        'message' => $action === 'apply'
            ? 'Configuracao de SLA e regras criadas/atualizadas no GLPI.'
            : 'Configuracao de SLA validada no GLPI.',
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[DashGLPI SLA bridge] class=%s code=%s message=%s file=%s line=%d\n",
            get_class($e),
            (string) $e->getCode(),
            $message !== '' ? $message : '(empty)',
            $e->getFile(),
            $e->getLine()
        )
    );
    plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => $message !== '' ? $message : 'Erro interno no bridge SLA.'], 500);
}

function plugin_dashglpi_sla_bridge_require_token(): void
{
    $expected = (string) getenv('DASHGLPI_BRIDGE_TOKEN');
    if ($expected === '') {
        plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => 'Bridge nao configurado.'], 503);
    }

    $provided = (string) ($_SERVER['HTTP_X_DASHGLPI_BRIDGE_TOKEN'] ?? '');
    if (!hash_equals($expected, $provided)) {
        plugin_dashglpi_sla_bridge_json(['ok' => false, 'error' => 'Token invalido.'], 403);
    }
}

function plugin_dashglpi_sla_bridge_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function plugin_dashglpi_sla_bridge_validate_payload(array $payload): array
{
    $definitionTimes = ['minute', 'hour', 'day', 'month'];
    $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
    $calendar = is_array($payload['calendar'] ?? null) ? $payload['calendar'] : [];
    $slm = is_array($payload['slm'] ?? null) ? $payload['slm'] : [];
    $slas = is_array($payload['slas'] ?? null) ? $payload['slas'] : [];

    $entityMode = plugin_dashglpi_sla_bridge_mode((string) ($entity['mode'] ?? 'existing'));
    $calendarMode = plugin_dashglpi_sla_bridge_mode((string) ($calendar['mode'] ?? 'existing'));

    $validated = [
        'entity' => [
            'mode' => $entityMode,
            'id' => max(0, (int) ($entity['id'] ?? 0)),
            'name' => trim((string) ($entity['name'] ?? '')),
            'parent_id' => max(0, (int) ($entity['parent_id'] ?? 0)),
            'saved_id' => max(0, (int) ($entity['saved_id'] ?? 0)),
        ],
        'calendar' => [
            'mode' => $calendarMode,
            'id' => max(0, (int) ($calendar['id'] ?? 0)),
            'name' => trim((string) ($calendar['name'] ?? '')),
            'is_recursive' => !empty($calendar['is_recursive']) ? 1 : 0,
            'saved_id' => max(0, (int) ($calendar['saved_id'] ?? 0)),
            'segments' => [],
        ],
        'slm' => [
            'name' => trim((string) ($slm['name'] ?? '')),
            'saved_id' => max(0, (int) ($slm['saved_id'] ?? 0)),
        ],
        'slas' => [],
    ];

    if (in_array($validated['entity']['mode'], ['new', 'edit'], true) && $validated['entity']['name'] === '') {
        throw new RuntimeException($validated['entity']['mode'] === 'edit' ? 'Informe o nome da entidade.' : 'Informe o nome da nova entidade.');
    }

    if ($validated['entity']['mode'] === 'edit' && $validated['entity']['id'] <= 0) {
        throw new RuntimeException('Selecione uma entidade.');
    }

    if (in_array($validated['calendar']['mode'], ['existing', 'edit'], true) && $validated['calendar']['id'] <= 0) {
        throw new RuntimeException('Selecione um calendario.');
    }

    if (in_array($validated['calendar']['mode'], ['new', 'edit'], true) && $validated['calendar']['name'] === '') {
        throw new RuntimeException($validated['calendar']['mode'] === 'edit' ? 'Informe o nome do calendario.' : 'Informe o nome do novo calendario.');
    }

    foreach (($calendar['segments'] ?? []) as $segment) {
        if (!is_array($segment)) {
            continue;
        }

        $day = (int) ($segment['day'] ?? -1);
        $begin = plugin_dashglpi_sla_bridge_time((string) ($segment['begin'] ?? ''));
        $end = plugin_dashglpi_sla_bridge_time((string) ($segment['end'] ?? ''));

        if (!in_array($day, [0, 1, 2, 3, 4, 5, 6], true)) {
            throw new RuntimeException('Dia de calendario invalido.');
        }

        if ($begin === null || $end === null || $begin >= $end) {
            throw new RuntimeException('Horario de calendario invalido.');
        }

        $validated['calendar']['segments'][] = [
            'day' => $day,
            'begin' => $begin,
            'end' => $end,
        ];
    }

    if (in_array($validated['calendar']['mode'], ['new', 'edit'], true) && count($validated['calendar']['segments']) === 0) {
        throw new RuntimeException('Calendario precisa de pelo menos um periodo.');
    }

    $seenSlaKeys = [];
    foreach ($slas as $sla) {
        if (!is_array($sla)) {
            continue;
        }

        $key = strtoupper(trim((string) ($sla['key'] ?? '')));
        $type = (int) ($sla['type'] ?? -1);
        $numberTime = (int) ($sla['number_time'] ?? 0);
        $definitionTime = (string) ($sla['definition_time'] ?? '');

        if (!preg_match('/^(TTO|TTR)-P[1-9][0-9]*$/', $key)) {
            throw new RuntimeException('Chave de SLA invalida.');
        }

        $expectedType = str_starts_with($key, 'TTO-') ? SLM::TTO : SLM::TTR;
        if (!in_array($type, [SLM::TTO, SLM::TTR], true)) {
            throw new RuntimeException('Tipo de SLA invalido.');
        }
        if ($type !== $expectedType) {
            throw new RuntimeException('Tipo de SLA nao corresponde a chave ' . $key . '.');
        }

        if ($numberTime < 1 || $numberTime > 1000) {
            throw new RuntimeException('Tempo maximo de SLA invalido.');
        }

        if (!in_array($definitionTime, $definitionTimes, true)) {
            throw new RuntimeException('Unidade de tempo de SLA invalida.');
        }

        $seenSlaKeys[$key] = true;
        $validated['slas'][] = [
            'key' => $key,
            'name' => $key,
            'type' => $type,
            'number_time' => $numberTime,
            'definition_time' => $definitionTime,
            'id' => max(0, (int) ($sla['id'] ?? 0)),
        ];
    }

    foreach (['TTO-P1', 'TTO-P2', 'TTO-P3', 'TTO-P4', 'TTR-P1', 'TTR-P2', 'TTR-P3', 'TTR-P4'] as $requiredKey) {
        if (empty($seenSlaKeys[$requiredKey])) {
            throw new RuntimeException('A configuracao precisa conter o SLA obrigatorio ' . $requiredKey . '.');
        }
    }

    return $validated;
}

function plugin_dashglpi_sla_bridge_validate_client_policy_payload(array $payload): array
{
    $validated = plugin_dashglpi_sla_bridge_validate_payload($payload);
    $policy = is_array($payload['policy'] ?? null) ? $payload['policy'] : [];
    $entitiesId = max(0, (int) ($policy['entities_id'] ?? $validated['entity']['id'] ?? 0));

    $ttoMode = plugin_dashglpi_sla_bridge_policy_mode((string) ($policy['tto_mode'] ?? 'fixed'), 'fixed');
    $ttrMode = plugin_dashglpi_sla_bridge_policy_mode((string) ($policy['ttr_mode'] ?? 'priority'), 'priority');

    $availableKeys = array_column($validated['slas'], 'key');
    $validated['policy'] = [
        'entities_id' => $entitiesId,
        'is_active' => !empty($policy['is_active']) ? 1 : 0,
        'is_recursive' => 1,
        'tto_mode' => $ttoMode,
        'tto_fixed_key' => plugin_dashglpi_sla_bridge_policy_key((string) ($policy['tto_fixed_key'] ?? 'TTO-P1'), 'TTO', 'TTO-P1', $availableKeys),
        'ttr_mode' => $ttrMode,
        'ttr_fixed_key' => plugin_dashglpi_sla_bridge_policy_key((string) ($policy['ttr_fixed_key'] ?? 'TTR-P1'), 'TTR', 'TTR-P1', $availableKeys),
    ];

    $validated['entity']['mode'] = 'existing';
    $validated['entity']['id'] = $validated['policy']['entities_id'];
    $validated['entity']['saved_id'] = $validated['policy']['entities_id'];

    return $validated;
}

function plugin_dashglpi_sla_bridge_validate_reminders_payload(array $payload): array
{
    $validated = plugin_dashglpi_sla_bridge_validate_payload($payload);
    $reminders = is_array($payload['reminders'] ?? null) ? $payload['reminders'] : [];
    $byKey = [];

    foreach ($reminders as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = strtoupper(trim((string) ($row['key'] ?? '')));
        if ($key !== '') {
            $byKey[$key] = $row;
        }
    }

    $validated['reminders'] = [];
    foreach ($validated['slas'] as $sla) {
        $key = (string) ($sla['key'] ?? '');
        if (!str_starts_with($key, 'TTR-')) {
            continue;
        }
        if (!isset($byKey[$key])) {
            throw new RuntimeException('Configuracao de alerta ausente para ' . $key . '.');
        }

        $row = $byKey[$key];
        $offsetValue = max(1, (int) ($row['offset_value'] ?? 0));
        $offsetUnit = (string) ($row['offset_unit'] ?? '');
        if (!in_array($offsetUnit, ['minute', 'hour', 'day', 'month'], true)) {
            throw new RuntimeException('Unidade de alerta inválida para ' . $key . '.');
        }

        $offsetSeconds = plugin_dashglpi_sla_bridge_duration_to_seconds($offsetValue, $offsetUnit);
        $slaSeconds = plugin_dashglpi_sla_bridge_duration_to_seconds(
            (int) ($sla['number_time'] ?? 0),
            (string) ($sla['definition_time'] ?? '')
        );
        $isActive = !empty($row['is_active']) ? 1 : 0;
        $slaId = max(0, (int) ($row['slas_id'] ?? $sla['id'] ?? 0));

        if ($isActive && $slaId <= 0) {
            throw new RuntimeException('Aplique os SLAs base antes de configurar o alerta ' . $key . '.');
        }
        if ($isActive && ($offsetSeconds <= 0 || $slaSeconds <= 0 || $offsetSeconds >= $slaSeconds)) {
            throw new RuntimeException('O alerta ' . $key . ' precisa avisar antes do tempo total do TTR.');
        }

        $validated['reminders'][] = [
            'key' => $key,
            'id' => max(0, (int) ($row['id'] ?? 0)),
            'slas_id' => $slaId,
            'is_active' => $isActive,
            'offset_value' => $offsetValue,
            'offset_unit' => $offsetUnit,
            'offset_seconds' => $offsetSeconds,
        ];
    }

    return $validated;
}

function plugin_dashglpi_sla_bridge_policy_mode(string $mode, string $fallback): string
{
    return in_array($mode, ['fixed', 'priority'], true) ? $mode : $fallback;
}

function plugin_dashglpi_sla_bridge_policy_key(string $key, string $kind, string $fallback, array $availableKeys = []): string
{
    $key = strtoupper(trim($key));
    if (!preg_match('/^' . preg_quote($kind, '/') . '-P[1-9][0-9]*$/', $key)) {
        return $fallback;
    }

    return !$availableKeys || in_array($key, $availableKeys, true) ? $key : $fallback;
}

function plugin_dashglpi_sla_bridge_key_bucket(string $key): string
{
    return preg_match('/-P([1-9][0-9]*)$/', strtoupper(trim($key)), $matches)
        ? 'P' . $matches[1]
        : '';
}

function plugin_dashglpi_sla_bridge_mode(string $mode): string
{
    return in_array($mode, ['existing', 'new', 'edit'], true) ? $mode : 'existing';
}

function plugin_dashglpi_sla_bridge_process(array $payload, bool $apply): array
{
    $entity = plugin_dashglpi_sla_bridge_resolve_entity($payload['entity'], $apply);
    $calendar = plugin_dashglpi_sla_bridge_resolve_calendar($payload['calendar'], $entity['id'], $apply);

    $slmName = trim((string) $payload['slm']['name']);
    if ($slmName === '') {
        $slmName = 'SLA Simplificado - ' . $entity['name'];
    }

    $slm = plugin_dashglpi_sla_bridge_resolve_slm([
        'id' => (int) $payload['slm']['saved_id'],
        'name' => $slmName,
        'entities_id' => $entity['id'],
        'calendars_id' => $calendar['id'],
    ], $apply);

    $slaResults = [];
    foreach ($payload['slas'] as $sla) {
        $slaResults[] = plugin_dashglpi_sla_bridge_resolve_sla([
            'id' => (int) $sla['id'],
            'key' => $sla['key'],
            'name' => $sla['name'],
            'type' => $sla['type'],
            'number_time' => $sla['number_time'],
            'definition_time' => $sla['definition_time'],
            'entities_id' => $entity['id'],
            'calendars_id' => $calendar['id'],
            'slms_id' => $slm['id'],
        ], $apply);
    }

    $ruleResults = plugin_dashglpi_sla_bridge_resolve_rules($entity['id'], $slaResults, $apply);

    return [
        'entity' => $entity,
        'calendar' => $calendar,
        'slm' => $slm,
        'slas' => $slaResults,
        'rules' => $ruleResults,
    ];
}

function plugin_dashglpi_sla_bridge_process_client_policy(array $payload, bool $apply): array
{
    $entity = plugin_dashglpi_sla_bridge_resolve_entity($payload['entity'], $apply);
    $calendar = plugin_dashglpi_sla_bridge_resolve_calendar($payload['calendar'], $entity['id'], $apply);

    $slmName = trim((string) $payload['slm']['name']);
    if ($slmName === '') {
        $slmName = 'SLA Cliente - ' . $entity['name'];
    }

    $slm = plugin_dashglpi_sla_bridge_resolve_slm([
        'id' => (int) $payload['slm']['saved_id'],
        'name' => $slmName,
        'entities_id' => $entity['id'],
        'calendars_id' => $calendar['id'],
    ], $apply);

    $slaResults = [];
    foreach ($payload['slas'] as $sla) {
        $slaResults[] = plugin_dashglpi_sla_bridge_resolve_sla([
            'id' => (int) $sla['id'],
            'key' => $sla['key'],
            'name' => $sla['name'],
            'type' => $sla['type'],
            'number_time' => $sla['number_time'],
            'definition_time' => $sla['definition_time'],
            'entities_id' => $entity['id'],
            'calendars_id' => $calendar['id'],
            'slms_id' => $slm['id'],
        ], $apply);
    }

    $ruleResults = plugin_dashglpi_sla_bridge_resolve_client_policy_rules($entity['id'], $slaResults, $payload['policy'], $apply);

    return [
        'entity' => $entity,
        'calendar' => $calendar,
        'slm' => $slm,
        'slas' => $slaResults,
        'rules' => $ruleResults,
        'policy' => $payload['policy'],
    ];
}

function plugin_dashglpi_sla_bridge_apply_reminders(array $payload): array
{
    $entityId = (int) ($payload['entity']['id'] ?? 0);
    $results = [];
    $keepIds = [];
    $managedNames = [];

    foreach ($payload['reminders'] as $reminder) {
        $managedNames[] = plugin_dashglpi_sla_bridge_reminder_name((string) $reminder['key']);
        $result = plugin_dashglpi_sla_bridge_resolve_reminder($reminder, $entityId);
        if ((int) ($result['id'] ?? 0) > 0) {
            $keepIds[] = (int) $result['id'];
        }
        $results[] = $result;
    }

    plugin_dashglpi_sla_bridge_purge_obsolete_reminders($managedNames, $keepIds);

    return [
        'entity' => [
            'id' => $entityId,
            'status' => 'existing',
        ],
        'reminders' => $results,
    ];
}

function plugin_dashglpi_sla_bridge_resolve_entity(array $input, bool $apply): array
{
    if ($input['mode'] === 'existing') {
        $entityId = (int) $input['id'];
        if ($entityId === 0) {
            return [
                'id' => 0,
                'name' => 'Entidade raiz',
                'status' => 'existing',
            ];
        }

        $entity = new Entity();
        if (!$entity->getFromDB($entityId)) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        return [
            'id' => (int) $entity->fields['id'],
            'name' => (string) ($entity->fields['completename'] ?: $entity->fields['name']),
            'status' => 'existing',
        ];
    }

    if ($input['mode'] === 'edit') {
        $entity = new Entity();
        if (!$entity->getFromDB((int) $input['id'])) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        $existing = $entity->fields;
        if ($apply && plugin_dashglpi_sla_bridge_changed($existing, [
            'name' => $input['name'],
            'entities_id' => $input['parent_id'],
        ])) {
            if (!$entity->update([
                'id' => (int) $input['id'],
                'name' => $input['name'],
                'entities_id' => $input['parent_id'],
            ])) {
                throw new RuntimeException('Falha ao atualizar entidade.');
            }
            $existing['name'] = $input['name'];
            $existing['entities_id'] = $input['parent_id'];
            $status = 'updated';
        } else {
            $status = 'existing';
        }

        return [
            'id' => (int) $input['id'],
            'name' => $input['name'],
            'status' => $status,
        ];
    }

    if (!$apply) {
        return ['id' => 0, 'name' => $input['name'], 'status' => 'pending'];
    }

    $entity = new Entity();
    $id = $entity->add([
        'name' => $input['name'],
        'entities_id' => $input['parent_id'],
        'comment' => 'Criado pelo configurador SLA Simples do DashGLPI.',
    ]);

    if (!$id) {
        throw new RuntimeException('Falha ao criar entidade.');
    }

    return ['id' => (int) $id, 'name' => $input['name'], 'status' => 'created'];
}

function plugin_dashglpi_sla_bridge_resolve_calendar(array $input, int $entitiesId, bool $apply): array
{
    $managedComment = 'Criado pelo configurador SLA Simples do DashGLPI.';

    if ($input['mode'] === 'existing') {
        $calendar = new Calendar();
        if (!$calendar->getFromDB((int) $input['id'])) {
            throw new RuntimeException('Calendario nao encontrado.');
        }

        return [
            'id' => (int) $calendar->fields['id'],
            'name' => (string) $calendar->fields['name'],
            'status' => 'existing',
        ];
    }

    if ($input['mode'] === 'edit') {
        $calendar = new Calendar();
        if (!$calendar->getFromDB((int) $input['id'])) {
            throw new RuntimeException('Calendario nao encontrado.');
        }

        $existing = $calendar->fields;
        $status = 'existing';
        $calendarFields = [
            'name' => $input['name'],
            'entities_id' => $entitiesId,
            'is_recursive' => $input['is_recursive'],
            'comment' => (string) ($existing['comment'] ?? ''),
        ];

        if ($apply && plugin_dashglpi_sla_bridge_changed($existing, $calendarFields)) {
            if (!$calendar->update(array_merge(['id' => (int) $input['id']], $calendarFields))) {
                throw new RuntimeException('Falha ao atualizar calendario.');
            }
            $status = 'updated';
        }

        if ($apply) {
            $changedSegments = plugin_dashglpi_sla_bridge_replace_segments((int) $input['id'], $entitiesId, $input['segments']);
            if ($changedSegments > 0 && $status === 'existing') {
                $status = 'updated';
            }
        }

        return ['id' => (int) $input['id'], 'name' => $input['name'], 'status' => $status];
    }

    if (!$apply) {
        return ['id' => 0, 'name' => $input['name'], 'status' => 'pending'];
    }

    $calendar = new Calendar();
    $id = $calendar->add([
        'name' => $input['name'],
        'entities_id' => $entitiesId,
        'is_recursive' => $input['is_recursive'],
        'comment' => $managedComment,
    ]);

    if (!$id) {
        throw new RuntimeException('Falha ao criar calendario.');
    }

    plugin_dashglpi_sla_bridge_replace_segments((int) $id, $entitiesId, $input['segments']);

    return ['id' => (int) $id, 'name' => $input['name'], 'status' => 'created'];
}

function plugin_dashglpi_sla_bridge_resolve_slm(array $input, bool $apply): array
{
    $existing = plugin_dashglpi_sla_bridge_find_one(SLM::class, [
        'name' => $input['name'],
        'entities_id' => $input['entities_id'],
    ], (int) $input['id']);

    $fields = [
        'name' => $input['name'],
        'entities_id' => $input['entities_id'],
        'is_recursive' => 1,
        'use_ticket_calendar' => 0,
        'calendars_id' => $input['calendars_id'],
        'comment' => 'Pacote criado pelo configurador SLA Simples do DashGLPI.',
    ];

    if ($existing) {
        if ($apply && plugin_dashglpi_sla_bridge_changed($existing, $fields)) {
            $slm = new SLM();
            if (!$slm->update(array_merge(['id' => (int) $existing['id']], $fields))) {
                throw new RuntimeException('Falha ao atualizar SLM.');
            }
            $status = 'updated';
        } else {
            $status = 'existing';
        }

        return ['id' => (int) $existing['id'], 'name' => $input['name'], 'status' => $status];
    }

    if (!$apply) {
        return ['id' => 0, 'name' => $input['name'], 'status' => 'pending'];
    }

    $slm = new SLM();
    $id = $slm->add($fields);
    if (!$id) {
        throw new RuntimeException('Falha ao criar SLM.');
    }

    return ['id' => (int) $id, 'name' => $input['name'], 'status' => 'created'];
}

function plugin_dashglpi_sla_bridge_resolve_sla(array $input, bool $apply): array
{
    $existing = plugin_dashglpi_sla_bridge_find_sla($input);
    $fields = [
        'name' => $input['name'],
        'entities_id' => $input['entities_id'],
        'is_recursive' => 1,
        'type' => $input['type'],
        'comment' => $input['type'] === SLM::TTO ? 'Tempo para atribuir' : 'Tempo para resolver',
        'number_time' => $input['number_time'],
        'definition_time' => $input['definition_time'],
        'use_ticket_calendar' => 0,
        'calendars_id' => $input['calendars_id'],
        'end_of_working_day' => 0,
        'slms_id' => $input['slms_id'],
    ];

    if ($existing) {
        if ($apply && plugin_dashglpi_sla_bridge_changed($existing, $fields)) {
            $sla = new SLA();
            if (!$sla->update(array_merge(['id' => (int) $existing['id']], $fields))) {
                throw new RuntimeException('Falha ao atualizar SLA ' . $input['key'] . '.');
            }
            $status = 'updated';
        } else {
            $status = 'existing';
        }

        return [
            'key' => $input['key'],
            'id' => (int) $existing['id'],
            'name' => $input['name'],
            'status' => $status,
        ];
    }

    if (!$apply) {
        return ['key' => $input['key'], 'id' => 0, 'name' => $input['name'], 'status' => 'pending'];
    }

    $sla = new SLA();
    $id = $sla->add($fields);
    if (!$id) {
        throw new RuntimeException('Falha ao criar SLA ' . $input['key'] . '.');
    }

    return ['key' => $input['key'], 'id' => (int) $id, 'name' => $input['name'], 'status' => 'created'];
}

function plugin_dashglpi_sla_bridge_resolve_rules(int $entitiesId, array $slaResults, bool $apply): array
{
    $slaIds = plugin_dashglpi_sla_bridge_sla_ids_by_key($slaResults);
    $rules = [];
    $ruleNames = [];

    foreach (plugin_dashglpi_sla_bridge_priority_map() as $mapping) {
        $bucket = $mapping['bucket'];
        $priority = (int) $mapping['priority'];
        $ttoId = (int) ($slaIds['TTO-' . $bucket] ?? 0);
        $ttrId = (int) ($slaIds['TTR-' . $bucket] ?? 0);
        $name = sprintf('DashGLPI SLA %s - Prioridade %d', $bucket, $priority);
        $ruleNames[] = $name;

        if (!$apply && ($ttoId <= 0 || $ttrId <= 0)) {
            $rules[] = [
                'name' => $name,
                'priority' => $priority,
                'bucket' => $bucket,
                'id' => 0,
                'status' => 'pending',
            ];
            continue;
        }

        if ($ttoId <= 0 || $ttrId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar os SLAs ' . $bucket . ' para criar regras.');
        }

        $rules[] = plugin_dashglpi_sla_bridge_resolve_rule([
            'name' => $name,
            'bucket' => $bucket,
            'priority' => $priority,
            'entities_id' => $entitiesId,
            'slas_id_tto' => $ttoId,
            'slas_id_ttr' => $ttrId,
        ], $apply);
    }

    if ($apply) {
        plugin_dashglpi_sla_bridge_purge_obsolete_rules($ruleNames, array_column($rules, 'id'));
    }

    return $rules;
}

function plugin_dashglpi_sla_bridge_resolve_rule(array $input, bool $apply): array
{
    $fields = [
        'name' => $input['name'],
        'sub_type' => RuleTicket::class,
        'entities_id' => (int) $input['entities_id'],
        'is_recursive' => 1,
        'is_active' => 1,
        'match' => Rule::AND_MATCHING,
        'condition' => 0,
        'description' => 'Aplica SLAs TTO/TTR criados pelo DashGLPI para prioridade ' . $input['priority'] . '.',
        'comment' => plugin_dashglpi_sla_bridge_rule_marker(),
    ];
    $criteria = [
        plugin_dashglpi_sla_bridge_entity_rule_criterion((int) $input['entities_id'], true),
        [
            'criteria' => 'priority',
            'condition' => Rule::PATTERN_IS,
            'pattern' => (string) $input['priority'],
        ],
    ];
    $actions = [
        [
            'action_type' => 'assign',
            'field' => 'slas_id_tto',
            'value' => (string) $input['slas_id_tto'],
        ],
        [
            'action_type' => 'assign',
            'field' => 'slas_id_ttr',
            'value' => (string) $input['slas_id_ttr'],
        ],
    ];

    $existing = plugin_dashglpi_sla_bridge_find_managed_rule((string) $input['name'], (int) $input['entities_id']);
    if (!$existing) {
        if (!$apply) {
            return [
                'id' => 0,
                'name' => $input['name'],
                'bucket' => $input['bucket'],
                'priority' => (int) $input['priority'],
                'status' => 'pending',
            ];
        }

        $rule = new RuleTicket();
        $id = $rule->add($fields);
        if (!$id) {
            throw new RuntimeException('Falha ao criar regra de SLA ' . $input['name'] . '.');
        }

        plugin_dashglpi_sla_bridge_replace_rule_children((int) $id, $criteria, $actions);

        return [
            'id' => (int) $id,
            'name' => $input['name'],
            'bucket' => $input['bucket'],
            'priority' => (int) $input['priority'],
            'status' => 'created',
        ];
    }

    $status = 'existing';
    if ($apply && plugin_dashglpi_sla_bridge_changed($existing, $fields)) {
        $rule = new RuleTicket();
        if (!$rule->update(array_merge(['id' => (int) $existing['id']], $fields))) {
            throw new RuntimeException('Falha ao atualizar regra de SLA ' . $input['name'] . '.');
        }
        $status = 'updated';
    }

    if ($apply && !plugin_dashglpi_sla_bridge_rule_children_match((int) $existing['id'], $criteria, $actions)) {
        plugin_dashglpi_sla_bridge_replace_rule_children((int) $existing['id'], $criteria, $actions);
        $status = 'updated';
    }

    return [
        'id' => (int) $existing['id'],
        'name' => $input['name'],
        'bucket' => $input['bucket'],
        'priority' => (int) $input['priority'],
        'status' => $status,
    ];
}

function plugin_dashglpi_sla_bridge_resolve_reminder(array $input, int $entitiesId): array
{
    $name = plugin_dashglpi_sla_bridge_reminder_name((string) $input['key']);
    $existing = plugin_dashglpi_sla_bridge_find_managed_reminder(
        $name,
        max(0, (int) ($input['id'] ?? 0))
    );

    if (empty($input['is_active'])) {
        if ($existing) {
            $item = new SlaLevel();
            if (!$item->delete(['id' => (int) $existing['id']], true)) {
                throw new RuntimeException('Falha ao remover alerta SLA ' . $input['key'] . '.');
            }
            return [
                'key' => (string) $input['key'],
                'id' => 0,
                'status' => 'deleted',
                'execution_time' => 0,
            ];
        }

        return [
            'key' => (string) $input['key'],
            'id' => 0,
            'status' => 'inactive',
            'execution_time' => 0,
        ];
    }

    $fields = [
        'name' => $name,
        'slas_id' => (int) $input['slas_id'],
        'execution_time' => -abs((int) $input['offset_seconds']),
        'is_active' => 1,
        'entities_id' => $entitiesId,
        'is_recursive' => 1,
        'match' => Rule::AND_MATCHING,
    ];
    $criteria = [];
    $actions = [[
        'action_type' => 'send',
        'field' => 'recall',
        'value' => '',
    ]];

    if (!$existing) {
        $item = new SlaLevel();
        $id = $item->add($fields);
        if (!$id) {
            throw new RuntimeException('Falha ao criar alerta SLA ' . $input['key'] . '.');
        }
        plugin_dashglpi_sla_bridge_replace_reminder_children((int) $id, $criteria, $actions);

        return [
            'key' => (string) $input['key'],
            'id' => (int) $id,
            'status' => 'created',
            'execution_time' => (int) $fields['execution_time'],
        ];
    }

    $status = 'existing';
    if (plugin_dashglpi_sla_bridge_changed($existing, $fields)) {
        $item = new SlaLevel();
        if (!$item->update(array_merge(['id' => (int) $existing['id']], $fields))) {
            throw new RuntimeException('Falha ao atualizar alerta SLA ' . $input['key'] . '.');
        }
        $status = 'updated';
    }

    if (!plugin_dashglpi_sla_bridge_reminder_children_match((int) $existing['id'], $criteria, $actions)) {
        plugin_dashglpi_sla_bridge_replace_reminder_children((int) $existing['id'], $criteria, $actions);
        $status = 'updated';
    }

    return [
        'key' => (string) $input['key'],
        'id' => (int) $existing['id'],
        'status' => $status,
        'execution_time' => (int) $fields['execution_time'],
    ];
}

function plugin_dashglpi_sla_bridge_resolve_client_policy_rules(int $entitiesId, array $slaResults, array $policy, bool $apply): array
{
    $slaIds = plugin_dashglpi_sla_bridge_sla_ids_by_key($slaResults);
    $rules = [];
    $ruleNames = [];
    $isActive = !empty($policy['is_active']) ? 1 : 0;
    $baseCriteria = [
        plugin_dashglpi_sla_bridge_entity_rule_criterion($entitiesId, !empty($policy['is_recursive'])),
    ];

    $ttoRules = plugin_dashglpi_sla_bridge_policy_rule_specs(
        $entitiesId,
        'TTO',
        (string) $policy['tto_mode'],
        (string) $policy['tto_fixed_key'],
        $slaIds,
        $baseCriteria,
        true
    );
    $ttrRules = plugin_dashglpi_sla_bridge_policy_rule_specs(
        $entitiesId,
        'TTR',
        (string) $policy['ttr_mode'],
        (string) $policy['ttr_fixed_key'],
        $slaIds,
        $baseCriteria,
        false
    );

    foreach (array_merge($ttoRules, $ttrRules) as $spec) {
        $ruleNames[] = $spec['name'];
        $rules[] = plugin_dashglpi_sla_bridge_resolve_client_policy_rule($spec, $isActive, $apply);
    }

    if ($apply) {
        plugin_dashglpi_sla_bridge_purge_client_policy_rules($entitiesId, $ruleNames, array_column($rules, 'id'));
    }

    return $rules;
}

function plugin_dashglpi_sla_bridge_policy_rule_specs(
    int $entitiesId,
    string $kind,
    string $mode,
    string $fixedKey,
    array $slaIds,
    array $baseCriteria,
    bool $requiresEmptyTechnician
): array {
    $field = $kind === 'TTO' ? 'slas_id_tto' : 'slas_id_ttr';
    $rules = [];

    if ($mode === 'fixed') {
        $slaId = (int) ($slaIds[$fixedKey] ?? 0);
        if ($slaId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar o SLA ' . $fixedKey . ' para a politica do cliente.');
        }

        $criteria = $baseCriteria;
        if ($requiresEmptyTechnician) {
            $criteria[] = [
                'criteria' => '_users_id_assign',
                'condition' => Rule::PATTERN_IS_EMPTY,
                'pattern' => '',
            ];
        }

        $rules[] = [
            'name' => sprintf('DashGLPI Cliente SLA %s Fixo %s - Entidade %d', $kind, $fixedKey, $entitiesId),
            'kind' => $kind,
            'mode' => 'fixed',
            'bucket' => plugin_dashglpi_sla_bridge_key_bucket($fixedKey),
            'priority' => 0,
            'entities_id' => $entitiesId,
            'criteria' => $criteria,
            'actions' => [[
                'action_type' => 'assign',
                'field' => $field,
                'value' => (string) $slaId,
            ]],
        ];

        return $rules;
    }

    foreach (plugin_dashglpi_sla_bridge_priority_map() as $mapping) {
        $bucket = (string) $mapping['bucket'];
        $priority = (int) $mapping['priority'];
        $key = $kind . '-' . $bucket;
        $slaId = (int) ($slaIds[$key] ?? 0);
        if ($slaId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar o SLA ' . $key . ' para a politica do cliente.');
        }

        $criteria = $baseCriteria;
        if ($requiresEmptyTechnician) {
            $criteria[] = [
                'criteria' => '_users_id_assign',
                'condition' => Rule::PATTERN_IS_EMPTY,
                'pattern' => '',
            ];
        }
        $criteria[] = [
            'criteria' => 'priority',
            'condition' => Rule::PATTERN_IS,
            'pattern' => (string) $priority,
        ];

        $rules[] = [
            'name' => sprintf('DashGLPI Cliente SLA %s %s Prioridade %d - Entidade %d', $kind, $bucket, $priority, $entitiesId),
            'kind' => $kind,
            'mode' => 'priority',
            'bucket' => $bucket,
            'priority' => $priority,
            'entities_id' => $entitiesId,
            'criteria' => $criteria,
            'actions' => [[
                'action_type' => 'assign',
                'field' => $field,
                'value' => (string) $slaId,
            ]],
        ];
    }

    return $rules;
}

function plugin_dashglpi_sla_bridge_resolve_client_policy_rule(array $spec, int $isActive, bool $apply): array
{
    $fields = [
        'name' => $spec['name'],
        'sub_type' => RuleTicket::class,
        'entities_id' => (int) $spec['entities_id'],
        'is_recursive' => 1,
        'is_active' => $isActive,
        'match' => Rule::AND_MATCHING,
        'condition' => 0,
        'description' => 'Aplica SLA ' . $spec['kind'] . ' da politica por cliente do DashGLPI.',
        'comment' => plugin_dashglpi_sla_bridge_client_policy_rule_marker(),
    ];

    $existing = plugin_dashglpi_sla_bridge_find_managed_rule_by_marker(
        (string) $spec['name'],
        (int) $spec['entities_id'],
        plugin_dashglpi_sla_bridge_client_policy_rule_marker()
    );

    if (!$existing) {
        if (!$apply) {
            return [
                'id' => 0,
                'name' => $spec['name'],
                'kind' => $spec['kind'],
                'mode' => $spec['mode'],
                'bucket' => $spec['bucket'],
                'priority' => (int) $spec['priority'],
                'status' => 'pending',
            ];
        }

        $rule = new RuleTicket();
        $id = $rule->add($fields);
        if (!$id) {
            throw new RuntimeException('Falha ao criar regra de politica SLA ' . $spec['name'] . '.');
        }

        plugin_dashglpi_sla_bridge_replace_rule_children((int) $id, $spec['criteria'], $spec['actions']);

        return [
            'id' => (int) $id,
            'name' => $spec['name'],
            'kind' => $spec['kind'],
            'mode' => $spec['mode'],
            'bucket' => $spec['bucket'],
            'priority' => (int) $spec['priority'],
            'status' => 'created',
        ];
    }

    $status = 'existing';
    if ($apply && plugin_dashglpi_sla_bridge_changed($existing, $fields)) {
        $rule = new RuleTicket();
        if (!$rule->update(array_merge(['id' => (int) $existing['id']], $fields))) {
            throw new RuntimeException('Falha ao atualizar regra de politica SLA ' . $spec['name'] . '.');
        }
        $status = 'updated';
    }

    if ($apply && !plugin_dashglpi_sla_bridge_rule_children_match((int) $existing['id'], $spec['criteria'], $spec['actions'])) {
        plugin_dashglpi_sla_bridge_replace_rule_children((int) $existing['id'], $spec['criteria'], $spec['actions']);
        $status = 'updated';
    }

    return [
        'id' => (int) $existing['id'],
        'name' => $spec['name'],
        'kind' => $spec['kind'],
        'mode' => $spec['mode'],
        'bucket' => $spec['bucket'],
        'priority' => (int) $spec['priority'],
        'status' => $status,
    ];
}

function plugin_dashglpi_sla_bridge_reapply_open_tickets(array $payload): array
{
    $entitiesId = (int) ($payload['entity']['id'] ?? 0);
    if ($entitiesId < 0) {
        throw new RuntimeException('Crie/atualize o SLA antes de reaplicar em chamados abertos.');
    }

    $entityIds = plugin_dashglpi_sla_bridge_entity_tree_ids($entitiesId);
    $slaIds = plugin_dashglpi_sla_bridge_sla_ids_by_key($payload['slas']);
    $summary = [
        'applied' => 0,
        'ignored' => 0,
        'errors' => 0,
        'details' => [],
    ];

    foreach (plugin_dashglpi_sla_bridge_priority_map() as $mapping) {
        $bucket = $mapping['bucket'];
        $priority = (int) $mapping['priority'];
        $ttoId = (int) ($slaIds['TTO-' . $bucket] ?? 0);
        $ttrId = (int) ($slaIds['TTR-' . $bucket] ?? 0);

        if ($ttoId <= 0 || $ttrId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar os SLAs ' . $bucket . ' para reaplicar chamados.');
        }

        $result = plugin_dashglpi_sla_bridge_reapply_priority_tickets($entityIds, $priority, $ttoId, $ttrId);
        $summary['applied'] += $result['applied'];
        $summary['ignored'] += $result['ignored'];
        $summary['errors'] += $result['errors'];
        $summary['details'][] = array_merge(['bucket' => $bucket, 'priority' => $priority], $result);
    }

    return [
        'entity' => [
            'id' => $entitiesId,
            'covered_ids' => $entityIds,
            'status' => 'existing',
        ],
        'tickets' => $summary,
    ];
}

function plugin_dashglpi_sla_bridge_reapply_priority_tickets(array $entityIds, int $priority, int $ttoId, int $ttrId): array
{
    global $DB;

    $table = Ticket::getTable();
    $whereBase = [
        $table . '.is_deleted' => 0,
        $table . '.entities_id' => $entityIds,
        $table . '.priority' => $priority,
        'NOT' => [
            $table . '.status' => [
                CommonITILObject::SOLVED,
                CommonITILObject::CLOSED,
            ],
        ],
    ];
    $total = plugin_dashglpi_sla_bridge_count_tickets($table, $whereBase);
    $whereEligible = array_merge($whereBase, [
        $table . '.slas_id_tto' => 0,
        $table . '.slas_id_ttr' => 0,
    ]);
    $iterator = $DB->request([
        'SELECT' => [$table . '.id'],
        'FROM' => $table,
        'WHERE' => $whereEligible,
        'ORDER' => [$table . '.id ASC'],
    ]);

    $applied = 0;
    $errors = 0;
    foreach ($iterator as $row) {
        $ticket = new Ticket();
        $ok = $ticket->update([
            'id' => (int) $row['id'],
            'slas_id_tto' => $ttoId,
            'slas_id_ttr' => $ttrId,
            '_disablenotif' => true,
        ]);

        if ($ok) {
            $applied++;
        } else {
            $errors++;
        }
    }

    return [
        'applied' => $applied,
        'ignored' => max(0, $total - $applied - $errors),
        'errors' => $errors,
    ];
}

function plugin_dashglpi_sla_bridge_reapply_client_policy_open_tickets(array $payload): array
{
    $policy = $payload['policy'] ?? [];
    if (empty($policy['is_active'])) {
        throw new RuntimeException('Politica SLA do cliente esta inativa.');
    }

    $entitiesId = (int) ($policy['entities_id'] ?? $payload['entity']['id'] ?? 0);
    if ($entitiesId < 0) {
        throw new RuntimeException('Selecione uma entidade para reaplicar a politica SLA.');
    }

    $entityIds = !empty($policy['is_recursive'])
        ? plugin_dashglpi_sla_bridge_entity_tree_ids($entitiesId)
        : [$entitiesId];
    $slaIds = plugin_dashglpi_sla_bridge_sla_ids_by_key($payload['slas']);
    $summary = [
        'applied' => 0,
        'ignored' => 0,
        'errors' => 0,
        'tto' => [],
        'ttr' => [],
    ];

    foreach (plugin_dashglpi_sla_bridge_policy_reapply_specs('TTO', (string) $policy['tto_mode'], (string) $policy['tto_fixed_key'], $slaIds) as $spec) {
        $result = plugin_dashglpi_sla_bridge_reapply_policy_field_tickets(
            $entityIds,
            'slas_id_tto',
            (int) $spec['sla_id'],
            $spec['priority'],
            true
        );
        $summary['applied'] += $result['applied'];
        $summary['ignored'] += $result['ignored'];
        $summary['errors'] += $result['errors'];
        $summary['tto'][] = array_merge($spec, $result);
    }

    foreach (plugin_dashglpi_sla_bridge_policy_reapply_specs('TTR', (string) $policy['ttr_mode'], (string) $policy['ttr_fixed_key'], $slaIds) as $spec) {
        $result = plugin_dashglpi_sla_bridge_reapply_policy_field_tickets(
            $entityIds,
            'slas_id_ttr',
            (int) $spec['sla_id'],
            $spec['priority'],
            false
        );
        $summary['applied'] += $result['applied'];
        $summary['ignored'] += $result['ignored'];
        $summary['errors'] += $result['errors'];
        $summary['ttr'][] = array_merge($spec, $result);
    }

    return [
        'entity' => [
            'id' => $entitiesId,
            'covered_ids' => $entityIds,
            'status' => 'existing',
        ],
        'tickets' => $summary,
        'policy' => $policy,
    ];
}

function plugin_dashglpi_sla_bridge_policy_reapply_specs(string $kind, string $mode, string $fixedKey, array $slaIds): array
{
    if ($mode === 'fixed') {
        $slaId = (int) ($slaIds[$fixedKey] ?? 0);
        if ($slaId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar o SLA ' . $fixedKey . ' para reaplicar chamados.');
        }

        return [[
            'kind' => $kind,
            'mode' => 'fixed',
            'bucket' => plugin_dashglpi_sla_bridge_key_bucket($fixedKey),
            'priority' => null,
            'sla_id' => $slaId,
        ]];
    }

    $specs = [];
    foreach (plugin_dashglpi_sla_bridge_priority_map() as $mapping) {
        $bucket = (string) $mapping['bucket'];
        $key = $kind . '-' . $bucket;
        $slaId = (int) ($slaIds[$key] ?? 0);
        if ($slaId <= 0) {
            throw new RuntimeException('Nao foi possivel localizar o SLA ' . $key . ' para reaplicar chamados.');
        }
        $specs[] = [
            'kind' => $kind,
            'mode' => 'priority',
            'bucket' => $bucket,
            'priority' => (int) $mapping['priority'],
            'sla_id' => $slaId,
        ];
    }

    return $specs;
}

function plugin_dashglpi_sla_bridge_reapply_policy_field_tickets(
    array $entityIds,
    string $field,
    int $slaId,
    ?int $priority,
    bool $requiresNoTechnician
): array {
    global $DB;

    $table = Ticket::getTable();
    $whereBase = [
        $table . '.is_deleted' => 0,
        $table . '.entities_id' => $entityIds,
        'NOT' => [
            $table . '.status' => [
                CommonITILObject::SOLVED,
                CommonITILObject::CLOSED,
            ],
        ],
    ];
    if ($priority !== null) {
        $whereBase[$table . '.priority'] = $priority;
    }

    $total = plugin_dashglpi_sla_bridge_count_policy_tickets($whereBase, $requiresNoTechnician);
    $whereEligible = array_merge($whereBase, [
        $table . '.' . $field => 0,
    ]);
    $iterator = $DB->request([
        'SELECT' => [$table . '.id'],
        'FROM' => $table,
        'WHERE' => $whereEligible,
        'ORDER' => [$table . '.id ASC'],
    ]);

    $applied = 0;
    $errors = 0;
    foreach ($iterator as $row) {
        $ticketId = (int) ($row['id'] ?? 0);
        if ($ticketId <= 0) {
            continue;
        }
        if ($requiresNoTechnician && plugin_dashglpi_sla_bridge_ticket_has_user_assignee($ticketId)) {
            continue;
        }

        $ticket = new Ticket();
        $ok = $ticket->update([
            'id' => $ticketId,
            $field => $slaId,
            '_disablenotif' => true,
        ]);

        if ($ok) {
            $applied++;
        } else {
            $errors++;
        }
    }

    return [
        'applied' => $applied,
        'ignored' => max(0, $total - $applied - $errors),
        'errors' => $errors,
    ];
}

function plugin_dashglpi_sla_bridge_count_policy_tickets(array $where, bool $requiresNoTechnician): int
{
    global $DB;

    $table = Ticket::getTable();
    $iterator = $DB->request([
        'SELECT' => [$table . '.id'],
        'FROM' => $table,
        'WHERE' => $where,
        'ORDER' => [$table . '.id ASC'],
    ]);

    $total = 0;
    foreach ($iterator as $row) {
        $ticketId = (int) ($row['id'] ?? 0);
        if ($ticketId <= 0) {
            continue;
        }
        if ($requiresNoTechnician && plugin_dashglpi_sla_bridge_ticket_has_user_assignee($ticketId)) {
            continue;
        }
        $total++;
    }

    return $total;
}

function plugin_dashglpi_sla_bridge_ticket_has_user_assignee(int $ticketId): bool
{
    $ticketUser = new Ticket_User();
    $rows = $ticketUser->find([
        'tickets_id' => $ticketId,
        'type' => CommonITILActor::ASSIGN,
    ], 'id ASC', 1);

    return !empty($rows);
}

function plugin_dashglpi_sla_bridge_entity_tree_ids(int $entitiesId): array
{
    $ids = [$entitiesId];
    $sons = getSonsOf(Entity::getTable(), $entitiesId);
    foreach (array_keys($sons) as $id) {
        $ids[] = (int) $id;
    }

    $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id >= 0)));
    sort($ids, SORT_NUMERIC);

    return $ids;
}

function plugin_dashglpi_sla_bridge_entity_rule_criterion(int $entitiesId, bool $isRecursive): array
{
    return [
        'criteria' => 'entities_id',
        'condition' => $entitiesId === 0
            ? Rule::PATTERN_IS
            : ($isRecursive ? Rule::PATTERN_UNDER : Rule::PATTERN_IS),
        'pattern' => (string) max(0, $entitiesId),
    ];
}

function plugin_dashglpi_sla_bridge_count_tickets(string $table, array $where): int
{
    global $DB;

    $row = $DB->request([
        'COUNT' => 'total',
        'FROM' => $table,
        'WHERE' => $where,
    ])->current();

    return (int) ($row['total'] ?? 0);
}

function plugin_dashglpi_sla_bridge_priority_map(): array
{
    return [
        ['bucket' => 'P1', 'priority' => 6],
        ['bucket' => 'P1', 'priority' => 5],
        ['bucket' => 'P2', 'priority' => 4],
        ['bucket' => 'P3', 'priority' => 3],
        ['bucket' => 'P4', 'priority' => 2],
        ['bucket' => 'P4', 'priority' => 1],
    ];
}

function plugin_dashglpi_sla_bridge_sla_ids_by_key(array $slaResults): array
{
    $ids = [];
    foreach ($slaResults as $sla) {
        $key = (string) ($sla['key'] ?? '');
        if ($key !== '') {
            $ids[$key] = (int) ($sla['id'] ?? 0);
        }
    }

    return $ids;
}

function plugin_dashglpi_sla_bridge_rule_marker(): string
{
    return 'Gerenciado pelo configurador SLA Simples do DashGLPI.';
}

function plugin_dashglpi_sla_bridge_client_policy_rule_marker(): string
{
    return 'Gerenciado pela politica SLA por Cliente do DashGLPI.';
}

function plugin_dashglpi_sla_bridge_reminder_marker(): string
{
    return 'DashGLPI SLA Reminder ';
}

function plugin_dashglpi_sla_bridge_reminder_name(string $key): string
{
    return plugin_dashglpi_sla_bridge_reminder_marker() . strtoupper(trim($key));
}

function plugin_dashglpi_sla_bridge_find_managed_rule(string $name, int $entitiesId): ?array
{
    return plugin_dashglpi_sla_bridge_find_managed_rule_by_marker($name, $entitiesId, plugin_dashglpi_sla_bridge_rule_marker());
}

function plugin_dashglpi_sla_bridge_find_managed_rule_by_marker(string $name, int $entitiesId, string $marker): ?array
{
    $rule = new RuleTicket();
    $rows = $rule->find([
        'name' => $name,
        'sub_type' => RuleTicket::class,
        'entities_id' => $entitiesId,
    ], 'id ASC');

    foreach ($rows as $row) {
        if (str_contains((string) ($row['comment'] ?? ''), $marker)) {
            return $row;
        }
    }

    return null;
}

function plugin_dashglpi_sla_bridge_find_managed_reminder(string $name, int $preferredId = 0): ?array
{
    $item = new SlaLevel();
    if ($preferredId > 0 && $item->getFromDB($preferredId)) {
        return $item->fields;
    }

    $rows = $item->find(['name' => $name], 'id ASC');
    foreach ($rows as $row) {
        if (str_starts_with((string) ($row['name'] ?? ''), plugin_dashglpi_sla_bridge_reminder_marker())) {
            return $row;
        }
    }

    return null;
}

function plugin_dashglpi_sla_bridge_purge_client_policy_rules(int $entitiesId, array $names, array $keepIds): int
{
    $names = array_values(array_unique(array_filter(array_map('strval', $names))));
    $keepIds = array_map('intval', $keepIds);
    $deleted = 0;
    $rule = new RuleTicket();
    $rows = $rule->find([
        'sub_type' => RuleTicket::class,
        'entities_id' => $entitiesId,
    ], 'id ASC');

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        if ($id <= 0 || in_array($id, $keepIds, true)) {
            continue;
        }
        if (!str_contains((string) ($row['comment'] ?? ''), plugin_dashglpi_sla_bridge_client_policy_rule_marker())) {
            continue;
        }
        if (in_array($name, $names, true)) {
            continue;
        }

        $item = new RuleTicket();
        if (!$item->delete(['id' => $id], true)) {
            throw new RuntimeException('Falha ao remover regra obsoleta de politica SLA ' . $name . '.');
        }
        $deleted++;
    }

    return $deleted;
}

function plugin_dashglpi_sla_bridge_purge_obsolete_rules(array $names, array $keepIds): int
{
    $names = array_values(array_unique(array_filter(array_map('strval', $names))));
    $keepIds = array_map('intval', $keepIds);
    if (!$names) {
        return 0;
    }

    $deleted = 0;
    $rule = new RuleTicket();
    $rows = $rule->find([
        'sub_type' => RuleTicket::class,
    ], 'id ASC');

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        if ($id <= 0 || in_array($id, $keepIds, true) || !in_array($name, $names, true)) {
            continue;
        }
        if (!str_contains((string) ($row['comment'] ?? ''), plugin_dashglpi_sla_bridge_rule_marker())) {
            continue;
        }

        $item = new RuleTicket();
        if (!$item->delete(['id' => $id], true)) {
            throw new RuntimeException('Falha ao remover regra obsoleta de SLA ' . $name . '.');
        }
        $deleted++;
    }

    return $deleted;
}

function plugin_dashglpi_sla_bridge_purge_obsolete_reminders(array $names, array $keepIds): int
{
    $names = array_values(array_unique(array_filter(array_map('strval', $names))));
    $keepIds = array_map('intval', $keepIds);
    if ($names === []) {
        return 0;
    }

    $deleted = 0;
    $item = new SlaLevel();
    $rows = $item->find([], 'id ASC');
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        if ($id <= 0 || in_array($id, $keepIds, true) || !in_array($name, $names, true)) {
            continue;
        }
        if (!str_starts_with($name, plugin_dashglpi_sla_bridge_reminder_marker())) {
            continue;
        }

        $reminder = new SlaLevel();
        if (!$reminder->delete(['id' => $id], true)) {
            throw new RuntimeException('Falha ao remover alerta SLA obsoleto ' . $name . '.');
        }
        $deleted++;
    }

    return $deleted;
}

function plugin_dashglpi_sla_bridge_rule_children_match(int $rulesId, array $criteria, array $actions): bool
{
    return plugin_dashglpi_sla_bridge_rule_criteria($rulesId) === plugin_dashglpi_sla_bridge_normalize_criteria($criteria)
        && plugin_dashglpi_sla_bridge_rule_actions($rulesId) === plugin_dashglpi_sla_bridge_normalize_actions($actions);
}

function plugin_dashglpi_sla_bridge_replace_rule_children(int $rulesId, array $criteria, array $actions): void
{
    foreach ((new RuleCriteria())->find(['rules_id' => $rulesId], 'id ASC') as $row) {
        $item = new RuleCriteria();
        if (!$item->delete(['id' => (int) $row['id']], true)) {
            throw new RuntimeException('Falha ao remover criterio antigo da regra de SLA.');
        }
    }

    foreach ((new RuleAction())->find(['rules_id' => $rulesId], 'id ASC') as $row) {
        $item = new RuleAction();
        if (!$item->delete(['id' => (int) $row['id']], true)) {
            throw new RuntimeException('Falha ao remover acao antiga da regra de SLA.');
        }
    }

    foreach ($criteria as $criterion) {
        $item = new RuleCriteria();
        if (!$item->add(array_merge(['rules_id' => $rulesId], $criterion))) {
            throw new RuntimeException('Falha ao criar criterio da regra de SLA.');
        }
    }

    foreach ($actions as $action) {
        $item = new RuleAction();
        if (!$item->add(array_merge(['rules_id' => $rulesId], $action))) {
            throw new RuntimeException('Falha ao criar acao da regra de SLA.');
        }
    }
}

function plugin_dashglpi_sla_bridge_reminder_children_match(int $slalevelsId, array $criteria, array $actions): bool
{
    return plugin_dashglpi_sla_bridge_reminder_criteria($slalevelsId) === plugin_dashglpi_sla_bridge_normalize_criteria($criteria)
        && plugin_dashglpi_sla_bridge_reminder_actions($slalevelsId) === plugin_dashglpi_sla_bridge_normalize_actions($actions);
}

function plugin_dashglpi_sla_bridge_replace_reminder_children(int $slalevelsId, array $criteria, array $actions): void
{
    foreach ((new SlaLevelCriteria())->find(['slalevels_id' => $slalevelsId], 'id ASC') as $row) {
        $item = new SlaLevelCriteria();
        if (!$item->delete(['id' => (int) $row['id']], true)) {
            throw new RuntimeException('Falha ao remover critério antigo do alerta SLA.');
        }
    }

    foreach ((new SlaLevelAction())->find(['slalevels_id' => $slalevelsId], 'id ASC') as $row) {
        $item = new SlaLevelAction();
        if (!$item->delete(['id' => (int) $row['id']], true)) {
            throw new RuntimeException('Falha ao remover ação antiga do alerta SLA.');
        }
    }

    foreach ($criteria as $criterion) {
        $item = new SlaLevelCriteria();
        if (!$item->add(array_merge(['slalevels_id' => $slalevelsId], $criterion))) {
            throw new RuntimeException('Falha ao criar critério do alerta SLA.');
        }
    }

    foreach ($actions as $action) {
        $item = new SlaLevelAction();
        if (!$item->add(array_merge(['slalevels_id' => $slalevelsId], $action))) {
            throw new RuntimeException('Falha ao criar ação do alerta SLA.');
        }
    }
}

function plugin_dashglpi_sla_bridge_rule_criteria(int $rulesId): array
{
    $rows = [];
    foreach ((new RuleCriteria())->find(['rules_id' => $rulesId], 'id ASC') as $row) {
        $rows[] = [
            'criteria' => (string) ($row['criteria'] ?? ''),
            'condition' => (int) ($row['condition'] ?? 0),
            'pattern' => (string) ($row['pattern'] ?? ''),
        ];
    }

    return plugin_dashglpi_sla_bridge_normalize_criteria($rows);
}

function plugin_dashglpi_sla_bridge_rule_actions(int $rulesId): array
{
    $rows = [];
    foreach ((new RuleAction())->find(['rules_id' => $rulesId], 'id ASC') as $row) {
        $rows[] = [
            'action_type' => (string) ($row['action_type'] ?? ''),
            'field' => (string) ($row['field'] ?? ''),
            'value' => (string) ($row['value'] ?? ''),
        ];
    }

    return plugin_dashglpi_sla_bridge_normalize_actions($rows);
}

function plugin_dashglpi_sla_bridge_reminder_criteria(int $slalevelsId): array
{
    $rows = [];
    foreach ((new SlaLevelCriteria())->find(['slalevels_id' => $slalevelsId], 'id ASC') as $row) {
        $rows[] = [
            'criteria' => (string) ($row['criteria'] ?? ''),
            'condition' => (int) ($row['condition'] ?? 0),
            'pattern' => (string) ($row['pattern'] ?? ''),
        ];
    }

    return plugin_dashglpi_sla_bridge_normalize_criteria($rows);
}

function plugin_dashglpi_sla_bridge_reminder_actions(int $slalevelsId): array
{
    $rows = [];
    foreach ((new SlaLevelAction())->find(['slalevels_id' => $slalevelsId], 'id ASC') as $row) {
        $rows[] = [
            'action_type' => (string) ($row['action_type'] ?? ''),
            'field' => (string) ($row['field'] ?? ''),
            'value' => (string) ($row['value'] ?? ''),
        ];
    }

    return plugin_dashglpi_sla_bridge_normalize_actions($rows);
}

function plugin_dashglpi_sla_bridge_normalize_criteria(array $rows): array
{
    $normalized = [];
    foreach ($rows as $row) {
        $normalized[] = [
            'criteria' => (string) ($row['criteria'] ?? ''),
            'condition' => (int) ($row['condition'] ?? 0),
            'pattern' => (string) ($row['pattern'] ?? ''),
        ];
    }

    usort($normalized, static fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
    return $normalized;
}

function plugin_dashglpi_sla_bridge_normalize_actions(array $rows): array
{
    $normalized = [];
    foreach ($rows as $row) {
        $normalized[] = [
            'action_type' => (string) ($row['action_type'] ?? ''),
            'field' => (string) ($row['field'] ?? ''),
            'value' => (string) ($row['value'] ?? ''),
        ];
    }

    usort($normalized, static fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
    return $normalized;
}

function plugin_dashglpi_sla_bridge_replace_segments(int $calendarsId, int $entitiesId, array $segments): int
{
    $deleted = 0;
    $existing = (new CalendarSegment())->find(['calendars_id' => $calendarsId], 'id ASC');

    foreach ($existing as $row) {
        $calendarSegment = new CalendarSegment();
        if (!$calendarSegment->delete(['id' => (int) $row['id']], true)) {
            throw new RuntimeException('Falha ao remover periodo antigo do calendario.');
        }
        $deleted++;
    }

    $created = plugin_dashglpi_sla_bridge_create_segments($calendarsId, $entitiesId, $segments);

    $calendar = new Calendar();
    if ($calendar->getFromDB($calendarsId)) {
        $calendar->updateDurationCache($calendarsId);
    }

    return $deleted + $created;
}

function plugin_dashglpi_sla_bridge_create_segments(int $calendarsId, int $entitiesId, array $segments): int
{
    $added = 0;
    foreach ($segments as $segment) {
        $calendarSegment = new CalendarSegment();
        $id = $calendarSegment->add([
            'calendars_id' => $calendarsId,
            'entities_id' => $entitiesId,
            'is_recursive' => 0,
            'day' => $segment['day'],
            'begin' => $segment['begin'],
            'end' => $segment['end'],
        ]);

        if (!$id) {
            throw new RuntimeException('Falha ao criar periodo do calendario.');
        }

        $added++;
    }

    return $added;
}

function plugin_dashglpi_sla_bridge_calendar_is_managed(array $calendar): bool
{
    return str_contains((string) ($calendar['comment'] ?? ''), 'configurador SLA Simples do DashGLPI');
}

function plugin_dashglpi_sla_bridge_find_sla(array $input): ?array
{
    if ((int) $input['id'] > 0) {
        $sla = new SLA();
        if ($sla->getFromDB((int) $input['id'])) {
            return $sla->fields;
        }
    }

    return plugin_dashglpi_sla_bridge_find_one(SLA::class, [
        'slms_id' => $input['slms_id'],
        'name' => $input['name'],
        'type' => $input['type'],
    ]);
}

function plugin_dashglpi_sla_bridge_find_one(string $class, array $criteria, int $preferredId = 0): ?array
{
    $item = new $class();
    if ($preferredId > 0 && $item->getFromDB($preferredId)) {
        return $item->fields;
    }

    $rows = $item->find($criteria, 'id ASC', 1);
    if (!$rows) {
        return null;
    }

    $row = reset($rows);
    return is_array($row) ? $row : null;
}

function plugin_dashglpi_sla_bridge_changed(array $existing, array $fields): bool
{
    foreach ($fields as $key => $value) {
        if (!array_key_exists($key, $existing)) {
            continue;
        }

        if ((string) $existing[$key] !== (string) $value) {
            return true;
        }
    }

    return false;
}

function plugin_dashglpi_sla_bridge_duration_to_seconds(int $value, string $unit): int
{
    $value = max(0, $value);

    return match ($unit) {
        'minute' => $value * 60,
        'hour' => $value * HOUR_TIMESTAMP,
        'day' => $value * DAY_TIMESTAMP,
        'month' => $value * MONTH_TIMESTAMP,
        default => 0,
    };
}

function plugin_dashglpi_sla_bridge_time(string $time): ?string
{
    $time = trim($time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return null;
    }

    [$hour, $minute, $second] = array_map('intval', explode(':', $time));
    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function plugin_dashglpi_sla_bridge_clear_glpi_cache(): array
{
    $cacheDir = plugin_dashglpi_sla_bridge_glpi_cache_dir();
    if (!is_dir($cacheDir)) {
        throw new RuntimeException('Diretorio de cache do GLPI nao encontrado.');
    }
    if (!is_writable($cacheDir)) {
        throw new RuntimeException('Diretorio de cache do GLPI sem permissao de escrita.');
    }

    $entries = scandir($cacheDir);
    if ($entries === false) {
        throw new RuntimeException('Falha ao listar o diretorio de cache do GLPI.');
    }

    $removedEntries = 0;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $cacheDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path) && !is_link($path)) {
            Toolbox::deleteDir($path);
        } else {
            if (!@unlink($path)) {
                throw new RuntimeException('Falha ao remover um item do cache do GLPI.');
            }
        }

        $removedEntries++;
    }

    return [
        'cache_path' => $cacheDir,
        'removed_entries' => $removedEntries,
        'cleared_at' => date('Y-m-d H:i:s'),
        'command' => 'rm -rf ' . rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*',
    ];
}

function plugin_dashglpi_sla_bridge_glpi_cache_dir(): string
{
    $candidates = [];

    if (defined('GLPI_VAR_DIR')) {
        $glpiVarDir = rtrim((string) GLPI_VAR_DIR, DIRECTORY_SEPARATOR);
        if ($glpiVarDir !== '') {
            $candidates[] = $glpiVarDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . '_cache';
            $candidates[] = $glpiVarDir . DIRECTORY_SEPARATOR . '_cache';
        }
    }

    $candidates[] = '/var/glpi/files/_cache';
    $candidates[] = '/var/glpi/_cache';

    foreach ($candidates as $candidate) {
        $realPath = realpath($candidate);
        if ($realPath !== false && is_dir($realPath)) {
            return $realPath;
        }
    }

    throw new RuntimeException('Diretorio de cache do GLPI nao encontrado.');
}

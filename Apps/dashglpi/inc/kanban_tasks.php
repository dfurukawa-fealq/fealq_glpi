<?php

require_once __DIR__ . '/ticket_create.php';

const DASHGLPI_KANBAN_TASKS_TABLE = 'glpi_plugin_dashglpi_kanban_tasks';
const DASHGLPI_KANBAN_TASK_OWNERS_TABLE = 'glpi_plugin_dashglpi_kanban_task_owners';

function dashglpi_kanban_tasks_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    dashglpi_db()->exec(
        'CREATE TABLE IF NOT EXISTS `' . DASHGLPI_KANBAN_TASKS_TABLE . "` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `content` TEXT NULL,
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `priority` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `nseq` INT UNSIGNED NOT NULL DEFAULT 0,
            `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `owner_users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `creator_users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `due_date` DATETIME NULL,
            `is_deleted` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dashglpi_kanban_tasks_entity_status` (`entities_id`, `status`, `nseq`, `is_deleted`),
            KEY `idx_dashglpi_kanban_tasks_owner` (`owner_users_id`, `is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    dashglpi_kanban_tasks_ensure_column('nseq', 'ALTER TABLE `' . DASHGLPI_KANBAN_TASKS_TABLE . '` ADD COLUMN `nseq` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `priority`');

    dashglpi_db()->exec(
        'CREATE TABLE IF NOT EXISTS `' . DASHGLPI_KANBAN_TASK_OWNERS_TABLE . "` (
            `kanban_tasks_id` INT UNSIGNED NOT NULL,
            `users_id` INT UNSIGNED NOT NULL,
            `nseq` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`kanban_tasks_id`, `users_id`),
            KEY `idx_dashglpi_kanban_task_owners_user` (`users_id`, `kanban_tasks_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $done = true;
}

function dashglpi_kanban_tasks_ensure_column(string $columnName, string $alterSql): void
{
    $pdo = dashglpi_db();
    $exists = $pdo->query(
        'SHOW COLUMNS FROM `' . DASHGLPI_KANBAN_TASKS_TABLE . '` LIKE ' . $pdo->quote($columnName)
    )->fetch();

    if (!$exists) {
        $pdo->exec($alterSql);
    }
}

function dashglpi_kanban_task_valid_status(int $status): int
{
    return in_array($status, [1, 2, 3, 4, 5, 6], true) ? $status : 1;
}

function dashglpi_kanban_task_default_entity_id(): int
{
    $scope = dashglpi_ticket_create_scope();
    return max(0, (int) ($scope['default_entity_id'] ?? 0));
}

function dashglpi_kanban_task_entity_id(array $input, ?int $fallbackEntityId = null): int
{
    $scope = dashglpi_ticket_create_scope();
    $defaultEntityId = $fallbackEntityId ?? (int) ($scope['default_entity_id'] ?? 0);
    $requestedEntityId = array_key_exists('entities_id', $input)
        ? (int) $input['entities_id']
        : $defaultEntityId;

    return dashglpi_ticket_create_assert_entity_id($scope, $requestedEntityId);
}

function dashglpi_kanban_task_owner_id(array $input, int $fallbackUserId): int
{
    $ownerId = max(0, (int) ($input['owner_users_id'] ?? 0));
    if ($ownerId <= 0) {
        return $fallbackUserId;
    }

    $row = dashglpi_fetch_one(
        "SELECT id
         FROM glpi_users
         WHERE id = ?
           AND is_active = 1
           AND is_deleted = 0
         LIMIT 1",
        [$ownerId]
    );

    return $row ? $ownerId : $fallbackUserId;
}

function dashglpi_kanban_task_owner_ids(array $input, int $fallbackUserId): array
{
    $raw = $input['owner_user_ids'] ?? null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    if (isset($input['owner_users_id'])) {
        array_unshift($raw, $input['owner_users_id']);
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = max(0, (int) $value);
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    if (!$ids && $fallbackUserId > 0) {
        $ids[] = $fallbackUserId;
    }
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $validRows = dashglpi_fetch_all(
        "SELECT id
         FROM glpi_users
         WHERE id IN ($placeholders)
           AND is_active = 1
           AND is_deleted = 0",
        $ids
    );
    $valid = array_map('intval', array_column($validRows, 'id'));
    $filtered = array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $valid, true)));

    return $filtered ?: [$fallbackUserId];
}

function dashglpi_kanban_task_sync_owners(int $taskId, array $ownerIds): void
{
    $pdo = dashglpi_db();
    $pdo->prepare('DELETE FROM `' . DASHGLPI_KANBAN_TASK_OWNERS_TABLE . '` WHERE kanban_tasks_id = ?')->execute([$taskId]);
    $insert = $pdo->prepare(
        'INSERT INTO `' . DASHGLPI_KANBAN_TASK_OWNERS_TABLE . '` (kanban_tasks_id, users_id, nseq) VALUES (?, ?, ?)'
    );
    foreach (array_values($ownerIds) as $index => $ownerId) {
        $insert->execute([$taskId, (int) $ownerId, $index]);
    }
}

function dashglpi_kanban_task_owners(int $taskId, int $fallbackOwnerId, string $fallbackOwnerName): array
{
    $label = dashglpi_kanban_task_user_label_sql('u');
    $rows = dashglpi_fetch_all(
        "SELECT kto.users_id AS id, $label AS name
         FROM `" . DASHGLPI_KANBAN_TASK_OWNERS_TABLE . "` kto
         INNER JOIN glpi_users u ON u.id = kto.users_id
         WHERE kto.kanban_tasks_id = ?
           AND u.is_active = 1
           AND u.is_deleted = 0
         ORDER BY kto.nseq ASC, kto.users_id ASC",
        [$taskId]
    );
    if ($rows) {
        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? '-'),
        ], $rows);
    }

    return $fallbackOwnerId > 0 ? [[
        'id' => $fallbackOwnerId,
        'name' => $fallbackOwnerName !== '' ? $fallbackOwnerName : ('Usuário #' . $fallbackOwnerId),
    ]] : [];
}

function dashglpi_kanban_task_scope(bool $myTasks, string $alias = 'kt'): array
{
    $context = dashglpi_current_user_context();
    $userId = (int) ($context['user_id'] ?? 0);

    if ($myTasks) {
        if ($userId <= 0) {
            return ['sql' => ' AND 1 = 0', 'params' => []];
        }

        return [
            'sql' => " AND ($alias.owner_users_id = ? OR EXISTS (
                SELECT 1
                FROM `" . DASHGLPI_KANBAN_TASK_OWNERS_TABLE . "` kto_scope
                WHERE kto_scope.kanban_tasks_id = $alias.id
                  AND kto_scope.users_id = ?
            ))",
            'params' => [$userId, $userId],
        ];
    }

    return dashglpi_scoped_entity_sql($alias . '.entities_id');
}

function dashglpi_kanban_task_filter_my_tasks(array $filters): bool
{
    $value = $filters['my_tasks'] ?? '1';
    return !in_array((string) $value, ['0', 'false', 'off', 'no'], true);
}

function dashglpi_kanban_task_user_label_sql(string $alias): string
{
    return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(NULLIF($alias.firstname, ''), $alias.name, ''), ' ', COALESCE($alias.realname, ''))), ''), $alias.name, '-')";
}

function dashglpi_kanban_task_status_label(int $status): string
{
    return match ($status) {
        1 => 'Aberto',
        3 => 'Planejado',
        2 => 'Em Atendimento',
        4 => 'Pendente',
        5 => 'Solucionado',
        6 => 'Fechado',
        default => 'Aberto',
    };
}

function dashglpi_kanban_task_stage_step(int $status): int
{
    return match ($status) {
        1 => 1,
        3 => 2,
        2 => 3,
        4 => 4,
        5 => 5,
        6 => 6,
        default => 1,
    };
}

function dashglpi_kanban_task_description_excerpt(string $content): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, 180, '...', 'UTF-8');
    }

    return strlen($text) > 180 ? substr($text, 0, 177) . '...' : $text;
}

function dashglpi_kanban_tasks_list(array $filters = []): array
{
    dashglpi_kanban_tasks_ensure_schema();

    $scope = dashglpi_kanban_task_scope(dashglpi_kanban_task_filter_my_tasks($filters), 'kt');
    $ownerLabel = dashglpi_kanban_task_user_label_sql('owner');
    $creatorLabel = dashglpi_kanban_task_user_label_sql('creator');

    $rows = dashglpi_fetch_all(
        "SELECT kt.id, kt.name, kt.content, kt.status, kt.priority, kt.nseq, kt.entities_id,
                kt.owner_users_id, kt.creator_users_id, kt.due_date,
                kt.date_creation AS date, kt.date_mod,
                COALESCE(e.completename, e.name, 'Entidade raiz') AS entity_name,
                $ownerLabel AS owner_name,
                $creatorLabel AS creator_name
         FROM `" . DASHGLPI_KANBAN_TASKS_TABLE . "` kt
         LEFT JOIN glpi_entities e ON e.id = kt.entities_id
         LEFT JOIN glpi_users owner ON owner.id = kt.owner_users_id
         LEFT JOIN glpi_users creator ON creator.id = kt.creator_users_id
         WHERE kt.is_deleted = 0" . $scope['sql'] . "
         ORDER BY kt.status ASC, kt.nseq ASC, kt.date_mod DESC, kt.id DESC
         LIMIT 500",
        $scope['params']
    );

    return array_map('dashglpi_kanban_task_view_model', $rows);
}

function dashglpi_kanban_task_view_model(array $row): array
{
    $status = dashglpi_kanban_task_valid_status((int) ($row['status'] ?? 1));
    $ownerName = trim((string) ($row['owner_name'] ?? ''));
    if ($ownerName === '') {
        $ownerName = '-';
    }
    $ownerId = (int) ($row['owner_users_id'] ?? 0);
    $owners = dashglpi_kanban_task_owners((int) ($row['id'] ?? 0), $ownerId, $ownerName);
    $ownerNames = array_values(array_filter(array_map(static fn (array $owner): string => trim((string) ($owner['name'] ?? '')), $owners)));
    $displayOwnerName = $ownerNames ? implode(', ', $ownerNames) : $ownerName;

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'content' => (string) ($row['content'] ?? ''),
        'description_excerpt' => dashglpi_kanban_task_description_excerpt((string) ($row['content'] ?? '')),
        'status' => $status,
        'kanban_status' => $status,
        'stage' => dashglpi_kanban_task_status_label($status),
        'stage_step' => dashglpi_kanban_task_stage_step($status),
        'status_label' => dashglpi_kanban_task_status_label($status),
        'priority' => max(1, min(5, (int) ($row['priority'] ?? 3))),
        'priority_label' => 'Prioridade ' . max(1, min(5, (int) ($row['priority'] ?? 3))),
        'nseq' => (int) ($row['nseq'] ?? 0),
        'entities_id' => (int) ($row['entities_id'] ?? 0),
        'entity_name' => (string) ($row['entity_name'] ?? 'Entidade raiz'),
        'owner_users_id' => $ownerId,
        'owner_user_ids' => array_map(static fn (array $owner): int => (int) ($owner['id'] ?? 0), $owners),
        'owner_names' => $ownerNames,
        'owners' => $owners,
        'technician_name' => $displayOwnerName,
        'owner_name' => $displayOwnerName,
        'requester_name' => (string) ($row['creator_name'] ?? $ownerName),
        'category' => 'Tarefa DashGLPI',
        'date' => (string) ($row['date'] ?? ''),
        'date_mod' => (string) ($row['date_mod'] ?? ''),
        'time_to_resolve' => (string) ($row['due_date'] ?? ''),
        'due_date' => (string) ($row['due_date'] ?? ''),
        'itemtype' => 'dashglpi_task',
        'row_key' => 'dashglpi_task:' . (int) ($row['id'] ?? 0),
        'readonly' => 0,
        'is_dashglpi_task' => 1,
        'notification_failed' => 0,
        'satisfaction_pending' => 0,
        'global_validation' => 0,
        'technician_count' => max(1, count($owners)),
        'group_count' => 0,
        'attachments_count' => 0,
        'followups_count' => 0,
        'active_sla_kind' => null,
        'active_sla_percent' => 0,
        'seconds_left' => null,
        'active_sla_overdue' => 0,
        'sla_status' => 'ok',
        'can_take' => 0,
    ];
}

function dashglpi_kanban_task_create(array $input): array
{
    dashglpi_kanban_tasks_ensure_schema();

    $context = dashglpi_current_user_context();
    $userId = (int) ($context['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Usuário não identificado.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Informe o título da tarefa.');
    }

    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 255, 'UTF-8');
    } else {
        $name = substr($name, 0, 255);
    }

    $status = dashglpi_kanban_task_valid_status((int) ($input['status'] ?? 1));
    $priority = max(1, min(5, (int) ($input['priority'] ?? 3)));
    $entityId = dashglpi_kanban_task_entity_id($input);
    $ownerIds = dashglpi_kanban_task_owner_ids($input, $userId);
    $ownerId = (int) ($ownerIds[0] ?? $userId);
    $content = trim((string) ($input['content'] ?? ''));
    $nseq = dashglpi_kanban_task_next_nseq($status, $entityId);

    $pdo = dashglpi_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
                (name, content, status, priority, nseq, entities_id, owner_users_id, creator_users_id, date_creation, date_mod)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $content, $status, $priority, $nseq, $entityId, $ownerId, $userId]);
        $taskId = (int) $pdo->lastInsertId();
        dashglpi_kanban_task_sync_owners($taskId, $ownerIds);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return dashglpi_kanban_task_get($taskId);
}

function dashglpi_kanban_task_get(int $taskId): array
{
    dashglpi_kanban_tasks_ensure_schema();
    $scope = dashglpi_scoped_entity_sql('kt.entities_id');
    $ownerLabel = dashglpi_kanban_task_user_label_sql('owner');
    $creatorLabel = dashglpi_kanban_task_user_label_sql('creator');
    $row = dashglpi_fetch_one(
        "SELECT kt.id, kt.name, kt.content, kt.status, kt.priority, kt.nseq, kt.entities_id,
                kt.owner_users_id, kt.creator_users_id, kt.due_date,
                kt.date_creation AS date, kt.date_mod,
                COALESCE(e.completename, e.name, 'Entidade raiz') AS entity_name,
                $ownerLabel AS owner_name,
                $creatorLabel AS creator_name
         FROM `" . DASHGLPI_KANBAN_TASKS_TABLE . "` kt
         LEFT JOIN glpi_entities e ON e.id = kt.entities_id
         LEFT JOIN glpi_users owner ON owner.id = kt.owner_users_id
         LEFT JOIN glpi_users creator ON creator.id = kt.creator_users_id
         WHERE kt.id = ?
           AND kt.is_deleted = 0" . $scope['sql'] . "
         LIMIT 1",
        array_merge([$taskId], $scope['params'])
    );

    if ($row) {
        return dashglpi_kanban_task_view_model($row);
    }

    throw new RuntimeException('Tarefa não encontrada ou fora do seu escopo.');
}

function dashglpi_kanban_task_assert_access(int $taskId): void
{
    $context = dashglpi_current_user_context();
    if (!empty($context['is_admin_bypass'])) {
        $row = dashglpi_fetch_one(
            'SELECT id FROM `' . DASHGLPI_KANBAN_TASKS_TABLE . '` WHERE id = ? AND is_deleted = 0 LIMIT 1',
            [$taskId]
        );
        if ($row) {
            return;
        }
    } else {
        $scope = dashglpi_scoped_entity_sql('kt.entities_id');
        $row = dashglpi_fetch_one(
            'SELECT kt.id FROM `' . DASHGLPI_KANBAN_TASKS_TABLE . '` kt WHERE kt.id = ? AND kt.is_deleted = 0' . $scope['sql'] . ' LIMIT 1',
            array_merge([$taskId], $scope['params'])
        );
        if ($row) {
            return;
        }
    }

    throw new RuntimeException('Tarefa não encontrada ou fora do seu escopo.');
}

function dashglpi_kanban_task_update_status(int $taskId, int $status): array
{
    dashglpi_kanban_tasks_ensure_schema();
    dashglpi_kanban_task_assert_access($taskId);
    $status = dashglpi_kanban_task_valid_status($status);
    $task = dashglpi_kanban_task_get($taskId);
    $nseq = (int) ($task['status'] ?? 0) === $status
        ? (int) ($task['nseq'] ?? 0)
        : dashglpi_kanban_task_next_nseq($status, (int) ($task['entities_id'] ?? 0));

    $stmt = dashglpi_db()->prepare(
        'UPDATE `' . DASHGLPI_KANBAN_TASKS_TABLE . '` SET status = ?, nseq = ?, date_mod = NOW() WHERE id = ? AND is_deleted = 0'
    );
    $stmt->execute([$status, $nseq, $taskId]);

    return dashglpi_kanban_task_get($taskId);
}

function dashglpi_kanban_task_next_nseq(int $status, int $entityId): int
{
    dashglpi_kanban_tasks_ensure_schema();
    $row = dashglpi_fetch_one(
        'SELECT MAX(nseq) AS max_nseq
         FROM `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
         WHERE status = ? AND entities_id = ? AND is_deleted = 0',
        [dashglpi_kanban_task_valid_status($status), max(0, $entityId)]
    );

    return $row && $row['max_nseq'] !== null ? ((int) $row['max_nseq']) + 1 : 0;
}

function dashglpi_kanban_task_reorder(int $taskId, int $status, array $orderedIds): array
{
    dashglpi_kanban_tasks_ensure_schema();
    dashglpi_kanban_task_assert_access($taskId);
    $status = dashglpi_kanban_task_valid_status($status);

    $ids = [];
    foreach ($orderedIds as $rawId) {
        $id = max(0, (int) $rawId);
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    if (!in_array($taskId, $ids, true)) {
        $ids[] = $taskId;
    }

    $pdo = dashglpi_db();
    $pdo->beginTransaction();
    try {
        $move = $pdo->prepare(
            'UPDATE `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
             SET status = ?, date_mod = NOW()
             WHERE id = ? AND is_deleted = 0'
        );
        $move->execute([$status, $taskId]);

        $seq = 0;
        $update = $pdo->prepare(
            'UPDATE `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
             SET nseq = ?, date_mod = NOW()
             WHERE id = ? AND status = ? AND is_deleted = 0'
        );
        foreach ($ids as $id) {
            dashglpi_kanban_task_assert_access($id);
            $update->execute([$seq, $id, $status]);
            $seq++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return dashglpi_kanban_task_get($taskId);
}

function dashglpi_kanban_task_update(int $taskId, array $input): array
{
    dashglpi_kanban_tasks_ensure_schema();
    dashglpi_kanban_task_assert_access($taskId);

    $context = dashglpi_current_user_context();
    $userId = (int) ($context['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Usuário não identificado.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Informe o título da tarefa.');
    }

    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 255, 'UTF-8');
    } else {
        $name = substr($name, 0, 255);
    }

    $content = trim((string) ($input['content'] ?? ''));
    $status = dashglpi_kanban_task_valid_status((int) ($input['status'] ?? 1));
    $priority = max(1, min(5, (int) ($input['priority'] ?? 3)));
    $ownerIds = dashglpi_kanban_task_owner_ids($input, $userId);
    $ownerId = (int) ($ownerIds[0] ?? $userId);
    $current = dashglpi_kanban_task_get($taskId);
    $entityId = dashglpi_kanban_task_entity_id($input, (int) ($current['entities_id'] ?? 0));
    $nseq = (int) ($current['status'] ?? 0) === $status
        && (int) ($current['entities_id'] ?? 0) === $entityId
        ? (int) ($current['nseq'] ?? 0)
        : dashglpi_kanban_task_next_nseq($status, $entityId);

    $pdo = dashglpi_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
             SET name = ?, content = ?, status = ?, priority = ?, nseq = ?, entities_id = ?, owner_users_id = ?, date_mod = NOW()
             WHERE id = ? AND is_deleted = 0'
        );
        $stmt->execute([$name, $content, $status, $priority, $nseq, $entityId, $ownerId, $taskId]);
        dashglpi_kanban_task_sync_owners($taskId, $ownerIds);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return dashglpi_kanban_task_get($taskId);
}

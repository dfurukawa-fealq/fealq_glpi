<?php

require_once __DIR__ . '/ticket_create.php';

const DASHGLPI_KANBAN_TASKS_TABLE = 'glpi_plugin_dashglpi_kanban_tasks';

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
            `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `owner_users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `creator_users_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `due_date` DATETIME NULL,
            `is_deleted` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dashglpi_kanban_tasks_entity_status` (`entities_id`, `status`, `is_deleted`),
            KEY `idx_dashglpi_kanban_tasks_owner` (`owner_users_id`, `is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $done = true;
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

function dashglpi_kanban_task_scope(bool $myTasks, string $alias = 'kt'): array
{
    $context = dashglpi_current_user_context();
    $userId = (int) ($context['user_id'] ?? 0);

    if ($myTasks) {
        if ($userId <= 0) {
            return ['sql' => ' AND 1 = 0', 'params' => []];
        }

        return [
            'sql' => " AND $alias.owner_users_id = ?",
            'params' => [$userId],
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
        "SELECT kt.id, kt.name, kt.content, kt.status, kt.priority, kt.entities_id,
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
         ORDER BY kt.date_mod DESC, kt.id DESC
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
        'entities_id' => (int) ($row['entities_id'] ?? 0),
        'entity_name' => (string) ($row['entity_name'] ?? 'Entidade raiz'),
        'technician_name' => $ownerName,
        'owner_name' => $ownerName,
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
        'technician_count' => 1,
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
    $entityId = dashglpi_kanban_task_default_entity_id();
    $ownerId = dashglpi_kanban_task_owner_id($input, $userId);
    $content = trim((string) ($input['content'] ?? ''));

    $stmt = dashglpi_db()->prepare(
        'INSERT INTO `' . DASHGLPI_KANBAN_TASKS_TABLE . '`
            (name, content, status, priority, entities_id, owner_users_id, creator_users_id, date_creation, date_mod)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$name, $content, $status, $priority, $entityId, $ownerId, $userId]);

    $taskId = (int) dashglpi_db()->lastInsertId();
    return dashglpi_kanban_task_get($taskId);
}

function dashglpi_kanban_task_get(int $taskId): array
{
    dashglpi_kanban_tasks_ensure_schema();
    $scope = dashglpi_scoped_entity_sql('kt.entities_id');
    $ownerLabel = dashglpi_kanban_task_user_label_sql('owner');
    $creatorLabel = dashglpi_kanban_task_user_label_sql('creator');
    $row = dashglpi_fetch_one(
        "SELECT kt.id, kt.name, kt.content, kt.status, kt.priority, kt.entities_id,
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

    $stmt = dashglpi_db()->prepare(
        'UPDATE `' . DASHGLPI_KANBAN_TASKS_TABLE . '` SET status = ?, date_mod = NOW() WHERE id = ? AND is_deleted = 0'
    );
    $stmt->execute([$status, $taskId]);

    return dashglpi_kanban_task_get($taskId);
}

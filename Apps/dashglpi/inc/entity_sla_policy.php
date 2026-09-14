<?php

const DASHGLPI_SLA_CLIENT_POLICIES_TABLE = 'glpi_plugin_dashglpi_sla_client_policies';

function dashglpi_sla_policy_ensure_table(): void
{
    dashglpi_db()->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_SLA_CLIENT_POLICIES_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entities_id INT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_recursive TINYINT(1) NOT NULL DEFAULT 1,
            tto_mode VARCHAR(20) NOT NULL DEFAULT 'fixed',
            tto_fixed_key VARCHAR(20) NOT NULL DEFAULT 'TTO-P1',
            ttr_mode VARCHAR(20) NOT NULL DEFAULT 'priority',
            ttr_fixed_key VARCHAR(20) NOT NULL DEFAULT 'TTR-P1',
            managed_ids JSON NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_sla_policy_entity (entities_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dashglpi_sla_policy_defaults(int $entitiesId = 0): array
{
    return [
        'id' => 0,
        'entities_id' => max(0, $entitiesId),
        'entity_name' => '',
        'is_active' => 1,
        'is_recursive' => 1,
        'tto_mode' => 'fixed',
        'tto_fixed_key' => 'TTO-P1',
        'ttr_mode' => 'priority',
        'ttr_fixed_key' => 'TTR-P1',
        'managed_ids' => [
            'calendars_id' => 0,
            'slms_id' => 0,
            'slas' => [],
            'rules' => [],
        ],
    ];
}

function dashglpi_sla_policy_all(): array
{
    dashglpi_sla_policy_ensure_table();

    $rows = dashglpi_fetch_all(
        "SELECT p.*,
                CASE
                    WHEN p.entities_id = 0 THEN 'Entidade raiz'
                    ELSE COALESCE(e.completename, e.name, CONCAT('#', p.entities_id))
                END AS entity_name
         FROM " . DASHGLPI_SLA_CLIENT_POLICIES_TABLE . " p
         LEFT JOIN glpi_entities e ON e.id = p.entities_id
         ORDER BY entity_name ASC"
    );

    return array_map('dashglpi_sla_policy_normalize_row', $rows);
}

function dashglpi_sla_policy_effective(int $entitiesId): array
{
    $chain = dashglpi_sla_policy_entity_chain($entitiesId);
    if (!$chain) {
        return dashglpi_sla_policy_effective_none();
    }

    $ids = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $chain);
    $policies = dashglpi_sla_policy_persisted_by_entity($ids);
    $requestedId = max(0, $entitiesId);

    foreach ($chain as $entity) {
        $entityId = (int) ($entity['id'] ?? 0);
        $policy = $policies[$entityId] ?? null;
        if (!$policy || (int) ($policy['is_active'] ?? 0) !== 1) {
            continue;
        }

        $isOwnEntity = $entityId === $requestedId;
        if (!$isOwnEntity && (int) ($policy['is_recursive'] ?? 0) !== 1) {
            continue;
        }

        $sourceName = dashglpi_sla_policy_entity_label($entity);
        $policy['entity_name'] = $sourceName;

        return [
            'origin' => $isOwnEntity ? 'entity' : 'inherited',
            'policy' => $policy,
            'source_entity' => [
                'id' => $entityId,
                'name' => (string) ($entity['name'] ?? ''),
                'completename' => $sourceName,
            ],
        ];
    }

    return dashglpi_sla_policy_effective_none();
}

function dashglpi_sla_policy_effective_sla_for_ticket(int $entitiesId, int $priority): array
{
    $effective = dashglpi_sla_policy_effective($entitiesId);
    $policy = is_array($effective['policy'] ?? null) ? $effective['policy'] : null;

    $result = [
        'applied' => false,
        'origin' => (string) ($effective['origin'] ?? 'none'),
        'source_entities_id' => (int) ($effective['source_entity']['id'] ?? 0),
        'source_entity_name' => (string) ($effective['source_entity']['completename'] ?? ''),
        'priority' => max(1, min(6, $priority)),
        'tto_key' => '',
        'tto_id' => 0,
        'ttr_key' => '',
        'ttr_id' => 0,
        'warnings' => [],
    ];

    if (!$policy) {
        return $result;
    }

    $managed = is_array($policy['managed_ids'] ?? null) ? $policy['managed_ids'] : [];
    $managedSlas = is_array($managed['slas'] ?? null) ? $managed['slas'] : [];
    $result['tto_key'] = dashglpi_sla_policy_key_for_ticket($policy, 'TTO', $priority);
    $result['ttr_key'] = dashglpi_sla_policy_key_for_ticket($policy, 'TTR', $priority);
    $result['tto_id'] = max(0, (int) ($managedSlas[$result['tto_key']] ?? 0));
    $result['ttr_id'] = max(0, (int) ($managedSlas[$result['ttr_key']] ?? 0));

    foreach (['tto' => 'TTO', 'ttr' => 'TTR'] as $prefix => $kind) {
        if ((int) $result[$prefix . '_id'] <= 0) {
            $result['warnings'][] = 'SLA ' . $result[$prefix . '_key'] . ' sem ID gerenciado.';
        }
    }

    $result['applied'] = $result['tto_id'] > 0 || $result['ttr_id'] > 0;

    return $result;
}

function dashglpi_sla_policy_effective_none(): array
{
    return [
        'origin' => 'none',
        'policy' => null,
        'source_entity' => [
            'id' => 0,
            'name' => '',
            'completename' => '',
        ],
    ];
}

function dashglpi_sla_policy_key_for_ticket(array $policy, string $kind, int $priority): string
{
    $kind = strtoupper($kind) === 'TTO' ? 'TTO' : 'TTR';
    $mode = (string) ($policy[strtolower($kind) . '_mode'] ?? 'fixed');
    if ($mode === 'priority') {
        return $kind . '-' . dashglpi_sla_policy_priority_bucket($priority);
    }

    $fallback = $kind . '-P1';
    return dashglpi_sla_policy_key_unchecked((string) ($policy[strtolower($kind) . '_fixed_key'] ?? $fallback), $kind, $fallback);
}

function dashglpi_sla_policy_priority_bucket(int $priority): string
{
    if ($priority >= 5) {
        return 'P1';
    }
    if ($priority === 4) {
        return 'P2';
    }
    if ($priority === 3) {
        return 'P3';
    }

    return 'P4';
}

function dashglpi_sla_policy_persisted_by_entity(array $entityIds): array
{
    $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
    if (!$entityIds || !dashglpi_sla_policy_storage_exists()) {
        return [];
    }

    $rows = dashglpi_sla_policy_storage_rows($entityIds);
    $policies = [];
    foreach ($rows as $row) {
        $policy = dashglpi_sla_policy_normalize_persisted_row($row);
        $policies[(int) $policy['entities_id']] = $policy;
    }

    return $policies;
}

function dashglpi_sla_policy_storage_exists(): bool
{
    if (function_exists('dashglpi_fetch_one')) {
        $row = dashglpi_fetch_one(
            "SELECT COUNT(*) AS total
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?",
            [DASHGLPI_SLA_CLIENT_POLICIES_TABLE]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    global $DB;
    if (!isset($DB)) {
        return false;
    }

    if (method_exists($DB, 'tableExists')) {
        return (bool) $DB->tableExists(DASHGLPI_SLA_CLIENT_POLICIES_TABLE);
    }

    $result = $DB->doQuery("SHOW TABLES LIKE '" . addslashes(DASHGLPI_SLA_CLIENT_POLICIES_TABLE) . "'");
    return (bool) $DB->numrows($result);
}

function dashglpi_sla_policy_storage_rows(array $entityIds): array
{
    $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
    sort($entityIds, SORT_NUMERIC);

    if (function_exists('dashglpi_fetch_all')) {
        $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
        return dashglpi_fetch_all(
            "SELECT *
             FROM " . DASHGLPI_SLA_CLIENT_POLICIES_TABLE . "
             WHERE entities_id IN ($placeholders)",
            $entityIds
        );
    }

    global $DB;
    $rows = [];
    foreach ($DB->request([
        'FROM' => DASHGLPI_SLA_CLIENT_POLICIES_TABLE,
        'WHERE' => ['entities_id' => $entityIds],
        'ORDER' => ['entities_id ASC'],
    ]) as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function dashglpi_sla_policy_entity_chain(int $entitiesId): array
{
    $chain = [];
    $seen = [];
    $currentId = max(0, $entitiesId);

    while (!isset($seen[$currentId])) {
        $seen[$currentId] = true;
        $entity = dashglpi_sla_policy_find_entity($currentId);
        if (!$entity) {
            break;
        }

        $chain[] = $entity;
        if ($currentId === 0) {
            break;
        }

        $parentId = max(0, (int) ($entity['entities_id'] ?? 0));
        if ($parentId === $currentId) {
            break;
        }

        $currentId = $parentId;
    }

    return $chain;
}

function dashglpi_sla_policy_find_entity(int $entitiesId): ?array
{
    if ($entitiesId === 0) {
        return ['id' => 0, 'name' => 'Entidade raiz', 'completename' => 'Entidade raiz', 'entities_id' => 0];
    }

    if (function_exists('dashglpi_fetch_one')) {
        return dashglpi_fetch_one(
            "SELECT id, name, completename, entities_id
             FROM glpi_entities
             WHERE id = ?
             LIMIT 1",
            [$entitiesId]
        );
    }

    global $DB;
    if (!isset($DB)) {
        return null;
    }

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'entities_id'],
        'FROM' => class_exists('Entity') ? Entity::getTable() : 'glpi_entities',
        'WHERE' => ['id' => $entitiesId],
        'LIMIT' => 1,
    ]) as $row) {
        return is_array($row) ? $row : null;
    }

    return null;
}

function dashglpi_sla_policy_entity_label(array $entity): string
{
    $label = trim((string) ($entity['completename'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    $label = trim((string) ($entity['name'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return (int) ($entity['id'] ?? 0) === 0 ? 'Entidade raiz' : 'Entidade #' . (int) ($entity['id'] ?? 0);
}

function dashglpi_sla_policy_normalize_persisted_row(array $row): array
{
    $defaults = dashglpi_sla_policy_defaults((int) ($row['entities_id'] ?? 0));
    $managed = json_decode((string) ($row['managed_ids'] ?? ''), true);
    if (!is_array($managed)) {
        $managed = $defaults['managed_ids'];
    }

    return [
        'id' => max(0, (int) ($row['id'] ?? 0)),
        'entities_id' => max(0, (int) ($row['entities_id'] ?? 0)),
        'entity_name' => (string) ($row['entity_name'] ?? ''),
        'is_active' => !empty($row['is_active']) ? 1 : 0,
        'is_recursive' => !empty($row['is_recursive']) ? 1 : 0,
        'tto_mode' => dashglpi_sla_policy_mode((string) ($row['tto_mode'] ?? 'fixed'), 'fixed'),
        'tto_fixed_key' => dashglpi_sla_policy_key_unchecked((string) ($row['tto_fixed_key'] ?? 'TTO-P1'), 'TTO', 'TTO-P1'),
        'ttr_mode' => dashglpi_sla_policy_mode((string) ($row['ttr_mode'] ?? 'priority'), 'priority'),
        'ttr_fixed_key' => dashglpi_sla_policy_key_unchecked((string) ($row['ttr_fixed_key'] ?? 'TTR-P1'), 'TTR', 'TTR-P1'),
        'managed_ids' => $managed,
    ];
}

function dashglpi_sla_policy_key_unchecked(string $key, string $kind, string $fallback): string
{
    $kind = strtoupper($kind);
    $key = strtoupper(trim($key));
    if (!preg_match('/^' . preg_quote($kind, '/') . '-P[1-9][0-9]*$/', $key)) {
        return $fallback;
    }

    return $key;
}

function dashglpi_sla_policy_get(int $entitiesId): array
{
    if ($entitiesId < 0) {
        throw new RuntimeException('Entidade invalida.');
    }

    dashglpi_sla_policy_ensure_table();
    $entity = dashglpi_sla_policy_entity($entitiesId);
    $defaults = dashglpi_sla_policy_defaults($entitiesId);
    $defaults['entity_name'] = (string) ($entity['completename'] ?? $entity['name'] ?? 'Entidade raiz');

    $row = dashglpi_fetch_one(
        "SELECT * FROM " . DASHGLPI_SLA_CLIENT_POLICIES_TABLE . " WHERE entities_id = ? LIMIT 1",
        [$entitiesId]
    );

    if (!$row) {
        return $defaults;
    }

    return array_merge($defaults, dashglpi_sla_policy_normalize_row($row), [
        'entity_name' => $defaults['entity_name'],
    ]);
}

function dashglpi_sla_policy_from_post(array $post): array
{
    $entitiesId = max(0, (int) ($post['entities_id'] ?? 0));
    $current = dashglpi_sla_policy_get($entitiesId);

    $policy = array_merge($current, [
        'entities_id' => $entitiesId,
        'is_active' => !empty($post['is_active']) ? 1 : 0,
        'is_recursive' => 1,
        'tto_mode' => dashglpi_sla_policy_mode((string) ($post['tto_mode'] ?? $current['tto_mode']), 'fixed'),
        'tto_fixed_key' => dashglpi_sla_policy_key((string) ($post['tto_fixed_key'] ?? $current['tto_fixed_key']), 'TTO', 'TTO-P1'),
        'ttr_mode' => dashglpi_sla_policy_mode((string) ($post['ttr_mode'] ?? $current['ttr_mode']), 'priority'),
        'ttr_fixed_key' => dashglpi_sla_policy_key((string) ($post['ttr_fixed_key'] ?? $current['ttr_fixed_key']), 'TTR', 'TTR-P1'),
    ]);

    if ($policy['entities_id'] < 0) {
        throw new RuntimeException('Selecione uma entidade.');
    }

    return $policy;
}

function dashglpi_sla_policy_save(array $policy): array
{
    dashglpi_sla_policy_ensure_table();
    $policy = dashglpi_sla_policy_normalize($policy);

    $managed = json_encode($policy['managed_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($managed === false) {
        throw new RuntimeException('Falha ao serializar IDs gerenciados da politica SLA.');
    }

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_SLA_CLIENT_POLICIES_TABLE . "
            (entities_id, is_active, is_recursive, tto_mode, tto_fixed_key, ttr_mode, ttr_fixed_key, managed_ids)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            is_active = VALUES(is_active),
            is_recursive = VALUES(is_recursive),
            tto_mode = VALUES(tto_mode),
            tto_fixed_key = VALUES(tto_fixed_key),
            ttr_mode = VALUES(ttr_mode),
            ttr_fixed_key = VALUES(ttr_fixed_key),
            managed_ids = VALUES(managed_ids),
            date_mod = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        $policy['entities_id'],
        $policy['is_active'],
        $policy['is_recursive'],
        $policy['tto_mode'],
        $policy['tto_fixed_key'],
        $policy['ttr_mode'],
        $policy['ttr_fixed_key'],
        $managed,
    ]);

    return dashglpi_sla_policy_get((int) $policy['entities_id']);
}

function dashglpi_sla_policy_build_payload(array $policy): array
{
    $policy = dashglpi_sla_policy_normalize($policy);
    $entity = dashglpi_sla_policy_entity((int) $policy['entities_id']);
    $entityName = (string) ($entity['completename'] ?? $entity['name'] ?? ('Entidade #' . $policy['entities_id']));
    $settings = dashglpi_get_settings('sla_simple');
    $managed = is_array($policy['managed_ids'] ?? null) ? $policy['managed_ids'] : [];

    $calendarId = (int) ($managed['calendars_id'] ?? 0);
    if ($calendarId <= 0) {
        $calendarId = (int) ($settings['calendar_id'] ?? 0);
    }
    if ($calendarId <= 0) {
        $calendarId = (int) ($settings['ids']['calendars_id'] ?? 0);
    }

    $calendar = $calendarId > 0
        ? [
            'mode' => 'existing',
            'id' => $calendarId,
            'name' => '',
            'is_recursive' => 1,
            'saved_id' => $calendarId,
            'segments' => [],
        ]
        : [
            'mode' => 'new',
            'id' => 0,
            'name' => 'Calendario SLA - ' . dashglpi_sla_policy_short_name($entityName),
            'is_recursive' => 1,
            'saved_id' => 0,
            'segments' => dashglpi_sla_policy_segments($settings),
        ];

    $slas = [];
    $managedSlas = is_array($managed['slas'] ?? null) ? $managed['slas'] : [];
    foreach (dashglpi_sla_keys_from_settings($settings) as $key) {
        $meta = dashglpi_sla_key_meta($key);
        if (!$meta) {
            continue;
        }
        $sla = is_array($settings['slas'][$key] ?? null)
            ? $settings['slas'][$key]
            : ['number_time' => 1, 'definition_time' => 'minute'];
        $slas[] = [
            'key' => $key,
            'name' => $key,
            'kind' => $meta['kind'],
            'priority' => $meta['priority'],
            'type' => $meta['type'],
            'number_time' => (int) ($sla['number_time'] ?? 1),
            'definition_time' => (string) ($sla['definition_time'] ?? 'minute'),
            'id' => max(0, (int) ($managedSlas[$key] ?? 0)),
        ];
    }

    return [
        'entity' => [
            'mode' => 'existing',
            'id' => (int) $policy['entities_id'],
            'name' => $entityName,
            'parent_id' => (int) ($entity['entities_id'] ?? 0),
            'saved_id' => (int) $policy['entities_id'],
        ],
        'calendar' => $calendar,
        'slm' => [
            'name' => 'SLA Cliente - ' . dashglpi_sla_policy_short_name($entityName),
            'saved_id' => max(0, (int) ($managed['slms_id'] ?? 0)),
        ],
        'slas' => $slas,
        'policy' => [
            'entities_id' => (int) $policy['entities_id'],
            'is_active' => (int) $policy['is_active'],
            'is_recursive' => 1,
            'tto_mode' => $policy['tto_mode'],
            'tto_fixed_key' => $policy['tto_fixed_key'],
            'ttr_mode' => $policy['ttr_mode'],
            'ttr_fixed_key' => $policy['ttr_fixed_key'],
        ],
    ];
}

function dashglpi_sla_policy_managed_ids_from_result(array $result, array $current = []): array
{
    $managed = is_array($current) ? $current : [];
    $managed['calendars_id'] = (int) ($result['calendar']['id'] ?? $managed['calendars_id'] ?? 0);
    $managed['slms_id'] = (int) ($result['slm']['id'] ?? $managed['slms_id'] ?? 0);
    $managed['slas'] = is_array($managed['slas'] ?? null) ? $managed['slas'] : [];
    foreach (($result['slas'] ?? []) as $sla) {
        $key = (string) ($sla['key'] ?? '');
        if ($key !== '') {
            $managed['slas'][$key] = (int) ($sla['id'] ?? 0);
        }
    }
    $managed['rules'] = [];
    foreach (($result['rules'] ?? []) as $rule) {
        $name = (string) ($rule['name'] ?? '');
        if ($name !== '') {
            $managed['rules'][$name] = (int) ($rule['id'] ?? 0);
        }
    }

    return $managed;
}

function dashglpi_sla_policy_normalize_row(array $row): array
{
    $managed = json_decode((string) ($row['managed_ids'] ?? ''), true);
    if (!is_array($managed)) {
        $managed = dashglpi_sla_policy_defaults((int) ($row['entities_id'] ?? 0))['managed_ids'];
    }

    return dashglpi_sla_policy_normalize([
        'id' => (int) ($row['id'] ?? 0),
        'entities_id' => (int) ($row['entities_id'] ?? 0),
        'entity_name' => (string) ($row['entity_name'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 1),
        'is_recursive' => (int) ($row['is_recursive'] ?? 1),
        'tto_mode' => (string) ($row['tto_mode'] ?? 'fixed'),
        'tto_fixed_key' => (string) ($row['tto_fixed_key'] ?? 'TTO-P1'),
        'ttr_mode' => (string) ($row['ttr_mode'] ?? 'priority'),
        'ttr_fixed_key' => (string) ($row['ttr_fixed_key'] ?? 'TTR-P1'),
        'managed_ids' => $managed,
    ]);
}

function dashglpi_sla_policy_normalize(array $policy): array
{
    $defaults = dashglpi_sla_policy_defaults((int) ($policy['entities_id'] ?? 0));
    $policy = array_merge($defaults, $policy);
    $policy['id'] = max(0, (int) ($policy['id'] ?? 0));
    $policy['entities_id'] = max(0, (int) ($policy['entities_id'] ?? 0));
    $policy['is_active'] = !empty($policy['is_active']) ? 1 : 0;
    $policy['is_recursive'] = 1;
    $policy['tto_mode'] = dashglpi_sla_policy_mode((string) $policy['tto_mode'], 'fixed');
    $policy['tto_fixed_key'] = dashglpi_sla_policy_key((string) $policy['tto_fixed_key'], 'TTO', 'TTO-P1');
    $policy['ttr_mode'] = dashglpi_sla_policy_mode((string) $policy['ttr_mode'], 'priority');
    $policy['ttr_fixed_key'] = dashglpi_sla_policy_key((string) $policy['ttr_fixed_key'], 'TTR', 'TTR-P1');
    $policy['managed_ids'] = is_array($policy['managed_ids'] ?? null) ? $policy['managed_ids'] : $defaults['managed_ids'];

    return $policy;
}

function dashglpi_sla_policy_mode(string $mode, string $fallback): string
{
    return in_array($mode, ['fixed', 'priority'], true) ? $mode : $fallback;
}

function dashglpi_sla_policy_key(string $key, string $kind, string $fallback): string
{
    $key = strtoupper(trim($key));
    if (!preg_match('/^' . preg_quote($kind, '/') . '-P[1-9][0-9]*$/', $key)) {
        return $fallback;
    }

    $settings = dashglpi_get_settings('sla_simple');
    return in_array($key, dashglpi_sla_keys_from_settings($settings, $kind), true) ? $key : $fallback;
}

function dashglpi_sla_policy_entity(int $entitiesId): array
{
    $row = dashglpi_fetch_one(
        "SELECT id, name, completename, entities_id
         FROM glpi_entities
         WHERE id = ?
         LIMIT 1",
        [$entitiesId]
    );

    if (!$row && $entitiesId === 0) {
        return ['id' => 0, 'name' => 'Entidade raiz', 'completename' => 'Entidade raiz', 'entities_id' => 0];
    }

    if (!$row) {
        throw new RuntimeException('Entidade nao encontrada.');
    }

    return $row;
}

function dashglpi_sla_policy_short_name(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return 'Entidade';
    }

    return substr($name, 0, 120);
}

function dashglpi_sla_policy_segments(array $settings): array
{
    $segments = [];
    $source = is_array($settings['calendar_segments'] ?? null)
        ? $settings['calendar_segments']
        : dashglpi_settings_defaults('sla_simple')['calendar_segments'];

    foreach ($source as $day => $segment) {
        if (empty($segment['enabled'])) {
            continue;
        }
        $segments[] = [
            'day' => (int) $day,
            'begin' => dashglpi_normalize_time((string) ($segment['begin'] ?? '08:00'), '08:00') . ':00',
            'end' => dashglpi_normalize_time((string) ($segment['end'] ?? '18:00'), '18:00') . ':00',
        ];
    }

    return $segments;
}

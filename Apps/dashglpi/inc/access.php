<?php

function dashglpi_functional_page_keys(): array
{
    return array_keys(dashglpi_profile_access_page_catalog());
}

function dashglpi_profile_access_settings(): array
{
    return dashglpi_get_settings('profile_access');
}

function dashglpi_current_user_context(bool $refresh = false): array
{
    static $cache = null;

    if (!$refresh && is_array($cache)) {
        return $cache;
    }

    $defaultPages = dashglpi_functional_page_keys();
    $user = dashglpi_current_user();
    if (!$user) {
        $cache = [
            'user_id' => 0,
            'is_admin_bypass' => false,
            'has_profile_rule' => false,
            'is_helpdesk_profile' => false,
            'effective_profile_id' => 0,
            'effective_profile_name' => '',
            'allowed_pages' => [],
            'scoped_entity_ids' => [],
            'has_entity_scope' => false,
            'default_page' => dashglpi_first_allowed_page([]),
            'profiles' => [],
        ];
        return $cache;
    }

    if (dashglpi_current_user_is_admin()) {
        $cache = [
            'user_id' => (int) $user['id'],
            'is_admin_bypass' => true,
            'has_profile_rule' => false,
            'is_helpdesk_profile' => false,
            'effective_profile_id' => 0,
            'effective_profile_name' => 'Admin',
            'allowed_pages' => $defaultPages,
            'scoped_entity_ids' => [],
            'has_entity_scope' => false,
            'default_page' => dashglpi_first_allowed_page($defaultPages),
            'profiles' => dashglpi_current_user_profiles((int) $user['id']),
        ];
        return $cache;
    }

    $profiles = dashglpi_current_user_profiles((int) $user['id']);
    $settings = dashglpi_profile_access_settings();
    $rulesByProfile = dashglpi_profile_access_rules_by_profile($settings['rules'] ?? []);
    $winner = null;

    foreach ($profiles as $profile) {
        $profileId = (int) ($profile['profile_id'] ?? 0);
        $rule = $rulesByProfile[$profileId] ?? null;
        if (!$rule || empty($rule['enabled'])) {
            continue;
        }

        if ($winner === null) {
            $winner = $rule;
            continue;
        }

        $winnerPriority = (int) ($winner['priority'] ?? 0);
        $rulePriority = (int) ($rule['priority'] ?? 0);
        if (
            $rulePriority < $winnerPriority
            || ($rulePriority === $winnerPriority && $profileId < (int) ($winner['profile_id'] ?? PHP_INT_MAX))
        ) {
            $winner = $rule;
        }
    }

    if ($winner === null) {
        $cache = [
            'user_id' => (int) $user['id'],
            'is_admin_bypass' => false,
            'has_profile_rule' => false,
            'is_helpdesk_profile' => false,
            'effective_profile_id' => 0,
            'effective_profile_name' => '',
            'allowed_pages' => $defaultPages,
            'scoped_entity_ids' => [],
            'has_entity_scope' => false,
            'default_page' => dashglpi_first_allowed_page($defaultPages),
            'profiles' => $profiles,
        ];
        return $cache;
    }

    $effectiveProfileId = (int) ($winner['profile_id'] ?? 0);
    $effectiveRows = array_values(array_filter(
        $profiles,
        static fn(array $row): bool => (int) ($row['profile_id'] ?? 0) === $effectiveProfileId
    ));
    $allowedPages = array_values(array_filter(
        array_map('strval', $winner['allowed_pages'] ?? []),
        static fn(string $page): bool => in_array($page, $defaultPages, true)
    ));
    $scopedEntityIds = dashglpi_expand_profile_entities($effectiveRows);
    $effectiveProfileName = trim((string) ($winner['profile_name'] ?? ''));
    if ($effectiveProfileName === '') {
        $effectiveProfileName = (string) ($effectiveRows[0]['profile_name'] ?? '');
    }
    $isHelpdeskProfile = (string) ($effectiveRows[0]['profile_interface'] ?? '') === 'helpdesk';

    $cache = [
        'user_id' => (int) $user['id'],
        'is_admin_bypass' => false,
        'has_profile_rule' => true,
        'is_helpdesk_profile' => $isHelpdeskProfile,
        'effective_profile_id' => $effectiveProfileId,
        'effective_profile_name' => $effectiveProfileName,
        'allowed_pages' => $allowedPages,
        'scoped_entity_ids' => $scopedEntityIds,
        'has_entity_scope' => true,
        'default_page' => dashglpi_first_allowed_page($allowedPages),
        'profiles' => $profiles,
    ];

    return $cache;
}

function dashglpi_current_user_profiles(int $userId): array
{
    return dashglpi_fetch_all(
        "SELECT pu.profiles_id AS profile_id,
                pu.entities_id,
                pu.is_recursive,
                pu.is_dynamic,
                pu.is_default_profile,
                p.name AS profile_name,
                p.interface AS profile_interface
         FROM glpi_profiles_users pu
         INNER JOIN glpi_profiles p ON p.id = pu.profiles_id
         WHERE pu.users_id = ?
         ORDER BY pu.is_default_profile DESC,
                  pu.is_dynamic ASC,
                  pu.profiles_id ASC,
                  pu.entities_id ASC",
        [$userId]
    );
}

function dashglpi_profile_access_rules_by_profile(array $rules): array
{
    $map = [];

    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $profileId = max(0, (int) ($rule['profile_id'] ?? 0));
        if ($profileId <= 0) {
            continue;
        }

        $map[$profileId] = [
            'profile_id' => $profileId,
            'enabled' => !empty($rule['enabled']) ? 1 : 0,
            'priority' => max(0, (int) ($rule['priority'] ?? 0)),
            'allowed_pages' => array_values(array_filter(
                array_map('strval', is_array($rule['allowed_pages'] ?? null) ? $rule['allowed_pages'] : []),
                static fn(string $page): bool => in_array($page, dashglpi_functional_page_keys(), true)
            )),
            'profile_name' => (string) ($rule['profile_name'] ?? ''),
        ];
    }

    return $map;
}

function dashglpi_expand_profile_entities(array $profileRows): array
{
    if (!$profileRows) {
        return [];
    }

    $entityRows = dashglpi_fetch_all(
        "SELECT id, entities_id
         FROM glpi_entities
         ORDER BY entities_id ASC, id ASC"
    );
    $childrenMap = [];
    foreach ($entityRows as $entity) {
        $parentId = (int) ($entity['entities_id'] ?? 0);
        $childrenMap[$parentId][] = (int) ($entity['id'] ?? 0);
    }

    $scoped = [];
    foreach ($profileRows as $row) {
        $entityId = max(0, (int) ($row['entities_id'] ?? 0));
        $isRecursive = !empty($row['is_recursive']);

        $scoped[$entityId] = true;
        if (!$isRecursive) {
            continue;
        }

        foreach (dashglpi_recursive_entity_ids($entityId, $childrenMap) as $childId) {
            $scoped[$childId] = true;
        }
    }

    $entityIds = array_map('intval', array_keys($scoped));
    sort($entityIds);

    return $entityIds;
}

function dashglpi_recursive_entity_ids(int $rootEntityId, array $childrenMap): array
{
    $queue = [$rootEntityId];
    $seen = [$rootEntityId => true];
    $collected = [];

    while ($queue) {
        $parentId = array_shift($queue);
        foreach ($childrenMap[$parentId] ?? [] as $childId) {
            $childId = (int) $childId;
            if (isset($seen[$childId])) {
                continue;
            }

            $seen[$childId] = true;
            $collected[] = $childId;
            $queue[] = $childId;
        }
    }

    sort($collected);
    return $collected;
}

function dashglpi_first_allowed_page(array $allowedPages): string
{
    $allowedSet = array_fill_keys(array_map('strval', $allowedPages), true);
    foreach (dashglpi_functional_page_keys() as $page) {
        if (isset($allowedSet[$page])) {
            return $page;
        }
    }

    return 'dashboard';
}

function dashglpi_current_user_can_access_page(string $pageKey): bool
{
    if (!in_array($pageKey, dashglpi_functional_page_keys(), true)) {
        return false;
    }

    $context = dashglpi_current_user_context();
    if (!empty($context['is_admin_bypass'])) {
        return true;
    }

    return in_array($pageKey, $context['allowed_pages'] ?? [], true);
}

function dashglpi_context_has_entity_scope(?array $context = null): bool
{
    $context = $context ?? dashglpi_current_user_context();
    return !empty($context['has_entity_scope']) && empty($context['is_admin_bypass']);
}

function dashglpi_scoped_entity_sql(string $column, bool $prependAnd = true): array
{
    $context = dashglpi_current_user_context();
    if (!dashglpi_context_has_entity_scope($context)) {
        return ['sql' => '', 'params' => []];
    }

    $entityIds = array_values(array_unique(array_map('intval', $context['scoped_entity_ids'] ?? [])));
    if (!$entityIds) {
        return ['sql' => $prependAnd ? ' AND 1=0' : '1=0', 'params' => []];
    }

    $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
    $prefix = $prependAnd ? ' AND ' : '';

    return [
        'sql' => $prefix . $column . " IN ($placeholders)",
        'params' => $entityIds,
    ];
}

function dashglpi_assert_page_access(string $pageKey): void
{
    if (dashglpi_current_user_can_access_page($pageKey)) {
        return;
    }

    if (dashglpi_is_ajax_request()) {
        dashglpi_json(['error' => 'Acesso restrito para esta tela.'], 403);
    }

    http_response_code(403);
    echo 'Acesso restrito para esta tela.';
    exit;
}

/**
 * Gate de visibilidade por direito GLPI (PLAN-20260709-019, Decisão 2):
 * Problema/Mudança só aparecem para perfis com o direito nativo correspondente
 * ('problem'/'change' com bit READ), espelhando o back-office do GLPI.
 * Chamado mantém o comportamento atual (sem gate adicional).
 */
function dashglpi_current_user_can_view_itil_type(string $typeKey): bool
{
    $type = dashglpi_itil_type($typeKey);
    if (!$type) {
        return false;
    }

    if ($typeKey === 'ticket') {
        return true;
    }

    $context = dashglpi_current_user_context();
    if (!empty($context['is_admin_bypass'])) {
        return true;
    }

    $profiles = is_array($context['profiles'] ?? null) ? $context['profiles'] : [];
    if (!$profiles) {
        return false;
    }

    $profileIds = [];
    if (!empty($context['has_profile_rule']) && (int) ($context['effective_profile_id'] ?? 0) > 0) {
        $profileIds = [(int) $context['effective_profile_id']];
    } else {
        foreach ($profiles as $profile) {
            $profileId = (int) ($profile['profile_id'] ?? 0);
            if ($profileId > 0) {
                $profileIds[] = $profileId;
            }
        }
        $profileIds = array_values(array_unique($profileIds));
    }

    if (!$profileIds) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($profileIds), '?'));
    $row = dashglpi_fetch_one(
        "SELECT id
         FROM glpi_profilerights
         WHERE name = ?
           AND profiles_id IN ($placeholders)
           AND (rights & 1) > 0
         LIMIT 1",
        array_merge([(string) $type['right']], $profileIds)
    );

    return (bool) $row;
}

/** Itemtypes ITIL visíveis para o usuário atual (sempre inclui 'ticket'). */
function dashglpi_current_user_visible_itil_types(): array
{
    return array_values(array_filter(
        dashglpi_itil_type_keys(),
        static fn(string $key): bool => dashglpi_current_user_can_view_itil_type($key)
    ));
}

function dashglpi_itil_object_exists(string $typeKey, int $objectId): bool
{
    if ($typeKey === 'ticket') {
        return dashglpi_ticket_exists($objectId);
    }

    $type = dashglpi_itil_type($typeKey);
    if (!$type) {
        return false;
    }

    return (bool) dashglpi_fetch_one(
        "SELECT id
         FROM {$type['table']}
         WHERE id = ?
           AND is_deleted = 0
         LIMIT 1",
        [$objectId]
    );
}

function dashglpi_itil_object_is_accessible(string $typeKey, int $objectId): bool
{
    if ($typeKey === 'ticket') {
        return dashglpi_ticket_is_accessible($objectId);
    }

    $type = dashglpi_itil_type($typeKey);
    if (!$type || !dashglpi_current_user_can_view_itil_type($typeKey)) {
        return false;
    }

    $context = dashglpi_current_user_context();
    if (!empty($context['is_admin_bypass'])) {
        return true;
    }

    $scope = dashglpi_scoped_entity_sql('t.entities_id');
    $row = dashglpi_fetch_one(
        "SELECT t.id
         FROM {$type['table']} t
         WHERE t.id = ?
           AND t.is_deleted = 0" . $scope['sql'] . "
         LIMIT 1",
        array_merge([$objectId], $scope['params'])
    );

    return (bool) $row;
}

function dashglpi_assert_itil_object_access(string $typeKey, int $objectId): void
{
    if ($typeKey === 'ticket') {
        dashglpi_assert_ticket_access($objectId);
        return;
    }

    $type = dashglpi_itil_type($typeKey);
    $label = $type ? mb_strtolower((string) $type['label'], 'UTF-8') : 'registro';

    if (!$type || !dashglpi_itil_object_exists($typeKey, $objectId)) {
        http_response_code(404);
        echo ucfirst($label) . ' nao encontrado.';
        exit;
    }

    if (dashglpi_itil_object_is_accessible($typeKey, $objectId)) {
        return;
    }

    if (dashglpi_is_ajax_request()) {
        dashglpi_json(['error' => 'Acesso restrito ao registro solicitado.'], 403);
    }

    http_response_code(403);
    echo 'Acesso restrito ao registro solicitado.';
    exit;
}

function dashglpi_ticket_exists(int $ticketId): bool
{
    return (bool) dashglpi_fetch_one(
        "SELECT id
         FROM glpi_tickets
         WHERE id = ?
           AND is_deleted = 0
         LIMIT 1",
        [$ticketId]
    );
}

function dashglpi_ticket_is_accessible(int $ticketId): bool
{
    $context = dashglpi_current_user_context();
    if (!empty($context['is_admin_bypass'])) {
        return true;
    }

    $scope = dashglpi_scoped_entity_sql('t.entities_id');
    $row = dashglpi_fetch_one(
        "SELECT t.id
         FROM glpi_tickets t
         WHERE t.id = ?
           AND t.is_deleted = 0" . $scope['sql'] . "
         LIMIT 1",
        array_merge([$ticketId], $scope['params'])
    );

    return (bool) $row;
}

function dashglpi_assert_ticket_access(int $ticketId): void
{
    if (!dashglpi_ticket_exists($ticketId)) {
        http_response_code(404);
        echo 'Chamado nao encontrado.';
        exit;
    }

    if (dashglpi_ticket_is_accessible($ticketId)) {
        return;
    }

    if (dashglpi_is_ajax_request()) {
        dashglpi_json(['error' => 'Acesso restrito ao chamado solicitado.'], 403);
    }

    http_response_code(403);
    echo 'Acesso restrito ao chamado solicitado.';
    exit;
}

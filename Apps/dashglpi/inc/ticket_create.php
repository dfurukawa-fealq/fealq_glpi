<?php

function dashglpi_ticket_create_scope(): array
{
    $context = dashglpi_current_user_context();
    $profiles = is_array($context['profiles'] ?? null) ? $context['profiles'] : [];
    $profileRows = [];

    $effectiveProfileId = (int) ($context['effective_profile_id'] ?? 0);
    if ($effectiveProfileId > 0) {
        $profileRows = array_values(array_filter(
            $profiles,
            static fn(array $row): bool => (int) ($row['profile_id'] ?? 0) === $effectiveProfileId
        ));
    }

    if (!$profileRows && $profiles) {
        $fallbackProfileId = (int) ($profiles[0]['profile_id'] ?? 0);
        $profileRows = array_values(array_filter(
            $profiles,
            static fn(array $row): bool => (int) ($row['profile_id'] ?? 0) === $fallbackProfileId
        ));
    }

    if (!empty($context['is_admin_bypass'])) {
        $entityIds = dashglpi_ticket_create_all_entity_ids();
    } elseif (!empty($context['has_profile_rule']) || !empty($context['is_helpdesk_profile']) || (string) ($profileRows[0]['profile_interface'] ?? '') === 'helpdesk') {
        $entityIds = dashglpi_ticket_create_direct_profile_entity_ids($profileRows);
    } else {
        $entityIds = $profileRows ? dashglpi_expand_profile_entities($profileRows) : [];
        if (!$entityIds) {
            $entityIds = [0];
        }
    }

    $entities = dashglpi_ticket_create_entities_catalog($entityIds);
    $defaultEntityId = dashglpi_ticket_create_default_entity_id($profileRows, $entities);
    $profileName = trim((string) ($context['effective_profile_name'] ?? ''));
    if ($profileName === '') {
        $profileName = trim((string) ($profileRows[0]['profile_name'] ?? ''));
    }

    return [
        'profile_id' => (int) ($profileRows[0]['profile_id'] ?? $effectiveProfileId),
        'profile_name' => $profileName !== '' ? $profileName : 'Perfil de atendimento',
        'profile_rows' => $profileRows,
        'entity_ids' => array_values(array_unique(array_map('intval', $entityIds))),
        'entities' => $entities,
        'default_entity_id' => $defaultEntityId,
        'show_entity_selector' => count($entities) > 1,
    ];
}

function dashglpi_ticket_create_direct_profile_entity_ids(array $profileRows): array
{
    $entityIds = [];
    foreach ($profileRows as $row) {
        $entityIds[] = max(0, (int) ($row['entities_id'] ?? 0));
    }

    $entityIds = array_values(array_unique($entityIds));
    sort($entityIds);

    return $entityIds ?: [0];
}
function dashglpi_ticket_create_all_entity_ids(): array
{
    $entityIds = [0];
    $rows = dashglpi_fetch_all(
        "SELECT id
         FROM glpi_entities
         ORDER BY completename ASC, name ASC"
    );

    foreach ($rows as $row) {
        $entityId = (int) ($row['id'] ?? 0);
        if ($entityId > 0) {
            $entityIds[] = $entityId;
        }
    }

    return array_values(array_unique($entityIds));
}

function dashglpi_ticket_create_entities_catalog(array $entityIds): array
{
    $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
    sort($entityIds);

    $entities = [];
    $hasRoot = in_array(0, $entityIds, true);
    if ($hasRoot) {
        $entities[] = [
            'id' => 0,
            'name' => 'Entidade raiz',
            'completename' => 'Entidade raiz',
            'label' => 'Entidade raiz',
        ];
    }

    $nonRootIds = array_values(array_filter($entityIds, static fn(int $id): bool => $id > 0));
    if ($nonRootIds) {
        $placeholders = implode(',', array_fill(0, count($nonRootIds), '?'));
        $rows = dashglpi_fetch_all(
            "SELECT id, name, completename
             FROM glpi_entities
             WHERE id IN ($placeholders)
             ORDER BY completename ASC, name ASC",
            $nonRootIds
        );

        foreach ($rows as $row) {
            $label = trim((string) ($row['completename'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['name'] ?? 'Entidade'));
            }

            $entities[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'completename' => (string) ($row['completename'] ?? ''),
                'label' => $label,
            ];
        }
    }

    if (!$entities) {
        $entities[] = [
            'id' => 0,
            'name' => 'Entidade raiz',
            'completename' => 'Entidade raiz',
            'label' => 'Entidade raiz',
        ];
    }

    return $entities;
}

function dashglpi_ticket_create_default_entity_id(array $profileRows, array $entities): int
{
    $allowedIds = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $entities);
    foreach ($profileRows as $row) {
        $entityId = (int) ($row['entities_id'] ?? 0);
        if (in_array($entityId, $allowedIds, true)) {
            return $entityId;
        }
    }

    return (int) ($entities[0]['id'] ?? 0);
}

function dashglpi_ticket_create_normalize_entity_id(array $scope, int $requestedEntityId): int
{
    $allowedIds = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $scope['entities'] ?? []);
    if (in_array($requestedEntityId, $allowedIds, true)) {
        return $requestedEntityId;
    }

    return (int) ($scope['default_entity_id'] ?? 0);
}

function dashglpi_ticket_create_assert_entity_id(array $scope, int $requestedEntityId): int
{
    $allowedIds = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $scope['entities'] ?? []);
    if (in_array($requestedEntityId, $allowedIds, true)) {
        return $requestedEntityId;
    }

    throw new RuntimeException('Entidade selecionada fora do seu escopo de acesso.');
}
function dashglpi_ticket_create_normalize_type($value): int
{
    $type = (int) $value;
    return in_array($type, [1, 2], true) ? $type : 1;
}

function dashglpi_ticket_create_normalize_urgency($value): int
{
    $urgency = (int) $value;
    return ($urgency >= 1 && $urgency <= 5) ? $urgency : 3;
}

function dashglpi_ticket_create_type_catalog(): array
{
    return [
        ['id' => 1, 'label' => 'Incidente'],
        ['id' => 2, 'label' => 'Requisicao'],
    ];
}

function dashglpi_ticket_create_urgency_catalog(): array
{
    return [
        ['id' => 1, 'label' => 'Muito baixa'],
        ['id' => 2, 'label' => 'Baixa'],
        ['id' => 3, 'label' => 'Media'],
        ['id' => 4, 'label' => 'Alta'],
        ['id' => 5, 'label' => 'Muito alta'],
    ];
}

function dashglpi_ticket_create_upload_max_label(): string
{
    $uploadMax = trim((string) ini_get('upload_max_filesize'));
    $postMax = trim((string) ini_get('post_max_size'));
    if ($uploadMax !== '' && $postMax !== '' && $uploadMax !== $postMax) {
        return $uploadMax . ' por arquivo / ' . $postMax . ' por envio';
    }

    return $uploadMax !== '' ? $uploadMax : ($postMax !== '' ? $postMax : 'limite do servidor');
}

function dashglpi_ticket_create_entity_scope_for_categories(int $entitiesId): array
{
    $scope = [$entitiesId, 0];
    $current = $entitiesId;
    $guard = 0;

    while ($current > 0 && $guard < 100) {
        $row = dashglpi_fetch_one(
            "SELECT entities_id FROM glpi_entities WHERE id = ? LIMIT 1",
            [$current]
        );
        if (!$row) {
            break;
        }
        $parentId = (int) ($row['entities_id'] ?? 0);
        if ($parentId <= 0 || in_array($parentId, $scope, true)) {
            break;
        }
        $scope[] = $parentId;
        $current = $parentId;
        $guard++;
    }

    return array_values(array_unique(array_map('intval', $scope)));
}

function dashglpi_ticket_create_categories_catalog(int $entitiesId, int $type): array
{
    $entityScope = dashglpi_ticket_create_entity_scope_for_categories($entitiesId);
    $placeholders = implode(',', array_fill(0, count($entityScope), '?'));
    $typeField = $type === 2 ? 'is_request' : 'is_incident';
    $rows = dashglpi_fetch_all(
        "SELECT id, name, completename, entities_id, is_recursive
         FROM glpi_itilcategories
         WHERE is_helpdeskvisible = 1
           AND {$typeField} = 1
           AND entities_id IN ($placeholders)
         ORDER BY completename ASC, name ASC",
        $entityScope
    );

    $categories = [];
    foreach ($rows as $row) {
        $categoryEntityId = (int) ($row['entities_id'] ?? 0);
        $appliesToEntity = $categoryEntityId === $entitiesId
            || (!empty($row['is_recursive']) && in_array($categoryEntityId, $entityScope, true));
        if (!$appliesToEntity) {
            continue;
        }

        $label = trim((string) ($row['completename'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['name'] ?? 'Categoria'));
        }

        $categories[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'completename' => (string) ($row['completename'] ?? ''),
            'label' => $label,
        ];
    }

    return $categories;
}
function dashglpi_ticket_create_can_change_requester(): bool
{
    return dashglpi_current_user_is_admin();
}

function dashglpi_ticket_create_user_label(array $user): string
{
    $display = trim((string) ($user['display'] ?? ''));
    if ($display === '') {
        $nameParts = array_filter([
            trim((string) ($user['firstname'] ?? '')),
            trim((string) ($user['realname'] ?? '')),
        ]);
        $display = trim(implode(' ', $nameParts));
    }
    if ($display === '') {
        $display = trim((string) ($user['name'] ?? 'Usuario'));
    }

    $email = trim((string) ($user['email'] ?? ''));
    return $email !== '' ? $display . ' <' . $email . '>' : $display;
}

function dashglpi_ticket_create_user_summary(array $user): array
{
    $label = dashglpi_ticket_create_user_label($user);
    $summary = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'display' => $label,
        'label' => $label,
    ];

    $email = trim((string) ($user['email'] ?? ''));
    if ($email !== '') {
        $summary['email'] = $email;
    }

    return $summary;
}

function dashglpi_ticket_create_user_exists(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    return (bool) dashglpi_fetch_one(
        "SELECT id
         FROM glpi_users
         WHERE id = ?
           AND is_deleted = 0
           AND is_active = 1
         LIMIT 1",
        [$userId]
    );
}

function dashglpi_ticket_create_requester_search_response(array $input = []): array
{
    if (!dashglpi_ticket_create_can_change_requester()) {
        return ['ok' => false, 'error' => 'Sem permissao para alterar o solicitante.'];
    }

    $query = trim(preg_replace('/\s+/', ' ', (string) ($input['q'] ?? '')) ?? '');
    $like = '%' . $query . '%';
    $rows = dashglpi_fetch_all(
        "SELECT u.id,
                u.name,
                u.realname,
                u.firstname,
                COALESCE(ue.email, '') AS email
         FROM glpi_users u
         LEFT JOIN glpi_useremails ue
           ON ue.users_id = u.id
          AND ue.is_default = 1
         WHERE u.is_deleted = 0
           AND u.is_active = 1
           AND (
                u.name LIKE ?
             OR u.realname LIKE ?
             OR u.firstname LIKE ?
             OR ue.email LIKE ?
           )
         ORDER BY u.realname ASC, u.firstname ASC, u.name ASC
         LIMIT 30",
        [$like, $like, $like, $like]
    );

    return [
        'ok' => true,
        'users' => array_map('dashglpi_ticket_create_user_summary', $rows),
    ];
}
function dashglpi_ticket_create_catalog_response(array $input = []): array
{
    $scope = dashglpi_ticket_create_scope();
    $selectedEntityId = dashglpi_ticket_create_normalize_entity_id(
        $scope,
        (int) ($input['entities_id'] ?? $scope['default_entity_id'] ?? 0)
    );
    $type = dashglpi_ticket_create_normalize_type($input['type'] ?? 1);

    $catalog = [
        'entities' => $scope['entities'],
        'default_entity_id' => (int) ($scope['default_entity_id'] ?? 0),
        'selected_entity_id' => $selectedEntityId,
        'selected_type' => $type,
        'types' => dashglpi_ticket_create_type_catalog(),
        'urgencies' => dashglpi_ticket_create_urgency_catalog(),
        'default_urgency' => 3,
        'show_ticket_urgency' => !array_key_exists('show_ticket_urgency', dashglpi_current_user_context()) || !empty(dashglpi_current_user_context()['show_ticket_urgency']),
        'categories' => dashglpi_ticket_create_categories_catalog($selectedEntityId, $type),
        'upload_max_label' => dashglpi_ticket_create_upload_max_label(),
    ];
    $catalog['entities'] = $scope['entities'];
    $catalog['selected_entity_id'] = $selectedEntityId;
    $catalog['default_entity_id'] = (int) ($scope['default_entity_id'] ?? 0);
    $catalog['show_entity_selector'] = !empty($scope['show_entity_selector']);
    $catalog['profile_name'] = (string) ($scope['profile_name'] ?? 'Perfil de atendimento');
    $catalog['requester'] = dashglpi_ticket_create_user_summary(dashglpi_current_user() ?: []);
    $catalog['can_change_requester'] = dashglpi_ticket_create_can_change_requester();
    $catalog['selected_type'] = $type;
    $catalog['can_access_tickets'] = dashglpi_current_user_can_access_page('tickets');

    return [
        'ok' => true,
        'catalog' => $catalog,
    ];
}

function dashglpi_ticket_create_payload_from_request(array $post): array
{
    $scope = dashglpi_ticket_create_scope();
    $user = dashglpi_current_user();
    if (!$user) {
        throw new RuntimeException('Sessao de usuario nao encontrada.');
    }

    $name = trim(preg_replace('/\s+/', ' ', (string) ($post['name'] ?? '')) ?? '');
    $content = trim((string) ($post['content'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Informe o titulo do chamado.');
    }
    if ($content === '') {
        throw new RuntimeException('Informe a descricao do chamado.');
    }

    $currentUserId = (int) $user['id'];
    $requesterId = $currentUserId;
    $postedRequesterId = max(0, (int) ($post['requester_id'] ?? 0));
    if ($postedRequesterId > 0 && $postedRequesterId !== $currentUserId) {
        if (!dashglpi_ticket_create_can_change_requester()) {
            throw new RuntimeException('Sem permissao para alterar o solicitante do chamado.');
        }
        if (!dashglpi_ticket_create_user_exists($postedRequesterId)) {
            throw new RuntimeException('Solicitante selecionado nao encontrado ou inativo.');
        }
        $requesterId = $postedRequesterId;
    }

    $context = dashglpi_current_user_context();
    $showTicketUrgency = !array_key_exists('show_ticket_urgency', $context) || !empty($context['show_ticket_urgency']);

    return [
        'action' => 'create',
        'requester_id' => $requesterId,
        'entities_id' => dashglpi_ticket_create_assert_entity_id($scope, (int) ($post['entities_id'] ?? 0)),
        'type' => dashglpi_ticket_create_normalize_type($post['type'] ?? 1),
        'urgency' => $showTicketUrgency ? dashglpi_ticket_create_normalize_urgency($post['urgency'] ?? 3) : 3,
        'itilcategories_id' => max(0, (int) ($post['itilcategories_id'] ?? 0)),
        'name' => $name,
        'content' => $content,
    ];
}

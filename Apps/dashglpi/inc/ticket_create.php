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

function dashglpi_ticket_create_catalog_response(array $input = []): array
{
    $scope = dashglpi_ticket_create_scope();
    $selectedEntityId = dashglpi_ticket_create_normalize_entity_id(
        $scope,
        (int) ($input['entities_id'] ?? $scope['default_entity_id'] ?? 0)
    );
    $type = dashglpi_ticket_create_normalize_type($input['type'] ?? 1);

    $bridgeResult = dashglpi_admin_bridge_request('ticket_create_config.php', [
        'action' => 'catalog',
        'entities' => $scope['entities'],
        'entities_id' => $selectedEntityId,
        'default_entity_id' => (int) ($scope['default_entity_id'] ?? 0),
        'type' => $type,
    ]);

    $catalog = is_array($bridgeResult['catalog'] ?? null) ? $bridgeResult['catalog'] : [];
    $catalog['entities'] = $scope['entities'];
    $catalog['selected_entity_id'] = $selectedEntityId;
    $catalog['default_entity_id'] = (int) ($scope['default_entity_id'] ?? 0);
    $catalog['show_entity_selector'] = !empty($scope['show_entity_selector']);
    $catalog['profile_name'] = (string) ($scope['profile_name'] ?? 'Perfil de atendimento');
    $catalog['requester'] = dashglpi_current_user();
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

    return [
        'action' => 'create',
        'requester_id' => (int) $user['id'],
        'entities_id' => dashglpi_ticket_create_normalize_entity_id($scope, (int) ($post['entities_id'] ?? 0)),
        'type' => dashglpi_ticket_create_normalize_type($post['type'] ?? 1),
        'urgency' => dashglpi_ticket_create_normalize_urgency($post['urgency'] ?? 3),
        'itilcategories_id' => max(0, (int) ($post['itilcategories_id'] ?? 0)),
        'name' => $name,
        'content' => $content,
    ];
}

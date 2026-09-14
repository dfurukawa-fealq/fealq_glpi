<?php

require_once __DIR__ . '/ajax_endpoint.php';

function dashglpi_admin_entities(): array
{
    if (dashglpi_admin_table_exists('glpi_plugin_dashglpi_entitysmtp_configs')) {
        return dashglpi_admin_entities_with_effective_smtp(dashglpi_fetch_all(
            "SELECT e.id, e.name, e.completename, e.entities_id, e.notification_subject_tag, e.autoclose_delay,
                    COALESCE(es.is_active, 0) AS entitysmtp_is_active,
                    COALESCE(es.host, '') AS entitysmtp_host,
                    COALESCE(es.port, 0) AS entitysmtp_port,
                    COALESCE(es.encryption, '') AS entitysmtp_encryption,
                    COALESCE(es.smtp_username, '') AS entitysmtp_username,
                    CASE WHEN COALESCE(es.smtp_passwd, '') <> '' THEN 1 ELSE 0 END AS entitysmtp_password_configured
             FROM glpi_entities e
             LEFT JOIN glpi_plugin_dashglpi_entitysmtp_configs es
               ON es.entities_id = e.id
             ORDER BY e.completename ASC, e.name ASC"
        ));
    }

    $entities = dashglpi_fetch_all(
        "SELECT id, name, completename, entities_id, notification_subject_tag, autoclose_delay
         FROM glpi_entities
         ORDER BY completename ASC, name ASC"
    );
    foreach ($entities as &$entity) {
        $entity['entitysmtp_is_active'] = 0;
        $entity['entitysmtp_host'] = '';
        $entity['entitysmtp_port'] = 0;
        $entity['entitysmtp_encryption'] = '';
        $entity['entitysmtp_username'] = '';
        $entity['entitysmtp_password_configured'] = 0;
    }
    unset($entity);

    return dashglpi_admin_entities_with_effective_smtp($entities);
}

function dashglpi_admin_entities_with_effective_smtp(array $entities): array
{
    $byId = [];
    foreach ($entities as $entity) {
        $byId[(int) ($entity['id'] ?? 0)] = $entity;
    }

    foreach ($entities as &$entity) {
        foreach ([
            'entitysmtp_is_active' => 0,
            'entitysmtp_host' => '',
            'entitysmtp_port' => 0,
            'entitysmtp_encryption' => '',
            'entitysmtp_username' => '',
            'entitysmtp_password_configured' => 0,
        ] as $key => $default) {
            if (!array_key_exists($key, $entity)) {
                $entity[$key] = $default;
            }
        }

        $effective = dashglpi_admin_entity_effective_smtp($entity, $byId);
        $entity['entitysmtp_effective_origin'] = $effective['origin'];
        $entity['entitysmtp_effective_source_entities_id'] = $effective['source_entities_id'];
        $entity['entitysmtp_effective_source_entity_name'] = $effective['source_entity_name'];
        $entity['entitysmtp_effective_host'] = $effective['host'];
        $entity['entitysmtp_effective_port'] = $effective['port'];
        $entity['entitysmtp_effective_encryption'] = $effective['encryption'];
        $entity['entitysmtp_effective_username'] = $effective['smtp_username'];
        $entity['entitysmtp_effective_password_configured'] = $effective['password_configured'];
    }
    unset($entity);

    return $entities;
}

function dashglpi_admin_entity_effective_smtp(array $entity, array $byId): array
{
    $visited = [];
    $current = $entity;

    while (is_array($current)) {
        $id = (int) ($current['id'] ?? 0);
        if (isset($visited[$id])) {
            break;
        }
        $visited[$id] = true;

        if (dashglpi_admin_entitysmtp_direct_active($current)) {
            return [
                'origin' => $id === (int) ($entity['id'] ?? 0) ? 'entity' : 'inherited',
                'source_entities_id' => $id,
                'source_entity_name' => (string) ($current['completename'] ?: $current['name'] ?: 'Entidade #' . $id),
                'host' => (string) ($current['entitysmtp_host'] ?? ''),
                'port' => (int) ($current['entitysmtp_port'] ?? 0),
                'encryption' => (string) ($current['entitysmtp_encryption'] ?? ''),
                'smtp_username' => (string) ($current['entitysmtp_username'] ?? ''),
                'password_configured' => (int) ($current['entitysmtp_password_configured'] ?? 0),
            ];
        }

        if ($id === 0) {
            break;
        }

        $parentId = max(0, (int) ($current['entities_id'] ?? 0));
        if ($parentId === $id || !isset($byId[$parentId])) {
            break;
        }

        $current = $byId[$parentId];
    }

    return [
        'origin' => 'global',
        'source_entities_id' => 0,
        'source_entity_name' => 'SMTP global do GLPI',
        'host' => '',
        'port' => 0,
        'encryption' => '',
        'smtp_username' => '',
        'password_configured' => 0,
    ];
}

function dashglpi_admin_entitysmtp_direct_active(array $entity): bool
{
    return (int) ($entity['entitysmtp_is_active'] ?? 0) === 1
        && trim((string) ($entity['entitysmtp_host'] ?? '')) !== '';
}

function dashglpi_admin_table_exists(string $table): bool
{
    $row = dashglpi_fetch_one(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?",
        [$table]
    );

    return (int) ($row['total'] ?? 0) > 0;
}

function dashglpi_admin_groups(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, completename, entities_id, groups_id, is_recursive
         FROM glpi_groups
         ORDER BY completename ASC, name ASC"
    );
}

function dashglpi_admin_categories(): array
{
    return dashglpi_fetch_all(
        "SELECT c.id, c.name, c.completename, c.entities_id, c.itilcategories_id,
                c.is_recursive, c.is_helpdeskvisible, c.is_incident, c.is_request,
                c.is_problem, c.is_change,
                COALESCE(e.completename, 'Entidade raiz') AS entity_name,
                COALESCE(p.completename, '') AS parent_name
         FROM glpi_itilcategories c
         LEFT JOIN glpi_entities e ON e.id = c.entities_id
         LEFT JOIN glpi_itilcategories p ON p.id = c.itilcategories_id
         ORDER BY c.completename ASC, c.name ASC"
    );
}

function dashglpi_admin_profiles(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name
         FROM glpi_profiles
         ORDER BY name ASC"
    );
}

function dashglpi_admin_profiles_full(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, interface, comment
         FROM glpi_profiles
         ORDER BY name ASC"
    );
}

function dashglpi_admin_profile_payload(array $post): array
{
    $action = (string) ($post['profile_action'] ?? 'save');

    if ($action === 'clone') {
        return [
            'action' => 'clone',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome do novo perfil.'),
        ];
    }

    if ($action === 'delete') {
        return [
            'action' => 'delete',
            'id' => max(0, (int) ($post['id'] ?? 0)),
        ];
    }

    $interface = (string) ($post['interface'] ?? 'helpdesk');
    if (!in_array($interface, ['central', 'helpdesk'], true)) {
        throw new RuntimeException('Interface do perfil invalida.');
    }

    return [
        'action' => 'save',
        'id' => max(0, (int) ($post['id'] ?? 0)),
        'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome do perfil.'),
        'interface' => $interface,
        'comment' => trim((string) ($post['comment'] ?? '')),
    ];
}

function dashglpi_admin_glpi_users(): array
{
    return dashglpi_fetch_all(
        "SELECT u.id, u.name, u.firstname, u.realname, u.is_active,
                ue.email,
                COALESCE(pu_primary.entities_id, 0) AS entities_id,
                COALESCE(pu_primary.profiles_id, 0) AS profiles_id,
                COALESCE(gu_primary.groups_id, 0) AS groups_id,
                COALESCE(
                    e_primary.completename,
                    CASE WHEN pu_primary.id IS NOT NULL THEN 'Entidade raiz' ELSE NULL END
                ) AS entity_name,
                p_primary.name AS profile_name,
                COALESCE(g_primary.completename, g_primary.name, '') AS group_name,
                COALESCE(
                    NULLIF(
                        GROUP_CONCAT(
                            DISTINCT CASE
                                WHEN pu_all.id IS NOT NULL THEN COALESCE(e_all.completename, 'Entidade raiz')
                                ELSE NULL
                            END
                            ORDER BY CASE
                                WHEN pu_all.id IS NOT NULL THEN COALESCE(e_all.completename, 'Entidade raiz')
                                ELSE NULL
                            END
                            SEPARATOR ', '
                        ),
                        ''
                    ),
                    COALESCE(
                        e_primary.completename,
                        CASE WHEN pu_primary.id IS NOT NULL THEN 'Entidade raiz' ELSE '' END
                    )
                ) AS entity_list,
                COALESCE(
                    NULLIF(
                        GROUP_CONCAT(
                            DISTINCT p_all.name
                            ORDER BY p_all.name
                            SEPARATOR ', '
                        ),
                        ''
                    ),
                    COALESCE(p_primary.name, '')
                ) AS profile_list
         FROM glpi_users u
         LEFT JOIN glpi_useremails ue
           ON ue.users_id = u.id
          AND ue.is_default = 1
         LEFT JOIN glpi_profiles_users pu_primary
           ON pu_primary.id = (
                SELECT pu2.id
                FROM glpi_profiles_users pu2
                LEFT JOIN glpi_profiles p2
                  ON p2.id = pu2.profiles_id
                WHERE pu2.users_id = u.id
                ORDER BY CASE
                            WHEN p2.name IN ('Super-Admin', 'Admin') THEN 0
                            ELSE 1
                         END ASC,
                         pu2.is_default_profile DESC,
                         pu2.is_dynamic ASC,
                         pu2.id ASC
                LIMIT 1
           )
         LEFT JOIN glpi_profiles p_primary
           ON p_primary.id = pu_primary.profiles_id
         LEFT JOIN glpi_entities e_primary
           ON e_primary.id = pu_primary.entities_id
         LEFT JOIN glpi_groups_users gu_primary
           ON gu_primary.id = (
                SELECT gu2.id
                FROM glpi_groups_users gu2
                WHERE gu2.users_id = u.id
                ORDER BY gu2.is_dynamic ASC, gu2.id ASC
                LIMIT 1
           )
         LEFT JOIN glpi_groups g_primary
           ON g_primary.id = gu_primary.groups_id
         LEFT JOIN glpi_profiles_users pu_all
           ON pu_all.users_id = u.id
         LEFT JOIN glpi_entities e_all
           ON e_all.id = pu_all.entities_id
         LEFT JOIN glpi_profiles p_all
           ON p_all.id = pu_all.profiles_id
         WHERE u.is_deleted = 0
         GROUP BY u.id, u.name, u.firstname, u.realname, u.is_active, ue.email,
                  pu_primary.id, pu_primary.entities_id, pu_primary.profiles_id,
                  p_primary.name, e_primary.completename,
                  gu_primary.groups_id, g_primary.completename, g_primary.name
         ORDER BY u.name ASC"
    );
}

function dashglpi_admin_entity_payload(array $post): array
{
    $action = (string) ($post['entity_action'] ?? 'save');

    if ($action === 'clone') {
        return [
            'action' => 'clone',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome da nova entidade.'),
        ];
    }

    if ($action === 'delete') {
        return [
            'action' => 'delete',
            'id' => max(0, (int) ($post['id'] ?? 0)),
        ];
    }

    return [
        'action' => $action === 'update' ? 'update' : 'save',
        'id' => max(0, (int) ($post['id'] ?? 0)),
        'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome da entidade.'),
        'parent_id' => max(0, (int) ($post['parent_id'] ?? 0)),
        'create_group' => !empty($post['create_group']),
        'create_sla_policy' => !empty($post['create_sla_policy']),
        'solution_closure' => dashglpi_admin_entity_solution_closure_payload($post, true),
    ];
}

function dashglpi_admin_entity_solution_closure_payload(array $post, bool $allowImplicitInherit = false): array
{
    $rawMode = array_key_exists('mode', $post)
        ? (string) ($post['mode'] ?? '')
        : (string) ($post['solution_closure_mode'] ?? '');
    $mode = trim($rawMode);
    if ($mode === '' && $allowImplicitInherit) {
        $mode = 'inherit';
    }
    if (!in_array($mode, ['inherit', 'immediate', 'days', 'never'], true)) {
        throw new RuntimeException('Modo do fechamento pós-solucao invalido.');
    }

    $rawDays = array_key_exists('days', $post)
        ? $post['days']
        : ($post['solution_closure_days'] ?? '');
    $days = trim((string) $rawDays);

    if ($mode === 'days') {
        if ($days === '' || !ctype_digit($days)) {
            throw new RuntimeException('Informe a quantidade de dias para o fechamento pós-solucao.');
        }

        $daysValue = (int) $days;
        if ($daysValue < 1 || $daysValue > 99) {
            throw new RuntimeException('Fechamento pós-solucao deve estar entre 1 e 99 dias.');
        }
    } else {
        $daysValue = 0;
    }

    return [
        'action' => 'save',
        'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
        'mode' => $mode,
        'days' => $daysValue,
    ];
}

function dashglpi_admin_entity_notification_prefix_payload(array $post): array
{
    $mode = trim((string) ($post['mode'] ?? ''));
    if (!in_array($mode, ['inherit', 'custom'], true)) {
        throw new RuntimeException('Modo do prefixo de notificacoes invalido.');
    }

    $prefix = dashglpi_admin_normalize_spaces((string) ($post['notification_subject_tag'] ?? ''));
    $prefix = substr($prefix, 0, 255);

    if ($mode === 'custom' && $prefix === '') {
        throw new RuntimeException('Informe o prefixo de notificacoes.');
    }

    return [
        'action' => 'save',
        'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
        'mode' => $mode,
        'notification_subject_tag' => $prefix,
    ];
}

function dashglpi_admin_entitysmtp_payload(array $post): array
{
    $action = trim((string) ($post['entitysmtp_action'] ?? $post['action'] ?? 'save_entity_smtp'));
    if (!in_array($action, ['save_entity_smtp', 'test_entity_smtp', 'activate_owner', 'restore_glpi_owner', 'status'], true)) {
        throw new RuntimeException('Acao SMTP por entidade invalida.');
    }

    if (in_array($action, ['activate_owner', 'restore_glpi_owner', 'status'], true)) {
        return ['action' => $action];
    }

    $entityId = max(0, (int) ($post['entities_id'] ?? 0));
    if ($action === 'test_entity_smtp') {
        return [
            'action' => $action,
            'entities_id' => $entityId,
        ];
    }

    return [
        'action' => $action,
        'entities_id' => $entityId,
        'admin_email' => dashglpi_admin_email_required((string) ($post['admin_email'] ?? '')),
        'admin_email_name' => dashglpi_admin_text((string) ($post['admin_email_name'] ?? ''), 255),
        'from_email' => dashglpi_admin_email_required((string) ($post['from_email'] ?? '')),
        'from_email_name' => dashglpi_admin_text((string) ($post['from_email_name'] ?? ''), 255),
        'replyto_email' => dashglpi_admin_email_required((string) ($post['replyto_email'] ?? '')),
        'replyto_email_name' => dashglpi_admin_text((string) ($post['replyto_email_name'] ?? ''), 255),
        'mailing_signature' => substr((string) ($post['mailing_signature'] ?? ''), 0, 65535),
        'is_active' => !empty($post['is_active']) ? 1 : 0,
        'host' => dashglpi_admin_text((string) ($post['host'] ?? ''), 255),
        'port' => (string) min(65535, max(0, (int) ($post['port'] ?? 587))),
        'encryption' => dashglpi_admin_entitysmtp_encryption((string) ($post['encryption'] ?? 'tls')),
        'smtp_username' => dashglpi_admin_text((string) ($post['smtp_username'] ?? ''), 255),
        'smtp_passwd' => (string) ($post['smtp_passwd'] ?? ''),
        'smtp_check_certificate' => !empty($post['smtp_check_certificate']) ? 1 : 0,
    ];
}

function dashglpi_admin_group_payload(array $post): array
{
    $action = (string) ($post['group_action'] ?? 'save');

    if ($action === 'clone') {
        return [
            'action' => 'clone',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome do novo grupo.'),
        ];
    }

    if ($action === 'delete') {
        return [
            'action' => 'delete',
            'id' => max(0, (int) ($post['id'] ?? 0)),
        ];
    }

    return [
        'action' => $action === 'update' ? 'update' : 'save',
        'id' => max(0, (int) ($post['id'] ?? 0)),
        'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome do grupo.'),
        'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
        'comment' => trim((string) ($post['comment'] ?? '')),
        'is_recursive' => !empty($post['is_recursive']) ? 1 : 0,
    ];
}

function dashglpi_admin_category_payload(array $post): array
{
    return array_merge(
        [
            'name' => dashglpi_admin_required_string((string) ($post['name'] ?? ''), 'Informe o nome da categoria.'),
            'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
            'parent_id' => max(0, (int) ($post['parent_id'] ?? 0)),
            'comment' => trim((string) ($post['comment'] ?? '')),
        ],
        dashglpi_admin_category_flags($post)
    );
}

function dashglpi_admin_user_payload(array $post): array
{
    $action = (string) ($post['user_action'] ?? 'save');

    if ($action === 'clone') {
        return [
            'action' => 'clone',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'login' => dashglpi_admin_required_string((string) ($post['login'] ?? ''), 'Informe o login do novo usuario.'),
        ];
    }

    return [
        'action' => 'save',
        'id' => max(0, (int) ($post['id'] ?? 0)),
        'login' => dashglpi_admin_required_string((string) ($post['login'] ?? ''), 'Informe o login do usuario.'),
        'firstname' => trim((string) ($post['firstname'] ?? '')),
        'realname' => trim((string) ($post['realname'] ?? '')),
        'email' => dashglpi_admin_email((string) ($post['email'] ?? '')),
        'password' => (string) ($post['password'] ?? ''),
        'is_active' => !empty($post['is_active']) ? 1 : 0,
        'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
        'profiles_id' => max(0, (int) ($post['profiles_id'] ?? 0)),
        'groups_id' => max(0, (int) ($post['groups_id'] ?? 0)),
    ];
}

function dashglpi_admin_category_import_preview(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Selecione um arquivo CSV valido.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || (!is_uploaded_file($tmpName) && !is_file($tmpName))) {
        throw new RuntimeException('Arquivo CSV nao encontrado.');
    }

    $handle = fopen($tmpName, 'rb');
    if (!$handle) {
        throw new RuntimeException('Nao foi possivel ler o arquivo CSV.');
    }

    try {
        $header = fgetcsv($handle, 0, ';');
        if (!is_array($header)) {
            throw new RuntimeException('CSV vazio.');
        }

        $columns = dashglpi_admin_category_csv_columns($header);
        if (!isset($columns['nome completo'], $columns['entidade'])) {
            throw new RuntimeException('CSV deve conter as colunas "Nome completo" e "Entidade".');
        }

        $entities = dashglpi_admin_entities();
        $entityMap = dashglpi_admin_entity_lookup($entities);
        $existingMap = dashglpi_admin_category_existing_map(dashglpi_admin_categories());
        $plannedMap = [];
        $seenRows = [];
        $seenExisting = [];
        $summary = [
            'ready' => 0,
            'created' => 0,
            'existing' => 0,
            'ignored' => 0,
            'errors' => 0,
            'fallback_root' => 0,
        ];
        $items = [];
        $rows = [];
        $fakeId = -1;
        $line = 1;

        while (($csvRow = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            if (dashglpi_admin_category_csv_blank_row($csvRow)) {
                continue;
            }

            $fullName = dashglpi_admin_normalize_spaces((string) ($csvRow[$columns['nome completo']] ?? ''));
            $entityText = dashglpi_admin_normalize_spaces((string) ($csvRow[$columns['entidade']] ?? ''));
            $segments = dashglpi_admin_category_segments($fullName);

            if (!$segments || dashglpi_admin_category_is_artifact($fullName)) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $fullName, $entityText, 'Linha ignorada: categoria invalida ou artefato.');
                continue;
            }

            $rowKey = dashglpi_admin_key($entityText . '|' . implode('>', $segments));
            if (isset($seenRows[$rowKey])) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $fullName, $entityText, 'Linha duplicada no CSV.');
                continue;
            }
            $seenRows[$rowKey] = true;

            $entityInfo = dashglpi_admin_resolve_csv_entity($entityText, $entityMap);
            if (!empty($entityInfo['fallback'])) {
                $summary['fallback_root']++;
            }

            $parentId = 0;
            foreach ($segments as $segment) {
                $nodeKey = dashglpi_admin_category_node_key((int) $entityInfo['id'], $parentId, $segment);
                if (isset($existingMap[$nodeKey])) {
                    if (!isset($seenExisting[$nodeKey])) {
                        $summary['existing']++;
                        $seenExisting[$nodeKey] = true;
                    }
                    $parentId = (int) $existingMap[$nodeKey]['id'];
                    continue;
                }

                if (isset($plannedMap[$nodeKey])) {
                    $parentId = (int) $plannedMap[$nodeKey]['id'];
                    continue;
                }

                $plannedMap[$nodeKey] = ['id' => $fakeId--];
                $summary['created']++;
                $parentId = (int) $plannedMap[$nodeKey]['id'];
            }

            $summary['ready']++;
            $status = !empty($entityInfo['fallback']) ? 'fallback' : 'ready';
            $reason = !empty($entityInfo['fallback'])
                ? 'Entidade nao encontrada, usando raiz.'
                : 'Pronto para importar.';
            $rows[] = dashglpi_admin_category_preview_row($line, $status, $fullName, $entityText, $reason, $entityInfo['name']);
            $items[] = array_merge(
                [
                    'path_segments' => $segments,
                    'full_name' => implode(' > ', $segments),
                    'entities_id' => (int) $entityInfo['id'],
                    'entity_name' => (string) $entityInfo['name'],
                    'source_entity' => $entityText,
                    'source_line' => $line,
                    'comment' => 'Importado pelo CSV de Categorias ITIL do DashGLPI.',
                ],
                dashglpi_admin_category_default_flags()
            );
        }
    } finally {
        fclose($handle);
    }

    return [
        'token' => bin2hex(random_bytes(16)),
        'summary' => $summary,
        'items' => $items,
        'rows' => $rows,
        'can_confirm' => count($items) > 0,
        'filename' => (string) ($file['name'] ?? ''),
    ];
}

function dashglpi_admin_category_default_flags(): array
{
    return [
        'is_recursive' => 1,
        'is_helpdeskvisible' => 1,
        'is_incident' => 1,
        'is_request' => 1,
        'is_problem' => 0,
        'is_change' => 0,
    ];
}

function dashglpi_admin_category_flags(array $source): array
{
    $defaults = dashglpi_admin_category_default_flags();
    $flags = [];
    foreach ($defaults as $key => $default) {
        $flags[$key] = array_key_exists($key, $source)
            ? (!empty($source[$key]) ? 1 : 0)
            : $default;
    }
    return $flags;
}

function dashglpi_admin_required_string(string $value, string $message): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        throw new RuntimeException($message);
    }

    return substr($value, 0, 255);
}

function dashglpi_admin_text(string $value, int $max): string
{
    return substr(dashglpi_admin_normalize_spaces($value), 0, $max);
}

function dashglpi_admin_normalize_spaces(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function dashglpi_admin_key(string $value): string
{
    $value = dashglpi_admin_normalize_spaces($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    return strtr($value, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
}

function dashglpi_admin_category_csv_columns(array $header): array
{
    $columns = [];
    foreach ($header as $index => $name) {
        $cleanName = preg_replace('/^\xEF\xBB\xBF/', '', (string) $name) ?? '';
        $cleanName = trim($cleanName, "\" \t\n\r\0\x0B");
        $key = dashglpi_admin_key($cleanName);
        if ($key !== '') {
            $columns[$key] = $index;
        }
    }
    return $columns;
}

function dashglpi_admin_category_csv_blank_row(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}

function dashglpi_admin_category_segments(string $fullName): array
{
    $parts = array_map('dashglpi_admin_normalize_spaces', preg_split('/\s*>\s*/', $fullName) ?: []);
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    foreach ($parts as $part) {
        if (dashglpi_admin_category_is_artifact($part) || strlen($part) > 255) {
            return [];
        }
    }
    return $parts;
}

function dashglpi_admin_category_is_artifact(string $value): bool
{
    return preg_match('/^\(\d+\)$/', trim($value)) === 1;
}

function dashglpi_admin_entity_lookup(array $entities): array
{
    $map = [];
    foreach ($entities as $entity) {
        $id = (int) ($entity['id'] ?? 0);
        foreach ([(string) ($entity['name'] ?? ''), (string) ($entity['completename'] ?? '')] as $name) {
            $key = dashglpi_admin_key($name);
            if ($key !== '') {
                $map[$key] = [
                    'id' => $id,
                    'name' => (string) ($entity['completename'] ?: $entity['name'] ?: 'Entidade #' . $id),
                ];
            }
        }
    }
    return $map;
}

function dashglpi_admin_resolve_csv_entity(string $entityText, array $entityMap): array
{
    $key = dashglpi_admin_key($entityText);
    if ($key !== '' && isset($entityMap[$key])) {
        return [
            'id' => (int) $entityMap[$key]['id'],
            'name' => (string) $entityMap[$key]['name'],
            'fallback' => false,
        ];
    }

    return [
        'id' => 0,
        'name' => 'Entidade raiz',
        'fallback' => $entityText !== '',
    ];
}

function dashglpi_admin_category_existing_map(array $categories): array
{
    $map = [];
    foreach ($categories as $category) {
        $map[dashglpi_admin_category_node_key(
            (int) ($category['entities_id'] ?? 0),
            (int) ($category['itilcategories_id'] ?? 0),
            (string) ($category['name'] ?? '')
        )] = $category;
    }
    return $map;
}

function dashglpi_admin_category_node_key(int $entitiesId, int $parentId, string $name): string
{
    return $entitiesId . '|' . $parentId . '|' . dashglpi_admin_key($name);
}

function dashglpi_admin_category_preview_row(
    int $line,
    string $status,
    string $fullName,
    string $sourceEntity,
    string $reason,
    string $entityName = ''
): array {
    return [
        'line' => $line,
        'status' => $status,
        'full_name' => $fullName,
        'source_entity' => $sourceEntity,
        'entity_name' => $entityName !== '' ? $entityName : ($sourceEntity !== '' ? $sourceEntity : 'Entidade raiz'),
        'reason' => $reason,
    ];
}

function dashglpi_admin_email(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail valido.');
    }

    return substr($email, 0, 255);
}

function dashglpi_admin_email_required(string $email): string
{
    $email = dashglpi_admin_email($email);
    if ($email === '') {
        throw new RuntimeException('Informe um e-mail valido.');
    }

    return $email;
}

function dashglpi_admin_entitysmtp_encryption(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['none', 'tls', 'ssl'], true) ? $value : 'tls';
}

function dashglpi_admin_bridge_request(string $endpoint, array $payload, array $files = []): array
{
    $baseUrl = dashglpi_env('DASHGLPI_BRIDGE_BASE_URL', '');
    if ($baseUrl === '') {
        $slaUrl = dashglpi_env('DASHGLPI_BRIDGE_URL', 'http://glpi/plugins/dashglpi/ajax/sla_config.php');
        $baseUrl = preg_replace('#/ajax/sla_config\.php$#', '', $slaUrl) ?: 'http://glpi/plugins/dashglpi';
    }

    $url = rtrim((string) $baseUrl, '/') . '/ajax/' . ltrim($endpoint, '/');
    $token = dashglpi_env('DASHGLPI_BRIDGE_TOKEN', '');

    if (!$url || !$token) {
        throw new RuntimeException('Bridge GLPI nao configurado. Defina DASHGLPI_BRIDGE_TOKEN.');
    }

    $headers = [
        'Accept: application/json',
        'X-DashGLPI-Bridge-Token: ' . $token,
    ];
    if ($files) {
        $boundary = '--------------------------dashglpi' . bin2hex(random_bytes(12));
        $body = dashglpi_admin_bridge_multipart_body($boundary, $payload, $files);
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
    } else {
        $body = json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Falha ao serializar payload.');
        }
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $transportError = null;
    set_error_handler(static function (int $severity, string $message) use (&$transportError): bool {
        $transportError = $message;
        return true;
    });
    try {
        $response = file_get_contents($url, false, $context);
    } finally {
        restore_error_handler();
    }

    $status = dashglpi_admin_http_status($http_response_header ?? []);
    $data = is_string($response) ? json_decode($response, true) : null;

    if ($response === false) {
        throw new RuntimeException('Falha ao chamar bridge GLPI' . ($transportError ? ': ' . $transportError : '.'));
    }

    if (!is_array($data)) {
        throw new RuntimeException('Resposta invalida do bridge GLPI. HTTP ' . ($status ?: 'sem status') . '.');
    }

    if ($status >= 400 || empty($data['ok'])) {
        $error = trim((string) ($data['error'] ?? $data['message'] ?? ''));
        throw new RuntimeException($error !== '' ? $error : 'Bridge GLPI retornou erro HTTP ' . $status . '.');
    }

    return $data;
}

function dashglpi_admin_bridge_multipart_body(string $boundary, array $payload, array $files): string
{
    $eol = "\r\n";
    $body = '';
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="payload"' . $eol . $eol;
    $body .= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $eol;

    foreach ($files as $file) {
        $fieldName = (string) ($file['field_name'] ?? 'attachments[]');
        $clientName = (string) ($file['client_name'] ?? 'arquivo');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            continue;
        }

        $content = file_get_contents($tmpName);
        if ($content === false) {
            throw new RuntimeException('Falha ao ler anexo enviado ao bridge GLPI.');
        }

        $mimeType = trim((string) ($file['mime_type'] ?? 'application/octet-stream'));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $safeClientName = str_replace(["\r", "\n", '"'], ['', '', "'"], $clientName);
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $safeClientName . '"' . $eol;
        $body .= 'Content-Type: ' . $mimeType . $eol . $eol;
        $body .= $content . $eol;
    }

    $body .= '--' . $boundary . '--' . $eol;
    return $body;
}

function dashglpi_admin_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $header, $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

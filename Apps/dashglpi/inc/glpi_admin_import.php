<?php

// Motor genérico de importação CSV (PLAN-20260708-017).
// Formato de referência: exportação CSV do próprio GLPI (separador ";", valores
// entre aspas, cabeçalho em PT-BR, BOM tolerado) — "o que o GLPI exporta, ele importa".
// Reutiliza os helpers de normalização/parse já validados no import de Categorias
// (dashglpi_admin_key, dashglpi_admin_category_segments, etc.) em glpi_admin.php.

require_once __DIR__ . '/glpi_admin.php';

const DASHGLPI_IMPORT_MAX_ROWS = 2000;
const DASHGLPI_IMPORT_CHUNK_SIZE = 200;

// ==================== Infraestrutura comum ====================

function dashglpi_admin_import_open(array $file): array
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

    $header = fgetcsv($handle, 0, ';');
    if (!is_array($header)) {
        fclose($handle);
        throw new RuntimeException('CSV vazio.');
    }

    return [
        'handle' => $handle,
        'columns' => dashglpi_admin_category_csv_columns($header),
        'filename' => (string) ($file['name'] ?? ''),
    ];
}

function dashglpi_admin_import_column(array $columns, array $aliases): ?int
{
    foreach ($aliases as $alias) {
        if (isset($columns[$alias])) {
            return (int) $columns[$alias];
        }
    }
    return null;
}

function dashglpi_admin_import_require_column(array $columns, array $aliases, string $message): int
{
    $index = dashglpi_admin_import_column($columns, $aliases);
    if ($index === null) {
        throw new RuntimeException($message);
    }
    return $index;
}

function dashglpi_admin_import_cell(array $csvRow, ?int $index): string
{
    if ($index === null) {
        return '';
    }
    return dashglpi_admin_normalize_spaces((string) ($csvRow[$index] ?? ''));
}

// Células multi-valor da exportação GLPI (ex.: "Perfis", "Entidades") podem vir
// separadas por vírgula ou quebra de linha — o import usa o primeiro valor.
function dashglpi_admin_import_first_value(string $value): string
{
    $parts = preg_split('/[\n,]+/', $value) ?: [];
    foreach ($parts as $part) {
        $part = dashglpi_admin_normalize_spaces($part);
        if ($part !== '') {
            return $part;
        }
    }
    return '';
}

function dashglpi_admin_import_bool(string $value, bool $default): int
{
    $key = dashglpi_admin_key($value);
    if ($key === '') {
        return $default ? 1 : 0;
    }
    if (in_array($key, ['1', 'sim', 'yes', 'true', 'ativo', 'ativa'], true)) {
        return 1;
    }
    if (in_array($key, ['0', 'nao', 'no', 'false', 'inativo', 'inativa'], true)) {
        return 0;
    }
    return $default ? 1 : 0;
}

function dashglpi_admin_import_summary_init(): array
{
    return [
        'ready' => 0,
        'created' => 0,
        'existing' => 0,
        'ignored' => 0,
        'errors' => 0,
        'fallback_root' => 0,
    ];
}

function dashglpi_admin_import_guard_rows(int $line): void
{
    if ($line - 1 > DASHGLPI_IMPORT_MAX_ROWS) {
        throw new RuntimeException('CSV excede o limite de ' . DASHGLPI_IMPORT_MAX_ROWS . ' linhas de dados.');
    }
}

function dashglpi_admin_import_finish(array $summary, array $items, array $rows, string $filename): array
{
    return [
        'token' => bin2hex(random_bytes(16)),
        'summary' => $summary,
        'items' => $items,
        'rows' => $rows,
        'can_confirm' => count($items) > 0,
        'filename' => $filename,
    ];
}

function dashglpi_admin_import_preview_response(array $preview): array
{
    return [
        'token' => $preview['token'],
        'summary' => $preview['summary'],
        'rows' => $preview['rows'],
        'can_confirm' => $preview['can_confirm'],
        'filename' => $preview['filename'],
    ];
}

function dashglpi_admin_import_confirm_items(string $token, string $sessionKey): array
{
    $preview = $_SESSION[$sessionKey] ?? null;

    if ($token === '' || !is_array($preview) || !hash_equals((string) ($preview['token'] ?? ''), $token)) {
        throw new RuntimeException('Previa de importacao expirada. Envie o CSV novamente.');
    }

    if (empty($preview['items']) || !is_array($preview['items'])) {
        throw new RuntimeException('Nao ha registros validos para importar.');
    }

    return $preview['items'];
}

// Envia os itens confirmados ao bridge em blocos, agregando os sumários
// (bridge tem timeout de 20s por chamada — ver PLAN-20260708-017 §6).
function dashglpi_admin_import_dispatch(string $endpoint, array $items, array $extraPayload = []): array
{
    $summary = ['created' => 0, 'existing' => 0, 'errors' => 0];
    $resultItems = [];
    $message = '';

    foreach (array_chunk($items, DASHGLPI_IMPORT_CHUNK_SIZE) as $chunk) {
        $data = dashglpi_admin_bridge_request($endpoint, $extraPayload + ['items' => $chunk]);
        foreach (['created', 'existing', 'errors'] as $key) {
            $summary[$key] += (int) ($data['summary'][$key] ?? 0);
        }
        if (is_array($data['items'] ?? null)) {
            $resultItems = array_merge($resultItems, $data['items']);
        }
        $message = (string) ($data['message'] ?? $message);
    }

    return [
        'summary' => $summary,
        'items' => $resultItems,
        'message' => $message,
    ];
}

function dashglpi_admin_import_node_key(int $scopeId, int $parentId, string $name): string
{
    return $scopeId . '|' . $parentId . '|' . dashglpi_admin_key($name);
}

// ==================== Entidades ====================

// A exportação do GLPI traz o completename com a entidade raiz como primeiro
// segmento (ex.: "Entidade Raiz > Cliente > Filial") — o segmento raiz é removido.
function dashglpi_admin_import_root_entity_keys(array $entities): array
{
    $keys = [
        dashglpi_admin_key('Entidade raiz') => true,
        dashglpi_admin_key('Root entity') => true,
    ];
    foreach ($entities as $entity) {
        if ((int) ($entity['id'] ?? -1) !== 0) {
            continue;
        }
        foreach ([(string) ($entity['name'] ?? ''), (string) ($entity['completename'] ?? '')] as $name) {
            $key = dashglpi_admin_key($name);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
    }
    return $keys;
}

function dashglpi_admin_entity_import_preview(array $file): array
{
    $csv = dashglpi_admin_import_open($file);
    $nameIndex = dashglpi_admin_import_require_column($csv['columns'], ['nome completo', 'complete name'], 'CSV deve conter a coluna "Nome completo".');

    $entities = dashglpi_admin_entities();
    $rootKeys = dashglpi_admin_import_root_entity_keys($entities);

    $existingMap = [];
    foreach ($entities as $entity) {
        $id = (int) ($entity['id'] ?? 0);
        if ($id === 0) {
            continue;
        }
        $existingMap[dashglpi_admin_import_node_key(0, max(0, (int) ($entity['entities_id'] ?? 0)), (string) ($entity['name'] ?? ''))] = $id;
    }

    $summary = dashglpi_admin_import_summary_init();
    $plannedMap = [];
    $seenRows = [];
    $seenExisting = [];
    $items = [];
    $rows = [];
    $fakeId = -1;
    $line = 1;

    try {
        while (($csvRow = fgetcsv($csv['handle'], 0, ';')) !== false) {
            $line++;
            dashglpi_admin_import_guard_rows($line);
            if (dashglpi_admin_category_csv_blank_row($csvRow)) {
                continue;
            }

            $fullName = dashglpi_admin_import_cell($csvRow, $nameIndex);
            $segments = dashglpi_admin_category_segments($fullName);
            if ($segments && isset($rootKeys[dashglpi_admin_key($segments[0])])) {
                array_shift($segments);
            }

            if (!$segments) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $fullName, '', 'Linha ignorada: entidade raiz ou nome invalido.', 'Entidade raiz');
                continue;
            }

            $rowKey = dashglpi_admin_key(implode('>', $segments));
            if (isset($seenRows[$rowKey])) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $fullName, '', 'Linha duplicada no CSV.', 'Entidade raiz');
                continue;
            }
            $seenRows[$rowKey] = true;

            $parentId = 0;
            foreach ($segments as $segment) {
                $nodeKey = dashglpi_admin_import_node_key(0, $parentId, $segment);
                if (isset($existingMap[$nodeKey])) {
                    if (!isset($seenExisting[$nodeKey])) {
                        $summary['existing']++;
                        $seenExisting[$nodeKey] = true;
                    }
                    $parentId = (int) $existingMap[$nodeKey];
                    continue;
                }
                if (isset($plannedMap[$nodeKey])) {
                    $parentId = (int) $plannedMap[$nodeKey];
                    continue;
                }
                $plannedMap[$nodeKey] = $fakeId--;
                $summary['created']++;
                $parentId = (int) $plannedMap[$nodeKey];
            }

            $summary['ready']++;
            $rows[] = dashglpi_admin_category_preview_row($line, 'ready', implode(' > ', $segments), '', 'Pronto para importar.', 'Entidade raiz');
            $items[] = [
                'path_segments' => $segments,
                'full_name' => implode(' > ', $segments),
                'source_line' => $line,
            ];
        }
    } finally {
        fclose($csv['handle']);
    }

    return dashglpi_admin_import_finish($summary, $items, $rows, $csv['filename']);
}

// ==================== Grupos ====================

function dashglpi_admin_group_import_preview(array $file): array
{
    $csv = dashglpi_admin_import_open($file);
    $nameIndex = dashglpi_admin_import_require_column($csv['columns'], ['nome completo', 'nome', 'complete name', 'name'], 'CSV deve conter a coluna "Nome completo" (ou "Nome").');
    $entityIndex = dashglpi_admin_import_column($csv['columns'], ['entidade', 'entidades', 'entity', 'entities']);
    $recursiveIndex = dashglpi_admin_import_column($csv['columns'], ['recursivo', 'recursiva', 'recursivo nas subentidades', 'recursive', 'child entities']);
    $commentIndex = dashglpi_admin_import_column($csv['columns'], ['comentarios', 'comentario', 'comments', 'comment']);

    $entityMap = dashglpi_admin_entity_lookup(dashglpi_admin_entities());

    $existingMap = [];
    foreach (dashglpi_admin_groups() as $group) {
        $existingMap[dashglpi_admin_import_node_key(
            max(0, (int) ($group['entities_id'] ?? 0)),
            max(0, (int) ($group['groups_id'] ?? 0)),
            (string) ($group['name'] ?? '')
        )] = (int) ($group['id'] ?? 0);
    }

    $summary = dashglpi_admin_import_summary_init();
    $plannedMap = [];
    $seenRows = [];
    $seenExisting = [];
    $items = [];
    $rows = [];
    $fakeId = -1;
    $line = 1;

    try {
        while (($csvRow = fgetcsv($csv['handle'], 0, ';')) !== false) {
            $line++;
            dashglpi_admin_import_guard_rows($line);
            if (dashglpi_admin_category_csv_blank_row($csvRow)) {
                continue;
            }

            $fullName = dashglpi_admin_import_cell($csvRow, $nameIndex);
            $entityText = dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $entityIndex));
            $segments = dashglpi_admin_category_segments($fullName);

            if (!$segments) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $fullName, $entityText, 'Linha ignorada: nome de grupo invalido.');
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
                $nodeKey = dashglpi_admin_import_node_key((int) $entityInfo['id'], $parentId, $segment);
                if (isset($existingMap[$nodeKey])) {
                    if (!isset($seenExisting[$nodeKey])) {
                        $summary['existing']++;
                        $seenExisting[$nodeKey] = true;
                    }
                    $parentId = (int) $existingMap[$nodeKey];
                    continue;
                }
                if (isset($plannedMap[$nodeKey])) {
                    $parentId = (int) $plannedMap[$nodeKey];
                    continue;
                }
                $plannedMap[$nodeKey] = $fakeId--;
                $summary['created']++;
                $parentId = (int) $plannedMap[$nodeKey];
            }

            $summary['ready']++;
            $status = !empty($entityInfo['fallback']) ? 'fallback' : 'ready';
            $reason = !empty($entityInfo['fallback'])
                ? 'Entidade nao encontrada, usando raiz.'
                : 'Pronto para importar.';
            $rows[] = dashglpi_admin_category_preview_row($line, $status, implode(' > ', $segments), $entityText, $reason, $entityInfo['name']);
            $items[] = [
                'path_segments' => $segments,
                'full_name' => implode(' > ', $segments),
                'entities_id' => (int) $entityInfo['id'],
                'entity_name' => (string) $entityInfo['name'],
                'source_entity' => $entityText,
                'is_recursive' => dashglpi_admin_import_bool(dashglpi_admin_import_cell($csvRow, $recursiveIndex), true),
                'comment' => dashglpi_admin_import_cell($csvRow, $commentIndex),
                'source_line' => $line,
            ];
        }
    } finally {
        fclose($csv['handle']);
    }

    return dashglpi_admin_import_finish($summary, $items, $rows, $csv['filename']);
}

// ==================== Perfis ====================

function dashglpi_admin_import_profile_interface(string $value): ?string
{
    $key = dashglpi_admin_key($value);
    if ($key === '') {
        return 'helpdesk';
    }
    if (str_contains($key, 'helpdesk') || str_contains($key, 'simplificada')) {
        return 'helpdesk';
    }
    if (str_contains($key, 'central') || str_contains($key, 'padrao') || str_contains($key, 'standard')) {
        return 'central';
    }
    return null;
}

function dashglpi_admin_profile_import_preview(array $file): array
{
    $csv = dashglpi_admin_import_open($file);
    $nameIndex = dashglpi_admin_import_require_column($csv['columns'], ['nome', 'name'], 'CSV deve conter a coluna "Nome".');
    $interfaceIndex = dashglpi_admin_import_column($csv['columns'], ['interface', 'interface do perfil', "profile's interface"]);
    $commentIndex = dashglpi_admin_import_column($csv['columns'], ['comentarios', 'comentario', 'comments', 'comment']);

    $existingMap = [];
    foreach (dashglpi_admin_profiles() as $profile) {
        $existingMap[dashglpi_admin_key((string) ($profile['name'] ?? ''))] = (int) ($profile['id'] ?? 0);
    }

    $summary = dashglpi_admin_import_summary_init();
    $seenRows = [];
    $items = [];
    $rows = [];
    $line = 1;

    try {
        while (($csvRow = fgetcsv($csv['handle'], 0, ';')) !== false) {
            $line++;
            dashglpi_admin_import_guard_rows($line);
            if (dashglpi_admin_category_csv_blank_row($csvRow)) {
                continue;
            }

            $name = dashglpi_admin_import_cell($csvRow, $nameIndex);
            $interfaceText = dashglpi_admin_import_cell($csvRow, $interfaceIndex);

            if ($name === '' || strlen($name) > 255) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $name, '', 'Linha ignorada: nome de perfil invalido.', '-');
                continue;
            }

            $nameKey = dashglpi_admin_key($name);
            if (isset($seenRows[$nameKey])) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $name, '', 'Linha duplicada no CSV.', '-');
                continue;
            }
            $seenRows[$nameKey] = true;

            $interface = dashglpi_admin_import_profile_interface($interfaceText);
            if ($interface === null) {
                $summary['errors']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'error', $name, '', 'Interface do perfil invalida: "' . $interfaceText . '".', '-');
                continue;
            }
            $interfaceLabel = $interface === 'central' ? 'Interface padrão (Central)' : 'Interface simplificada (Helpdesk)';

            if (isset($existingMap[$nameKey])) {
                $summary['existing']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'existing', $name, '', 'Perfil ja existe no GLPI.', $interfaceLabel);
                continue;
            }

            $summary['ready']++;
            $summary['created']++;
            // A exportação do GLPI não inclui "Interface": perfil novo entraria como
            // Helpdesk sem aviso — tornar isso visível na prévia (PLAN-20260709-018, decisão 3).
            if ($interfaceIndex === null) {
                $summary['fallback_root']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'warning', $name, '', 'Coluna "Interface" ausente no CSV; perfil sera criado com a interface simplificada (Helpdesk).', $interfaceLabel);
            } else {
                $rows[] = dashglpi_admin_category_preview_row($line, 'ready', $name, '', 'Pronto para importar.', $interfaceLabel);
            }
            $items[] = [
                'name' => $name,
                'full_name' => $name,
                'interface' => $interface,
                'comment' => dashglpi_admin_import_cell($csvRow, $commentIndex),
                'source_line' => $line,
            ];
        }
    } finally {
        fclose($csv['handle']);
    }

    return dashglpi_admin_import_finish($summary, $items, $rows, $csv['filename']);
}

// ==================== Usuários ====================

// Inclui usuários removidos (is_deleted=1): eles não aparecem na UI, mas o login
// continua reservado no GLPI (ver PLAN-20260708-017 §8.5).
function dashglpi_admin_user_login_map(): array
{
    $map = [];
    foreach (dashglpi_fetch_all('SELECT id, name, is_deleted FROM glpi_users') as $user) {
        $key = dashglpi_admin_key((string) ($user['name'] ?? ''));
        if ($key !== '') {
            $map[$key] = [
                'id' => (int) ($user['id'] ?? 0),
                'is_deleted' => (int) ($user['is_deleted'] ?? 0),
            ];
        }
    }
    return $map;
}

function dashglpi_admin_import_group_lookup(array $groups): array
{
    $map = [];
    foreach ($groups as $group) {
        $id = (int) ($group['id'] ?? 0);
        foreach ([(string) ($group['name'] ?? ''), (string) ($group['completename'] ?? '')] as $name) {
            $key = dashglpi_admin_key($name);
            if ($key !== '') {
                $map[$key] = [
                    'id' => $id,
                    'name' => (string) ($group['completename'] ?: $group['name'] ?: 'Grupo #' . $id),
                ];
            }
        }
    }
    return $map;
}

// $defaultProfileId: perfil aplicado a linhas sem coluna "Perfil" (seletor do form
// de import — PLAN-20260709-018, decisão 1). Validado contra a lista real de perfis.
// Aliases PT-BR + EN: os cabeçalhos da exportação GLPI dependem do idioma da sessão
// de quem exportou (PT-BR: "Usuário"/"Último nome"; EN: "Users"/"Last name").
function dashglpi_admin_user_import_preview(array $file, int $defaultProfileId = 0): array
{
    $csv = dashglpi_admin_import_open($file);
    $loginIndex = dashglpi_admin_import_require_column($csv['columns'], ['usuario', 'usuarios', 'login', 'user', 'users', 'username'], 'CSV deve conter a coluna "Usuário" (ou "Login").');
    $firstnameIndex = dashglpi_admin_import_column($csv['columns'], ['nome', 'primeiro nome', 'first name']);
    $realnameIndex = dashglpi_admin_import_column($csv['columns'], ['sobrenome', 'ultimo nome', 'last name', 'surname']);
    $emailIndex = dashglpi_admin_import_column($csv['columns'], ['e-mails', 'e-mail', 'email', 'emails']);
    $phoneIndex = dashglpi_admin_import_column($csv['columns'], ['telefone', 'telefones', 'phone', 'phones']);
    $entityIndex = dashglpi_admin_import_column($csv['columns'], ['entidade', 'entidades', 'entity', 'entities']);
    $profileIndex = dashglpi_admin_import_column($csv['columns'], ['perfil', 'perfis', 'profile', 'profiles']);
    $groupIndex = dashglpi_admin_import_column($csv['columns'], ['grupo', 'grupos', 'group', 'groups']);
    $activeIndex = dashglpi_admin_import_column($csv['columns'], ['ativo', 'active']);
    $passwordIndex = dashglpi_admin_import_column($csv['columns'], ['senha', 'password']);

    $entityMap = dashglpi_admin_entity_lookup(dashglpi_admin_entities());
    $groupMap = dashglpi_admin_import_group_lookup(dashglpi_admin_groups());
    $loginMap = dashglpi_admin_user_login_map();

    $profileMap = [];
    $profilesById = [];
    $privilegedProfiles = [];
    foreach (dashglpi_admin_profiles() as $profile) {
        $key = dashglpi_admin_key((string) ($profile['name'] ?? ''));
        if ($key !== '') {
            $info = [
                'id' => (int) ($profile['id'] ?? 0),
                'name' => (string) ($profile['name'] ?? ''),
            ];
            $profileMap[$key] = $info;
            $profilesById[$info['id']] = $info;
            if (in_array($key, ['super-admin', 'admin'], true)) {
                $privilegedProfiles[$key] = true;
            }
        }
    }

    // Nunca confiar no ID cru do POST: o perfil default precisa existir no GLPI.
    $defaultProfile = null;
    if ($defaultProfileId > 0) {
        if (!isset($profilesById[$defaultProfileId])) {
            throw new RuntimeException('Perfil padrao selecionado nao encontrado no GLPI.');
        }
        $defaultProfile = $profilesById[$defaultProfileId];
    }

    $summary = dashglpi_admin_import_summary_init();
    $seenRows = [];
    $items = [];
    $rows = [];
    $line = 1;

    try {
        while (($csvRow = fgetcsv($csv['handle'], 0, ';')) !== false) {
            $line++;
            dashglpi_admin_import_guard_rows($line);
            if (dashglpi_admin_category_csv_blank_row($csvRow)) {
                continue;
            }

            $login = dashglpi_admin_import_cell($csvRow, $loginIndex);
            $firstname = dashglpi_admin_import_cell($csvRow, $firstnameIndex);
            $realname = dashglpi_admin_import_cell($csvRow, $realnameIndex);
            $email = dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $emailIndex));
            $entityText = dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $entityIndex));
            $profileText = dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $profileIndex));
            $groupText = dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $groupIndex));
            $label = $login . (($firstname . $realname) !== '' ? ' — ' . trim($firstname . ' ' . $realname) : '');

            if ($login === '' || strlen($login) > 255) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $label, $entityText, 'Linha ignorada: login invalido.');
                continue;
            }

            $loginKey = dashglpi_admin_key($login);
            if (isset($seenRows[$loginKey])) {
                $summary['ignored']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'ignored', $label, $entityText, 'Linha duplicada no CSV.');
                continue;
            }
            $seenRows[$loginKey] = true;

            if (isset($loginMap[$loginKey])) {
                $summary['existing']++;
                $reason = 'Login ja existe no GLPI.'
                    . ((int) $loginMap[$loginKey]['is_deleted'] === 1 ? ' (usuario na lixeira do GLPI)' : '');
                $rows[] = dashglpi_admin_category_preview_row($line, 'existing', $label, $entityText, $reason);
                continue;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $summary['errors']++;
                $rows[] = dashglpi_admin_category_preview_row($line, 'error', $label, $entityText, 'E-mail invalido: "' . $email . '".');
                continue;
            }

            // Perfil efetivo: coluna "Perfil" da linha → perfil default do seletor → erro.
            $profileKey = dashglpi_admin_key($profileText);
            $profileInfo = null;
            if ($profileKey !== '' && isset($profileMap[$profileKey])) {
                $profileInfo = $profileMap[$profileKey];
            } elseif ($profileKey === '' && $defaultProfile !== null) {
                $profileInfo = $defaultProfile;
                $profileKey = dashglpi_admin_key($defaultProfile['name']);
            }
            if ($profileInfo === null) {
                $summary['errors']++;
                $reason = $profileText === ''
                    ? 'Informe o perfil do usuario (coluna "Perfil" ou o perfil padrao do formulario de import).'
                    : 'Perfil nao encontrado: "' . $profileText . '".';
                $rows[] = dashglpi_admin_category_preview_row($line, 'error', $label, $entityText, $reason);
                continue;
            }

            $entityInfo = dashglpi_admin_resolve_csv_entity($entityText, $entityMap);

            $groupInfo = ['id' => 0, 'name' => ''];
            $groupWarning = '';
            if ($groupText !== '') {
                $groupKey = dashglpi_admin_key($groupText);
                if (isset($groupMap[$groupKey])) {
                    $groupInfo = $groupMap[$groupKey];
                } else {
                    $groupWarning = 'Grupo "' . $groupText . '" nao encontrado; usuario sera importado sem grupo.';
                }
            }

            $warnings = [];
            if (!empty($entityInfo['fallback'])) {
                $warnings[] = 'Entidade nao encontrada, usando raiz.';
            }
            if (isset($privilegedProfiles[$profileKey])) {
                $warnings[] = 'Atencao: perfil privilegiado (' . $profileInfo['name'] . ').';
            }
            if ($groupWarning !== '') {
                $warnings[] = $groupWarning;
            }

            $summary['ready']++;
            $summary['created']++;
            if ($warnings) {
                $summary['fallback_root']++;
            }
            $destination = $entityInfo['name'] . ' · ' . $profileInfo['name'];
            $rows[] = dashglpi_admin_category_preview_row(
                $line,
                $warnings ? 'warning' : 'ready',
                $label,
                $entityText,
                $warnings ? implode(' ', $warnings) : 'Pronto para importar.',
                $destination
            );
            $items[] = [
                'login' => $login,
                'full_name' => $label,
                'firstname' => $firstname,
                'realname' => $realname,
                'email' => $email,
                'phone' => dashglpi_admin_import_first_value(dashglpi_admin_import_cell($csvRow, $phoneIndex)),
                // Senha bruta do CSV (sem normalizar espaços internos); vazia → o
                // bridge gera senha aleatória forte, nunca exposta em prévia/logs.
                'password' => $passwordIndex !== null ? trim((string) ($csvRow[$passwordIndex] ?? '')) : '',
                'is_active' => dashglpi_admin_import_bool(dashglpi_admin_import_cell($csvRow, $activeIndex), true),
                'entities_id' => (int) $entityInfo['id'],
                'profiles_id' => (int) $profileInfo['id'],
                'groups_id' => (int) $groupInfo['id'],
                'source_line' => $line,
            ];
        }
    } finally {
        fclose($csv['handle']);
    }

    return dashglpi_admin_import_finish($summary, $items, $rows, $csv['filename']);
}

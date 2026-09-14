<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('user', function (array $payload): array {
    if (isset($payload['items']) && is_array($payload['items'])) {
        require_once __DIR__ . '/admin_batch_lib.php';

        $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_user_batch_save', 'usuario');

        return [
            'summary' => $result['summary'],
            'items' => $result['items'],
            'message' => 'Usuarios processados no GLPI.',
        ];
    }

    $action = (string) ($payload['action'] ?? 'save');

    $result = match ($action) {
        'clone' => plugin_dashglpi_user_clone($payload),
        default => plugin_dashglpi_user_save($payload),
    };

    return [
        'user' => $result,
        'message' => plugin_dashglpi_user_status_message($result['status'] ?? ''),
    ];
}, 'Erro interno no bridge de usuario.');

function plugin_dashglpi_user_status_message(string $status): string
{
    return match ($status) {
        'updated' => 'Usuario atualizado no GLPI.',
        'cloned' => 'Usuario clonado no GLPI.',
        default => 'Usuario criado no GLPI.',
    };
}

// Import CSV: idempotente por login (find inclui usuários na lixeira, pois o
// login segue reservado). Sem senha no CSV → senha aleatória forte, jamais
// retornada/logada (decisões aprovadas no PLAN-20260708-017).
function plugin_dashglpi_user_batch_save(array $item): array
{
    $login = plugin_dashglpi_admin_bridge_name((string) ($item['login'] ?? ''), 'Informe o login do usuario.');

    $existing = plugin_dashglpi_admin_bridge_find_one(User::class, ['name' => $login]);
    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'login' => $login,
            'full_name' => (string) ($item['full_name'] ?? $login),
            'source_line' => (int) ($item['source_line'] ?? 0),
            'status' => 'existing',
        ];
    }

    if (trim((string) ($item['password'] ?? '')) === '') {
        $item['password'] = plugin_dashglpi_batch_random_password();
    }
    $item['id'] = 0;

    $row = plugin_dashglpi_user_save($item);
    $row['full_name'] = (string) ($item['full_name'] ?? $login);
    $row['source_line'] = (int) ($item['source_line'] ?? 0);

    return $row;
}

function plugin_dashglpi_user_clone(array $payload): array
{
    $sourceId = max(0, (int) ($payload['id'] ?? 0));
    $login = plugin_dashglpi_admin_bridge_name((string) ($payload['login'] ?? ''), 'Informe o login do novo usuario.');

    $user = new User();
    if ($sourceId <= 0 || !$user->getFromDB($sourceId)) {
        throw new RuntimeException('Usuario de origem nao encontrado.');
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(User::class, ['name' => $login]);
    if ($existing) {
        throw new RuntimeException('Ja existe um usuario com este login.');
    }

    $newId = $user->clone([
        'name' => $login,
        'is_active' => 0,
    ]);

    if ($newId === false || (int) $newId <= 0) {
        throw new RuntimeException('Falha ao clonar usuario.');
    }

    return [
        'id' => (int) $newId,
        'source_id' => $sourceId,
        'login' => $login,
        'status' => 'cloned',
        'is_active' => 0,
    ];
}

function plugin_dashglpi_user_save(array $payload): array
{
    return (static function () use ($payload): array {
        $userId = max(0, (int) ($payload['id'] ?? 0));
        $login = plugin_dashglpi_admin_bridge_name((string) ($payload['login'] ?? ''), 'Informe o login do usuario.');
        $firstname = trim((string) ($payload['firstname'] ?? ''));
        $realname = trim((string) ($payload['realname'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $isActive = !empty($payload['is_active']) ? 1 : 0;
        $entitiesId = max(0, (int) ($payload['entities_id'] ?? 0));
        $profilesId = max(0, (int) ($payload['profiles_id'] ?? 0));
        $groupsId = max(0, (int) ($payload['groups_id'] ?? 0));

        if ($userId <= 0 && trim($password) === '') {
            throw new RuntimeException('Informe a senha temporaria.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Informe um e-mail valido.');
        }

        plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');
        plugin_dashglpi_admin_bridge_require_item(Profile::class, $profilesId, 'Perfil nao encontrado.');
        if ($groupsId > 0) {
            plugin_dashglpi_admin_bridge_require_item(Group::class, $groupsId, 'Grupo nao encontrado.');
        }

        $existing = plugin_dashglpi_admin_bridge_find_one(User::class, ['name' => $login]);
        if ($existing && (int) ($existing['id'] ?? 0) !== $userId) {
            throw new RuntimeException('Ja existe um usuario com este login.');
        }

        $user = new User();
        if ($userId > 0) {
            if (!$user->getFromDB($userId)) {
                throw new RuntimeException('Usuario nao encontrado.');
            }

            $input = [
                'id' => $userId,
                'name' => $login,
                'firstname' => substr($firstname, 0, 255),
                'realname' => substr($realname, 0, 255),
                'is_active' => $isActive,
            ];
            if (trim($password) !== '') {
                $input['password'] = $password;
                $input['password2'] = $password;
            }

            if (!$user->update($input)) {
                throw new RuntimeException('Falha ao atualizar usuario.');
            }
        } else {
            $input = [
                'name' => $login,
                'firstname' => substr($firstname, 0, 255),
                'realname' => substr($realname, 0, 255),
                'password' => $password,
                'password2' => $password,
                'is_active' => $isActive,
                'comment' => 'Criado pelo cadastro de Usuario do DashGLPI.',
            ];
            // Telefone só vem do import CSV (PLAN-20260709-018); o formulário
            // unitário não envia o campo e o update não o toca.
            if ($phone !== '') {
                $input['phone'] = substr($phone, 0, 255);
            }
            $userId = (int) $user->add($input);

            if ($userId <= 0) {
                throw new RuntimeException('Falha ao criar usuario.');
            }
        }

        plugin_dashglpi_admin_bridge_sync_profile($userId, $profilesId, $entitiesId);
        plugin_dashglpi_admin_bridge_sync_email($userId, $email);
        plugin_dashglpi_admin_bridge_sync_group_user($userId, $groupsId);

        return [
            'id' => $userId,
            'login' => $login,
            'entities_id' => $entitiesId,
            'profiles_id' => $profilesId,
            'groups_id' => $groupsId,
            'status' => $userId > 0 && !empty($payload['id']) ? 'updated' : 'created',
        ];
    })();
}

function plugin_dashglpi_admin_bridge_find_first(string $class, array $criteria, string $order): ?array
{
    $item = new $class();
    $rows = $item->find($criteria, $order, 1);
    if (!$rows) {
        return null;
    }

    $row = reset($rows);
    return is_array($row) ? $row : null;
}

function plugin_dashglpi_admin_bridge_sync_profile(int $userId, int $profilesId, int $entitiesId): void
{
    $profileUser = new Profile_User();
    $existing = plugin_dashglpi_admin_bridge_find_first(
        Profile_User::class,
        ['users_id' => $userId],
        'is_default_profile DESC, is_dynamic ASC, id ASC'
    );

    if ($existing) {
        $ok = $profileUser->update([
            'id' => (int) $existing['id'],
            'users_id' => $userId,
            'profiles_id' => $profilesId,
            'entities_id' => $entitiesId,
            'is_recursive' => (int) ($existing['is_recursive'] ?? 0),
            'is_dynamic' => (int) ($existing['is_dynamic'] ?? 0),
            'is_default_profile' => 1,
        ]);
        if (!$ok) {
            throw new RuntimeException('Falha ao atualizar perfil do usuario.');
        }
        return;
    }

    $id = (int) $profileUser->add([
        'users_id' => $userId,
        'profiles_id' => $profilesId,
        'entities_id' => $entitiesId,
        'is_recursive' => 0,
        'is_dynamic' => 0,
        'is_default_profile' => 1,
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao vincular perfil ao usuario.');
    }
}

function plugin_dashglpi_admin_bridge_sync_email(int $userId, string $email): void
{
    if (!class_exists('UserEmail')) {
        return;
    }

    $userEmail = new UserEmail();
    $existing = plugin_dashglpi_admin_bridge_find_first(
        UserEmail::class,
        ['users_id' => $userId],
        'is_default DESC, is_dynamic ASC, id ASC'
    );

    if ($email === '') {
        if ($existing && !$userEmail->delete(['id' => (int) $existing['id']], true)) {
            throw new RuntimeException('Falha ao remover e-mail do usuario.');
        }
        return;
    }

    if ($existing) {
        $ok = $userEmail->update([
            'id' => (int) $existing['id'],
            'users_id' => $userId,
            'email' => $email,
            'is_default' => 1,
            'is_dynamic' => (int) ($existing['is_dynamic'] ?? 0),
        ]);
        if (!$ok) {
            throw new RuntimeException('Falha ao atualizar e-mail do usuario.');
        }
        return;
    }

    $id = (int) $userEmail->add([
        'users_id' => $userId,
        'email' => $email,
        'is_default' => 1,
        'is_dynamic' => 0,
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao adicionar e-mail ao usuario.');
    }
}

function plugin_dashglpi_admin_bridge_sync_group_user(int $userId, int $groupsId): void
{
    if (!class_exists('Group_User')) {
        return;
    }

    $groupUser = new Group_User();
    $existing = plugin_dashglpi_admin_bridge_find_first(
        Group_User::class,
        ['users_id' => $userId],
        'is_dynamic ASC, id ASC'
    );

    if ($groupsId <= 0) {
        if ($existing && !$groupUser->delete(['id' => (int) $existing['id']], true)) {
            throw new RuntimeException('Falha ao remover grupo do usuario.');
        }
        return;
    }

    if ($existing) {
        $ok = $groupUser->update([
            'id' => (int) $existing['id'],
            'users_id' => $userId,
            'groups_id' => $groupsId,
            'is_dynamic' => (int) ($existing['is_dynamic'] ?? 0),
            'is_manager' => (int) ($existing['is_manager'] ?? 0),
            'is_userdelegate' => (int) ($existing['is_userdelegate'] ?? 0),
        ]);
        if (!$ok) {
            throw new RuntimeException('Falha ao atualizar grupo do usuario.');
        }
        return;
    }

    $id = (int) $groupUser->add([
        'users_id' => $userId,
        'groups_id' => $groupsId,
        'is_dynamic' => 0,
        'is_manager' => 0,
        'is_userdelegate' => 0,
    ]);

    if ($id <= 0) {
        throw new RuntimeException('Falha ao vincular usuario ao grupo.');
    }
}

<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('profile', function (array $payload): array {
    if (isset($payload['items']) && is_array($payload['items'])) {
        require_once __DIR__ . '/admin_batch_lib.php';

        $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_profile_batch_save', 'perfil');

        return [
            'summary' => $result['summary'],
            'items' => $result['items'],
            'message' => 'Perfis processados no GLPI.',
        ];
    }

    $action = (string) ($payload['action'] ?? 'save');

    $result = match ($action) {
        'clone' => plugin_dashglpi_profile_clone($payload),
        'delete' => plugin_dashglpi_profile_delete($payload),
        default => plugin_dashglpi_profile_save($payload),
    };

    return [
        'profile' => $result,
        'message' => plugin_dashglpi_profile_status_message($result['status'] ?? ''),
    ];
}, 'Erro interno no bridge de perfil.');

function plugin_dashglpi_profile_status_message(string $status): string
{
    return match ($status) {
        'updated' => 'Perfil atualizado no GLPI.',
        'cloned' => 'Perfil clonado no GLPI.',
        'deleted' => 'Perfil excluido no GLPI.',
        default => 'Perfil criado no GLPI.',
    };
}

// Import CSV: idempotente por nome (PLAN-20260708-017).
function plugin_dashglpi_profile_batch_save(array $item): array
{
    $name = plugin_dashglpi_admin_bridge_name((string) ($item['name'] ?? ''), 'Informe o nome do perfil.');

    $existing = plugin_dashglpi_admin_bridge_find_one(Profile::class, ['name' => $name]);
    if ($existing) {
        return [
            'id' => (int) $existing['id'],
            'name' => $name,
            'full_name' => $name,
            'source_line' => (int) ($item['source_line'] ?? 0),
            'status' => 'existing',
        ];
    }

    $item['id'] = 0;
    $row = plugin_dashglpi_profile_save($item);
    $row['full_name'] = $name;
    $row['source_line'] = (int) ($item['source_line'] ?? 0);

    return $row;
}

function plugin_dashglpi_profile_save(array $payload): array
{
    $profileId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do perfil.');
    $interface = (string) ($payload['interface'] ?? 'helpdesk');
    $comment = trim((string) ($payload['comment'] ?? ''));

    if (!in_array($interface, ['central', 'helpdesk'], true)) {
        throw new RuntimeException('Interface do perfil invalida.');
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Profile::class, ['name' => $name]);
    if ($existing && (int) ($existing['id'] ?? 0) !== $profileId) {
        throw new RuntimeException('Ja existe um perfil com este nome.');
    }

    $profile = new Profile();
    if ($profileId > 0) {
        if (!$profile->getFromDB($profileId)) {
            throw new RuntimeException('Perfil nao encontrado.');
        }

        if (!$profile->update([
            'id' => $profileId,
            'name' => $name,
            'interface' => $interface,
            'comment' => $comment,
        ])) {
            throw new RuntimeException('Falha ao atualizar perfil.');
        }

        return [
            'id' => $profileId,
            'name' => $name,
            'interface' => $interface,
            'status' => 'updated',
        ];
    }

    $profileId = (int) $profile->add([
        'name' => $name,
        'interface' => $interface,
        'comment' => $comment !== '' ? $comment : 'Criado pelo cadastro de Perfil do DashGLPI.',
    ]);

    if ($profileId <= 0) {
        throw new RuntimeException('Falha ao criar perfil.');
    }

    return [
        'id' => $profileId,
        'name' => $name,
        'interface' => $interface,
        'status' => 'created',
    ];
}

function plugin_dashglpi_profile_clone(array $payload): array
{
    $sourceId = max(0, (int) ($payload['id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do novo perfil.');

    if ($sourceId <= 0) {
        throw new RuntimeException('Perfil de origem nao encontrado.');
    }

    $profile = new Profile();
    if (!$profile->getFromDB($sourceId)) {
        throw new RuntimeException('Perfil de origem nao encontrado.');
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Profile::class, ['name' => $name]);
    if ($existing) {
        throw new RuntimeException('Ja existe um perfil com este nome.');
    }

    $newId = $profile->clone(['name' => $name]);
    if ($newId === false || (int) $newId <= 0) {
        throw new RuntimeException('Falha ao clonar perfil.');
    }

    return [
        'id' => (int) $newId,
        'source_id' => $sourceId,
        'name' => $name,
        'status' => 'cloned',
    ];
}

function plugin_dashglpi_profile_delete(array $payload): array
{
    $profileId = max(0, (int) ($payload['id'] ?? 0));
    if ($profileId <= 0) {
        throw new RuntimeException('Perfil nao encontrado.');
    }

    $profile = new Profile();
    if (!$profile->getFromDB($profileId)) {
        throw new RuntimeException('Perfil nao encontrado.');
    }

    if ((int) ($profile->fields['is_default'] ?? 0) === 1) {
        throw new RuntimeException('Nao e possivel excluir: este perfil esta marcado como padrao no GLPI.');
    }

    global $DB;

    $usageCount = (int) ($DB->request(['COUNT' => 'total', 'FROM' => 'glpi_profiles_users', 'WHERE' => ['profiles_id' => $profileId]])->current()['total'] ?? 0);
    if ($usageCount > 0) {
        throw new RuntimeException('Nao e possivel excluir: este perfil esta em uso por um ou mais usuarios.');
    }

    if (!$profile->delete(['id' => $profileId], true)) {
        throw new RuntimeException('Falha ao excluir perfil.');
    }

    return [
        'id' => $profileId,
        'status' => 'deleted',
    ];
}

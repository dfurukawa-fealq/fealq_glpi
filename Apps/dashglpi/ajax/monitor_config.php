<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/admin_batch_lib.php';

plugin_dashglpi_admin_bridge_handle('monitor_import', function (array $payload): array {
    $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_monitor_import_save', 'monitor');

    return [
        'summary' => $result['summary'],
        'items' => $result['items'],
        'message' => 'Monitores processados no GLPI.',
    ];
}, 'Erro interno no bridge de importacao de monitores.');

function plugin_dashglpi_monitor_import_save(array $item): array
{
    $name = plugin_dashglpi_admin_bridge_name((string) ($item['name'] ?? ''), 'Informe o nome do monitor.');
    $inventory = trim((string) ($item['inventory'] ?? ''));
    $serial = trim((string) ($item['serial'] ?? ''));

    $existing = plugin_dashglpi_admin_bridge_find_one(Monitor::class, ['name' => $name, 'is_deleted' => 0]);
    if (!$existing && $inventory !== '') {
        $existing = plugin_dashglpi_admin_bridge_find_one(Monitor::class, ['otherserial' => $inventory, 'is_deleted' => 0]);
    }
    if (!$existing && $serial !== '') {
        $existing = plugin_dashglpi_admin_bridge_find_one(Monitor::class, ['serial' => $serial, 'is_deleted' => 0]);
    }

    $input = plugin_dashglpi_monitor_import_input($item, $name, $serial, $inventory);
    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);

    $monitor = new Monitor();
    if ($existing) {
        $id = (int) $existing['id'];
        if (!$monitor->update(['id' => $id] + $input)) {
            throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao atualizar monitor "' . $name . '".'));
        }

        return [
            'id' => $id,
            'name' => $name,
            'source_line' => (int) ($item['source_line'] ?? 0),
            'status' => 'updated',
        ];
    }

    $id = (int) $monitor->add($input);
    if ($id <= 0) {
        throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao criar monitor "' . $name . '".'));
    }

    return [
        'id' => $id,
        'name' => $name,
        'source_line' => (int) ($item['source_line'] ?? 0),
        'status' => 'created',
    ];
}

function plugin_dashglpi_monitor_import_input(array $item, string $name, string $serial, string $inventory): array
{
    $input = [
        'name' => $name,
        'entities_id' => 0,
        'serial' => $serial,
        'otherserial' => $inventory,
        'comment' => plugin_dashglpi_monitor_import_comment($item),
    ];

    $map = [
        'status' => ['field' => 'states_id', 'class' => State::class],
        'manufacturer' => ['field' => 'manufacturers_id', 'class' => Manufacturer::class],
        'location' => ['field' => 'locations_id', 'class' => Location::class],
        'type' => ['field' => 'monitortypes_id', 'class' => MonitorType::class],
        'model' => ['field' => 'monitormodels_id', 'class' => MonitorModel::class],
    ];

    foreach ($map as $source => $target) {
        $id = plugin_dashglpi_monitor_import_dropdown_id($target['class'], (string) ($item[$source] ?? ''));
        if ($id > 0) {
            $input[$target['field']] = $id;
        }
    }

    $userId = plugin_dashglpi_monitor_import_user_id((string) ($item['user'] ?? ''));
    if ($userId > 0) {
        $input['users_id'] = $userId;
    }

    $lastUpdate = plugin_dashglpi_monitor_import_date((string) ($item['last_update'] ?? ''), true);
    if ($lastUpdate !== '') {
        $input['date_mod'] = $lastUpdate;
    }

    return $input;
}

function plugin_dashglpi_monitor_import_dropdown_id(string $class, string $value): int
{
    $value = plugin_dashglpi_monitor_import_clean($value);
    if ($value === '' || !class_exists($class)) {
        return 0;
    }

    $fields = is_a($class, CommonTreeDropdown::class, true) ? ['completename', 'name'] : ['name'];
    foreach ($fields as $field) {
        $existing = plugin_dashglpi_admin_bridge_find_one($class, [$field => $value]);
        if ($existing) {
            return (int) $existing['id'];
        }
    }

    $object = new $class();
    $input = ['name' => $value];
    if (is_a($class, CommonTreeDropdown::class, true)) {
        $input += ['entities_id' => 0, 'is_recursive' => 1];
    }

    $id = (int) $object->add($input);
    if ($id <= 0) {
        throw new RuntimeException('Falha ao criar valor de cadastro "' . $value . '".');
    }

    return $id;
}

function plugin_dashglpi_monitor_import_user_id(string $value): int
{
    $value = plugin_dashglpi_monitor_import_clean($value);
    if ($value === '') {
        return 0;
    }

    foreach (['name', 'realname', 'firstname'] as $field) {
        $user = plugin_dashglpi_admin_bridge_find_one(User::class, [$field => $value, 'is_deleted' => 0]);
        if ($user) {
            return (int) $user['id'];
        }
    }

    return 0;
}

function plugin_dashglpi_monitor_import_comment(array $item): string
{
    $labels = [
        'source_id' => 'ID origem',
        'status' => 'Status',
        'manufacturer' => 'Fabricante',
        'location' => 'Localizacao',
        'type' => 'Tipo',
        'model' => 'Modelo',
        'last_update' => 'Ultima atualizacao',
        'user' => 'Usuario',
    ];

    $parts = [];
    foreach ($labels as $field => $label) {
        $value = plugin_dashglpi_monitor_import_clean((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    return $parts ? implode("\n", $parts) : 'Importado pelo CSV de Monitores do DashGLPI.';
}

function plugin_dashglpi_monitor_import_clean(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function plugin_dashglpi_monitor_import_date(string $value, bool $withTime): string
{
    $value = plugin_dashglpi_monitor_import_clean($value);
    if ($value === '') {
        return '';
    }

    $formats = $withTime
        ? ['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y']
        : ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return $withTime ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d', $timestamp);
}
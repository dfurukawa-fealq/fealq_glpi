<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/admin_batch_lib.php';

plugin_dashglpi_admin_bridge_handle('computer_import', function (array $payload): array {
    $result = plugin_dashglpi_batch_process($payload, 'plugin_dashglpi_computer_import_save', 'computador');

    return [
        'summary' => $result['summary'],
        'items' => $result['items'],
        'message' => 'Computadores processados no GLPI.',
    ];
}, 'Erro interno no bridge de importacao de computadores.');

function plugin_dashglpi_computer_import_save(array $item): array
{
    $name = plugin_dashglpi_admin_bridge_name((string) ($item['name'] ?? ''), 'Informe o nome do computador.');
    $serial = trim((string) ($item['serial'] ?? ''));
    $inventory = trim((string) ($item['inventory'] ?? ''));

    $existing = plugin_dashglpi_admin_bridge_find_one(Computer::class, ['name' => $name, 'is_deleted' => 0]);
    if (!$existing && $serial !== '') {
        $existing = plugin_dashglpi_admin_bridge_find_one(Computer::class, ['serial' => $serial, 'is_deleted' => 0]);
    }
    if (!$existing && $inventory !== '') {
        $existing = plugin_dashglpi_admin_bridge_find_one(Computer::class, ['otherserial' => $inventory, 'is_deleted' => 0]);
    }

    $input = plugin_dashglpi_computer_import_input($item, $name, $serial, $inventory);
    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);

    $computer = new Computer();
    if ($existing) {
        $id = (int) $existing['id'];
        if (!$computer->update(['id' => $id] + $input)) {
            throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao atualizar computador "' . $name . '".'));
        }
        plugin_dashglpi_computer_import_after_save($id, $item);

        return [
            'id' => $id,
            'name' => $name,
            'source_line' => (int) ($item['source_line'] ?? 0),
            'status' => 'updated',
        ];
    }

    $id = (int) $computer->add($input);
    if ($id <= 0) {
        throw new RuntimeException(plugin_dashglpi_admin_bridge_last_message('Falha ao criar computador "' . $name . '".'));
    }
    plugin_dashglpi_computer_import_after_save($id, $item);

    return [
        'id' => $id,
        'name' => $name,
        'source_line' => (int) ($item['source_line'] ?? 0),
        'status' => 'created',
    ];
}

function plugin_dashglpi_computer_import_input(array $item, string $name, string $serial, string $inventory): array
{
    $input = [
        'name' => $name,
        'entities_id' => 0,
        'serial' => $serial,
        'otherserial' => $inventory,
        'comment' => plugin_dashglpi_computer_import_comment($item),
    ];

    $map = [
        'status' => ['field' => 'states_id', 'class' => State::class],
        'manufacturer' => ['field' => 'manufacturers_id', 'class' => Manufacturer::class],
        'type' => ['field' => 'computertypes_id', 'class' => ComputerType::class],
        'model' => ['field' => 'computermodels_id', 'class' => ComputerModel::class],
        'location' => ['field' => 'locations_id', 'class' => Location::class],
    ];

    foreach ($map as $source => $target) {
        $id = plugin_dashglpi_computer_import_dropdown_id($target['class'], (string) ($item[$source] ?? ''));
        if ($id > 0) {
            $input[$target['field']] = $id;
        }
    }

    $userId = plugin_dashglpi_computer_import_user_id((string) ($item['user'] ?? ''));
    if ($userId > 0) {
        $input['users_id'] = $userId;
    }

    $lastUpdate = plugin_dashglpi_computer_import_date((string) ($item['last_update'] ?? ''), true);
    if ($lastUpdate !== '') {
        $input['last_inventory_update'] = $lastUpdate;
    }

    return $input;
}

function plugin_dashglpi_computer_import_after_save(int $computerId, array $item): void
{
    plugin_dashglpi_computer_import_operating_system($computerId, (string) ($item['os'] ?? ''));
    plugin_dashglpi_computer_import_processor($computerId, (string) ($item['processor'] ?? ''));
    plugin_dashglpi_computer_import_warranty($computerId, (string) ($item['warranty_date'] ?? ''));
}

function plugin_dashglpi_computer_import_dropdown_id(string $class, string $value): int
{
    $value = plugin_dashglpi_computer_import_clean($value);
    if ($value === '' || !class_exists($class)) {
        return 0;
    }

    $isTreeDropdown = is_a($class, CommonTreeDropdown::class, true);
    if ($class === DeviceProcessor::class) {
        $fields = ['designation'];
    } else {
        $fields = $isTreeDropdown ? ['completename', 'name'] : ['name'];
    }
    foreach ($fields as $field) {
        $existing = plugin_dashglpi_admin_bridge_find_one($class, [$field => $value]);
        if ($existing) {
            return (int) $existing['id'];
        }
    }

    $object = new $class();
    $input = ($class === DeviceProcessor::class) ? ['designation' => $value] : ['name' => $value];
    if ($isTreeDropdown) {
        $input += ['entities_id' => 0, 'is_recursive' => 1];
    }

    $id = (int) $object->add($input);
    if ($id <= 0) {
        throw new RuntimeException('Falha ao criar valor de cadastro "' . $value . '".');
    }

    return $id;
}

function plugin_dashglpi_computer_import_user_id(string $value): int
{
    $value = plugin_dashglpi_computer_import_clean($value);
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

function plugin_dashglpi_computer_import_operating_system(int $computerId, string $value): void
{
    $value = plugin_dashglpi_computer_import_clean($value);
    if ($value === '' || !class_exists(OperatingSystem::class) || !class_exists(Item_OperatingSystem::class)) {
        return;
    }

    $osId = plugin_dashglpi_computer_import_dropdown_id(OperatingSystem::class, $value);
    if ($osId <= 0) {
        return;
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Item_OperatingSystem::class, [
        'itemtype' => Computer::class,
        'items_id' => $computerId,
    ]);
    if ($existing) {
        $relation = new Item_OperatingSystem();
        $relation->update(['id' => (int) $existing['id'], 'operatingsystems_id' => $osId]);
        return;
    }

    $relation = new Item_OperatingSystem();
    $relation->add([
        'itemtype' => Computer::class,
        'items_id' => $computerId,
        'operatingsystems_id' => $osId,
        'entities_id' => 0,
        'is_dynamic' => 0,
    ]);
}

function plugin_dashglpi_computer_import_processor(int $computerId, string $value): void
{
    $value = plugin_dashglpi_computer_import_clean($value);
    if ($value === '' || !class_exists(DeviceProcessor::class) || !class_exists(Item_DeviceProcessor::class)) {
        return;
    }

    $processorId = plugin_dashglpi_computer_import_dropdown_id(DeviceProcessor::class, $value);
    if ($processorId <= 0) {
        return;
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Item_DeviceProcessor::class, [
        'itemtype' => Computer::class,
        'items_id' => $computerId,
        'deviceprocessors_id' => $processorId,
        'is_deleted' => 0,
    ]);
    if ($existing) {
        return;
    }

    $relation = new Item_DeviceProcessor();
    $relation->add([
        'itemtype' => Computer::class,
        'items_id' => $computerId,
        'deviceprocessors_id' => $processorId,
        'entities_id' => 0,
        'is_dynamic' => 0,
    ]);
}

function plugin_dashglpi_computer_import_warranty(int $computerId, string $value): void
{
    $date = plugin_dashglpi_computer_import_date($value, false);
    if ($date === '' || !class_exists(Infocom::class)) {
        return;
    }

    $existing = plugin_dashglpi_admin_bridge_find_one(Infocom::class, [
        'itemtype' => Computer::class,
        'items_id' => $computerId,
    ]);

    $input = [
        'itemtype' => Computer::class,
        'items_id' => $computerId,
        'entities_id' => 0,
        'warranty_date' => $date,
    ];

    $infocom = new Infocom();
    if ($existing) {
        $infocom->update(['id' => (int) $existing['id']] + $input);
        return;
    }

    $infocom->add($input);
}

function plugin_dashglpi_computer_import_comment(array $item): string
{
    $labels = [
        'status' => 'Status',
        'manufacturer' => 'Fabricante',
        'type' => 'Tipo',
        'model' => 'Modelo',
        'os' => 'Sistema operacional',
        'location' => 'Localizacao',
        'last_update' => 'Ult. atualizacao',
        'processor' => 'Processador',
        'user' => 'Usuario',
        'warranty_date' => 'Data garantia',
    ];

    $parts = [];
    foreach ($labels as $field => $label) {
        $value = plugin_dashglpi_computer_import_clean((string) ($item[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    return $parts ? implode("\n", $parts) : 'Importado pelo CSV de Computadores do DashGLPI.';
}

function plugin_dashglpi_computer_import_clean(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function plugin_dashglpi_computer_import_date(string $value, bool $withTime): string
{
    $value = plugin_dashglpi_computer_import_clean($value);
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
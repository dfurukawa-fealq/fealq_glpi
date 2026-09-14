<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('health_diagnostics', function (array $payload): array {
    $parseBytes = static function (string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    };

    $memoryLimit = (string) ini_get('memory_limit');
    $uploadMaxFilesize = (string) ini_get('upload_max_filesize');
    $postMaxSize = (string) ini_get('post_max_size');
    $maxExecutionTime = (string) ini_get('max_execution_time');

    return [
        'php_config' => [
            'memory_limit' => $memoryLimit,
            'memory_limit_bytes' => $parseBytes($memoryLimit),
            'upload_max_filesize' => $uploadMaxFilesize,
            'upload_max_filesize_bytes' => $parseBytes($uploadMaxFilesize),
            'post_max_size' => $postMaxSize,
            'post_max_size_bytes' => $parseBytes($postMaxSize),
            'max_execution_time' => (int) $maxExecutionTime,
        ],
    ];
}, 'Erro interno no bridge de diagnostico.');

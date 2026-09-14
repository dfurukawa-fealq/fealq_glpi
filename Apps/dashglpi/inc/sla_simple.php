<?php

const DASHGLPI_SLA_KEYS = [
    'TTO-P1' => ['kind' => 'TTO', 'priority' => 'P1', 'type' => 1, 'label' => 'TTO-P1'],
    'TTO-P2' => ['kind' => 'TTO', 'priority' => 'P2', 'type' => 1, 'label' => 'TTO-P2'],
    'TTO-P3' => ['kind' => 'TTO', 'priority' => 'P3', 'type' => 1, 'label' => 'TTO-P3'],
    'TTO-P4' => ['kind' => 'TTO', 'priority' => 'P4', 'type' => 1, 'label' => 'TTO-P4'],
    'TTR-P1' => ['kind' => 'TTR', 'priority' => 'P1', 'type' => 0, 'label' => 'TTR-P1'],
    'TTR-P2' => ['kind' => 'TTR', 'priority' => 'P2', 'type' => 0, 'label' => 'TTR-P2'],
    'TTR-P3' => ['kind' => 'TTR', 'priority' => 'P3', 'type' => 0, 'label' => 'TTR-P3'],
    'TTR-P4' => ['kind' => 'TTR', 'priority' => 'P4', 'type' => 0, 'label' => 'TTR-P4'],
];

function dashglpi_sla_key_meta(string $key): ?array
{
    $parts = dashglpi_sla_settings_key_parts($key);
    if (!$parts) {
        return null;
    }

    return [
        'kind' => $parts['kind'],
        'priority' => 'P' . $parts['number'],
        'type' => $parts['kind'] === 'TTO' ? 1 : 0,
        'label' => $parts['kind'] . '-P' . $parts['number'],
        'number' => $parts['number'],
    ];
}

function dashglpi_sla_keys_from_settings(array $settings, ?string $kind = null): array
{
    $keys = array_keys(is_array($settings['slas'] ?? null) ? $settings['slas'] : []);
    $keys = array_values(array_filter($keys, static function (string $key) use ($kind): bool {
        $meta = dashglpi_sla_key_meta($key);
        return $meta !== null && ($kind === null || $meta['kind'] === $kind);
    }));
    usort($keys, 'dashglpi_sla_settings_key_compare');
    return $keys;
}

function dashglpi_sla_compact_duration_label(array $sla): string
{
    $number = max(1, (int) ($sla['number_time'] ?? 1));
    $unit = (string) ($sla['definition_time'] ?? 'minute');

    return match ($unit) {
        'minute' => $number . ' min',
        'hour' => $number . ' h',
        'day' => $number . ' ' . ($number === 1 ? 'dia' : 'dias'),
        'month' => $number . ' ' . ($number === 1 ? 'mês' : 'meses'),
        default => $number . ' min',
    };
}

function dashglpi_sla_display_label(array $settings, string $key): string
{
    $normalized = strtoupper(trim($key));
    $meta = dashglpi_sla_key_meta($normalized);
    if (!$meta) {
        return $normalized;
    }

    $sla = is_array($settings['slas'][$normalized] ?? null) ? $settings['slas'][$normalized] : [];
    if (!$sla) {
        return $meta['label'];
    }

    return $meta['label'] . ' - {' . dashglpi_sla_compact_duration_label($sla) . '}';
}

function dashglpi_sla_display_labels(array $settings, ?string $kind = null): array
{
    $labels = [];
    foreach (dashglpi_sla_keys_from_settings($settings, $kind) as $key) {
        $labels[$key] = dashglpi_sla_display_label($settings, $key);
    }

    return $labels;
}

function dashglpi_sla_entities(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, completename, entities_id
         FROM glpi_entities
         ORDER BY completename ASC, name ASC"
    );
}

function dashglpi_sla_calendars(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, entities_id, is_recursive
         FROM glpi_calendars
         ORDER BY name ASC"
    );
}

function dashglpi_sla_build_payload_from_post(array $post): array
{
    $settings = dashglpi_validate_sla_simple_settings([
        'entities_id' => (int) ($post['entities_id'] ?? 0),
        'entity_new_name' => (string) ($post['entity_new_name'] ?? ''),
        'entity_parent_id' => (int) ($post['entity_parent_id'] ?? 0),
        'calendar_id' => (int) ($post['calendar_id'] ?? 0),
        'calendar_new_name' => (string) ($post['calendar_new_name'] ?? ''),
        'calendar_is_recursive' => !empty($post['calendar_is_recursive']) ? 1 : 0,
        'slm_name' => (string) ($post['slm_name'] ?? ''),
        'slas' => dashglpi_sla_post_slas($post),
        'calendar_segments' => dashglpi_sla_post_segments($post),
        'ids' => dashglpi_get_settings('sla_simple')['ids'] ?? [],
    ]);

    $entityMode = dashglpi_sla_mode((string) ($post['entity_mode'] ?? 'existing'));
    $calendarMode = dashglpi_sla_mode((string) ($post['calendar_mode'] ?? 'existing'));
    $isCreatingNewScope = $entityMode === 'new' || $calendarMode === 'new';
    $entityName = $entityMode === 'edit'
        ? trim((string) ($post['entity_edit_name'] ?? $post['entity_new_name'] ?? ''))
        : (string) $settings['entity_new_name'];
    $entityParentId = $entityMode === 'edit'
        ? (int) ($post['entity_edit_parent_id'] ?? $post['entity_parent_id'] ?? 0)
        : (int) $settings['entity_parent_id'];
    $calendarName = $calendarMode === 'edit'
        ? trim((string) ($post['calendar_edit_name'] ?? $post['calendar_new_name'] ?? ''))
        : (string) $settings['calendar_new_name'];
    $calendarRecursive = $calendarMode === 'edit'
        ? (!empty($post['calendar_edit_is_recursive']) || !empty($post['calendar_is_recursive']) ? 1 : 0)
        : (int) $settings['calendar_is_recursive'];

    $slas = [];
    foreach (dashglpi_sla_keys_from_settings($settings) as $key) {
        $meta = dashglpi_sla_key_meta($key);
        if (!$meta) {
            continue;
        }
        $sla = $settings['slas'][$key];
        $slas[] = [
            'key' => $key,
            'name' => $meta['label'],
            'kind' => $meta['kind'],
            'priority' => $meta['priority'],
            'type' => $meta['type'],
            'number_time' => $sla['number_time'],
            'definition_time' => $sla['definition_time'],
            'id' => $isCreatingNewScope ? 0 : (int) ($settings['ids']['slas'][$key] ?? 0),
        ];
    }

    $segments = [];
    foreach ($settings['calendar_segments'] as $day => $segment) {
        if (!$segment['enabled']) {
            continue;
        }

        $segments[] = [
            'day' => (int) $day,
            'begin' => $segment['begin'] . ':00',
            'end' => $segment['end'] . ':00',
        ];
    }

    return [
        'entity' => [
            'mode' => $entityMode,
            'id' => $entityMode === 'new' ? 0 : (int) $settings['entities_id'],
            'name' => $entityName,
            'parent_id' => $entityParentId,
            'saved_id' => $entityMode === 'edit' ? (int) $settings['entities_id'] : 0,
        ],
        'calendar' => [
            'mode' => $calendarMode,
            'id' => $calendarMode === 'new' ? 0 : (int) $settings['calendar_id'],
            'name' => $calendarName,
            'is_recursive' => $calendarRecursive,
            'saved_id' => $calendarMode === 'edit' ? (int) $settings['calendar_id'] : 0,
            'segments' => $segments,
        ],
        'slm' => [
            'name' => $settings['slm_name'],
            'saved_id' => $isCreatingNewScope ? 0 : (int) ($settings['ids']['slms_id'] ?? 0),
        ],
        'slas' => $slas,
    ];
}

function dashglpi_sla_build_payload_from_settings(array $settings): array
{
    $entitiesId = array_key_exists('entities_id', $settings)
        ? (int) $settings['entities_id']
        : (int) ($settings['ids']['entities_id'] ?? 0);
    $calendarId = (int) ($settings['calendar_id'] ?? 0);
    if ($calendarId <= 0) {
        $calendarId = (int) ($settings['ids']['calendars_id'] ?? 0);
    }

    $post = [
        'entity_mode' => 'existing',
        'entities_id' => $entitiesId,
        'calendar_mode' => 'existing',
        'calendar_id' => $calendarId,
        'slm_name' => (string) ($settings['slm_name'] ?? ''),
        'sla_number' => [],
        'sla_unit' => [],
        'calendar_day_enabled' => [],
        'calendar_day_begin' => [],
        'calendar_day_end' => [],
    ];

    foreach (dashglpi_sla_keys_from_settings($settings) as $key) {
        $sla = is_array($settings['slas'][$key] ?? null) ? $settings['slas'][$key] : [];
        $post['sla_number'][$key] = (int) ($sla['number_time'] ?? 1);
        $post['sla_unit'][$key] = (string) ($sla['definition_time'] ?? 'minute');
    }

    $segments = is_array($settings['calendar_segments'] ?? null) ? $settings['calendar_segments'] : [];
    foreach ($segments as $day => $segment) {
        $post['calendar_day_begin'][$day] = (string) ($segment['begin'] ?? '08:00');
        $post['calendar_day_end'][$day] = (string) ($segment['end'] ?? '18:00');
        if (!empty($segment['enabled'])) {
            $post['calendar_day_enabled'][$day] = '1';
        }
    }

    return dashglpi_sla_build_payload_from_post($post);
}

function dashglpi_sla_mode(string $mode): string
{
    return in_array($mode, ['existing', 'new', 'edit'], true) ? $mode : 'existing';
}

function dashglpi_sla_settings_from_post_and_result(array $post, array $result): array
{
    $current = dashglpi_get_settings('sla_simple');
    $payload = dashglpi_sla_build_payload_from_post($post);

    $slas = [];
    foreach ($payload['slas'] as $sla) {
        $slas[$sla['key']] = [
            'number_time' => $sla['number_time'],
            'definition_time' => $sla['definition_time'],
        ];
    }

    $ids = $current['ids'] ?? [];
    $ids['entities_id'] = (int) ($result['entity']['id'] ?? $payload['entity']['id'] ?? 0);
    $ids['calendars_id'] = (int) ($result['calendar']['id'] ?? $payload['calendar']['id'] ?? 0);
    $ids['slms_id'] = (int) ($result['slm']['id'] ?? 0);
    $ids['slas'] = [];

    foreach (($result['slas'] ?? []) as $slaResult) {
        $key = (string) ($slaResult['key'] ?? '');
        if ($key !== '') {
            $ids['slas'][$key] = (int) ($slaResult['id'] ?? 0);
        }
    }
    $ids['rules'] = [];
    foreach (($result['rules'] ?? []) as $ruleResult) {
        $name = trim((string) ($ruleResult['name'] ?? ''));
        if ($name !== '') {
            $ids['rules'][$name] = (int) ($ruleResult['id'] ?? 0);
        }
    }
    $ids['reminders'] = is_array($current['ids']['reminders'] ?? null) ? $current['ids']['reminders'] : [];
    $ids['sla_notification'] = is_array($current['ids']['sla_notification'] ?? null)
        ? $current['ids']['sla_notification']
        : ['template_id' => 0, 'translation_id' => 0, 'notification_id' => 0];

    $resolvedEntityId = (int) ($result['entity']['id'] ?? $payload['entity']['id'] ?? 0);
    $resolvedCalendarId = (int) ($result['calendar']['id'] ?? $payload['calendar']['id'] ?? 0);

    return [
        'entities_id' => $resolvedEntityId,
        'entity_new_name' => (string) ($payload['entity']['name'] ?? ''),
        'entity_parent_id' => (int) ($payload['entity']['parent_id'] ?? 0),
        'calendar_id' => $resolvedCalendarId,
        'calendar_new_name' => (string) ($payload['calendar']['name'] ?? ''),
        'calendar_is_recursive' => (int) ($payload['calendar']['is_recursive'] ?? 0),
        'slm_name' => (string) ($payload['slm']['name'] ?? ''),
        'slas' => $slas,
        'calendar_segments' => dashglpi_sla_post_segments($post),
        'ids' => $ids,
        'reminders' => $current['reminders'] ?? [],
        'sla_notification' => $current['sla_notification'] ?? [],
    ];
}

function dashglpi_sla_post_slas(array $post): array
{
    $slas = [];
    $numbers = is_array($post['sla_number'] ?? null) ? $post['sla_number'] : [];
    $units = is_array($post['sla_unit'] ?? null) ? $post['sla_unit'] : [];
    foreach ($numbers as $rawKey => $numberTime) {
        $key = strtoupper(trim((string) $rawKey));
        if (!dashglpi_sla_settings_key_is_valid($key)) {
            continue;
        }
        $slas[$key] = [
            'number_time' => (int) $numberTime,
            'definition_time' => (string) ($units[$rawKey] ?? $units[$key] ?? ''),
        ];
    }
    uksort($slas, 'dashglpi_sla_settings_key_compare');

    return $slas;
}

function dashglpi_sla_post_segments(array $post): array
{
    $segments = [];
    $enabled = is_array($post['calendar_day_enabled'] ?? null) ? $post['calendar_day_enabled'] : [];
    $begin = is_array($post['calendar_day_begin'] ?? null) ? $post['calendar_day_begin'] : [];
    $end = is_array($post['calendar_day_end'] ?? null) ? $post['calendar_day_end'] : [];

    foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
        $segments[$day] = [
            'enabled' => isset($enabled[$day]),
            'begin' => (string) ($begin[$day] ?? '08:00'),
            'end' => (string) ($end[$day] ?? '18:00'),
        ];
    }

    return $segments;
}

function dashglpi_sla_bridge_request(string $action, array $payload): array
{
    $url = dashglpi_env('DASHGLPI_BRIDGE_URL', 'http://glpi/plugins/dashglpi/ajax/sla_config.php');
    $token = dashglpi_env('DASHGLPI_BRIDGE_TOKEN', '');

    if (!$url || !$token) {
        throw new RuntimeException('Bridge GLPI nao configurado. Defina DASHGLPI_BRIDGE_URL e DASHGLPI_BRIDGE_TOKEN.');
    }

    $body = json_encode([
        'action' => $action,
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        throw new RuntimeException('Falha ao serializar payload de SLA.');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-DashGLPI-Bridge-Token: ' . $token,
            ],
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

    $status = dashglpi_sla_http_status($http_response_header ?? []);
    $data = is_string($response) ? json_decode($response, true) : null;

    if ($response === false) {
        dashglpi_sla_log_bridge_failure($action, $url, $status, null, $transportError);
        throw new RuntimeException('Falha ao chamar bridge GLPI' . ($transportError ? ': ' . $transportError : '.'));
    }

    if (!is_array($data)) {
        dashglpi_sla_log_bridge_failure($action, $url, $status, $response, $transportError);
        throw new RuntimeException('Resposta invalida do bridge GLPI. HTTP ' . ($status ?: 'sem status') . '.');
    }

    if ($status >= 400 || empty($data['ok'])) {
        $error = dashglpi_sla_bridge_error_message($data);
        $error = dashglpi_sla_bridge_user_error($status, $error, $url);
        dashglpi_sla_log_bridge_failure($action, $url, $status, $response, $transportError, $error);
        throw new RuntimeException($error !== '' ? $error : 'Bridge GLPI retornou erro HTTP ' . $status . '.');
    }

    return $data;
}

function dashglpi_sla_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $header, $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function dashglpi_sla_log_bridge_failure(
    string $action,
    string $url,
    int $status,
    ?string $response,
    ?string $transportError = null,
    ?string $bridgeError = null
): void {
    $parts = [
        'action=' . $action,
        'url=' . $url,
        'status=' . ($status ?: 'none'),
    ];

    if ($bridgeError !== null && $bridgeError !== '') {
        $parts[] = 'bridge_error=' . $bridgeError;
    }

    if ($transportError !== null && $transportError !== '') {
        $parts[] = 'transport_error=' . $transportError;
    }

    if ($response !== null) {
        $parts[] = 'response_sample=' . dashglpi_sla_response_sample($response);
    }

    error_log('[DashGLPI] SLA bridge failure: ' . implode(' | ', $parts));
}

function dashglpi_sla_bridge_error_message(array $data): string
{
    $parts = [];

    foreach (['error', 'title', 'message'] as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }

        $value = $data[$key];
        if (is_bool($value)) {
            continue;
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
    }

    return implode(': ', array_values(array_unique($parts)));
}

function dashglpi_sla_bridge_user_error(int $status, string $error, string $url): string
{
    if ($status === 404 && stripos($url, '/plugins/dashglpi/ajax/sla_config.php') !== false) {
        return 'Plugin "Dashboard GLPI Pro" inativo ou nao instalado no GLPI. Reinstale/ative o plugin no GLPI e confirme que o servico GLPI foi rebuildado/redeployado com o arquivo plugins/dashglpi/ajax/sla_config.php.';
    }

    return $error;
}

function dashglpi_sla_response_sample(string $response): string
{
    $sample = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
    if (strlen($sample) > 700) {
        $sample = substr($sample, 0, 700) . '...';
    }

    return $sample;
}

function dashglpi_sla_dataset(): array
{
    $settings = dashglpi_get_settings('sla_simple');
    $catalog = function_exists('dashglpi_notification_catalog_data')
        ? dashglpi_notification_catalog_data()
        : [];

    return [
        'settings' => $settings,
        'entities' => dashglpi_sla_entities(),
        'calendars' => dashglpi_sla_calendars(),
        'rules_status' => dashglpi_sla_rules_status($settings),
        'reminders' => dashglpi_sla_reminders_status($settings),
        'sla_notification' => dashglpi_sla_notification_state($settings, $catalog),
        'automation' => dashglpi_sla_automation_status($catalog),
        'notification_catalog_subset' => dashglpi_sla_notification_catalog_subset($catalog),
        'ticket_templates' => dashglpi_sla_ticket_templates(),
    ];
}

function dashglpi_sla_rules_status(array $settings): array
{
    $rules = [];
    $managedCount = 0;
    $baseReady = dashglpi_sla_base_ids_ready($settings);
    $ruleIds = is_array($settings['ids']['rules'] ?? null) ? $settings['ids']['rules'] : [];

    foreach (dashglpi_sla_rule_specs() as $spec) {
        $id = max(0, (int) ($ruleIds[$spec['name']] ?? 0));
        $status = $id > 0 ? 'managed' : ($baseReady ? 'pending' : 'blocked');
        if ($id > 0) {
            $managedCount++;
        }

        $rules[] = [
            'name' => $spec['name'],
            'bucket' => $spec['bucket'],
            'priority' => $spec['priority'],
            'id' => $id,
            'status' => $status,
            'status_label' => dashglpi_sla_managed_status_label($status),
            'tto_key' => 'TTO-' . $spec['bucket'],
            'ttr_key' => 'TTR-' . $spec['bucket'],
            'tto_id' => (int) ($settings['ids']['slas']['TTO-' . $spec['bucket']] ?? 0),
            'ttr_id' => (int) ($settings['ids']['slas']['TTR-' . $spec['bucket']] ?? 0),
        ];
    }

    return [
        'base_ready' => $baseReady,
        'managed_rule_count' => $managedCount,
        'entity_id' => (int) ($settings['ids']['entities_id'] ?? $settings['entities_id'] ?? 0),
        'calendar_id' => (int) ($settings['ids']['calendars_id'] ?? $settings['calendar_id'] ?? 0),
        'slm_id' => (int) ($settings['ids']['slms_id'] ?? 0),
        'items' => $rules,
    ];
}

function dashglpi_sla_reminders_status(array $settings): array
{
    $rows = dashglpi_sla_managed_reminder_rows($settings);
    $items = [];

    foreach ($settings['reminders'] ?? [] as $key => $config) {
        $sla = is_array($settings['slas'][$key] ?? null) ? $settings['slas'][$key] : [];
        $slaId = (int) ($settings['ids']['slas'][$key] ?? 0);
        $managedId = (int) ($settings['ids']['reminders'][$key] ?? 0);
        $row = $rows[$managedId] ?? null;
        $durationSeconds = dashglpi_sla_duration_seconds($sla);
        $offsetSeconds = dashglpi_sla_duration_seconds([
            'number_time' => (int) ($config['offset_value'] ?? 0),
            'definition_time' => (string) ($config['offset_unit'] ?? 'minute'),
        ]);
        $canApply = $slaId > 0 && $durationSeconds > 0 && $offsetSeconds > 0 && $offsetSeconds < $durationSeconds;
        $isActive = !empty($config['is_active']);

        if (!$isActive) {
            $status = ($managedId > 0 || $row) ? 'to_remove' : 'inactive';
        } elseif ($slaId <= 0 || !$canApply) {
            $status = 'blocked';
        } elseif ($row) {
            $matchesCurrent = (int) ($row['slas_id'] ?? 0) === $slaId
                && (int) ($row['execution_time'] ?? 0) === -$offsetSeconds
                && (int) ($row['is_active'] ?? 0) === 1;
            $status = $matchesCurrent ? 'managed' : 'drift';
        } elseif ($managedId > 0) {
            $status = 'missing';
        } else {
            $status = 'pending';
        }

        $items[] = [
            'key' => $key,
            'priority' => dashglpi_sla_key_meta($key)['priority'] ?? '',
            'sla_id' => $slaId,
            'managed_id' => $managedId,
            'is_active' => $isActive ? 1 : 0,
            'offset_value' => (int) ($config['offset_value'] ?? 0),
            'offset_unit' => (string) ($config['offset_unit'] ?? 'minute'),
            'offset_seconds' => $offsetSeconds,
            'offset_label' => dashglpi_sla_duration_label((int) ($config['offset_value'] ?? 0), (string) ($config['offset_unit'] ?? 'minute')),
            'sla_duration_seconds' => $durationSeconds,
            'can_apply' => $canApply,
            'status' => $status,
            'status_label' => dashglpi_sla_managed_status_label($status),
            'managed_status' => $row ? 'present' : ($managedId > 0 ? 'missing' : 'absent'),
            'managed_execution_time' => $row ? (int) ($row['execution_time'] ?? 0) : 0,
        ];
    }

    return [
        'base_ready' => dashglpi_sla_ttr_ids_ready($settings),
        'items' => $items,
    ];
}

function dashglpi_sla_notification_state(array $settings, array $catalog): array
{
    $notificationDefaults = is_array($settings['sla_notification'] ?? null) ? $settings['sla_notification'] : [];
    $managedIds = is_array($settings['ids']['sla_notification'] ?? null)
        ? $settings['ids']['sla_notification']
        : ['template_id' => 0, 'translation_id' => 0, 'notification_id' => 0];
    $subset = dashglpi_sla_notification_catalog_subset($catalog);
    $specialEvent = is_array($subset['special_event'] ?? null) ? $subset['special_event'] : [
        'available' => false,
        'event_key' => '',
        'event_label' => '',
    ];
    $templates = dashglpi_sla_ticket_templates();
    $notifications = function_exists('dashglpi_notifications_list')
        ? dashglpi_notifications_list($catalog)
        : [];

    $templateId = max(0, (int) ($managedIds['template_id'] ?? $notificationDefaults['template_id'] ?? 0));
    $notificationId = max(0, (int) ($managedIds['notification_id'] ?? 0));
    $templateRow = dashglpi_sla_find_ticket_template($templates, $templateId, (string) ($notificationDefaults['template_name'] ?? ''));
    $notificationRow = dashglpi_sla_find_ticket_notification(
        $notifications,
        $notificationId,
        (string) ($notificationDefaults['name'] ?? ''),
        (string) ($specialEvent['event_key'] ?? '')
    );

    $recipientKeys = dashglpi_sla_notification_recipient_keys($notificationDefaults, $notificationRow);
    $recipientLabels = [];
    foreach ($recipientKeys as $key) {
        $recipientLabels[] = function_exists('dashglpi_notification_target_label')
            ? dashglpi_notification_target_label($catalog, 'Ticket', (string) ($specialEvent['event_key'] ?? ''), $key)
            : $key;
    }

    return [
        'base_ready' => dashglpi_sla_base_ids_ready($settings),
        'blocked' => empty($specialEvent['available']),
        'blocked_message' => empty($specialEvent['available'])
            ? 'O evento especial de lembrete automático de SLA não está disponível no catálogo do GLPI.'
            : '',
        'event' => $specialEvent,
        'itemtype' => 'Ticket',
        'itemtype_label' => (string) ($subset['itemtype_label'] ?? 'Chamado'),
        'recipient_catalog' => is_array($subset['targets'] ?? null) ? $subset['targets'] : [],
        'recipient_keys' => $recipientKeys,
        'recipient_labels' => array_values(array_unique(array_filter(array_map('strval', $recipientLabels)))),
        'notification' => [
            'id' => (int) ($notificationRow['id'] ?? $notificationId),
            'name' => (string) ($notificationDefaults['name'] ?? $notificationRow['name'] ?? 'SLA'),
            'entities_id' => (int) ($notificationDefaults['entities_id'] ?? $notificationRow['entities_id'] ?? 0),
            'is_recursive' => (int) ($notificationDefaults['is_recursive'] ?? $notificationRow['is_recursive'] ?? 1),
            'is_active' => (int) ($notificationDefaults['is_active'] ?? $notificationRow['is_active'] ?? 1),
            'status' => $notificationRow ? 'managed' : ($notificationId > 0 ? 'missing' : 'pending'),
            'status_label' => dashglpi_sla_managed_status_label($notificationRow ? 'managed' : ($notificationId > 0 ? 'missing' : 'pending')),
        ],
        'template' => [
            'id' => (int) ($templateRow['id'] ?? $templateId),
            'translation_id' => (int) ($templateRow['translation_id'] ?? $managedIds['translation_id'] ?? 0),
            'name' => (string) ($notificationDefaults['template_name'] ?? $templateRow['name'] ?? 'Notificação por E-mail SLA'),
            'language' => trim((string) ($notificationDefaults['template_language'] ?? '')) !== ''
                ? (string) $notificationDefaults['template_language']
                : (string) ($templateRow['language'] ?? 'pt_BR'),
            'subject' => trim((string) ($notificationDefaults['template_subject'] ?? '')) !== ''
                ? (string) $notificationDefaults['template_subject']
                : (string) ($templateRow['subject'] ?? ''),
            'content_html' => trim((string) ($notificationDefaults['template_content_html'] ?? '')) !== ''
                ? (string) $notificationDefaults['template_content_html']
                : (string) ($templateRow['content_html'] ?? ''),
            'content_text' => trim((string) ($notificationDefaults['template_content_text'] ?? '')) !== ''
                ? (string) $notificationDefaults['template_content_text']
                : (string) ($templateRow['content_text'] ?? ''),
            'status' => $templateRow ? 'managed' : ($templateId > 0 ? 'missing' : 'pending'),
            'status_label' => dashglpi_sla_managed_status_label($templateRow ? 'managed' : ($templateId > 0 ? 'missing' : 'pending')),
        ],
        'managed_ids' => [
            'template_id' => $templateId,
            'translation_id' => (int) ($managedIds['translation_id'] ?? 0),
            'notification_id' => $notificationId,
        ],
    ];
}

function dashglpi_sla_automation_status(array $catalog): array
{
    $delivery = function_exists('dashglpi_notification_delivery_status')
        ? dashglpi_notification_delivery_status()
        : [];
    $automation = is_array($catalog['automation'] ?? null) ? $catalog['automation'] : [];

    $queued = dashglpi_sla_crontask_status(array_merge(
        is_array($automation['queuednotification'] ?? null) ? $automation['queuednotification'] : [],
        is_array($delivery) ? $delivery : []
    ), true);
    $slaticket = dashglpi_sla_crontask_status(
        is_array($automation['slaticket'] ?? null) ? $automation['slaticket'] : [],
        false
    );

    return [
        'queuednotification' => $queued,
        'slaticket' => $slaticket,
        'recommendation_ok' => !empty($queued['recommendation_ok']) && !empty($slaticket['recommendation_ok']),
    ];
}

function dashglpi_sla_notification_catalog_subset(array $catalog): array
{
    $specialEvent = $catalog['special_events']['ticket']['sla_reminder'] ?? [
        'available' => false,
        'event_key' => '',
        'event_label' => '',
    ];
    $eventKey = !empty($specialEvent['available']) ? (string) ($specialEvent['event_key'] ?? '') : '';

    return [
        'itemtype' => 'Ticket',
        'itemtype_label' => (string) ($catalog['itemtypes']['Ticket'] ?? 'Chamado'),
        'special_event' => $specialEvent,
        'targets' => $eventKey !== ''
            ? (array) ($catalog['targets_by_itemtype']['Ticket'][$eventKey] ?? [])
            : [],
    ];
}

function dashglpi_sla_ticket_templates(): array
{
    if (!function_exists('dashglpi_notification_templates')) {
        return [];
    }

    return array_values(array_filter(
        dashglpi_notification_templates(),
        static fn(array $row): bool => (string) ($row['itemtype'] ?? '') === 'Ticket'
    ));
}

function dashglpi_sla_rule_specs(): array
{
    $rows = [];
    foreach (dashglpi_sla_priority_map() as $mapping) {
        $rows[] = [
            'name' => sprintf('DashGLPI SLA %s - Prioridade %d', $mapping['bucket'], $mapping['priority']),
            'bucket' => $mapping['bucket'],
            'priority' => $mapping['priority'],
        ];
    }

    return $rows;
}

function dashglpi_sla_priority_map(): array
{
    return [
        ['bucket' => 'P1', 'priority' => 6],
        ['bucket' => 'P1', 'priority' => 5],
        ['bucket' => 'P2', 'priority' => 4],
        ['bucket' => 'P3', 'priority' => 3],
        ['bucket' => 'P4', 'priority' => 2],
        ['bucket' => 'P4', 'priority' => 1],
    ];
}

function dashglpi_sla_build_reminders_payload(array $settings, ?array $reminders = null): array
{
    $payload = dashglpi_sla_build_payload_from_settings($settings);
    $reminders = is_array($reminders) ? $reminders : (is_array($settings['reminders'] ?? null) ? $settings['reminders'] : []);
    $payload['reminders'] = [];

    foreach ($reminders as $key => $config) {
        if (!str_starts_with((string) $key, 'TTR-')) {
            continue;
        }

        $payload['reminders'][] = [
            'key' => (string) $key,
            'id' => (int) ($settings['ids']['reminders'][$key] ?? 0),
            'slas_id' => (int) ($settings['ids']['slas'][$key] ?? 0),
            'is_active' => !empty($config['is_active']) ? 1 : 0,
            'offset_value' => max(1, (int) ($config['offset_value'] ?? 1)),
            'offset_unit' => (string) ($config['offset_unit'] ?? 'minute'),
        ];
    }

    return $payload;
}

function dashglpi_sla_reminders_from_post(array $post, array $current): array
{
    $legacyActive = is_array($post['reminder_is_active'] ?? null) ? $post['reminder_is_active'] : [];
    $legacyValue = is_array($post['reminder_offset_value'] ?? null) ? $post['reminder_offset_value'] : [];
    $legacyUnit = is_array($post['reminder_offset_unit'] ?? null) ? $post['reminder_offset_unit'] : [];
    $inputRows = is_array($post['reminders'] ?? null) ? $post['reminders'] : [];
    $reminders = is_array($current['reminders'] ?? null) ? $current['reminders'] : [];

    foreach ($reminders as $key => $reminder) {
        $row = is_array($inputRows[$key] ?? null) ? $inputRows[$key] : [];
        if (!array_key_exists($key, $inputRows) && !array_key_exists($key, $legacyActive) && !array_key_exists($key, $legacyValue) && !array_key_exists($key, $legacyUnit)) {
            continue;
        }

        $reminders[$key] = [
            'is_active' => !empty($row['is_active']) || !empty($legacyActive[$key]) ? 1 : 0,
            'offset_value' => max(1, (int) ($row['offset_value'] ?? $legacyValue[$key] ?? $reminder['offset_value'] ?? 10)),
            'offset_unit' => (string) ($row['offset_unit'] ?? $legacyUnit[$key] ?? $reminder['offset_unit'] ?? 'minute'),
        ];
    }

    $candidate = $current;
    $candidate['reminders'] = $reminders;
    return dashglpi_validate_sla_simple_settings($candidate)['reminders'];
}

function dashglpi_sla_notification_from_post(array $post, array $current): array
{
    $input = is_array($post['sla_notification'] ?? null) ? $post['sla_notification'] : [];
    if ($input === []) {
        $input = [
            'name' => $post['sla_notification_name'] ?? null,
            'entities_id' => $post['sla_notification_entities_id'] ?? null,
            'is_recursive' => $post['sla_notification_is_recursive'] ?? null,
            'is_active' => $post['sla_notification_is_active'] ?? null,
            'template_name' => $post['sla_notification_template_name'] ?? null,
            'template_id' => $post['sla_notification_template_id'] ?? null,
            'template_translation_id' => $post['sla_notification_template_translation_id'] ?? null,
            'template_language' => $post['sla_notification_template_language'] ?? null,
            'template_subject' => $post['sla_notification_template_subject'] ?? null,
            'template_content_text' => $post['sla_notification_template_content_text'] ?? null,
            'template_content_html' => $post['sla_notification_template_content_html'] ?? null,
            'recipient_keys' => $post['sla_notification_recipient_keys'] ?? [],
        ];
        $input = array_filter($input, static fn($value): bool => $value !== null);
    }

    $candidate = $current;
    $candidate['sla_notification'] = array_merge(
        is_array($current['sla_notification'] ?? null) ? $current['sla_notification'] : [],
        $input
    );

    return dashglpi_validate_sla_simple_settings($candidate)['sla_notification'];
}

function dashglpi_sla_settings_with_reminders(array $current, array $reminders, array $result): array
{
    $next = $current;
    $next['reminders'] = $reminders;

    $ids = is_array($current['ids'] ?? null) ? $current['ids'] : [];
    $ids['reminders'] = [];
    $rows = [];
    foreach ((array) ($result['reminders'] ?? []) as $row) {
        if (is_array($row)) {
            $rows[(string) ($row['key'] ?? '')] = $row;
        }
    }

    foreach ($reminders as $key => $_) {
        $ids['reminders'][$key] = max(0, (int) ($rows[$key]['id'] ?? 0));
    }

    $next['ids'] = $ids;
    return dashglpi_validate_sla_simple_settings($next);
}

function dashglpi_sla_settings_with_notification(
    array $current,
    array $notification,
    array $templateResult,
    array $notificationResult
): array {
    $next = $current;
    $ids = is_array($current['ids'] ?? null) ? $current['ids'] : [];
    $bundle = is_array($templateResult['template_bundle'] ?? null) ? $templateResult['template_bundle'] : [];
    $savedNotification = is_array($notificationResult['notification'] ?? null) ? $notificationResult['notification'] : [];

    $ids['sla_notification'] = [
        'template_id' => max(0, (int) ($bundle['template_id'] ?? $ids['sla_notification']['template_id'] ?? 0)),
        'translation_id' => max(0, (int) ($bundle['translation_id'] ?? $ids['sla_notification']['translation_id'] ?? 0)),
        'notification_id' => max(0, (int) ($savedNotification['id'] ?? $ids['sla_notification']['notification_id'] ?? 0)),
    ];
    $next['ids'] = $ids;

    $notification['template_id'] = $ids['sla_notification']['template_id'];
    $notification['template_translation_id'] = $ids['sla_notification']['translation_id'];
    $next['sla_notification'] = $notification;

    return dashglpi_validate_sla_simple_settings($next);
}

function dashglpi_sla_template_bundle_payload(array $notification): array
{
    return [
        'action' => 'save_template_bundle',
        'template_id' => max(0, (int) ($notification['template_id'] ?? 0)),
        'translation_id' => max(0, (int) ($notification['template_translation_id'] ?? 0)),
        'name' => (string) ($notification['template_name'] ?? 'Notificação por E-mail SLA'),
        'itemtype' => 'Ticket',
        'language' => (string) ($notification['template_language'] ?? 'pt_BR'),
        'subject' => (string) ($notification['template_subject'] ?? 'Notificação de SLA'),
        'content_html' => (string) ($notification['template_content_html'] ?? ''),
        'content_text' => (string) ($notification['template_content_text'] ?? ''),
    ];
}

function dashglpi_sla_notification_bridge_payload(array $notification, string $eventKey, int $templateId, int $notificationId): array
{
    return [
        'action' => 'save',
        'id' => $notificationId,
        'name' => (string) ($notification['name'] ?? 'SLA'),
        'itemtype' => 'Ticket',
        'event' => $eventKey,
        'entities_id' => max(0, (int) ($notification['entities_id'] ?? 0)),
        'is_recursive' => !empty($notification['is_recursive']) ? 1 : 0,
        'is_active' => !empty($notification['is_active']) ? 1 : 0,
        'template_id' => $templateId,
        'recipients' => array_values(array_unique(array_filter(array_map(
            'strval',
            (array) ($notification['recipient_keys'] ?? [])
        )))),
    ];
}

function dashglpi_sla_base_ids_ready(array $settings): bool
{
    if ((int) ($settings['ids']['calendars_id'] ?? 0) <= 0 || (int) ($settings['ids']['slms_id'] ?? 0) <= 0) {
        return false;
    }

    foreach (DASHGLPI_SLA_KEYS as $key => $_meta) {
        if ((int) ($settings['ids']['slas'][$key] ?? 0) <= 0) {
            return false;
        }
    }

    return true;
}

function dashglpi_sla_ttr_ids_ready(array $settings): bool
{
    foreach (dashglpi_sla_keys_from_settings($settings, 'TTR') as $key) {
        if ((int) ($settings['ids']['slas'][$key] ?? 0) <= 0) {
            return false;
        }
    }

    return true;
}

function dashglpi_sla_managed_reminder_rows(array $settings): array
{
    $ids = array_values(array_unique(array_filter(array_map(
        static fn($value): int => max(0, (int) $value),
        (array) ($settings['ids']['reminders'] ?? [])
    ))));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $rows = dashglpi_fetch_all(
        "SELECT id, name, slas_id, execution_time, is_active, entities_id, is_recursive, `match`
         FROM glpi_slalevels
         WHERE id IN ($placeholders)",
        $ids
    );

    $indexed = [];
    foreach ($rows as $row) {
        $indexed[(int) ($row['id'] ?? 0)] = $row;
    }

    return $indexed;
}

function dashglpi_sla_duration_seconds(array $row): int
{
    $value = max(0, (int) ($row['number_time'] ?? 0));
    $unit = (string) ($row['definition_time'] ?? '');

    return match ($unit) {
        'minute' => $value * 60,
        'hour' => $value * 3600,
        'day' => $value * 86400,
        'month' => $value * 2592000,
        default => 0,
    };
}

function dashglpi_sla_duration_label(int $value, string $unit): string
{
    $label = match ($unit) {
        'minute' => $value === 1 ? 'minuto' : 'minutos',
        'hour' => $value === 1 ? 'hora' : 'horas',
        'day' => $value === 1 ? 'dia' : 'dias',
        'month' => $value === 1 ? 'mês' : 'meses',
        default => $unit,
    };

    return trim($value . ' ' . $label);
}

function dashglpi_sla_managed_status_label(string $status): string
{
    return match ($status) {
        'managed' => 'Gerenciado',
        'inactive' => 'Desativado',
        'to_remove' => 'Remover',
        'blocked' => 'Bloqueado',
        'drift' => 'Difere',
        'missing' => 'Ausente',
        default => 'Pendente',
    };
}

function dashglpi_sla_find_ticket_template(array $templates, int $templateId, string $templateName): ?array
{
    foreach ($templates as $row) {
        if ($templateId > 0 && (int) ($row['id'] ?? 0) === $templateId) {
            return $row;
        }
    }

    $templateName = trim($templateName);
    if ($templateName === '') {
        return null;
    }

    foreach ($templates as $row) {
        if (strcasecmp((string) ($row['name'] ?? ''), $templateName) === 0) {
            return $row;
        }
    }

    return null;
}

function dashglpi_sla_find_ticket_notification(array $notifications, int $notificationId, string $name, string $event): ?array
{
    foreach ($notifications as $row) {
        if ($notificationId > 0 && (int) ($row['id'] ?? 0) === $notificationId) {
            return $row;
        }
    }

    $name = trim($name);
    foreach ($notifications as $row) {
        if ((string) ($row['itemtype'] ?? '') !== 'Ticket') {
            continue;
        }
        if ($event !== '' && (string) ($row['event'] ?? '') !== $event) {
            continue;
        }
        if ($name !== '' && strcasecmp((string) ($row['name'] ?? ''), $name) !== 0) {
            continue;
        }
        return $row;
    }

    return null;
}

function dashglpi_sla_notification_recipient_keys(array $draft, ?array $notificationRow): array
{
    $draftKeys = array_values(array_unique(array_filter(array_map(
        'strval',
        (array) ($draft['recipient_keys'] ?? [])
    ))));
    if ($draftKeys !== []) {
        return $draftKeys;
    }

    return array_values(array_unique(array_filter(array_map(
        'strval',
        (array) ($notificationRow['recipient_keys'] ?? [])
    ))));
}

function dashglpi_sla_crontask_status(array $task, bool $withQueueMetrics): array
{
    $available = !empty($task['available']);
    $state = (int) ($task['state'] ?? 0);
    $mode = (int) ($task['mode'] ?? 0);
    $frequency = (int) ($task['frequency'] ?? 0);
    $lastrun = trim((string) ($task['lastrun'] ?? ''));
    $lastRunAge = 0;
    if ($lastrun !== '') {
        $timestamp = strtotime($lastrun);
        if ($timestamp !== false) {
            $lastRunAge = max(0, time() - $timestamp);
        }
    }

    $recommendationOk = $available && $state === 1 && $mode === 2 && $frequency === 60;
    $attention = !$available || !$recommendationOk;
    if ($available && $lastrun !== '') {
        $attention = $attention || $lastRunAge > max(180, $frequency * 3);
    }
    if ($available && $lastrun === '') {
        $attention = true;
    }

    if ($withQueueMetrics) {
        $attention = $attention || (!empty($task['attention_active']));
    }

    return [
        'available' => $available,
        'id' => (int) ($task['id'] ?? 0),
        'itemtype' => (string) ($task['itemtype'] ?? ''),
        'name' => (string) ($task['name'] ?? ''),
        'state' => $state,
        'state_label' => (string) ($task['state_label'] ?? dashglpi_sla_crontask_state_label($state)),
        'mode' => $mode,
        'mode_label' => (string) ($task['mode_label'] ?? dashglpi_sla_crontask_mode_label($mode)),
        'allowmode' => (int) ($task['allowmode'] ?? 0),
        'frequency' => $frequency,
        'lastrun' => $lastrun,
        'lastcode' => (string) ($task['lastcode'] ?? ''),
        'last_run_age_seconds' => $lastRunAge,
        'pending_count' => $withQueueMetrics ? (int) ($task['pending_count'] ?? 0) : 0,
        'delay_seconds' => $withQueueMetrics ? (int) ($task['delay_seconds'] ?? 0) : 0,
        'recommendation_ok' => $recommendationOk,
        'attention_active' => $attention,
    ];
}

function dashglpi_sla_crontask_state_label(int $state): string
{
    return match ($state) {
        1 => 'Ativa',
        2 => 'Em execução',
        default => 'Desativada',
    };
}

function dashglpi_sla_crontask_mode_label(int $mode): string
{
    return match ($mode) {
        2 => 'CLI',
        1 => 'GLPI',
        default => 'Desconhecido',
    };
}

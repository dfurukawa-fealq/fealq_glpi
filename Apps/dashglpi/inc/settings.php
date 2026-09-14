<?php

const DASHGLPI_SETTINGS_TABLE = 'glpi_plugin_dashglpi_settings';

function dashglpi_profile_access_page_catalog(): array
{
    return [
        'dashboard' => ['label' => 'Visao Geral', 'icon' => 'fa-th-large'],
        'tickets' => ['label' => 'Chamados', 'icon' => 'fa-ticket-alt'],
        'sla' => ['label' => 'Monitor SLA', 'icon' => 'fa-clock'],
        'ranking' => ['label' => 'Ranking Tecnicos', 'icon' => 'fa-trophy'],
        'assets' => ['label' => 'Inventario de Ativos', 'icon' => 'fa-network-wired'],
    ];
}

function dashglpi_settings_defaults(string $namespace): array
{
    return match ($namespace) {
        'reports' => [
            'brand' => 'inb',
            'pdf' => 'browser',
            'attachments' => 'images_inline',
            'visibility' => 'complete',
            'app_name' => 'Fealq - GLPI',
            'logo_url' => 'https://fealq.org.br/wp-content/uploads/2026/01/cropped-08_2025-Fealq-Campanha-50-anos-v10-Brandbook-elementos-visuais-9.png',
            'logo_light_url' => 'https://fealq.org.br/wp-content/uploads/2026/01/cropped-08_2025-Fealq-Campanha-50-anos-v10-Brandbook-elementos-visuais-9.png',
            'logo_dark_url' => 'https://fealq.org.br/wp-content/uploads/2026/01/cropped-08_2025-Fealq-Campanha-50-anos-v10-Brandbook-elementos-visuais-9.png',            
        ],
        'sla_simple' => [
            'entities_id' => 0,
            'entity_new_name' => '',
            'entity_parent_id' => 0,
            'calendar_id' => 0,
            'calendar_new_name' => '',
            'calendar_is_recursive' => 0,
            'slm_name' => '',
            'ids' => [
                'entities_id' => 0,
                'calendars_id' => 0,
                'slms_id' => 0,
                'slas' => [],
                'rules' => [],
                'reminders' => [],
                'sla_notification' => [
                    'template_id' => 0,
                    'translation_id' => 0,
                    'notification_id' => 0,
                ],
            ],
            'slas' => [
                'TTO-P1' => ['number_time' => 15, 'definition_time' => 'minute'],
                'TTO-P2' => ['number_time' => 30, 'definition_time' => 'minute'],
                'TTO-P3' => ['number_time' => 2, 'definition_time' => 'hour'],
                'TTO-P4' => ['number_time' => 4, 'definition_time' => 'hour'],
                'TTR-P1' => ['number_time' => 4, 'definition_time' => 'hour'],
                'TTR-P2' => ['number_time' => 8, 'definition_time' => 'hour'],
                'TTR-P3' => ['number_time' => 2, 'definition_time' => 'day'],
                'TTR-P4' => ['number_time' => 5, 'definition_time' => 'day'],
            ],
            'calendar_segments' => [
                1 => ['enabled' => true, 'begin' => '08:00', 'end' => '18:00'],
                2 => ['enabled' => true, 'begin' => '08:00', 'end' => '18:00'],
                3 => ['enabled' => true, 'begin' => '08:00', 'end' => '18:00'],
                4 => ['enabled' => true, 'begin' => '08:00', 'end' => '18:00'],
                5 => ['enabled' => true, 'begin' => '08:00', 'end' => '18:00'],
                6 => ['enabled' => false, 'begin' => '08:00', 'end' => '18:00'],
                0 => ['enabled' => false, 'begin' => '08:00', 'end' => '18:00'],
            ],
            'reminders' => [
                'TTR-P1' => ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute'],
                'TTR-P2' => ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute'],
                'TTR-P3' => ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute'],
                'TTR-P4' => ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute'],
            ],
            'sla_notification' => [
                'name' => 'SLA',
                'entities_id' => 0,
                'is_recursive' => 1,
                'is_active' => 1,
                'template_name' => 'Notificação por E-mail SLA',
                'template_id' => 0,
                'template_translation_id' => 0,
                'template_language' => 'pt_BR',
                'template_subject' => 'Notificação de SLA',
                'template_content_text' => '',
                'template_content_html' => '',
                'recipient_keys' => [],
            ],
        ],
        'alerting' => [
            'sla_threshold_minutes' => 15,
            'warn_threshold_minutes' => 0,
            'max_age_hours' => 0,
            'since_date' => '',
            'teams' => ['enabled' => false, 'webhook_url' => ''],
            'whatsapp' => ['enabled' => false, 'api_url' => '', 'api_key' => '', 'instance' => '', 'recipients' => []],
            'telegram' => ['enabled' => false, 'bot_token' => '', 'chat_ids' => []],
            'n8n' => ['enabled' => false, 'webhook_url' => ''],
            'entities' => [],
            'groups' => [],
        ],
        'menu' => [
            'settings' => true,
            'assets' => true,
            'sla' => true,
        ],
        'profile_access' => [
            'rules' => [],
        ],
        default => [],
    };
}

function dashglpi_ensure_settings_table(): void
{
    dashglpi_db()->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_SETTINGS_TABLE . " (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            namespace VARCHAR(80) NOT NULL,
            data JSON NOT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_settings_namespace (namespace)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dashglpi_get_settings(string $namespace): array
{
    dashglpi_ensure_settings_table();

    $row = dashglpi_fetch_one(
        "SELECT data FROM " . DASHGLPI_SETTINGS_TABLE . " WHERE namespace = ? LIMIT 1",
        [$namespace]
    );

    $defaults = dashglpi_settings_defaults($namespace);
    if (!$row) {
        dashglpi_save_settings($namespace, $defaults);
        return $defaults;
    }

    $data = json_decode((string) $row['data'], true);
    if (!is_array($data)) {
        return $defaults;
    }

    return dashglpi_validate_settings($namespace, $data);
}

function dashglpi_save_settings(string $namespace, array $data): void
{
    dashglpi_ensure_settings_table();

    $data = dashglpi_validate_settings($namespace, $data);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Falha ao serializar configuracoes.');
    }

    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_SETTINGS_TABLE . " (namespace, data)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE data = VALUES(data), date_mod = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$namespace, $json]);
}

function dashglpi_validate_settings(string $namespace, array $data): array
{
    if ($namespace === 'sla_simple') {
        return dashglpi_validate_sla_simple_settings($data);
    }

    if ($namespace === 'profile_access') {
        return dashglpi_validate_profile_access_settings($data);
    }

    if ($namespace === 'alerting') {
        return dashglpi_validate_alerting_settings($data);
    }

    if ($namespace !== 'reports') {
        return array_merge(dashglpi_settings_defaults($namespace), $data);
    }

    $defaults = dashglpi_settings_defaults('reports');
    $configuredAppName = trim((string) ($data['app_name'] ?? $defaults['app_name']));
    if (in_array(strtolower($configuredAppName), [
        'dashglpi',
        'glpi dash pro',
        'Fealq - dashboard glpi pro',
    ], true)) {
        $configuredAppName = $defaults['app_name'];
    }

    $legacyLogoUrl = dashglpi_normalize_logo_url(
        (string) ($data['logo_url'] ?? $defaults['logo_url']),
        $defaults['logo_url']
    );
    $logoLightUrl = dashglpi_normalize_logo_url(
        (string) ($data['logo_light_url'] ?? $legacyLogoUrl),
        $legacyLogoUrl
    );
    $logoDarkUrl = dashglpi_normalize_logo_url(
        (string) ($data['logo_dark_url'] ?? $legacyLogoUrl),
        $legacyLogoUrl
    );

    return [
        'brand' => in_array(($data['brand'] ?? ''), ['inb'], true) ? $data['brand'] : $defaults['brand'],
        'pdf' => in_array(($data['pdf'] ?? ''), ['browser'], true) ? $data['pdf'] : $defaults['pdf'],
        'attachments' => in_array(($data['attachments'] ?? ''), ['images_inline', 'table_only'], true)
            ? $data['attachments']
            : $defaults['attachments'],
        'visibility' => in_array(($data['visibility'] ?? ''), ['complete', 'public_only'], true)
            ? $data['visibility']
            : $defaults['visibility'],
        'app_name' => dashglpi_normalize_app_name($configuredAppName, $defaults['app_name']),
        'logo_url' => $logoLightUrl,
        'logo_light_url' => $logoLightUrl,
        'logo_dark_url' => $logoDarkUrl,
    ];
}

function dashglpi_validate_sla_simple_settings(array $data): array
{
    $defaults = dashglpi_settings_defaults('sla_simple');
    $definitionTimes = ['minute', 'hour', 'day', 'month'];
    $reminderUnits = ['minute', 'hour', 'day', 'month'];

    $settings = array_merge($defaults, $data);
    $settings['entities_id'] = max(0, (int) ($data['entities_id'] ?? $defaults['entities_id']));
    $settings['entity_new_name'] = trim((string) ($data['entity_new_name'] ?? ''));
    $settings['entity_parent_id'] = max(0, (int) ($data['entity_parent_id'] ?? 0));
    $settings['calendar_id'] = max(0, (int) ($data['calendar_id'] ?? $defaults['calendar_id']));
    $settings['calendar_new_name'] = trim((string) ($data['calendar_new_name'] ?? ''));
    $settings['calendar_is_recursive'] = !empty($data['calendar_is_recursive']) ? 1 : 0;
    $settings['slm_name'] = trim((string) ($data['slm_name'] ?? ''));

    $settings['slas'] = [];
    $slaInputs = is_array($data['slas'] ?? null) ? $data['slas'] : [];
    $slaKeys = array_keys($defaults['slas']);
    foreach ($slaInputs as $key => $_) {
        $key = strtoupper(trim((string) $key));
        if (dashglpi_sla_settings_key_is_valid($key) && !in_array($key, $slaKeys, true)) {
            $slaKeys[] = $key;
        }
    }
    usort($slaKeys, 'dashglpi_sla_settings_key_compare');

    foreach ($slaKeys as $key) {
        $slaDefault = $defaults['slas'][$key] ?? ['number_time' => 1, 'definition_time' => 'minute'];
        $slaInput = is_array($slaInputs[$key] ?? null) ? $slaInputs[$key] : [];
        $numberTime = (int) ($slaInput['number_time'] ?? $slaDefault['number_time']);
        $definitionTime = (string) ($slaInput['definition_time'] ?? $slaDefault['definition_time']);

        $settings['slas'][$key] = [
            'number_time' => min(1000, max(1, $numberTime)),
            'definition_time' => in_array($definitionTime, $definitionTimes, true)
                ? $definitionTime
                : $slaDefault['definition_time'],
        ];
    }

    $settings['calendar_segments'] = [];
    $segmentsInput = is_array($data['calendar_segments'] ?? null) ? $data['calendar_segments'] : [];
    foreach ($defaults['calendar_segments'] as $day => $segmentDefault) {
        $segmentInput = is_array($segmentsInput[$day] ?? null) ? $segmentsInput[$day] : [];
        $begin = dashglpi_normalize_time((string) ($segmentInput['begin'] ?? $segmentDefault['begin']), $segmentDefault['begin']);
        $end = dashglpi_normalize_time((string) ($segmentInput['end'] ?? $segmentDefault['end']), $segmentDefault['end']);

        $settings['calendar_segments'][(int) $day] = [
            'enabled' => !empty($segmentInput['enabled']),
            'begin' => $begin,
            'end' => $end,
        ];
    }

    $settings['reminders'] = [];
    $reminderInputs = is_array($data['reminders'] ?? null) ? $data['reminders'] : [];
    $reminderKeys = array_values(array_filter(
        array_keys($settings['slas']),
        static fn (string $key): bool => str_starts_with($key, 'TTR-')
    ));
    foreach (array_keys($reminderInputs) as $key) {
        $key = strtoupper(trim((string) $key));
        if (
            dashglpi_sla_settings_key_is_valid($key)
            && str_starts_with($key, 'TTR-')
            && !in_array($key, $reminderKeys, true)
        ) {
            $reminderKeys[] = $key;
        }
    }
    usort($reminderKeys, 'dashglpi_sla_settings_key_compare');

    foreach ($reminderKeys as $key) {
        $defaultReminder = $defaults['reminders'][$key] ?? ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute'];
        $reminderInput = is_array($reminderInputs[$key] ?? null) ? $reminderInputs[$key] : [];
        $offsetUnit = (string) ($reminderInput['offset_unit'] ?? $defaultReminder['offset_unit']);

        $settings['reminders'][$key] = [
            'is_active' => !empty($reminderInput['is_active']) ? 1 : 0,
            'offset_value' => min(1000, max(1, (int) ($reminderInput['offset_value'] ?? $defaultReminder['offset_value']))),
            'offset_unit' => in_array($offsetUnit, $reminderUnits, true)
                ? $offsetUnit
                : $defaultReminder['offset_unit'],
        ];
    }

    $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
    $settings['ids'] = [
        'entities_id' => max(0, (int) ($ids['entities_id'] ?? 0)),
        'calendars_id' => max(0, (int) ($ids['calendars_id'] ?? 0)),
        'slms_id' => max(0, (int) ($ids['slms_id'] ?? 0)),
        'slas' => [],
        'rules' => [],
        'reminders' => [],
        'sla_notification' => [
            'template_id' => 0,
            'translation_id' => 0,
            'notification_id' => 0,
        ],
    ];

    if (is_array($ids['slas'] ?? null)) {
        foreach (array_keys($settings['slas']) as $key) {
            $settings['ids']['slas'][$key] = max(0, (int) ($ids['slas'][$key] ?? 0));
        }
    }

    if (is_array($ids['rules'] ?? null)) {
        foreach ($ids['rules'] as $key => $value) {
            $ruleKey = trim((string) $key);
            if ($ruleKey === '') {
                continue;
            }
            $settings['ids']['rules'][$ruleKey] = max(0, (int) $value);
        }
    }

    if (is_array($ids['reminders'] ?? null)) {
        foreach (array_keys($settings['reminders']) as $key) {
            $settings['ids']['reminders'][$key] = max(0, (int) ($ids['reminders'][$key] ?? 0));
        }
    }

    $notificationIds = is_array($ids['sla_notification'] ?? null) ? $ids['sla_notification'] : [];
    $settings['ids']['sla_notification'] = [
        'template_id' => max(0, (int) ($notificationIds['template_id'] ?? 0)),
        'translation_id' => max(0, (int) ($notificationIds['translation_id'] ?? 0)),
        'notification_id' => max(0, (int) ($notificationIds['notification_id'] ?? 0)),
    ];

    $slaNotification = is_array($data['sla_notification'] ?? null) ? $data['sla_notification'] : [];
    $notificationDefaults = $defaults['sla_notification'];
    $recipientKeys = [];
    $recipientInputs = is_array($slaNotification['recipient_keys'] ?? null) ? $slaNotification['recipient_keys'] : [];
    foreach ($recipientInputs as $rawKey) {
        $key = trim((string) $rawKey);
        if ($key !== '' && preg_match('/^\d+_\d+$/', $key) === 1 && !in_array($key, $recipientKeys, true)) {
            $recipientKeys[] = $key;
        }
    }

    $settings['sla_notification'] = [
        'name' => substr(trim(preg_replace('/\s+/', ' ', (string) ($slaNotification['name'] ?? $notificationDefaults['name'])) ?? ''), 0, 255),
        'entities_id' => max(0, (int) ($slaNotification['entities_id'] ?? $notificationDefaults['entities_id'])),
        'is_recursive' => array_key_exists('is_recursive', $slaNotification)
            ? (!empty($slaNotification['is_recursive']) ? 1 : 0)
            : (int) $notificationDefaults['is_recursive'],
        'is_active' => array_key_exists('is_active', $slaNotification)
            ? (!empty($slaNotification['is_active']) ? 1 : 0)
            : (int) $notificationDefaults['is_active'],
        'template_name' => substr(trim((string) ($slaNotification['template_name'] ?? $notificationDefaults['template_name'])), 0, 255),
        'template_id' => max(0, (int) ($slaNotification['template_id'] ?? $notificationDefaults['template_id'])),
        'template_translation_id' => max(0, (int) ($slaNotification['template_translation_id'] ?? $notificationDefaults['template_translation_id'])),
        'template_language' => preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', (string) ($slaNotification['template_language'] ?? ''))
            ? (string) ($slaNotification['template_language'] ?? $notificationDefaults['template_language'])
            : $notificationDefaults['template_language'],
        'template_subject' => substr((string) ($slaNotification['template_subject'] ?? $notificationDefaults['template_subject']), 0, 255),
        'template_content_text' => substr((string) ($slaNotification['template_content_text'] ?? $notificationDefaults['template_content_text']), 0, 65535),
        'template_content_html' => substr((string) ($slaNotification['template_content_html'] ?? $notificationDefaults['template_content_html']), 0, 65535),
        'recipient_keys' => $recipientKeys,
    ];

    if ($settings['sla_notification']['name'] === '') {
        $settings['sla_notification']['name'] = $notificationDefaults['name'];
    }
    if ($settings['sla_notification']['template_name'] === '') {
        $settings['sla_notification']['template_name'] = $notificationDefaults['template_name'];
    }

    return $settings;
}

function dashglpi_helpdesk_profile_ids(): array
{
    return array_map(
        'intval',
        array_column(
            dashglpi_fetch_all("SELECT id FROM glpi_profiles WHERE interface = 'helpdesk'"),
            'id'
        )
    );
}

function dashglpi_validate_profile_access_settings(array $data): array
{
    $defaults = dashglpi_settings_defaults('profile_access');
    $pageKeys = array_keys(dashglpi_profile_access_page_catalog());
    $inputRules = $data['rules'] ?? [];
    if (!is_array($inputRules)) {
        $inputRules = [];
    }

    $rules = [];
    $seenProfiles = [];
    $enabledPriorities = [];

    foreach ($inputRules as $rawRule) {
        if (!is_array($rawRule)) {
            continue;
        }

        $profileId = max(0, (int) ($rawRule['profile_id'] ?? 0));
        if ($profileId <= 0) {
            throw new RuntimeException('Perfil invalido na configuracao de acesso.');
        }
        if (isset($seenProfiles[$profileId])) {
            throw new RuntimeException('Nao permitir duplicidade de regra para o mesmo perfil.');
        }

        $enabled = !empty($rawRule['enabled']) ? 1 : 0;
        $priorityRaw = trim((string) ($rawRule['priority'] ?? ''));
        $priority = $priorityRaw === '' ? 0 : min(99999, max(1, (int) $priorityRaw));

        $allowedPages = [];
        $allowedInputs = $rawRule['allowed_pages'] ?? [];
        if (is_string($allowedInputs) && $allowedInputs !== '') {
            $allowedInputs = array_map('trim', explode(',', $allowedInputs));
        }
        if (!is_array($allowedInputs)) {
            $allowedInputs = [];
        }

        foreach ($allowedInputs as $page) {
            $page = trim((string) $page);
            if (in_array($page, $pageKeys, true) && !in_array($page, $allowedPages, true)) {
                $allowedPages[] = $page;
            }
        }

        if ($enabled && $priority <= 0) {
            throw new RuntimeException('Nao permitir regra ativa sem prioridade.');
        }
        if ($enabled && !$allowedPages) {
            throw new RuntimeException('Nao permitir regra ativa sem ao menos uma pagina selecionada.');
        }
        if ($enabled) {
            if (isset($enabledPriorities[$priority])) {
                throw new RuntimeException('Nao permitir duas regras ativas com a mesma prioridade.');
            }
            $enabledPriorities[$priority] = true;
        }

        $shouldPersist = $enabled || $priority > 0 || $allowedPages;
        if (!$shouldPersist) {
            continue;
        }

        $seenProfiles[$profileId] = true;
        $rules[] = [
            'profile_id' => $profileId,
            'enabled' => $enabled,
            'priority' => $priority,
            'allowed_pages' => array_values($allowedPages),
        ];
    }

    // Perfis GLPI com interface "helpdesk" (ex.: Self-Service) sempre trazem a
    // regra ativa e travada com "Visao Geral" + "Chamados" liberadas, mesmo que
    // o admin nunca tenha configurado (ou tenha tentado desativar) essa regra.
    $helpdeskProfileIds = dashglpi_helpdesk_profile_ids();
    $rulesIndexByProfile = [];
    foreach ($rules as $index => $rule) {
        $rulesIndexByProfile[$rule['profile_id']] = $index;
    }

    foreach ($helpdeskProfileIds as $profileId) {
        if (isset($rulesIndexByProfile[$profileId])) {
            $index = $rulesIndexByProfile[$profileId];
            $rules[$index]['allowed_pages'] = array_values(array_unique(array_merge(
                $rules[$index]['allowed_pages'],
                ['dashboard', 'tickets']
            )));

            if ((int) $rules[$index]['enabled'] !== 1) {
                $rules[$index]['enabled'] = 1;
                $priority = (int) $rules[$index]['priority'];
                if ($priority <= 0 || isset($enabledPriorities[$priority])) {
                    $priority = 1;
                    while (isset($enabledPriorities[$priority])) {
                        $priority++;
                    }
                }
                $rules[$index]['priority'] = $priority;
                $enabledPriorities[$priority] = true;
            }

            continue;
        }

        $priority = 1;
        while (isset($enabledPriorities[$priority])) {
            $priority++;
        }
        $enabledPriorities[$priority] = true;

        $seenProfiles[$profileId] = true;
        $rules[] = [
            'profile_id' => $profileId,
            'enabled' => 1,
            'priority' => $priority,
            'allowed_pages' => ['dashboard', 'tickets'],
        ];
    }

    usort($rules, static function (array $left, array $right): int {
        $leftEnabled = (int) ($left['enabled'] ?? 0);
        $rightEnabled = (int) ($right['enabled'] ?? 0);
        if ($leftEnabled !== $rightEnabled) {
            return $rightEnabled <=> $leftEnabled;
        }

        $leftPriority = (int) ($left['priority'] ?? 0);
        $rightPriority = (int) ($right['priority'] ?? 0);
        if ($leftPriority !== $rightPriority) {
            if ($leftPriority === 0) {
                return 1;
            }
            if ($rightPriority === 0) {
                return -1;
            }
            return $leftPriority <=> $rightPriority;
        }

        return ((int) ($left['profile_id'] ?? 0)) <=> ((int) ($right['profile_id'] ?? 0));
    });

    return [
        'rules' => array_values($rules ?: $defaults['rules']),
    ];
}

function dashglpi_validate_alerting_settings(array $data): array
{
    $defaults = dashglpi_settings_defaults('alerting');

    $teams = is_array($data['teams'] ?? null) ? $data['teams'] : [];
    $whatsapp = is_array($data['whatsapp'] ?? null) ? $data['whatsapp'] : [];
    $telegram = is_array($data['telegram'] ?? null) ? $data['telegram'] : [];
    $n8n = is_array($data['n8n'] ?? null) ? $data['n8n'] : [];

    $settings = [
        'sla_threshold_minutes' => min(10080, max(1, (int) ($data['sla_threshold_minutes'] ?? $defaults['sla_threshold_minutes']))),
        'warn_threshold_minutes' => min(10080, max(0, (int) ($data['warn_threshold_minutes'] ?? 0))),
        // Trava de idade (PLAN-20260704-014): 0 = sem teto extra além do piso absoluto abaixo.
        'max_age_hours' => min(8760, max(0, (int) ($data['max_age_hours'] ?? 0))),
        // Piso absoluto ("rodar desde de"): vazio = sem piso. Datas futuras são rejeitadas
        // (suprimiriam todo alerta silenciosamente) e caem de volta para "sem piso".
        'since_date' => dashglpi_alerting_since_date((string) ($data['since_date'] ?? '')),
        'teams' => [
            'enabled' => !empty($teams['enabled']) ? true : false,
            'webhook_url' => dashglpi_alerting_url((string) ($teams['webhook_url'] ?? '')),
        ],
        'whatsapp' => [
            'enabled' => !empty($whatsapp['enabled']) ? true : false,
            'api_url' => dashglpi_alerting_url((string) ($whatsapp['api_url'] ?? '')),
            'api_key' => trim((string) ($whatsapp['api_key'] ?? '')),
            'instance' => substr(trim((string) ($whatsapp['instance'] ?? '')), 0, 120),
            'recipients' => dashglpi_alerting_digit_list($whatsapp['recipients'] ?? []),
        ],
        'telegram' => [
            'enabled' => !empty($telegram['enabled']) ? true : false,
            'bot_token' => substr(trim((string) ($telegram['bot_token'] ?? '')), 0, 120),
            'chat_ids' => dashglpi_alerting_id_list($telegram['chat_ids'] ?? ($telegram['chat_id'] ?? [])),
        ],
        // Canal n8n (PLAN-20260704-015): mesmo padrão de validação de URL do Teams.
        'n8n' => [
            'enabled' => !empty($n8n['enabled']) ? true : false,
            'webhook_url' => dashglpi_alerting_url((string) ($n8n['webhook_url'] ?? '')),
        ],
        'entities' => [],
        'groups' => [],
    ];

    // warn deve ser menor que o limite de estouro para fazer sentido.
    if ($settings['warn_threshold_minutes'] >= $settings['sla_threshold_minutes']) {
        $settings['warn_threshold_minutes'] = 0;
    }

    $entities = is_array($data['entities'] ?? null) ? $data['entities'] : [];
    foreach ($entities as $entityId => $override) {
        $entityId = (int) $entityId;
        if ($entityId < 0 || !is_array($override)) {
            continue;
        }
        $clean = [];
        if (isset($override['sla_threshold_minutes'])) {
            $threshold = (int) $override['sla_threshold_minutes'];
            if ($threshold > 0) {
                $clean['sla_threshold_minutes'] = min(10080, $threshold);
            }
        }
        $recipients = dashglpi_alerting_digit_list($override['whatsapp_recipients'] ?? []);
        if ($recipients) {
            $clean['whatsapp_recipients'] = $recipients;
        }
        $chatIds = dashglpi_alerting_id_list($override['telegram_chat_ids'] ?? []);
        if ($chatIds) {
            $clean['telegram_chat_ids'] = $chatIds;
        }
        $webhook = dashglpi_alerting_url((string) ($override['teams_webhook_url'] ?? ''));
        if ($webhook !== '') {
            $clean['teams_webhook_url'] = $webhook;
        }
        if ($clean) {
            $settings['entities'][(string) $entityId] = $clean;
        }
    }

    // Roteamento do TaskWorker (PLAN-007): webhook Teams por grupo/fila GLPI, já que
    // Incoming Webhook não suporta DM por técnico individual.
    $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
    foreach ($groups as $groupId => $override) {
        $groupId = (int) $groupId;
        if ($groupId <= 0 || !is_array($override)) {
            continue;
        }
        $webhook = dashglpi_alerting_url((string) ($override['teams_webhook_url'] ?? ''));
        if ($webhook !== '') {
            $settings['groups'][(string) $groupId] = ['teams_webhook_url' => $webhook];
        }
    }

    return $settings;
}

function dashglpi_alerting_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    // filter_var(FILTER_VALIDATE_URL) rejeita hostnames com underscore, mas os
    // nomes de containers Docker deste ecossistema usam underscore (kawa_mysql,
    // kawa_evolutionapi, ...). Validamos esquema + host manualmente para aceitar
    // destinos internos válidos na fealq-network, além de URLs públicas.
    if (!preg_match('/^https?:\/\//i', $url)) {
        return '';
    }

    $parts = parse_url($url);
    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
    if ($host === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $host)) {
        return '';
    }

    return $url;
}

/**
 * Piso absoluto de varredura ("rodar desde de"). Aceita 'Y-m-d' ou 'Y-m-d H:i:s'; datas
 * futuras ou inválidas caem para '' (sem piso) em vez de suprimir todo alerta em silêncio.
 */
function dashglpi_alerting_since_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false || $timestamp > time()) {
        return '';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

/** @return string[] Lista de números só-dígitos (WhatsApp). */
function dashglpi_alerting_digit_list($raw): array
{
    if (is_string($raw)) {
        $raw = preg_split('/[\s,;]+/', $raw) ?: [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $item) {
        $digits = preg_replace('/\D+/', '', (string) $item);
        if ($digits !== '' && strlen($digits) >= 10 && !in_array($digits, $out, true)) {
            $out[] = $digits;
        }
    }

    return $out;
}

/** @return string[] Lista de chat ids do Telegram (inteiros, podem ser negativos). */
function dashglpi_alerting_id_list($raw): array
{
    if (is_scalar($raw)) {
        $raw = preg_split('/[\s,;]+/', (string) $raw) ?: [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $item) {
        $id = trim((string) $item);
        if ($id !== '' && preg_match('/^-?\d+$/', $id) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

function dashglpi_sla_settings_key_is_valid(string $key): bool
{
    return preg_match('/^(TTO|TTR)-P[1-9][0-9]*$/', strtoupper(trim($key))) === 1;
}

function dashglpi_sla_settings_key_compare(string $left, string $right): int
{
    $leftParts = dashglpi_sla_settings_key_parts($left);
    $rightParts = dashglpi_sla_settings_key_parts($right);
    if (!$leftParts || !$rightParts) {
        return strcmp($left, $right);
    }

    $kindOrder = ['TTO' => 0, 'TTR' => 1];
    $kindCompare = ($kindOrder[$leftParts['kind']] ?? 99) <=> ($kindOrder[$rightParts['kind']] ?? 99);
    return $kindCompare !== 0 ? $kindCompare : ($leftParts['number'] <=> $rightParts['number']);
}

function dashglpi_sla_settings_key_parts(string $key): ?array
{
    $key = strtoupper(trim($key));
    if (!preg_match('/^(TTO|TTR)-P([1-9][0-9]*)$/', $key, $matches)) {
        return null;
    }

    return [
        'kind' => $matches[1],
        'number' => (int) $matches[2],
    ];
}

function dashglpi_normalize_time(string $time, string $fallback): string
{
    $time = trim($time);
    if (preg_match('/^\d{1,4}$/', $time)) {
        $time = str_pad($time, 4, '0', STR_PAD_LEFT);
        $hour = min(23, max(0, (int) substr($time, 0, 2)));
        $minute = min(59, max(0, (int) substr($time, 2, 2)));
        return sprintf('%02d:%02d', $hour, $minute);
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        if ($hour <= 23 && $minute <= 59) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return dashglpi_normalize_time(substr($time, 0, 5), $fallback);
    }

    return $fallback;
}

function dashglpi_report_brand(array $settings): array
{
    $appName = dashglpi_normalize_app_name(
        (string) ($settings['app_name'] ?? ''),
        dashglpi_settings_defaults('reports')['app_name']
    );

    return [
        'name' => $appName,
        'source' => 'Central de Atendimento ' . $appName,
        'logo' => dashglpi_normalize_logo_url(
            (string) ($settings['logo_light_url'] ?? $settings['logo_url'] ?? ''),
            dashglpi_settings_defaults('reports')['logo_light_url']
        ),
        'primary' => '#0A2540',
        'accent' => '#00B3A4',
    ];
}

function dashglpi_normalize_app_name(string $name, string $fallback): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return $fallback;
    }

    return substr($name, 0, 80);
}

function dashglpi_normalize_logo_url(string $url, string $fallback): string
{
    $url = trim($url);
    if ($url === '') {
        return $fallback;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    return $fallback;
}

function dashglpi_admin_users(): array
{
    $raw = (string) dashglpi_env('DASHGLPI_ADMIN_USERS', '');
    if (trim($raw) === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $item): string => strtolower(trim($item)),
        explode(',', $raw)
    )));
}

function dashglpi_current_user_is_admin(): bool
{
    $user = dashglpi_current_user();
    if (!$user) {
        return false;
    }

    $login = strtolower((string) ($user['name'] ?? ''));
    $adminUsers = dashglpi_admin_users();
    if ($adminUsers && in_array($login, $adminUsers, true)) {
        return true;
    }

    if ($login === 'admin') {
        return true;
    }

    try {
        $row = dashglpi_fetch_one(
            "SELECT p.id
             FROM glpi_profiles_users pu
             INNER JOIN glpi_profiles p ON p.id = pu.profiles_id
             WHERE pu.users_id = ?
               AND p.name IN ('Super-Admin', 'Admin')
             LIMIT 1",
            [(int) $user['id']]
        );

        return (bool) $row;
    } catch (Throwable) {
        return false;
    }
}

function dashglpi_require_admin(): void
{
    dashglpi_require_auth();

    if (!dashglpi_current_user_is_admin()) {
        if (dashglpi_is_ajax_request()) {
            dashglpi_json(['error' => 'Acesso restrito a administradores.'], 403);
        }

        http_response_code(403);
        echo 'Acesso restrito a administradores.';
        exit;
    }
}

function dashglpi_is_image_document(array $document): bool
{
    return in_array((string) ($document['mime'] ?? ''), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
}

function dashglpi_document_preview_url(int $ticketId, int $documentId, string $itemtypeKey = 'ticket'): string
{
    $typeQuery = ($itemtypeKey !== '' && $itemtypeKey !== 'ticket')
        ? '&itemtype=' . rawurlencode($itemtypeKey)
        : '';

    return '/front/document-preview.php?ticket_id=' . $ticketId . '&document_id=' . $documentId . $typeQuery;
}

/**
 * WHERE de documentos relacionados, parametrizado pelo registry ITIL
 * (PLAN-20260709-019, Fase D). Os literais interpolados vêm exclusivamente de
 * inc/itil_types.php (nunca de input do usuário).
 */
function dashglpi_itil_related_document_where_sql(array $type): string
{
    $itemtype = (string) $type['glpi_itemtype'];
    $taskItemtype = (string) $type['task_itemtype'];
    $taskTable = (string) $type['task_table'];
    $fk = (string) $type['fk'];

    return "(
        (di.itemtype = '$itemtype' AND di.items_id = ?)
        OR (
            di.itemtype = 'ITILFollowup'
            AND di.items_id IN (
                SELECT f.id
                FROM glpi_itilfollowups f
                WHERE f.itemtype = '$itemtype'
                  AND f.items_id = ?
            )
        )
        OR (
            di.itemtype = '$taskItemtype'
            AND di.items_id IN (
                SELECT tt.id
                FROM $taskTable tt
                WHERE tt.$fk = ?
            )
        )
        OR (
            di.itemtype = 'ITILSolution'
            AND di.items_id IN (
                SELECT s.id
                FROM glpi_itilsolutions s
                WHERE s.itemtype = '$itemtype'
                  AND s.items_id = ?
            )
        )
    )";
}

function dashglpi_itil_related_documents(array $type, int $objectId): array
{
    $where = dashglpi_itil_related_document_where_sql($type);

    return dashglpi_fetch_all(
        "SELECT d.id, d.name, d.filename, d.filepath, d.mime, d.comment, d.link,
                di.itemtype AS relation_itemtype,
                di.items_id AS relation_item_id,
                COALESCE(di.date, di.date_creation, di.date_mod, d.date_mod) AS attached_at,
                u.name AS user_name, u.firstname, u.realname
         FROM glpi_documents_items di
         INNER JOIN glpi_documents d ON d.id = di.documents_id
         LEFT JOIN glpi_users u ON u.id = di.users_id
         WHERE d.is_deleted = 0
           AND $where
         ORDER BY attached_at, d.id",
        [$objectId, $objectId, $objectId, $objectId]
    );
}

function dashglpi_itil_related_document(array $type, int $objectId, int $documentId): ?array
{
    $where = dashglpi_itil_related_document_where_sql($type);

    return dashglpi_fetch_one(
        "SELECT d.id, d.name, d.filename, d.filepath, d.mime
         FROM glpi_documents d
         WHERE d.id = ?
           AND d.is_deleted = 0
           AND EXISTS (
               SELECT 1
               FROM glpi_documents_items di
               WHERE di.documents_id = d.id
                 AND $where
           )
         LIMIT 1",
        [$documentId, $objectId, $objectId, $objectId, $objectId]
    );
}

function dashglpi_ticket_related_documents(int $ticketId): array
{
    return dashglpi_itil_related_documents(dashglpi_itil_type('ticket'), $ticketId);
}

function dashglpi_ticket_related_document(int $ticketId, int $documentId): ?array
{
    return dashglpi_itil_related_document(dashglpi_itil_type('ticket'), $ticketId, $documentId);
}

function dashglpi_document_path(?string $filepath): ?string
{
    $filepath = trim((string) $filepath);
    if ($filepath === '' || str_contains($filepath, "\0")) {
        return null;
    }

    $base = rtrim((string) dashglpi_env('GLPI_FILES_DIR', '/var/glpi/files'), "/\\");
    $baseReal = realpath($base);
    if ($baseReal === false) {
        return null;
    }

    $normalized = str_replace('\\', '/', $filepath);
    $segments = array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== '');
    if (in_array('..', $segments, true)) {
        return null;
    }

    $candidates = [];
    if (str_starts_with($normalized, '/')) {
        $candidates[] = $normalized;
    } else {
        $relative = ltrim($normalized, '/');
        $candidates[] = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $candidates[] = $baseReal . DIRECTORY_SEPARATOR . '_uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
            return $real;
        }
    }

    return null;
}

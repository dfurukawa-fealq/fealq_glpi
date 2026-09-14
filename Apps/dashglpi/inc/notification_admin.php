<?php

const DASHGLPI_NOTIFICATION_ITEMTYPES = [
    'Ticket' => 'Chamado',
    'Problem' => 'Problema',
    'Change' => 'Mudança',
    'MailCollector' => 'Coletor de e-mail',
    'User' => 'Usuário',
];

function dashglpi_notification_dataset(): array
{
    $catalog = dashglpi_notification_catalog_data();

    return [
        'summary' => dashglpi_notification_summary(),
        'notifications' => dashglpi_notifications_list($catalog),
        'templates' => dashglpi_notification_templates(),
        'mail_settings' => dashglpi_notification_mail_settings(),
        'delivery' => dashglpi_notification_delivery_status(),
        'entities' => dashglpi_notification_entities(),
        'catalog' => $catalog,
    ];
}

function dashglpi_notification_catalog_data(): array
{
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $response = dashglpi_admin_bridge_request('notification_config.php', ['action' => 'catalog']);
    $catalog = is_array($response['catalog'] ?? null) ? $response['catalog'] : [];
    $catalog['itemtypes'] = is_array($catalog['itemtypes'] ?? null) ? $catalog['itemtypes'] : DASHGLPI_NOTIFICATION_ITEMTYPES;
    $catalog['events_by_itemtype'] = is_array($catalog['events_by_itemtype'] ?? null) ? $catalog['events_by_itemtype'] : [];
    $catalog['targets_by_itemtype'] = is_array($catalog['targets_by_itemtype'] ?? null) ? $catalog['targets_by_itemtype'] : [];
    $catalog['special_events'] = is_array($catalog['special_events'] ?? null) ? $catalog['special_events'] : [];
    $catalog['automation'] = is_array($catalog['automation'] ?? null) ? $catalog['automation'] : [];
    $catalog['oauth'] = is_array($catalog['oauth'] ?? null) ? $catalog['oauth'] : ['providers' => [], 'additional_parameters' => []];
    $catalog['mail_settings'] = is_array($catalog['mail_settings'] ?? null) ? $catalog['mail_settings'] : [];

    return $catalog;
}

function dashglpi_notification_entities(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, completename
         FROM glpi_entities
         ORDER BY completename ASC, name ASC"
    );
}

function dashglpi_notification_templates(): array
{
    return dashglpi_fetch_all(
        "SELECT nt.id, nt.name, nt.itemtype, nt.date_mod,
                tr.id AS translation_id, tr.language, tr.subject,
                tr.content_text, tr.content_html
         FROM glpi_notificationtemplates nt
         LEFT JOIN glpi_notificationtemplatetranslations tr
           ON tr.id = (
                SELECT tr2.id
                FROM glpi_notificationtemplatetranslations tr2
                WHERE tr2.notificationtemplates_id = nt.id
                ORDER BY
                    CASE
                        WHEN tr2.language = 'pt_BR' THEN 0
                        WHEN tr2.language IS NULL OR tr2.language = '' THEN 1
                        ELSE 2
                    END,
                    tr2.id ASC
                LIMIT 1
            )
         ORDER BY nt.name ASC"
    );
}

function dashglpi_notification_mail_settings(): array
{
    $catalog = dashglpi_notification_catalog_data();
    return is_array($catalog['mail_settings'] ?? null) ? $catalog['mail_settings'] : [];
}

function dashglpi_notifications_list(array $catalog): array
{
    $notifications = dashglpi_fetch_all(
        "SELECT n.id, n.name, n.itemtype, n.event, n.entities_id, n.is_recursive, n.is_active,
                n.date_mod, COALESCE(e.completename, 'Entidade raiz') AS entity_name,
                MIN(CASE WHEN nnt.mode = 'mailing' THEN nnt.notificationtemplates_id ELSE NULL END) AS template_id,
                GROUP_CONCAT(DISTINCT nt.name ORDER BY nt.name SEPARATOR ', ') AS template_names
         FROM glpi_notifications n
         LEFT JOIN glpi_entities e ON e.id = n.entities_id
         LEFT JOIN glpi_notifications_notificationtemplates nnt ON nnt.notifications_id = n.id
         LEFT JOIN glpi_notificationtemplates nt ON nt.id = nnt.notificationtemplates_id
         GROUP BY n.id, n.name, n.itemtype, n.event, n.entities_id, n.is_recursive, n.is_active, n.date_mod, e.completename
         ORDER BY n.name ASC"
    );

    if ($notifications === []) {
        return [];
    }

    $targetRows = dashglpi_fetch_all(
        "SELECT notifications_id, type, items_id, is_exclusion
         FROM glpi_notificationtargets
         ORDER BY notifications_id ASC, type ASC, items_id ASC"
    );

    $notificationsById = [];
    foreach ($notifications as $row) {
        $row['template_id'] = max(0, (int) ($row['template_id'] ?? 0));
        $row['recipient_keys'] = [];
        $row['recipient_labels'] = [];
        $row['recipient_summary'] = '-';
        $row['has_exclusions'] = false;
        $notificationsById[(int) $row['id']] = $row;
    }

    foreach ($targetRows as $targetRow) {
        $notificationId = (int) ($targetRow['notifications_id'] ?? 0);
        if (!isset($notificationsById[$notificationId])) {
            continue;
        }

        $key = (string) ((int) ($targetRow['type'] ?? 0)) . '_' . (string) ((int) ($targetRow['items_id'] ?? 0));
        $isExcluded = !empty($targetRow['is_exclusion']);
        $label = dashglpi_notification_target_label(
            $catalog,
            (string) $notificationsById[$notificationId]['itemtype'],
            (string) $notificationsById[$notificationId]['event'],
            $key,
            $isExcluded
        );
        if (!$isExcluded) {
            $notificationsById[$notificationId]['recipient_keys'][] = $key;
        } else {
            $notificationsById[$notificationId]['has_exclusions'] = true;
        }
        $notificationsById[$notificationId]['recipient_labels'][] = $label;
    }

    foreach ($notificationsById as &$row) {
        $uniqueKeys = array_values(array_unique(array_filter(array_map('strval', $row['recipient_keys']))));
        $uniqueLabels = array_values(array_unique(array_filter(array_map('strval', $row['recipient_labels']))));
        $row['recipient_keys'] = $uniqueKeys;
        $row['recipient_labels'] = $uniqueLabels;
        $row['recipient_summary'] = $uniqueLabels !== [] ? implode(', ', $uniqueLabels) : '-';
    }
    unset($row);

    return array_values($notificationsById);
}

function dashglpi_notification_target_label(array $catalog, string $itemtype, string $event, string $key, bool $excluded = false): string
{
    $targets = $catalog['targets_by_itemtype'][$itemtype][$event] ?? [];
    $label = trim((string) ($targets[$key] ?? ''));

    if ($label === '') {
        $label = dashglpi_notification_target_fallback_label($key);
    }

    if ($excluded) {
        return 'Exceto: ' . $label;
    }

    return $label;
}

function dashglpi_notification_target_fallback_label(string $key): string
{
    if (preg_match('/^(\d+)_(\d+)$/', $key, $matches) !== 1) {
        return $key;
    }

    return 'Destino ' . $matches[1] . ':' . $matches[2];
}

function dashglpi_notification_summary(): array
{
    $active = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_notifications WHERE is_active = 1");
    $templates = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_notificationtemplates");
    $queued = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_queuednotifications WHERE mode = 'mailing' AND is_deleted = 0 AND sent_time IS NULL");

    return [
        'active' => (int) ($active['total'] ?? 0),
        'templates' => (int) ($templates['total'] ?? 0),
        'queued' => (int) ($queued['total'] ?? 0),
    ];
}

function dashglpi_notification_delivery_status(): array
{
    $task = dashglpi_fetch_one(
        "SELECT id, itemtype, name, frequency, state, mode, allowmode, lastrun, lastcode
         FROM glpi_crontasks
         WHERE name = 'queuednotification'
         LIMIT 1"
    );

    if (!$task) {
        return [
            'available' => false,
            'pending_count' => 0,
            'delay_seconds' => 0,
            'attention_active' => true,
            'recommendation_ok' => false,
        ];
    }

    $pending = dashglpi_fetch_one(
        "SELECT COUNT(*) AS total, MIN(send_time) AS oldest_send_time
         FROM glpi_queuednotifications
         WHERE mode = 'mailing'
           AND is_deleted = 0
           AND sent_time IS NULL
           AND send_time <= NOW()"
    ) ?? [];

    $lastSent = dashglpi_fetch_one(
        "SELECT MAX(sent_time) AS last_sent_at
         FROM glpi_queuednotifications
         WHERE mode = 'mailing'
           AND is_deleted = 1
           AND sent_time IS NOT NULL"
    ) ?? [];

    $pendingCount = (int) ($pending['total'] ?? 0);
    $oldestSendTime = trim((string) ($pending['oldest_send_time'] ?? ''));
    $delaySeconds = 0;
    if ($pendingCount > 0 && $oldestSendTime !== '') {
        $oldestTimestamp = strtotime($oldestSendTime);
        if ($oldestTimestamp !== false) {
            $delaySeconds = max(0, time() - $oldestTimestamp);
        }
    }

    $mode = (int) ($task['mode'] ?? 0);
    $state = (int) ($task['state'] ?? 0);
    $frequency = (int) ($task['frequency'] ?? 0);
    $attentionActive = $pendingCount > 0 && $delaySeconds > max(120, $frequency * 2);

    return [
        'available' => true,
        'id' => (int) ($task['id'] ?? 0),
        'itemtype' => (string) ($task['itemtype'] ?? ''),
        'name' => (string) ($task['name'] ?? ''),
        'frequency' => $frequency,
        'state' => $state,
        'state_label' => match ($state) {
            0 => 'Desativada',
            1 => 'Ativa',
            2 => 'Em execução',
            default => 'Desconhecido',
        },
        'mode' => $mode,
        'mode_label' => match ($mode) {
            1 => 'GLPI',
            2 => 'CLI',
            default => 'Desconhecido',
        },
        'allowmode' => (int) ($task['allowmode'] ?? 0),
        'lastrun' => (string) ($task['lastrun'] ?? ''),
        'lastcode' => (string) ($task['lastcode'] ?? ''),
        'pending_count' => $pendingCount,
        'oldest_send_time' => $oldestSendTime,
        'delay_seconds' => $delaySeconds,
        'last_sent_at' => (string) ($lastSent['last_sent_at'] ?? ''),
        'attention_active' => $attentionActive,
        'recommendation_ok' => $state === 1 && $mode === 2 && $frequency === 60,
    ];
}

function dashglpi_mailcollectors_list(): array
{
    return dashglpi_fetch_all(
        "SELECT id, name, host, login, is_active, errors, last_collect_date, date_mod,
                filesize_max, collect_only_unread
         FROM glpi_mailcollectors
         ORDER BY name ASC"
    );
}

function dashglpi_mailcollector_summary(): array
{
    $active = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_mailcollectors WHERE is_active = 1");
    $errors = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_mailcollectors WHERE errors > 0");
    $total = dashglpi_fetch_one("SELECT COUNT(*) AS total FROM glpi_mailcollectors");

    return [
        'active' => (int) ($active['total'] ?? 0),
        'errors' => (int) ($errors['total'] ?? 0),
        'total' => (int) ($total['total'] ?? 0),
    ];
}

function dashglpi_notification_payload(array $post): array
{
    $action = (string) ($post['notification_action'] ?? 'save');

    return match ($action) {
        'toggle' => [
            'action' => 'toggle',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'is_active' => !empty($post['is_active']) ? 1 : 0,
        ],
        'clone' => [
            'action' => 'clone',
            'id' => max(0, (int) ($post['id'] ?? 0)),
        ],
        'bulk_disable' => [
            'action' => 'bulk_disable',
            'ids' => dashglpi_notification_id_array($post['ids'] ?? []),
        ],
        'save_mail_settings' => dashglpi_notification_mail_payload($post),
        'save_template_translation' => dashglpi_notification_template_payload($post),
        'send_test_email' => [
            'action' => 'send_test_email',
            'admin_email' => dashglpi_notification_email((string) ($post['admin_email'] ?? ''), true),
        ],
        'fix_followup_template' => [
            'action' => 'fix_followup_template',
            'preset' => dashglpi_notification_text((string) ($post['preset'] ?? 'hafen'), 50),
        ],
        'create_individual_template' => [
            'action' => 'create_individual_template',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'language' => dashglpi_notification_language((string) ($post['language'] ?? 'pt_BR')),
        ],
        'update_queue' => ['action' => 'update_queue'],
        default => dashglpi_notification_save_payload($post),
    };
}

function dashglpi_notification_save_payload(array $post): array
{
    return [
        'action' => 'save',
        'id' => max(0, (int) ($post['id'] ?? 0)),
        'name' => dashglpi_notification_required((string) ($post['name'] ?? ''), 'Informe o nome da notificacao.'),
        'itemtype' => dashglpi_notification_itemtype((string) ($post['itemtype'] ?? '')),
        'event' => dashglpi_notification_text((string) ($post['event'] ?? ''), 120),
        'entities_id' => max(0, (int) ($post['entities_id'] ?? 0)),
        'is_recursive' => !empty($post['is_recursive']) ? 1 : 0,
        'is_active' => !empty($post['is_active']) ? 1 : 0,
        'template_id' => max(0, (int) ($post['template_id'] ?? 0)),
        'recipients' => dashglpi_notification_recipient_keys($post['recipients'] ?? []),
    ];
}

function dashglpi_notification_mail_payload(array $post): array
{
    return [
        'action' => 'save_mail_settings',
        'settings' => [
            'admin_email' => dashglpi_notification_email((string) ($post['admin_email'] ?? ''), true),
            'admin_email_name' => dashglpi_notification_text((string) ($post['admin_email_name'] ?? ''), 255),
            'from_email' => dashglpi_notification_email((string) ($post['from_email'] ?? ''), false),
            'from_email_name' => dashglpi_notification_text((string) ($post['from_email_name'] ?? ''), 255),
            'replyto_email' => dashglpi_notification_email((string) ($post['replyto_email'] ?? ''), false),
            'replyto_email_name' => dashglpi_notification_text((string) ($post['replyto_email_name'] ?? ''), 255),
            'noreply_email' => dashglpi_notification_email((string) ($post['noreply_email'] ?? ''), false),
            'noreply_email_name' => dashglpi_notification_text((string) ($post['noreply_email_name'] ?? ''), 255),
            'attach_ticket_documents_to_mail' => (string) min(2, max(0, (int) ($post['attach_ticket_documents_to_mail'] ?? 0))),
            'attach_documents_to_notifications_for_anonymous' => (string) min(1, max(0, (int) ($post['attach_documents_to_notifications_for_anonymous'] ?? 0))),
            'mailing_signature' => substr((string) ($post['mailing_signature'] ?? ''), 0, 65535),
            'smtp_mode' => dashglpi_notification_smtp_mode((string) ($post['smtp_mode'] ?? '0')),
            'smtp_max_retries' => (string) min(50, max(0, (int) ($post['smtp_max_retries'] ?? 5))),
            'smtp_retry_time' => (string) min(1440, max(0, (int) ($post['smtp_retry_time'] ?? 5))),
            'smtp_check_certificate' => !empty($post['smtp_check_certificate']) ? '1' : '0',
            'smtp_host' => dashglpi_notification_text((string) ($post['smtp_host'] ?? ''), 255),
            'smtp_port' => (string) min(65535, max(0, (int) ($post['smtp_port'] ?? 0))),
            'smtp_username' => dashglpi_notification_text((string) ($post['smtp_username'] ?? ''), 255),
            'smtp_passwd' => (string) ($post['smtp_passwd'] ?? ''),
            'smtp_clear_password' => !empty($post['smtp_clear_password']) ? '1' : '0',
            'smtp_sender' => dashglpi_notification_email((string) ($post['smtp_sender'] ?? ''), false),
            'smtp_oauth_provider' => dashglpi_notification_text((string) ($post['smtp_oauth_provider'] ?? ''), 255),
            'smtp_oauth_client_id' => dashglpi_notification_text((string) ($post['smtp_oauth_client_id'] ?? ''), 2048),
            'smtp_oauth_client_secret' => (string) ($post['smtp_oauth_client_secret'] ?? ''),
            'smtp_oauth_options_json' => dashglpi_notification_oauth_options_json((string) ($post['smtp_oauth_options_json'] ?? '{}')),
        ],
    ];
}

function dashglpi_notification_template_payload(array $post): array
{
    return [
        'action' => 'save_template_translation',
        'template_id' => max(0, (int) ($post['template_id'] ?? 0)),
        'translation_id' => max(0, (int) ($post['translation_id'] ?? 0)),
        'language' => dashglpi_notification_language((string) ($post['language'] ?? 'pt_BR')),
        'subject' => dashglpi_notification_required((string) ($post['subject'] ?? ''), 'Informe o assunto do modelo.'),
        'content_text' => (string) ($post['content_text'] ?? ''),
        'content_html' => (string) ($post['content_html'] ?? ''),
    ];
}

function dashglpi_mailcollector_payload(array $post): array
{
    $action = (string) ($post['collector_action'] ?? 'create');

    if ($action === 'toggle') {
        return [
            'action' => 'toggle',
            'id' => max(0, (int) ($post['id'] ?? 0)),
            'is_active' => !empty($post['is_active']) ? 1 : 0,
        ];
    }

    return [
        'action' => 'create',
        'name' => dashglpi_notification_required((string) ($post['name'] ?? ''), 'Informe o nome do coletor.'),
        'host' => dashglpi_notification_required((string) ($post['host'] ?? ''), 'Informe o host do coletor.'),
        'login' => dashglpi_notification_required((string) ($post['login'] ?? ''), 'Informe o login do coletor.'),
        'password' => (string) ($post['password'] ?? ''),
        'is_active' => !empty($post['is_active']) ? 1 : 0,
        'filesize_max' => min(104857600, max(1024, (int) ($post['filesize_max'] ?? 2097152))),
        'collect_only_unread' => !empty($post['collect_only_unread']) ? 1 : 0,
    ];
}

function dashglpi_notification_required(string $value, string $message): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        throw new RuntimeException($message);
    }

    return substr($value, 0, 255);
}

function dashglpi_notification_text(string $value, int $max): string
{
    return substr(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 0, $max);
}

function dashglpi_notification_email(string $value, bool $required): string
{
    $value = dashglpi_notification_text($value, 255);
    if ($value === '') {
        if ($required) {
            throw new RuntimeException('Informe um e-mail valido.');
        }
        return '';
    }

    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail valido.');
    }

    return $value;
}

function dashglpi_notification_language(string $language): string
{
    $language = dashglpi_notification_text($language, 10);
    if ($language === '') {
        return 'pt_BR';
    }

    if (!preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $language)) {
        throw new RuntimeException('Idioma do modelo invalido.');
    }

    return $language;
}

function dashglpi_notification_itemtype(string $itemtype): string
{
    if (!array_key_exists($itemtype, DASHGLPI_NOTIFICATION_ITEMTYPES)) {
        throw new RuntimeException('Tipo de notificacao invalido.');
    }

    return $itemtype;
}

function dashglpi_notification_smtp_mode(string $value): string
{
    $allowed = ['0', '1', '4'];
    return in_array($value, $allowed, true) ? $value : '0';
}

function dashglpi_notification_oauth_options_json(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '{}';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Configuracao OAuth invalida.');
    }

    $normalized = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($normalized)) {
        throw new RuntimeException('Configuracao OAuth invalida.');
    }

    return $normalized;
}

function dashglpi_notification_recipient_keys(mixed $value): array
{
    $items = is_array($value) ? $value : (preg_split('/[\s,;]+/', (string) $value) ?: []);
    $keys = [];
    foreach ($items as $item) {
        $key = trim((string) $item);
        if ($key === '') {
            continue;
        }
        if (preg_match('/^\d+_\d+$/', $key) !== 1) {
            throw new RuntimeException('Destinatario invalido.');
        }
        $keys[] = $key;
    }

    return array_values(array_unique($keys));
}

function dashglpi_notification_id_array(mixed $value): array
{
    $items = is_array($value) ? $value : (preg_split('/[\s,;]+/', (string) $value) ?: []);
    $ids = [];
    foreach ($items as $item) {
        $id = max(0, (int) $item);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

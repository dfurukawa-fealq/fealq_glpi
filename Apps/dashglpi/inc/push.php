<?php

const DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE = 'glpi_plugin_dashglpi_push_subscriptions';
const DASHGLPI_PUSH_DELIVERIES_TABLE = 'glpi_plugin_dashglpi_push_deliveries';

function dashglpi_push_config(): array
{
    return [
        'enabled' => filter_var(dashglpi_env('DASHGLPI_PUSH_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
        'subject' => trim((string) dashglpi_env('DASHGLPI_PUSH_VAPID_SUBJECT', '')),
        'public_key' => trim((string) dashglpi_env('DASHGLPI_PUSH_VAPID_PUBLIC_KEY', '')),
        'private_key' => trim((string) dashglpi_env('DASHGLPI_PUSH_VAPID_PRIVATE_KEY', '')),
    ];
}

function dashglpi_push_is_configured(): bool
{
    $config = dashglpi_push_config();
    return !empty($config['enabled'])
        && $config['subject'] !== ''
        && $config['public_key'] !== ''
        && $config['private_key'] !== '';
}

function dashglpi_push_public_key(): string
{
    return (string) (dashglpi_push_config()['public_key'] ?? '');
}

function dashglpi_push_ensure_tables(): void
{
    $db = dashglpi_db();
    $db->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            users_id INT UNSIGNED NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            endpoint VARCHAR(2048) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_error TEXT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_mod TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_push_endpoint (endpoint_hash),
            KEY idx_dashglpi_push_user_active (users_id, is_active),
            KEY idx_dashglpi_push_date_mod (date_mod)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_PUSH_DELIVERIES_TABLE . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tickets_id INT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'sent',
            error_message TEXT NULL,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dashglpi_push_delivery (tickets_id, subscription_id),
            KEY idx_dashglpi_push_delivery_ticket (tickets_id),
            KEY idx_dashglpi_push_delivery_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function dashglpi_push_subscription_from_request(array $payload): array
{
    $endpoint = trim((string) ($payload['endpoint'] ?? ''));
    $keys = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
    $p256dh = trim((string) ($keys['p256dh'] ?? ''));
    $auth = trim((string) ($keys['auth'] ?? ''));
    $contentEncoding = trim((string) ($payload['contentEncoding'] ?? 'aes128gcm'));

    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($endpoint), 'https://')) {
        throw new InvalidArgumentException('Endpoint Push inválido.');
    }
    if ($p256dh === '' || $auth === '') {
        throw new InvalidArgumentException('Chaves da subscription Push incompletas.');
    }
    if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
        $contentEncoding = 'aes128gcm';
    }

    return [
        'endpoint' => substr($endpoint, 0, 2048),
        'endpoint_hash' => hash('sha256', $endpoint),
        'p256dh' => substr($p256dh, 0, 255),
        'auth' => substr($auth, 0, 255),
        'content_encoding' => $contentEncoding,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ];
}

function dashglpi_push_save_subscription(int $userId, array $subscription): void
{
    dashglpi_push_ensure_tables();
    $stmt = dashglpi_db()->prepare(
        "INSERT INTO " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . "
            (users_id, endpoint_hash, endpoint, p256dh, auth, content_encoding, user_agent, is_active, last_error)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL)
         ON DUPLICATE KEY UPDATE
            users_id = VALUES(users_id), endpoint = VALUES(endpoint), p256dh = VALUES(p256dh),
            auth = VALUES(auth), content_encoding = VALUES(content_encoding), user_agent = VALUES(user_agent),
            is_active = 1, last_error = NULL"
    );
    $stmt->execute([
        $userId,
        $subscription['endpoint_hash'],
        $subscription['endpoint'],
        $subscription['p256dh'],
        $subscription['auth'],
        $subscription['content_encoding'],
        $subscription['user_agent'],
    ]);
}

function dashglpi_push_remove_subscription(int $userId, string $endpoint): void
{
    dashglpi_push_ensure_tables();
    $stmt = dashglpi_db()->prepare(
        "UPDATE " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . "
            SET is_active = 0, date_mod = CURRENT_TIMESTAMP
          WHERE users_id = ? AND endpoint_hash = ?"
    );
    $stmt->execute([$userId, hash('sha256', trim($endpoint))]);
}

function dashglpi_push_user_can_receive_ticket(int $userId, int $entityId): bool
{
    if ($userId <= 0 || $entityId < 0) {
        return false;
    }

    $user = dashglpi_fetch_one(
        "SELECT id FROM glpi_users WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1",
        [$userId]
    );
    if (!$user) {
        return false;
    }

    $profiles = dashglpi_current_user_profiles($userId);
    if (!$profiles) {
        return false;
    }

    $settings = dashglpi_profile_access_settings();
    $rulesByProfile = dashglpi_profile_access_rules_by_profile($settings['rules'] ?? []);
    $enabledRules = [];
    foreach ($profiles as $profile) {
        $profileId = (int) ($profile['profile_id'] ?? 0);
        $rule = $rulesByProfile[$profileId] ?? null;
        if ($rule && !empty($rule['enabled'])) {
            $enabledRules[$profileId] = $rule;
        }
    }

    if ($enabledRules) {
        uasort($enabledRules, static function (array $left, array $right): int {
            $priority = (int) ($left['priority'] ?? 0) <=> (int) ($right['priority'] ?? 0);
            return $priority !== 0
                ? $priority
                : (int) ($left['profile_id'] ?? 0) <=> (int) ($right['profile_id'] ?? 0);
        });
        $winner = reset($enabledRules);
        $effectiveProfileId = (int) ($winner['profile_id'] ?? 0);
        if (!in_array('tickets', $winner['allowed_pages'] ?? [], true)) {
            return false;
        }
        $effectiveRows = array_values(array_filter(
            $profiles,
            static fn(array $row): bool => (int) ($row['profile_id'] ?? 0) === $effectiveProfileId
        ));
        if (!$effectiveRows || (string) ($effectiveRows[0]['profile_interface'] ?? '') !== 'central') {
            return false;
        }
        return dashglpi_push_profile_has_ticket_scope($effectiveProfileId, $effectiveRows, $entityId);
    }

    $centralProfileIds = [];
    foreach ($profiles as $profile) {
        if ((string) ($profile['profile_interface'] ?? '') === 'central') {
            $centralProfileIds[(int) ($profile['profile_id'] ?? 0)] = true;
        }
    }
    foreach (array_keys($centralProfileIds) as $profileId) {
        $profileRows = array_values(array_filter(
            $profiles,
            static fn(array $row): bool => (int) ($row['profile_id'] ?? 0) === (int) $profileId
        ));
        if (dashglpi_push_profile_has_ticket_scope((int) $profileId, $profileRows, $entityId)) {
            return true;
        }
    }

    return false;
}

function dashglpi_push_profile_has_ticket_scope(int $profileId, array $profileRows, int $entityId): bool
{
    if (!$profileRows || !$profileId) {
        return false;
    }

    $right = dashglpi_fetch_one(
        "SELECT id FROM glpi_profilerights
          WHERE profiles_id = ? AND name = 'ticket' AND (rights & 1) > 0 LIMIT 1",
        [$profileId]
    );
    if (!$right) {
        return false;
    }

    return in_array($entityId, dashglpi_expand_profile_entities($profileRows), true);
}

function dashglpi_push_read_request_payload(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function dashglpi_push_ticket_candidates(int $windowHours = 24): array
{
    $windowHours = max(1, min(168, $windowHours));
    return dashglpi_fetch_all(
        "SELECT t.id, t.name, t.content, t.entities_id, t.date,
                COALESCE(e.completename, e.name, CONCAT('#', t.entities_id)) AS entity_name
           FROM glpi_tickets t
           LEFT JOIN glpi_entities e ON e.id = t.entities_id
          WHERE t.is_deleted = 0
            AND t.date >= DATE_SUB(NOW(), INTERVAL {$windowHours} HOUR)
          ORDER BY t.date ASC, t.id ASC
          LIMIT 500"
    );
}

function dashglpi_push_ticket_subscriptions(int $ticketId): array
{
    return dashglpi_fetch_all(
        "SELECT ps.id AS subscription_id, ps.users_id, ps.endpoint, ps.p256dh, ps.auth,
                ps.content_encoding, ps.endpoint_hash
           FROM " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . " ps
           INNER JOIN glpi_users u ON u.id = ps.users_id
           INNER JOIN glpi_tickets t ON t.id = ? AND t.is_deleted = 0
          WHERE ps.is_active = 1
            AND u.is_active = 1
            AND u.is_deleted = 0
            AND t.date >= ps.date_creation
            AND NOT EXISTS (
                SELECT 1
                  FROM " . DASHGLPI_PUSH_DELIVERIES_TABLE . " pd
                 WHERE pd.tickets_id = ?
                   AND pd.subscription_id = ps.id
                   AND pd.status = 'sent'
            )
          ORDER BY ps.id ASC",
        [$ticketId, $ticketId]
    );
}

function dashglpi_push_payload_for_ticket(array $ticket): string
{
    $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
        strip_tags((string) ($ticket['content'] ?? '')),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    )) ?? '');
    $description = function_exists('mb_substr')
        ? mb_substr($description, 0, 120, 'UTF-8')
        : substr($description, 0, 120);
    $title = trim((string) ($ticket['name'] ?? ''));
    if ($title === '') {
        $title = 'Novo chamado';
    }

    $baseUrl = rtrim((string) dashglpi_env('DASHGLPI_PUBLIC_URL', ''), '/');
    $url = ($baseUrl !== '' ? $baseUrl : '') . '/front/dashboard.php?ticket_id='
        . (int) ($ticket['id'] ?? 0) . '#tickets';

    return json_encode([
        'title' => 'Novo chamado #' . (int) ($ticket['id'] ?? 0),
        'body' => $title . ($description !== '' ? ' — ' . $description : ''),
        'url' => $url,
        'tag' => 'dashglpi-ticket-' . (int) ($ticket['id'] ?? 0),
        'icon' => ($baseUrl !== '' ? $baseUrl : '') . '/pwa-icons/icon-192.svg',
        'badge' => ($baseUrl !== '' ? $baseUrl : '') . '/pwa-icons/icon-192.svg',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function dashglpi_push_mark_sent(int $ticketId, int $subscriptionId): void
{
    $stmt = dashglpi_db()->prepare(
        "INSERT IGNORE INTO " . DASHGLPI_PUSH_DELIVERIES_TABLE . "
            (tickets_id, subscription_id, status, sent_at)
         VALUES (?, ?, 'sent', CURRENT_TIMESTAMP)"
    );
    $stmt->execute([$ticketId, $subscriptionId]);
}

function dashglpi_push_mark_failure(int $subscriptionId, string $reason, bool $permanent = false): void
{
    $db = dashglpi_db();
    $stmt = $db->prepare(
        "UPDATE " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . "
            SET is_active = ?, last_error = ?, date_mod = CURRENT_TIMESTAMP
          WHERE id = ?"
    );
    $stmt->execute([$permanent ? 0 : 1, substr($reason, 0, 1000), $subscriptionId]);
}

/**
 * Envia Push de chamados recentes. O intervalo é deliberadamente maior que o
 * ciclo do worker: a tabela de entregas garante idempotência mesmo após restart.
 *
 * @return array{scanned:int,dispatched:int,errors:string[]}
 */
function dashglpi_push_worker_run(bool $dryRun = false): array
{
    dashglpi_push_ensure_tables();
    $result = ['scanned' => 0, 'dispatched' => 0, 'errors' => []];
    if (!dashglpi_push_is_configured()) {
        return $result;
    }

    $autoload = __DIR__ . '/../php-vendor/autoload.php';
    if (!is_file($autoload)) {
        $result['errors'][] = 'Dependência Web Push ausente no container.';
        return $result;
    }
    require_once $autoload;

    $tickets = dashglpi_push_ticket_candidates();
    $eligibilityCache = [];
    $config = dashglpi_push_config();
    foreach ($tickets as $ticket) {
        $ticketId = (int) ($ticket['id'] ?? 0);
        $entityId = (int) ($ticket['entities_id'] ?? 0);
        if ($ticketId <= 0) {
            continue;
        }
        $subscriptions = dashglpi_push_ticket_subscriptions($ticketId);
        foreach ($subscriptions as $subscription) {
            $result['scanned']++;
            $userId = (int) ($subscription['users_id'] ?? 0);
            $eligibilityKey = $userId . ':' . $entityId;
            if (!array_key_exists($eligibilityKey, $eligibilityCache)) {
                $eligibilityCache[$eligibilityKey] = dashglpi_push_user_can_receive_ticket($userId, $entityId);
            }
            if (!$eligibilityCache[$eligibilityKey]) {
                continue;
            }
            if ($dryRun) {
                $result['dispatched']++;
                continue;
            }

            try {
                $webPush = new \Minishlink\WebPush\WebPush([
                    'VAPID' => [
                        'subject' => $config['subject'],
                        'publicKey' => $config['public_key'],
                        'privateKey' => $config['private_key'],
                    ],
                ]);
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $subscription['endpoint'],
                        'publicKey' => $subscription['p256dh'],
                        'authToken' => $subscription['auth'],
                        'contentEncoding' => $subscription['content_encoding'],
                    ]),
                    dashglpi_push_payload_for_ticket($ticket)
                );
                foreach ($webPush->flush() as $report) {
                    if ($report->isSuccess()) {
                        dashglpi_push_mark_sent($ticketId, (int) $subscription['subscription_id']);
                        $result['dispatched']++;
                        continue;
                    }

                    $reason = (string) $report->getReason();
                    $statusCode = 0;
                    if (method_exists($report, 'getResponse') && $report->getResponse()) {
                        $statusCode = (int) $report->getResponse()->getStatusCode();
                    }
                    dashglpi_push_mark_failure(
                        (int) $subscription['subscription_id'],
                        $reason,
                        in_array($statusCode, [404, 410], true)
                    );
                    $result['errors'][] = 'Endpoint Push rejeitado' . ($statusCode ? ' (' . $statusCode . ')' : '') . '.';
                }
            } catch (Throwable $e) {
                dashglpi_push_mark_failure((int) $subscription['subscription_id'], $e->getMessage());
                $result['errors'][] = 'Chamado #' . $ticketId . ': falha ao enfileirar Push.';
            }
        }
    }

    return $result;
}

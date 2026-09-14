<?php

declare(strict_types=1);

require_once '/var/www/html/inc/bootstrap.php';

function followup_config_read_payload(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo de payload nao encontrado: ' . $path);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Falha ao ler o payload JSON.');
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Payload JSON invalido.');
    }

    return $decoded;
}

function followup_config_string(array $payload, string $key, string $default = ''): string
{
    return trim((string) ($payload[$key] ?? $default));
}

function followup_config_string_array(array $payload, string $key, array $default = []): array
{
    $values = $payload[$key] ?? $default;
    if (!is_array($values)) {
        $values = $default;
    }

    $items = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string) $value),
        $values
    ), static fn(string $value): bool => $value !== '')));

    return $items;
}

function followup_config_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function followup_config_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function followup_config_insert_template(PDO $pdo, string $name, string $itemtype): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO glpi_notificationtemplates (name, itemtype, comment)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $itemtype,
        'Criado pelo script de configuracao de follow-up do DashGLPI.',
    ]);

    return (int) $pdo->lastInsertId();
}

function followup_config_insert_translation(PDO $pdo, int $templateId, string $language, string $subject, string $contentText, string $contentHtml): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO glpi_notificationtemplatetranslations
            (notificationtemplates_id, language, subject, content_text, content_html)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$templateId, $language, $subject, $contentText, $contentHtml]);

    return (int) $pdo->lastInsertId();
}

function followup_config_update_translation(PDO $pdo, int $translationId, int $templateId, string $language, string $subject, string $contentText, string $contentHtml): void
{
    $stmt = $pdo->prepare(
        'UPDATE glpi_notificationtemplatetranslations
         SET notificationtemplates_id = ?,
             language = ?,
             subject = ?,
             content_text = ?,
             content_html = ?
         WHERE id = ?'
    );
    $stmt->execute([$templateId, $language, $subject, $contentText, $contentHtml, $translationId]);
}

function followup_config_bind_notifications(PDO $pdo, array $notificationIds, int $templateId): void
{
    $deleteStmt = $pdo->prepare(
        "DELETE FROM glpi_notifications_notificationtemplates
         WHERE notifications_id = ?
           AND mode = 'mailing'"
    );
    $insertStmt = $pdo->prepare(
        "INSERT INTO glpi_notifications_notificationtemplates
            (notifications_id, notificationtemplates_id, mode)
         VALUES (?, ?, 'mailing')"
    );

    foreach ($notificationIds as $notificationId) {
        $deleteStmt->execute([(int) $notificationId]);
        $insertStmt->execute([(int) $notificationId, $templateId]);
    }
}

function followup_config_find_notifications(PDO $pdo, string $itemtype, array $events, array $notificationNames): array
{
    $eventPlaceholders = implode(', ', array_fill(0, count($events), '?'));

    $baseSql = "
        SELECT n.id,
               n.name,
               n.event,
               n.is_active,
               MIN(nt.id) AS current_template_id,
               GROUP_CONCAT(DISTINCT nt.name ORDER BY nt.name SEPARATOR ', ') AS current_template_names
        FROM glpi_notifications n
        LEFT JOIN glpi_notifications_notificationtemplates nnt
               ON nnt.notifications_id = n.id
              AND nnt.mode = 'mailing'
        LEFT JOIN glpi_notificationtemplates nt
               ON nt.id = nnt.notificationtemplates_id
        WHERE n.itemtype = ?
          AND n.event IN ($eventPlaceholders)
    ";

    $params = array_merge([$itemtype], $events);

    if ($notificationNames !== []) {
        $namePlaceholders = implode(', ', array_fill(0, count($notificationNames), '?'));
        $sql = $baseSql . " AND n.name IN ($namePlaceholders)
            GROUP BY n.id, n.name, n.event, n.is_active
            ORDER BY n.id ASC";

        $rows = followup_config_fetch_all($pdo, $sql, array_merge($params, $notificationNames));
        if ($rows !== []) {
            return ['rows' => $rows, 'used_name_filter' => true];
        }
    }

    $sql = $baseSql . '
        GROUP BY n.id, n.name, n.event, n.is_active
        ORDER BY n.id ASC';

    return [
        'rows' => followup_config_fetch_all($pdo, $sql, $params),
        'used_name_filter' => false,
    ];
}

function followup_config_assert_template_content(string $contentHtml, string $contentText): void
{
    foreach ([$contentHtml, $contentText] as $content) {
        if (!str_contains($content, '##FOREACH LAST 1 followups##')) {
            throw new RuntimeException('O template informado nao usa ##FOREACH LAST 1 followups## para o ultimo acompanhamento.');
        }

        if (!str_contains($content, '##ENDFOREACHfollowups##')) {
            throw new RuntimeException('O template informado nao fecha ##ENDFOREACHfollowups##.');
        }

        if (!str_contains($content, '##followup.description##')) {
            throw new RuntimeException('O template informado nao referencia ##followup.description##.');
        }
    }
}

function followup_config_output(array $payload): void
{
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
    ) . PHP_EOL;
}

try {
    if (($argc ?? 0) < 2) {
        throw new RuntimeException('Uso: php configure_followup_notification.php /tmp/payload.json');
    }

    $payload = followup_config_read_payload((string) $argv[1]);

    $itemtype = followup_config_string($payload, 'itemtype', 'Ticket');
    $templateName = followup_config_string($payload, 'template_name');
    $sourceTemplateName = followup_config_string($payload, 'source_template_name', 'Tickets');
    $language = followup_config_string($payload, 'language', 'pt_BR');
    $subject = (string) ($payload['subject'] ?? '##ticket.title## - ##ticket.action##');
    $contentHtml = (string) ($payload['content_html'] ?? '');
    $contentText = (string) ($payload['content_text'] ?? '');
    $events = followup_config_string_array($payload, 'events', ['add_followup', 'update_followup']);
    $notificationNames = followup_config_string_array($payload, 'notification_names', ['Add Followup', 'Update Followup']);
    $dryRun = !empty($payload['dry_run']);

    if ($templateName === '') {
        throw new RuntimeException('Informe o nome do template de follow-up.');
    }
    if ($language === '') {
        throw new RuntimeException('Informe o idioma da traducao.');
    }
    if ($subject === '') {
        throw new RuntimeException('Informe o assunto do template.');
    }
    if ($contentHtml === '') {
        throw new RuntimeException('Informe o HTML do template.');
    }
    if ($contentText === '') {
        throw new RuntimeException('Informe o conteudo texto do template.');
    }
    if ($events === []) {
        throw new RuntimeException('Informe ao menos um evento de follow-up.');
    }

    followup_config_assert_template_content($contentHtml, $contentText);

    $pdo = dashglpi_db();
    $pdo->beginTransaction();

    $sourceTemplate = null;
    if ($sourceTemplateName !== '') {
        $sourceTemplate = followup_config_fetch_one(
            $pdo,
            'SELECT id, name, itemtype
             FROM glpi_notificationtemplates
             WHERE itemtype = ?
               AND name = ?
             LIMIT 1',
            [$itemtype, $sourceTemplateName]
        );
    }

    $targetTemplate = followup_config_fetch_one(
        $pdo,
        'SELECT id, name, itemtype
         FROM glpi_notificationtemplates
         WHERE itemtype = ?
           AND name = ?
         LIMIT 1',
        [$itemtype, $templateName]
    );

    $templateCreated = false;
    $templateId = 0;
    if ($targetTemplate) {
        $templateId = (int) ($targetTemplate['id'] ?? 0);
    } else {
        $templateId = followup_config_insert_template($pdo, $templateName, $itemtype);
        $templateCreated = true;
    }

    if ($templateId <= 0) {
        throw new RuntimeException('Falha ao resolver o template de follow-up.');
    }

    $existingTranslation = followup_config_fetch_one(
        $pdo,
        'SELECT id
         FROM glpi_notificationtemplatetranslations
         WHERE notificationtemplates_id = ?
           AND language = ?
         ORDER BY id ASC
         LIMIT 1',
        [$templateId, $language]
    );

    $translationCreated = false;
    $translationId = 0;
    if ($existingTranslation) {
        $translationId = (int) ($existingTranslation['id'] ?? 0);
        followup_config_update_translation($pdo, $translationId, $templateId, $language, $subject, $contentText, $contentHtml);
    } else {
        $translationId = followup_config_insert_translation($pdo, $templateId, $language, $subject, $contentText, $contentHtml);
        $translationCreated = true;
    }

    $notificationLookup = followup_config_find_notifications($pdo, $itemtype, $events, $notificationNames);
    $notifications = is_array($notificationLookup['rows'] ?? null) ? $notificationLookup['rows'] : [];
    $usedNameFilter = !empty($notificationLookup['used_name_filter']);

    if ($notifications === []) {
        throw new RuntimeException('Nenhuma notificacao Ticket de follow-up foi encontrada para os eventos informados.');
    }

    $notificationIds = array_map(
        static fn(array $row): int => (int) ($row['id'] ?? 0),
        $notifications
    );
    followup_config_bind_notifications($pdo, $notificationIds, $templateId);

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    followup_config_output([
        'ok' => true,
        'dry_run' => $dryRun,
        'template' => [
            'id' => $templateId,
            'name' => $templateName,
            'itemtype' => $itemtype,
            'created' => $templateCreated,
            'translation_id' => $translationId,
            'translation_created' => $translationCreated,
            'language' => $language,
            'subject' => $subject,
        ],
        'source_template' => $sourceTemplate,
        'notifications' => array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'is_active' => (int) ($row['is_active'] ?? 0),
                'previous_template_id' => max(0, (int) ($row['current_template_id'] ?? 0)),
                'previous_template_names' => (string) ($row['current_template_names'] ?? ''),
                'new_template_id' => $templateId,
                'new_template_name' => $templateName,
            ],
            $notifications
        ),
        'matched_by' => [
            'events' => $events,
            'notification_names' => $notificationNames,
            'used_name_filter' => $usedNameFilter,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    followup_config_output([
        'ok' => false,
        'error' => trim($e->getMessage()) !== '' ? trim($e->getMessage()) : get_class($e),
    ]);
    exit(1);
}

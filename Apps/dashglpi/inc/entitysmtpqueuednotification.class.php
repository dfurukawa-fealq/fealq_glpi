<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginDashglpiEntitysmtpQueuednotification extends CommonDBTM
{
    public static $rightname = 'config';

    private const CONFIG_TABLE = 'glpi_plugin_dashglpi_entitysmtp_configs';
    private const LOG_TABLE = 'glpi_plugin_dashglpi_entitysmtp_logs';
    private const SETTINGS_TABLE = 'glpi_plugin_dashglpi_settings';
    private const OWNER_NAMESPACE = 'entitysmtp_owner';
    private const TASK_NAME = 'dashglpi_entitysmtp_queuednotification';
    private const NATIVE_TASK_NAME = 'queuednotification';
    private const LOCK_NAME = 'dashglpi_entitysmtp_queue';
    private const OWNER_LOCK_NAME = 'dashglpi_entitysmtp_owner';

    public static function getTypeName($nb = 0)
    {
        return 'DashGLPI SMTP por entidade';
    }

    public static function install(): void
    {
        self::ensureSchema();

        if (!class_exists(CronTask::class)) {
            return;
        }

        CronTask::register(
            self::class,
            self::TASK_NAME,
            MINUTE_TIMESTAMP,
            [
                'state' => CronTask::STATE_DISABLE,
                'mode' => CronTask::MODE_EXTERNAL,
                'param' => 20,
                'comment' => 'DashGLPI: envia a fila de notificacoes usando SMTP por entidade quando configurado.',
            ]
        );
    }

    public static function cronInfo($name): array
    {
        return match ($name) {
            self::TASK_NAME => [
                'description' => 'DashGLPI: envia notificacoes com SMTP especifico por entidade',
                'parameter' => 'Quantidade maxima de notificacoes por lote',
            ],
            default => [],
        };
    }

    public static function crondashglpi_entitysmtp_queuednotification(?CronTask $task = null): int
    {
        if (!Notification_NotificationTemplate::hasActiveMode()) {
            return 0;
        }

        self::ensureSchema();

        if (!self::acquireQueueLock()) {
            Toolbox::logInFile('cron', "[DashGLPI entity SMTP] fila ja esta em processamento.\n");
            return 0;
        }

        try {
            $memoryLimit = (int) Toolbox::getMemoryLimit();
            if ($memoryLimit > 0 && $memoryLimit < (512 * 1024 * 1024)) {
                Toolbox::safeIniSet('memory_limit', '512M');
            }

            $sendTime = date('Y-m-d H:i:s', strtotime('+1 minutes'));
            $limit = max(1, (int) ($task?->fields['param'] ?? 20));
            $pendings = QueuedNotification::getPendings($sendTime, $limit);
            $processed = 0;
            $cronStatus = 0;

            foreach ($pendings as $mode => $rows) {
                if ($mode === Notification_NotificationTemplate::MODE_MAIL) {
                    foreach ($rows as $row) {
                        $result = self::sendMailingRow($row);
                        if ($result > 0) {
                            $processed += $result;
                            $cronStatus = 1;
                        }
                    }
                    continue;
                }

                $result = self::sendNativeMode((string) $mode, $rows);
                if ($result !== false) {
                    $processed += (int) $result;
                    $cronStatus = 1;
                }
            }

            if ($task !== null && $processed > 0) {
                $task->addVolume($processed);
            }

            return $cronStatus;
        } finally {
            self::releaseQueueLock();
        }
    }

    public static function load(array $payload): array
    {
        self::ensureSchema();
        $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
        $entity = self::findEntity($entityId);
        $state = self::entitySmtpState($entity);

        return [
            'entitysmtp' => $state,
            'smtp' => $state['smtp'],
            'effective_smtp' => $state['effective_smtp'],
            'owner' => self::ownerStatus(),
            'recent_logs' => self::recentLogs($entityId),
        ];
    }

    public static function status(): array
    {
        self::ensureSchema();

        return [
            'owner' => self::ownerStatus(),
            'summary' => self::summary(),
        ];
    }

    public static function saveEntitySmtp(array $payload): array
    {
        global $DB;

        self::ensureSchema();
        $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
        $entity = self::findEntity($entityId);
        $identity = self::identityInput($payload);
        $smtp = self::smtpInput($payload, $entityId);

        $entityItem = new Entity();
        if (!$entityItem->getFromDB($entityId)) {
            throw new RuntimeException('Entidade nao encontrada.');
        }

        $entityUpdate = ['id' => $entityId] + $identity;
        if (!$entityItem->update($entityUpdate)) {
            throw new RuntimeException('Falha ao salvar identidade de e-mail da entidade.');
        }

        $existing = self::configRow($entityId);
        $password = (string) ($payload['smtp_passwd'] ?? '');
        if ($password !== '') {
            $smtp['smtp_passwd'] = (new GLPIKey())->encrypt($password);
        } elseif ($existing && (string) ($existing['smtp_passwd'] ?? '') !== '') {
            $smtp['smtp_passwd'] = (string) $existing['smtp_passwd'];
        } else {
            $smtp['smtp_passwd'] = '';
        }

        if ((int) $smtp['is_active'] === 1 && $smtp['smtp_username'] !== '' && $smtp['smtp_passwd'] === '') {
            throw new RuntimeException('Informe a senha SMTP para a entidade.');
        }

        if ($existing) {
            if (!$DB->update(self::CONFIG_TABLE, $smtp, ['entities_id' => $entityId])) {
                throw new RuntimeException('Falha ao atualizar SMTP da entidade.');
            }
        } else {
            $smtp['entities_id'] = $entityId;
            if (!$DB->insert(self::CONFIG_TABLE, $smtp)) {
                throw new RuntimeException('Falha ao criar SMTP da entidade.');
            }
        }

        $updated = self::findEntity($entityId);
        $state = self::entitySmtpState($updated);

        return [
            'entitysmtp' => $state,
            'smtp' => $state['smtp'],
            'effective_smtp' => $state['effective_smtp'],
            'owner' => self::ownerStatus(),
            'summary' => self::summary(),
        ];
    }

    public static function testEntitySmtp(array $payload): array
    {
        self::ensureSchema();
        $entityId = max(0, (int) ($payload['entities_id'] ?? 0));
        $entity = self::findEntity($entityId);
        $state = self::entitySmtpState($entity);
        $recipient = trim((string) ($state['identity']['admin_email'] ?? ''));
        $recipientName = trim((string) ($state['identity']['admin_email_name'] ?? ''));

        if ($recipient === '' || !NotificationMailing::isUserAddressValid($recipient)) {
            throw new RuntimeException('Informe um e-mail de administrador valido para testar.');
        }

        $effective = self::effectiveConfig((int) ($entity['id'] ?? 0));
        $config = is_array($effective['config'] ?? null) ? $effective['config'] : null;
        if ($config === null) {
            throw new RuntimeException('A entidade nao possui SMTP proprio ou herdado ativo para testar.');
        }

        $source = is_array($effective['source_entity'] ?? null) ? $effective['source_entity'] : [];
        $origin = (string) ($effective['origin'] ?? 'entity');
        $sourceEntityId = (int) ($source['id'] ?? $entityId);
        $sourceEntityName = (string) ($source['completename'] ?? $source['name'] ?? self::entityLabel($entity));
        $from = self::senderForEntity((int) ($entity['id'] ?? 0), $config);
        $mailer = self::mailerForConfig($config, $from['email']);
        $mail = $mailer->getEmail();
        $mail->from(new Address($from['email'], $from['name']));
        $mail->to(new Address($recipient, $recipientName));
        $mail->subject('[DashGLPI] Teste SMTP da entidade');
        $mail->text("Este e-mail confirma o envio SMTP especifico da entidade pelo DashGLPI.\n\nEntidade: " . self::entityLabel($entity));

        if (!$mailer->send()) {
            self::logPluginDelivery(0, $entityId, $sourceEntityId, $sourceEntityName, $origin, (string) ($config['host'] ?? ''), 'error', $mailer->getError());
            throw new RuntimeException($mailer->getError() ?: 'Falha ao enviar e-mail de teste.');
        }

        self::logPluginDelivery(0, $entityId, $sourceEntityId, $sourceEntityName, $origin, (string) ($config['host'] ?? ''), 'sent', '');
        $state = self::entitySmtpState(self::findEntity($entityId));

        return [
            'test_email' => ['status' => 'sent'],
            'entitysmtp' => $state,
            'smtp' => $state['smtp'],
            'effective_smtp' => $state['effective_smtp'],
            'recent_logs' => self::recentLogs($entityId),
        ];
    }

    public static function activateOwner(): array
    {
        global $DB;

        self::ensureSchema();
        if (!self::acquireOwnerLock()) {
            throw new RuntimeException('Outra alteracao de dono da fila esta em andamento.');
        }

        $native = self::crontaskByName(self::NATIVE_TASK_NAME);
        $dash = self::ensureDashCrontask();

        $inTransaction = false;
        try {
            if (!$native['available']) {
                throw new RuntimeException('A tarefa queuednotification nao foi encontrada.');
            }
            if ((int) ($native['state'] ?? 0) === CronTask::STATE_RUNNING || (int) ($dash['state'] ?? 0) === CronTask::STATE_RUNNING) {
                throw new RuntimeException('Nao e possivel trocar o dono enquanto uma tarefa de fila esta em execucao.');
            }

            $DB->beginTransaction();
            $inTransaction = true;

            $owner = self::ownerSettings(false);
            $owner['snapshot'] = self::crontaskSnapshotForRestore($native);
            $owner['active'] = true;
            $owner['activated_at'] = date('Y-m-d H:i:s');
            self::saveOwnerSettings($owner, false);

            if (!$DB->update('glpi_crontasks', ['state' => CronTask::STATE_DISABLE], ['name' => self::NATIVE_TASK_NAME])) {
                throw new RuntimeException('Falha ao pausar queuednotification.');
            }
            if (!$DB->update(
                'glpi_crontasks',
                [
                    'state' => CronTask::STATE_WAITING,
                    'mode' => CronTask::MODE_EXTERNAL,
                    'frequency' => MINUTE_TIMESTAMP,
                    'param' => max(1, (int) ($native['param'] ?? 20)),
                ],
                ['name' => self::TASK_NAME]
            )) {
                throw new RuntimeException('Falha ao ativar dashglpi_entitysmtp_queuednotification.');
            }

            $DB->commit();
            $inTransaction = false;
        } catch (Throwable $e) {
            if ($inTransaction) {
                $DB->rollBack();
            }
            throw $e;
        } finally {
            self::releaseOwnerLock();
        }

        return [
            'owner' => self::ownerStatus(),
            'summary' => self::summary(),
        ];
    }

    public static function restoreGlpiOwner(): array
    {
        global $DB;

        self::ensureSchema();
        if (!self::acquireOwnerLock()) {
            throw new RuntimeException('Outra alteracao de dono da fila esta em andamento.');
        }

        $native = self::crontaskByName(self::NATIVE_TASK_NAME);
        $dash = self::crontaskByName(self::TASK_NAME);
        $inTransaction = false;
        try {
            if ((int) ($native['state'] ?? 0) === CronTask::STATE_RUNNING || (int) ($dash['state'] ?? 0) === CronTask::STATE_RUNNING) {
                throw new RuntimeException('Nao e possivel devolver a fila enquanto uma tarefa esta em execucao.');
            }

            $DB->beginTransaction();
            $inTransaction = true;

            $owner = self::ownerSettings(false);
            $snapshot = is_array($owner['snapshot'] ?? null) ? $owner['snapshot'] : [];
            $restore = $snapshot ?: [
                'state' => CronTask::STATE_WAITING,
                'mode' => CronTask::MODE_EXTERNAL,
                'allowmode' => CronTask::MODE_INTERNAL | CronTask::MODE_EXTERNAL,
                'frequency' => MINUTE_TIMESTAMP,
                'param' => 20,
                'hourmin' => 0,
                'hourmax' => 24,
                'logs_lifetime' => 30,
            ];

            if (!$DB->update('glpi_crontasks', self::crontaskRestoreFields($restore), ['name' => self::NATIVE_TASK_NAME])) {
                throw new RuntimeException('Falha ao restaurar queuednotification.');
            }
            if ($dash['available'] && !$DB->update('glpi_crontasks', ['state' => CronTask::STATE_DISABLE], ['name' => self::TASK_NAME])) {
                throw new RuntimeException('Falha ao pausar dashglpi_entitysmtp_queuednotification.');
            }

            $owner['active'] = false;
            $owner['restored_at'] = date('Y-m-d H:i:s');
            self::saveOwnerSettings($owner, false);

            $DB->commit();
            $inTransaction = false;
        } catch (Throwable $e) {
            if ($inTransaction) {
                $DB->rollBack();
            }
            throw $e;
        } finally {
            self::releaseOwnerLock();
        }

        return [
            'owner' => self::ownerStatus(),
            'summary' => self::summary(),
        ];
    }

    public static function ownerStatus(): array
    {
        self::ensureSchema();
        $settings = self::ownerSettings();
        $native = self::crontaskByName(self::NATIVE_TASK_NAME);
        $dash = self::ensureDashCrontask();
        $nativeActive = $native['available'] && (int) ($native['state'] ?? 0) === CronTask::STATE_WAITING;
        $dashActive = $dash['available'] && (int) ($dash['state'] ?? 0) === CronTask::STATE_WAITING;

        return [
            'owner' => $dashActive ? 'dash' : 'glpi',
            'active' => $dashActive,
            'conflict' => $dashActive && $nativeActive,
            'snapshot_available' => is_array($settings['snapshot'] ?? null) && $settings['snapshot'] !== [],
            'activated_at' => (string) ($settings['activated_at'] ?? ''),
            'restored_at' => (string) ($settings['restored_at'] ?? ''),
            'native_task' => $native,
            'dash_task' => $dash,
        ];
    }

    public static function summary(): array
    {
        global $DB;

        self::ensureSchema();
        $result = $DB->doQuery(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active
             FROM " . self::CONFIG_TABLE
        );
        $row = $DB->fetchArray($result) ?: [];

        return [
            'configured_entities' => (int) ($row['total'] ?? 0),
            'active_entities' => (int) ($row['active'] ?? 0),
        ];
    }

    public static function ensureSchema(): void
    {
        global $DB;

        if (!isset($DB)) {
            return;
        }

        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS " . self::CONFIG_TABLE . " (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entities_id INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                host VARCHAR(255) NOT NULL DEFAULT '',
                port INT UNSIGNED NOT NULL DEFAULT 587,
                encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
                smtp_username VARCHAR(255) NOT NULL DEFAULT '',
                smtp_passwd TEXT NULL,
                smtp_check_certificate TINYINT(1) NOT NULL DEFAULT 1,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dashglpi_entitysmtp_entity (entities_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS " . self::LOG_TABLE . " (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                queuednotifications_id INT UNSIGNED NOT NULL DEFAULT 0,
                entities_id INT UNSIGNED NOT NULL DEFAULT 0,
                smtp_entities_id INT UNSIGNED NOT NULL DEFAULT 0,
                smtp_entity_name VARCHAR(255) NOT NULL DEFAULT '',
                mode VARCHAR(50) NOT NULL DEFAULT 'mailing',
                origin VARCHAR(20) NOT NULL DEFAULT 'global',
                smtp_host VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT '',
                error_message TEXT NULL,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_dashglpi_entitysmtp_logs_queue (queuednotifications_id),
                KEY idx_dashglpi_entitysmtp_logs_entity (entities_id),
                KEY idx_dashglpi_entitysmtp_logs_smtp_entity (smtp_entities_id),
                KEY idx_dashglpi_entitysmtp_logs_date (date_creation)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS " . self::SETTINGS_TABLE . " (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                namespace VARCHAR(80) NOT NULL,
                data JSON NOT NULL,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dashglpi_settings_namespace (namespace)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::ensureSchemaUpgrades();
    }

    private static function ensureSchemaUpgrades(): void
    {
        global $DB;

        if (!$DB->fieldExists(self::LOG_TABLE, 'smtp_entities_id', false)) {
            $DB->doQuery(
                "ALTER TABLE " . self::LOG_TABLE . "
                 ADD smtp_entities_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER entities_id"
            );
        }

        if (!$DB->fieldExists(self::LOG_TABLE, 'smtp_entity_name', false)) {
            $DB->doQuery(
                "ALTER TABLE " . self::LOG_TABLE . "
                 ADD smtp_entity_name VARCHAR(255) NOT NULL DEFAULT '' AFTER smtp_entities_id"
            );
        }
    }

    private static function sendMailingRow(array $row): int
    {
        $entityId = max(0, (int) ($row['entities_id'] ?? 0));
        $effective = self::effectiveConfig($entityId);
        $config = is_array($effective['config'] ?? null) ? $effective['config'] : null;

        if (!is_array($config)) {
            $result = NotificationEventMailing::send([$row]);
            self::logPluginDelivery((int) ($row['id'] ?? 0), $entityId, 0, 'SMTP global do GLPI', 'global', '', $result > 0 ? 'sent' : 'error', $result > 0 ? '' : 'Falha no envio global; consulte mail-error.log.');
            return (int) $result;
        }

        $source = is_array($effective['source_entity'] ?? null) ? $effective['source_entity'] : [];
        $origin = (string) ($effective['origin'] ?? 'entity');
        $sourceEntityId = (int) ($source['id'] ?? $entityId);
        $sourceEntityName = (string) ($source['completename'] ?? $source['name'] ?? '');
        $host = (string) ($config['host'] ?? '');
        try {
            $sender = self::senderForEntity($entityId, $config);
            $row = self::withEntityMailIdentity($row, $entityId, $sender);
            $mailer = self::mailerForConfig($config, $sender['email']);
            NotificationEventMailing::setMailer($mailer);
            $result = NotificationEventMailing::send([$row]);
            $error = $mailer->getError();
        } catch (Throwable $e) {
            NotificationEventMailing::setMailer(null);
            self::markDeliveryFailure($row, $e->getMessage());
            self::logPluginDelivery((int) ($row['id'] ?? 0), $entityId, $sourceEntityId, $sourceEntityName, $origin, $host, 'error', $e->getMessage());
            return 0;
        } finally {
            NotificationEventMailing::setMailer(null);
        }

        self::logPluginDelivery(
            (int) ($row['id'] ?? 0),
            $entityId,
            $sourceEntityId,
            $sourceEntityName,
            $origin,
            $host,
            $result > 0 ? 'sent' : 'error',
            $result > 0 ? '' : ($error ?: 'Falha no envio; consulte mail-error.log.')
        );

        return (int) $result;
    }

    private static function sendNativeMode(string $mode, array $rows): int|false
    {
        $eventclass = 'NotificationEvent' . ucfirst($mode);
        $conf = Notification_NotificationTemplate::getMode($mode);
        if ($conf['from'] !== 'core') {
            $eventclass = 'Plugin' . ucfirst($conf['from']) . $eventclass;
        }

        return $eventclass::send($rows);
    }

    private static function configRow(int $entityId): ?array
    {
        global $DB;

        self::ensureSchema();
        $iterator = $DB->request([
            'FROM' => self::CONFIG_TABLE,
            'WHERE' => ['entities_id' => $entityId],
            'LIMIT' => 1,
        ]);
        $row = $iterator->current();

        return is_array($row) ? $row : null;
    }

    private static function effectiveConfig(int $entityId): array
    {
        foreach (self::entityChain($entityId) as $entity) {
            $row = self::configRow((int) ($entity['id'] ?? 0));
            if (!self::configIsUsable($row)) {
                continue;
            }

            return [
                'origin' => (int) ($entity['id'] ?? 0) === $entityId ? 'entity' : 'inherited',
                'config' => $row,
                'source_entity' => self::entitySummary($entity),
            ];
        }

        return [
            'origin' => 'global',
            'config' => null,
            'source_entity' => null,
        ];
    }

    private static function configIsUsable(?array $row): bool
    {
        return is_array($row)
            && (int) ($row['is_active'] ?? 0) === 1
            && trim((string) ($row['host'] ?? '')) !== '';
    }

    private static function entitySmtpState(array $entity): array
    {
        $entityId = (int) ($entity['id'] ?? 0);
        $config = self::configRow($entityId);

        return [
            'entity' => [
                'id' => $entityId,
                'name' => (string) ($entity['name'] ?? ''),
                'completename' => self::entityLabel($entity),
                'parent_id' => max(0, (int) ($entity['entities_id'] ?? 0)),
            ],
            'identity' => [
                'admin_email' => (string) ($entity['admin_email'] ?? ''),
                'admin_email_name' => (string) ($entity['admin_email_name'] ?? ''),
                'from_email' => (string) ($entity['from_email'] ?? ''),
                'from_email_name' => (string) ($entity['from_email_name'] ?? ''),
                'replyto_email' => (string) ($entity['replyto_email'] ?? ''),
                'replyto_email_name' => (string) ($entity['replyto_email_name'] ?? ''),
                'mailing_signature' => (string) ($entity['mailing_signature'] ?? ''),
            ],
            'smtp' => self::directSmtpState($entity, $config),
            'effective_smtp' => self::effectiveSmtpState($entityId),
        ];
    }

    private static function directSmtpState(array $entity, ?array $config): array
    {
        return [
            'origin' => self::configIsUsable($config) ? 'entity' : 'none',
            'source_entities_id' => (int) ($entity['id'] ?? 0),
            'source_entity_name' => self::entityLabel($entity),
        ] + self::smtpStateFromConfig($config);
    }

    private static function effectiveSmtpState(int $entityId): array
    {
        $effective = self::effectiveConfig($entityId);
        $config = is_array($effective['config'] ?? null) ? $effective['config'] : null;
        if ($config === null) {
            return self::globalSmtpState();
        }

        $source = is_array($effective['source_entity'] ?? null) ? $effective['source_entity'] : [];

        return [
            'origin' => (string) ($effective['origin'] ?? 'entity'),
            'source_entities_id' => (int) ($source['id'] ?? 0),
            'source_entity_name' => (string) ($source['completename'] ?? $source['name'] ?? 'Entidade'),
        ] + self::smtpStateFromConfig($config);
    }

    private static function smtpStateFromConfig(?array $config): array
    {
        return [
            'is_active' => !empty($config['is_active']) ? 1 : 0,
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 587),
            'encryption' => self::encryption((string) ($config['encryption'] ?? 'tls')),
            'smtp_username' => (string) ($config['smtp_username'] ?? ''),
            'smtp_check_certificate' => !isset($config['smtp_check_certificate']) || (int) $config['smtp_check_certificate'] === 1 ? 1 : 0,
            'password_configured' => trim((string) ($config['smtp_passwd'] ?? '')) !== '',
        ];
    }

    private static function globalSmtpState(): array
    {
        global $CFG_GLPI;

        return [
            'origin' => 'global',
            'source_entities_id' => 0,
            'source_entity_name' => 'SMTP global do GLPI',
            'is_active' => 0,
            'host' => (string) ($CFG_GLPI['smtp_host'] ?? ''),
            'port' => (int) ($CFG_GLPI['smtp_port'] ?? 0),
            'encryption' => self::globalEncryption(),
            'smtp_username' => (string) ($CFG_GLPI['smtp_username'] ?? ''),
            'smtp_check_certificate' => !isset($CFG_GLPI['smtp_check_certificate']) || (int) $CFG_GLPI['smtp_check_certificate'] === 1 ? 1 : 0,
            'password_configured' => trim((string) ($CFG_GLPI['smtp_passwd'] ?? '')) !== '',
        ];
    }

    private static function identityInput(array $payload): array
    {
        $adminEmail = self::email((string) ($payload['admin_email'] ?? ''), true);
        $fromEmail = self::email((string) ($payload['from_email'] ?? $adminEmail), true);
        $replyEmail = self::email((string) ($payload['replyto_email'] ?? $adminEmail), true);

        return [
            'admin_email' => $adminEmail,
            'admin_email_name' => self::text((string) ($payload['admin_email_name'] ?? ''), 255),
            'from_email' => $fromEmail,
            'from_email_name' => self::text((string) ($payload['from_email_name'] ?? ''), 255),
            'replyto_email' => $replyEmail,
            'replyto_email_name' => self::text((string) ($payload['replyto_email_name'] ?? ''), 255),
            'mailing_signature' => substr((string) ($payload['mailing_signature'] ?? ''), 0, 65535),
        ];
    }

    private static function smtpInput(array $payload, int $entityId): array
    {
        $isActive = !empty($payload['is_active']) ? 1 : 0;
        $host = self::text((string) ($payload['host'] ?? ''), 255);
        $port = min(65535, max(0, (int) ($payload['port'] ?? 587)));
        $encryption = self::encryption((string) ($payload['encryption'] ?? 'tls'));
        $username = self::text((string) ($payload['smtp_username'] ?? ''), 255);
        $checkCertificate = array_key_exists('smtp_check_certificate', $payload)
            ? (!empty($payload['smtp_check_certificate']) ? 1 : 0)
            : 1;

        if ($isActive) {
            if ($host === '') {
                throw new RuntimeException('Informe o host SMTP da entidade.');
            }
            if ($port <= 0) {
                throw new RuntimeException('Informe uma porta SMTP valida.');
            }
        }

        $fromEmail = self::email((string) ($payload['from_email'] ?? ''), true);
        if ($isActive && str_contains(strtolower($username), '@') && strtolower($fromEmail) !== strtolower($username)) {
            throw new RuntimeException('Para este provedor SMTP, o remetente deve ser igual ao login SMTP.');
        }

        return [
            'entities_id' => $entityId,
            'is_active' => $isActive,
            'host' => $host,
            'port' => $port > 0 ? $port : 587,
            'encryption' => $encryption,
            'smtp_username' => $username,
            'smtp_check_certificate' => $checkCertificate,
        ];
    }

    private static function mailerForConfig(array $config, string $smtpSender): GLPIMailer
    {
        global $CFG_GLPI;

        $password = '';
        $encrypted = (string) ($config['smtp_passwd'] ?? '');
        if ($encrypted !== '') {
            $password = (new GLPIKey())->decrypt($encrypted);
        }

        $dsn = self::smtpDsn($config, $password);
        $previous = [
            'smtp_mode' => $CFG_GLPI['smtp_mode'] ?? MAIL_MAIL,
            'smtp_sender' => $CFG_GLPI['smtp_sender'] ?? '',
        ];
        $CFG_GLPI['smtp_mode'] = MAIL_SMTP;
        $CFG_GLPI['smtp_sender'] = $smtpSender;

        try {
            return new GLPIMailer(Transport::fromDsn($dsn));
        } finally {
            $CFG_GLPI['smtp_mode'] = $previous['smtp_mode'];
            $CFG_GLPI['smtp_sender'] = $previous['smtp_sender'];
        }
    }

    private static function smtpDsn(array $config, string $password): string
    {
        $scheme = self::encryption((string) ($config['encryption'] ?? 'tls')) === 'ssl' ? 'smtps' : 'smtp';
        $host = (string) ($config['host'] ?? '');
        $port = max(1, (int) ($config['port'] ?? 587));
        $username = (string) ($config['smtp_username'] ?? '');
        $auth = $username !== ''
            ? rawurlencode($username) . ':' . rawurlencode($password) . '@'
            : '';
        $query = [];
        if (empty($config['smtp_check_certificate'])) {
            $query[] = 'verify_peer=0';
        }
        if (self::encryption((string) ($config['encryption'] ?? 'tls')) === 'none') {
            $query[] = 'auto_tls=0';
        }

        return sprintf(
            '%s://%s%s:%d%s',
            $scheme,
            $auth,
            $host,
            $port,
            $query ? '?' . implode('&', $query) : ''
        );
    }

    private static function senderForEntity(int $entityId, array $config): array
    {
        $sender = Config::getFromEmailSender($entityId);
        $email = trim((string) ($sender['email'] ?? ''));
        if ($email === '') {
            $email = trim((string) ($config['smtp_username'] ?? ''));
        }
        if ($email === '' || !NotificationMailing::isUserAddressValid($email)) {
            throw new RuntimeException('Remetente da entidade invalido.');
        }

        return [
            'email' => $email,
            'name' => (string) ($sender['name'] ?? ''),
        ];
    }

    private static function withEntityMailIdentity(array $row, int $entityId, array $sender): array
    {
        $row['sender'] = (string) ($sender['email'] ?? '');
        $row['sendername'] = (string) ($sender['name'] ?? '');

        $replyTo = Config::getReplyToEmailSender($entityId);
        $replyEmail = trim((string) ($replyTo['email'] ?? ''));
        if ($replyEmail !== '' && NotificationMailing::isUserAddressValid($replyEmail)) {
            $row['replyto'] = $replyEmail;
            $row['replytoname'] = (string) ($replyTo['name'] ?? '');
        }

        return $row;
    }

    private static function markDeliveryFailure(array $row, string $error): void
    {
        global $CFG_GLPI;

        $notification = new QueuedNotification();
        $notification->getFromResultSet($row);
        $retries = (int) ($CFG_GLPI['smtp_max_retries'] ?? 0) - (int) ($notification->fields['sent_try'] ?? 0);

        Toolbox::logInFile(
            'mail-error',
            sprintf(
                "Warning: an email was undeliverable to %s with %d retries remaining. Message: %s, Error: %s\n",
                (string) ($notification->fields['recipient'] ?? ''),
                $retries,
                (string) ($notification->fields['name'] ?? ''),
                $error
            )
        );

        if ($retries <= 0) {
            $notification->delete(['id' => (int) ($notification->fields['id'] ?? 0)]);
            return;
        }

        $input = [
            'id' => (int) ($notification->fields['id'] ?? 0),
            'sent_try' => (int) ($notification->fields['sent_try'] ?? 0) + 1,
        ];
        if ((int) ($CFG_GLPI['smtp_retry_time'] ?? 0) > 0) {
            $input['send_time'] = date('Y-m-d H:i:s', strtotime('+' . (int) $CFG_GLPI['smtp_retry_time'] . ' minutes'));
        }
        $notification->update($input);
    }

    private static function logPluginDelivery(
        int $queueId,
        int $entityId,
        int $smtpEntityId,
        string $smtpEntityName,
        string $origin,
        string $host,
        string $status,
        string $error
    ): void {
        global $DB;

        self::ensureSchema();
        $DB->insert(self::LOG_TABLE, [
            'queuednotifications_id' => $queueId,
            'entities_id' => $entityId,
            'smtp_entities_id' => $smtpEntityId,
            'smtp_entity_name' => substr($smtpEntityName, 0, 255),
            'mode' => Notification_NotificationTemplate::MODE_MAIL,
            'origin' => substr($origin, 0, 20),
            'smtp_host' => substr($host, 0, 255),
            'status' => substr($status, 0, 20),
            'error_message' => $error !== '' ? substr($error, 0, 65535) : null,
        ]);
    }

    private static function recentLogs(int $entityId): array
    {
        global $DB;

        self::ensureSchema();
        $where = $entityId > 0 ? ['entities_id' => $entityId] : [];
        $rows = [];
        foreach ($DB->request([
            'FROM' => self::LOG_TABLE,
            'WHERE' => $where,
            'ORDER' => ['date_creation DESC', 'id DESC'],
            'LIMIT' => 8,
        ]) as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'queuednotifications_id' => (int) ($row['queuednotifications_id'] ?? 0),
                'entities_id' => (int) ($row['entities_id'] ?? 0),
                'smtp_entities_id' => (int) ($row['smtp_entities_id'] ?? 0),
                'smtp_entity_name' => (string) ($row['smtp_entity_name'] ?? ''),
                'origin' => (string) ($row['origin'] ?? ''),
                'smtp_host' => (string) ($row['smtp_host'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'error_message' => (string) ($row['error_message'] ?? ''),
                'date_creation' => (string) ($row['date_creation'] ?? ''),
            ];
        }

        return $rows;
    }

    private static function acquireQueueLock(): bool
    {
        global $DB;

        $result = $DB->doQuery("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0) AS lock_status");
        $row = $DB->fetchArray($result);
        return (int) ($row['lock_status'] ?? 0) === 1;
    }

    private static function releaseQueueLock(): void
    {
        global $DB;

        $DB->doQuery("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
    }

    private static function acquireOwnerLock(): bool
    {
        global $DB;

        $result = $DB->doQuery("SELECT GET_LOCK('" . self::OWNER_LOCK_NAME . "', 0) AS lock_status");
        $row = $DB->fetchArray($result);
        return (int) ($row['lock_status'] ?? 0) === 1;
    }

    private static function releaseOwnerLock(): void
    {
        global $DB;

        $DB->doQuery("SELECT RELEASE_LOCK('" . self::OWNER_LOCK_NAME . "')");
    }

    private static function findEntity(int $entityId): array
    {
        global $DB;

        foreach ($DB->request([
            'SELECT' => [
                'id', 'name', 'completename', 'entities_id',
                'admin_email', 'admin_email_name',
                'from_email', 'from_email_name',
                'replyto_email', 'replyto_email_name',
                'mailing_signature',
            ],
            'FROM' => Entity::getTable(),
            'WHERE' => ['id' => $entityId],
            'LIMIT' => 1,
        ]) as $row) {
            if (is_array($row)) {
                return $row;
            }
        }

        throw new RuntimeException('Entidade nao encontrada.');
    }

    private static function entityChain(int $entityId): array
    {
        $chain = [];
        $seen = [];
        $currentId = max(0, $entityId);

        while (!isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $entity = self::findEntity($currentId);
            $chain[] = $entity;

            if ($currentId === 0) {
                break;
            }

            $parentId = max(0, (int) ($entity['entities_id'] ?? 0));
            if ($parentId === $currentId) {
                break;
            }

            $currentId = $parentId;
        }

        return $chain;
    }

    private static function entitySummary(array $entity): array
    {
        return [
            'id' => (int) ($entity['id'] ?? 0),
            'name' => (string) ($entity['name'] ?? ''),
            'completename' => self::entityLabel($entity),
        ];
    }

    private static function entityLabel(array $entity): string
    {
        $label = trim((string) ($entity['completename'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $label = trim((string) ($entity['name'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        return (int) ($entity['id'] ?? 0) === 0 ? 'Entidade raiz' : 'Entidade';
    }

    private static function crontaskByName(string $name): array
    {
        global $DB;

        foreach ($DB->request([
            'FROM' => CronTask::getTable(),
            'WHERE' => ['name' => $name],
            'LIMIT' => 1,
        ]) as $row) {
            if (is_array($row)) {
                return ['available' => true] + $row;
            }
        }

        return ['available' => false, 'name' => $name];
    }

    private static function ensureDashCrontask(): array
    {
        $task = self::crontaskByName(self::TASK_NAME);
        if ($task['available']) {
            return $task;
        }

        CronTask::register(
            self::class,
            self::TASK_NAME,
            MINUTE_TIMESTAMP,
            [
                'state' => CronTask::STATE_DISABLE,
                'mode' => CronTask::MODE_EXTERNAL,
                'param' => 20,
                'comment' => 'DashGLPI: envia a fila de notificacoes usando SMTP por entidade quando configurado.',
            ]
        );

        return self::crontaskByName(self::TASK_NAME);
    }

    private static function crontaskSnapshotForRestore(array $task): array
    {
        $fields = ['state', 'mode', 'allowmode', 'frequency', 'param', 'hourmin', 'hourmax', 'logs_lifetime', 'comment'];
        $snapshot = [];
        foreach ($fields as $field) {
            $snapshot[$field] = $task[$field] ?? null;
        }

        return $snapshot;
    }

    private static function crontaskRestoreFields(array $snapshot): array
    {
        $fields = [];
        foreach (['state', 'mode', 'allowmode', 'frequency', 'param', 'hourmin', 'hourmax', 'logs_lifetime', 'comment'] as $field) {
            if (array_key_exists($field, $snapshot)) {
                $fields[$field] = $snapshot[$field];
            }
        }

        return $fields;
    }

    private static function ownerSettings(bool $ensureSchema = true): array
    {
        global $DB;

        if ($ensureSchema) {
            self::ensureSchema();
        }
        foreach ($DB->request([
            'SELECT' => ['data'],
            'FROM' => self::SETTINGS_TABLE,
            'WHERE' => ['namespace' => self::OWNER_NAMESPACE],
            'LIMIT' => 1,
        ]) as $row) {
            $data = json_decode((string) ($row['data'] ?? '{}'), true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    private static function saveOwnerSettings(array $settings, bool $ensureSchema = true): void
    {
        global $DB;

        if ($ensureSchema) {
            self::ensureSchema();
        }
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Falha ao serializar estado de dono da fila.');
        }

        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM' => self::SETTINGS_TABLE,
            'WHERE' => ['namespace' => self::OWNER_NAMESPACE],
            'LIMIT' => 1,
        ])->current();

        if (is_array($existing)) {
            $DB->update(self::SETTINGS_TABLE, ['data' => $json], ['namespace' => self::OWNER_NAMESPACE]);
            return;
        }

        $DB->insert(self::SETTINGS_TABLE, [
            'namespace' => self::OWNER_NAMESPACE,
            'data' => $json,
        ]);
    }

    private static function text(string $value, int $max): string
    {
        return substr(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 0, $max);
    }

    private static function email(string $value, bool $required): string
    {
        $value = self::text($value, 255);
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

    private static function encryption(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['none', 'tls', 'ssl'], true) ? $value : 'tls';
    }

    private static function globalEncryption(): string
    {
        global $CFG_GLPI;

        $mode = (int) ($CFG_GLPI['smtp_mode'] ?? 0);
        if (defined('MAIL_SMTPS') && $mode === MAIL_SMTPS) {
            return 'ssl';
        }

        return 'tls';
    }
}

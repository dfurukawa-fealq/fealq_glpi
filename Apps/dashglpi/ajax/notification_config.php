<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/../inc/notification_followup_presets.php';

use Glpi\Mail\SMTP\OauthConfig;

plugin_dashglpi_admin_bridge_handle('notification', function (array $payload): array {
    $action = (string) ($payload['action'] ?? 'save');

    return match ($action) {
        'catalog' => ['catalog' => plugin_dashglpi_notification_catalog()],
        'toggle' => plugin_dashglpi_notification_toggle($payload),
        'clone' => plugin_dashglpi_notification_clone($payload),
        'bulk_disable' => plugin_dashglpi_notification_bulk_disable($payload),
        'save_mail_settings' => plugin_dashglpi_notification_save_mail_settings($payload),
        'send_test_email' => plugin_dashglpi_notification_send_test_email($payload),
        'save_template_translation' => plugin_dashglpi_notification_save_template_translation($payload),
        'save_template_bundle' => plugin_dashglpi_notification_save_template_bundle($payload),
        'fix_followup_template' => plugin_dashglpi_notification_fix_followup_template($payload),
        'create_individual_template' => plugin_dashglpi_notification_create_individual_template($payload),
        'update_queue' => plugin_dashglpi_notification_update_queue(),
        'update_sla_automation' => plugin_dashglpi_notification_update_sla_automation(),
        default => plugin_dashglpi_notification_save($payload),
    };
}, 'Erro interno no bridge de notificacao.');

function plugin_dashglpi_notification_toggle(array $payload): array
{
    $id = max(0, (int) ($payload['id'] ?? 0));
    $notification = new Notification();
    if (!$notification->getFromDB($id)) {
        throw new RuntimeException('Notificacao nao encontrada.');
    }

    $isActive = !empty($payload['is_active']) ? 1 : 0;
    if (!$notification->update(['id' => $id, 'is_active' => $isActive])) {
        throw new RuntimeException('Falha ao atualizar status da notificacao.');
    }

    return ['notification' => ['id' => $id, 'status' => 'updated', 'is_active' => $isActive]];
}

function plugin_dashglpi_notification_bulk_disable(array $payload): array
{
    global $DB;

    $ids = array_values(array_unique(array_filter(array_map(static fn($value): int => max(0, (int) $value), (array) ($payload['ids'] ?? [])))));
    if ($ids === []) {
        throw new RuntimeException('Selecione ao menos uma notificacao.');
    }

    $updated = 0;
    foreach ($ids as $id) {
        $notification = new Notification();
        if ($notification->getFromDB($id) && $notification->update(['id' => $id, 'is_active' => 0])) {
            $updated++;
        }
    }

    if ($updated === 0) {
        throw new RuntimeException('Nenhuma notificacao foi desativada.');
    }

    return ['bulk_disable' => ['requested' => count($ids), 'updated' => $updated]];
}

function plugin_dashglpi_notification_clone(array $payload): array
{
    $id = max(0, (int) ($payload['id'] ?? 0));
    $notification = new Notification();
    if (!$notification->getFromDB($id)) {
        throw new RuntimeException('Notificacao nao encontrada.');
    }

    $newId = $notification->clone(['is_active' => 0]);
    if ($newId === false || (int) $newId <= 0) {
        throw new RuntimeException('Falha ao clonar notificacao.');
    }

    return [
        'notification_clone' => [
            'source_id' => $id,
            'id' => (int) $newId,
            'status' => 'cloned',
            'is_active' => 0,
        ],
    ];
}

function plugin_dashglpi_notification_save_mail_settings(array $payload): array
{
    $settings = $payload['settings'] ?? null;
    if (!is_array($settings)) {
        throw new RuntimeException('Configuracao de e-mail invalida.');
    }

    plugin_dashglpi_notification_validate_sender_login_rule($settings);
    $beforeSnapshot = plugin_dashglpi_notification_mail_settings_snapshot();
    $config = new Config();
    $input = [
        'id' => Config::getConfigIDForContext('core'),
        'admin_email' => (string) ($settings['admin_email'] ?? ''),
        'admin_email_name' => (string) ($settings['admin_email_name'] ?? ''),
        'from_email' => (string) ($settings['from_email'] ?? ''),
        'from_email_name' => (string) ($settings['from_email_name'] ?? ''),
        'replyto_email' => (string) ($settings['replyto_email'] ?? ''),
        'replyto_email_name' => (string) ($settings['replyto_email_name'] ?? ''),
        'noreply_email' => (string) ($settings['noreply_email'] ?? ''),
        'noreply_email_name' => (string) ($settings['noreply_email_name'] ?? ''),
        'attach_ticket_documents_to_mail' => min(2, max(0, (int) ($settings['attach_ticket_documents_to_mail'] ?? 0))),
        'attach_documents_to_notifications_for_anonymous' => min(1, max(0, (int) ($settings['attach_documents_to_notifications_for_anonymous'] ?? 0))),
        'mailing_signature' => (string) ($settings['mailing_signature'] ?? ''),
        'smtp_mode' => max(0, (int) ($settings['smtp_mode'] ?? 0)),
        'smtp_max_retries' => max(0, (int) ($settings['smtp_max_retries'] ?? 0)),
        'smtp_retry_time' => max(0, (int) ($settings['smtp_retry_time'] ?? 0)),
        'smtp_check_certificate' => !empty($settings['smtp_check_certificate']) ? 1 : 0,
        'smtp_host' => (string) ($settings['smtp_host'] ?? ''),
        'smtp_port' => max(0, (int) ($settings['smtp_port'] ?? 0)),
        'smtp_username' => (string) ($settings['smtp_username'] ?? ''),
        'smtp_sender' => (string) ($settings['smtp_sender'] ?? ''),
        'smtp_oauth_provider' => (string) ($settings['smtp_oauth_provider'] ?? ''),
        'smtp_oauth_client_id' => (string) ($settings['smtp_oauth_client_id'] ?? ''),
        'smtp_oauth_options' => plugin_dashglpi_notification_decode_oauth_options((string) ($settings['smtp_oauth_options_json'] ?? '{}')),
    ];

    $smtpPassword = (string) ($settings['smtp_passwd'] ?? '');
    if ($smtpPassword !== '') {
        $input['smtp_passwd'] = $smtpPassword;
    }
    if (!empty($settings['smtp_clear_password'])) {
        $input['_blank_smtp_passwd'] = 1;
    }

    $oauthSecret = (string) ($settings['smtp_oauth_client_secret'] ?? '');
    if ($oauthSecret !== '') {
        $input['smtp_oauth_client_secret'] = $oauthSecret;
    }

    // Config::update() for core settings persists values through Config::prepareInputForUpdate()
    // and returns false by design, so persistence must be verified from the reloaded snapshot.
    $config->update($input);
    if (Config::loadLegacyConfiguration() === false) {
        throw new RuntimeException('Falha ao recarregar configuracao de e-mail no GLPI.');
    }

    $afterSnapshot = plugin_dashglpi_notification_mail_settings_snapshot();
    plugin_dashglpi_notification_assert_mail_settings_persisted($settings, $beforeSnapshot, $afterSnapshot);

    return ['mail_settings' => $afterSnapshot];
}

function plugin_dashglpi_notification_send_test_email(array $payload): array
{
    $adminEmail = trim((string) ($payload['admin_email'] ?? ''));
    if ($adminEmail === '') {
        throw new RuntimeException('Informe o e-mail do administrador para o teste.');
    }

    if (!class_exists('NotificationMailing')) {
        throw new RuntimeException('Envio de e-mail de teste indisponivel nesta versao do GLPI.');
    }

    if (Config::loadLegacyConfiguration() === false) {
        throw new RuntimeException('Falha ao recarregar configuracao de e-mail no GLPI.');
    }

    plugin_dashglpi_notification_validate_sender_login_rule(plugin_dashglpi_notification_mail_settings_snapshot());
    $result = NotificationMailing::testNotification();
    if (!is_array($result) || empty($result['success'])) {
        $message = is_array($result) ? trim((string) ($result['error'] ?? '')) : '';
        plugin_dashglpi_notification_log_smtp_failure($message);
        throw new RuntimeException($message !== '' ? $message : 'O GLPI nao conseguiu enviar o e-mail de teste.');
    }

    return [
        'test_email' => ['status' => 'sent'],
        'mail_settings' => plugin_dashglpi_notification_mail_settings_snapshot(),
    ];
}

function plugin_dashglpi_notification_save_template_translation(array $payload): array
{
    $templateId = max(0, (int) ($payload['template_id'] ?? 0));
    $translationId = max(0, (int) ($payload['translation_id'] ?? 0));
    plugin_dashglpi_admin_bridge_require_item(NotificationTemplate::class, $templateId, 'Modelo de notificacao nao encontrado.');

    $fields = [
        'notificationtemplates_id' => $templateId,
        'language' => substr((string) ($payload['language'] ?? 'pt_BR'), 0, 10),
        'subject' => substr((string) ($payload['subject'] ?? ''), 0, 255),
        'content_text' => (string) ($payload['content_text'] ?? ''),
        'content_html' => (string) ($payload['content_html'] ?? ''),
    ];

    if ($fields['subject'] === '') {
        throw new RuntimeException('Informe o assunto do modelo.');
    }

    if ($translationId > 0) {
        plugin_dashglpi_notification_translation_update($translationId, $templateId, $fields);
    } else {
        $translationId = plugin_dashglpi_notification_translation_insert($fields);
    }

    return ['template_translation' => ['id' => $translationId, 'status' => 'saved']];
}

function plugin_dashglpi_notification_save_template_bundle(array $payload): array
{
    $templateId = max(0, (int) ($payload['template_id'] ?? 0));
    $translationId = max(0, (int) ($payload['translation_id'] ?? 0));
    $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do modelo.');
    $itemtype = trim((string) ($payload['itemtype'] ?? ''));

    if ($itemtype === '') {
        throw new RuntimeException('Informe o tipo do modelo.');
    }

    $template = new NotificationTemplate();
    if ($templateId > 0) {
        if (!$template->getFromDB($templateId)) {
            throw new RuntimeException('Modelo de notificacao nao encontrado.');
        }
        if (!$template->update([
            'id' => $templateId,
            'name' => $name,
            'itemtype' => $itemtype,
        ])) {
            throw new RuntimeException('Falha ao atualizar modelo de notificacao.');
        }
    } else {
        $existing = plugin_dashglpi_admin_bridge_find_one(NotificationTemplate::class, [
            'name' => $name,
            'itemtype' => $itemtype,
        ]);

        if ($existing) {
            $templateId = (int) ($existing['id'] ?? 0);
        } else {
            $templateId = (int) $template->add([
                'name' => $name,
                'itemtype' => $itemtype,
                'comment' => 'Criado pelo DashGLPI.',
            ]);
            if ($templateId <= 0) {
                throw new RuntimeException('Falha ao criar modelo de notificacao.');
            }
        }
    }

    $fields = [
        'notificationtemplates_id' => $templateId,
        'language' => substr((string) ($payload['language'] ?? 'pt_BR'), 0, 10),
        'subject' => substr((string) ($payload['subject'] ?? ''), 0, 255),
        'content_text' => (string) ($payload['content_text'] ?? ''),
        'content_html' => (string) ($payload['content_html'] ?? ''),
    ];

    if ($fields['subject'] === '') {
        throw new RuntimeException('Informe o assunto do modelo.');
    }

    if ($translationId <= 0) {
        $existingTranslation = plugin_dashglpi_notification_find_translation($templateId, $fields['language']);
        $translationId = (int) ($existingTranslation['id'] ?? 0);
    }

    if ($translationId > 0) {
        plugin_dashglpi_notification_translation_update($translationId, $templateId, $fields);
    } else {
        $translationId = plugin_dashglpi_notification_translation_insert($fields);
    }

    return [
        'template_bundle' => [
            'template_id' => $templateId,
            'translation_id' => $translationId,
            'name' => $name,
            'itemtype' => $itemtype,
            'language' => $fields['language'],
            'subject' => $fields['subject'],
            'status' => 'saved',
        ],
    ];
}

function plugin_dashglpi_notification_fix_followup_template(array $payload): array
{
    $presetName = (string) ($payload['preset'] ?? 'hafen');
    $preset = dashglpi_followup_template_preset($presetName);
    $bundleResult = plugin_dashglpi_notification_save_template_bundle([
        'name' => (string) ($preset['template_name'] ?? 'Tickets Acompanhamento'),
        'itemtype' => (string) ($preset['itemtype'] ?? 'Ticket'),
        'language' => (string) ($preset['language'] ?? 'pt_BR'),
        'subject' => (string) ($preset['subject'] ?? ''),
        'content_html' => (string) ($preset['content_html'] ?? ''),
        'content_text' => (string) ($preset['content_text'] ?? ''),
    ]);

    $templateBundle = (array) ($bundleResult['template_bundle'] ?? []);
    $templateId = max(0, (int) ($templateBundle['template_id'] ?? 0));
    if ($templateId <= 0) {
        throw new RuntimeException('Falha ao preparar o modelo dedicado de acompanhamento.');
    }

    $notifications = plugin_dashglpi_notification_find_ticket_followup_notifications();
    if ($notifications === []) {
        throw new RuntimeException('Nenhuma notificacao de follow-up do tipo Ticket foi encontrada para correcao.');
    }

    $updatedIds = [];
    $updatedEvents = [];
    foreach ($notifications as $notification) {
        $notificationId = max(0, (int) ($notification['id'] ?? 0));
        $event = trim((string) ($notification['event'] ?? ''));
        if ($notificationId <= 0 || $event === '') {
            continue;
        }

        plugin_dashglpi_notification_link_mail_template($notificationId, $templateId);
        $updatedIds[] = $notificationId;
        $updatedEvents[$event] = true;
    }

    if ($updatedIds === []) {
        throw new RuntimeException('Nenhuma notificacao de follow-up elegivel foi atualizada.');
    }

    return [
        'followup_template_fix' => [
            'status' => 'updated',
            'preset' => strtolower(trim($presetName)) ?: 'hafen',
            'template_id' => $templateId,
            'translation_id' => max(0, (int) ($templateBundle['translation_id'] ?? 0)),
            'template_name' => (string) ($templateBundle['name'] ?? ($preset['template_name'] ?? 'Tickets Acompanhamento')),
            'updated_notification_ids' => array_values(array_unique(array_map('intval', $updatedIds))),
            'updated_events' => array_values(array_keys($updatedEvents)),
        ],
    ];
}

function plugin_dashglpi_notification_create_individual_template(array $payload): array
{
    $notificationId = max(0, (int) ($payload['id'] ?? 0));
    if ($notificationId <= 0) {
        throw new RuntimeException('Notificacao invalida para criar modelo individual.');
    }

    $notification = plugin_dashglpi_admin_bridge_require_item(Notification::class, $notificationId, 'Notificacao nao encontrada.');
    $notificationName = plugin_dashglpi_admin_bridge_name((string) ($notification['name'] ?? ''), 'A notificacao nao possui nome para criar modelo.');
    $itemtype = trim((string) ($notification['itemtype'] ?? ''));
    if ($itemtype === '') {
        throw new RuntimeException('A notificacao nao possui tipo para criar modelo.');
    }

    $language = substr(trim((string) ($payload['language'] ?? 'pt_BR')), 0, 10);
    if ($language === '') {
        $language = 'pt_BR';
    }

    $contentText = sprintf(
        "Modelo criado pelo DashGLPI para a notificacao \"%s\".\n\nEdite este conteudo antes de usar em producao.",
        $notificationName
    );
    $escapedName = htmlspecialchars($notificationName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $contentHtml = sprintf(
        '<p>Modelo criado pelo DashGLPI para a notificacao <strong>%s</strong>.</p><p>Edite este conteudo antes de usar em producao.</p>',
        $escapedName
    );

    $bundleResult = plugin_dashglpi_notification_save_template_bundle([
        'name' => $notificationName,
        'itemtype' => $itemtype,
        'language' => $language,
        'subject' => $notificationName,
        'content_html' => $contentHtml,
        'content_text' => $contentText,
    ]);

    $templateBundle = (array) ($bundleResult['template_bundle'] ?? []);
    $templateId = max(0, (int) ($templateBundle['template_id'] ?? 0));
    if ($templateId <= 0) {
        throw new RuntimeException('Falha ao preparar o modelo individual.');
    }

    plugin_dashglpi_notification_link_mail_template($notificationId, $templateId);

    return [
        'individual_template' => [
            'notification_id' => $notificationId,
            'template_id' => $templateId,
            'translation_id' => max(0, (int) ($templateBundle['translation_id'] ?? 0)),
            'template_name' => $notificationName,
            'language' => $language,
            'status' => 'linked',
        ],
    ];
}

function plugin_dashglpi_notification_update_queue(): array
{
    return ['queue' => plugin_dashglpi_notification_update_crontask('QueuedNotification', 'queuednotification')];
}

function plugin_dashglpi_notification_update_sla_automation(): array
{
    return [
        'automation' => [
            'queuednotification' => plugin_dashglpi_notification_update_crontask('QueuedNotification', 'queuednotification'),
            'slaticket' => plugin_dashglpi_notification_update_crontask('SlaLevel_Ticket', 'slaticket'),
        ],
    ];
}

function plugin_dashglpi_notification_mail_config_values(): array
{
    $values = Config::getConfigurationValues('core', NotificationMailingSetting::getRelatedConfigKeys());
    $values['url_base'] = (string) Config::getConfigurationValue('core', 'url_base');

    return $values;
}

function plugin_dashglpi_notification_mail_settings_snapshot(?array $configValues = null): array
{
    $values = $configValues ?? plugin_dashglpi_notification_mail_config_values();
    $oauthOptions = plugin_dashglpi_notification_decode_oauth_options((string) ($values['smtp_oauth_options'] ?? '{}'));

    return [
        'admin_email' => (string) ($values['admin_email'] ?? ''),
        'admin_email_name' => (string) ($values['admin_email_name'] ?? ''),
        'from_email' => (string) ($values['from_email'] ?? ''),
        'from_email_name' => (string) ($values['from_email_name'] ?? ''),
        'replyto_email' => (string) ($values['replyto_email'] ?? ''),
        'replyto_email_name' => (string) ($values['replyto_email_name'] ?? ''),
        'noreply_email' => (string) ($values['noreply_email'] ?? ''),
        'noreply_email_name' => (string) ($values['noreply_email_name'] ?? ''),
        'attach_ticket_documents_to_mail' => (string) ($values['attach_ticket_documents_to_mail'] ?? '0'),
        'attach_documents_to_notifications_for_anonymous' => (string) ($values['attach_documents_to_notifications_for_anonymous'] ?? '0'),
        'mailing_signature' => (string) ($values['mailing_signature'] ?? ''),
        'smtp_mode' => (string) ($values['smtp_mode'] ?? '0'),
        'smtp_max_retries' => (string) ($values['smtp_max_retries'] ?? '0'),
        'smtp_retry_time' => (string) ($values['smtp_retry_time'] ?? '0'),
        'smtp_check_certificate' => !empty($values['smtp_check_certificate']) ? '1' : '0',
        'smtp_host' => (string) ($values['smtp_host'] ?? ''),
        'smtp_port' => (string) ($values['smtp_port'] ?? '0'),
        'smtp_username' => (string) ($values['smtp_username'] ?? ''),
        'smtp_sender' => (string) ($values['smtp_sender'] ?? ''),
        'smtp_oauth_provider' => (string) ($values['smtp_oauth_provider'] ?? ''),
        'smtp_oauth_client_id' => (string) ($values['smtp_oauth_client_id'] ?? ''),
        'smtp_oauth_options' => $oauthOptions,
        'smtp_oauth_options_json' => plugin_dashglpi_notification_encode_oauth_options($oauthOptions),
        'smtp_oauth_callback_url' => rtrim((string) ($values['url_base'] ?? ''), '/') . '/front/smtp_oauth2_callback.php',
        'smtp_password_configured' => (string) ($values['smtp_passwd'] ?? '') !== '',
        'smtp_oauth_client_secret_configured' => (string) ($values['smtp_oauth_client_secret'] ?? '') !== '',
        'smtp_oauth_refresh_token_configured' => (string) ($values['smtp_oauth_refresh_token'] ?? '') !== '',
    ];
}

function plugin_dashglpi_notification_normalize_mail_identity(?string $value): string
{
    return strtolower(trim((string) $value));
}

function plugin_dashglpi_notification_validate_sender_login_rule(array $settings): void
{
    $smtpMode = (int) ($settings['smtp_mode'] ?? 0);
    $smtpUsername = plugin_dashglpi_notification_normalize_mail_identity((string) ($settings['smtp_username'] ?? ''));
    if ($smtpMode !== MAIL_SMTP || !str_contains($smtpUsername, '@')) {
        return;
    }

    $fromEmail = plugin_dashglpi_notification_normalize_mail_identity((string) ($settings['from_email'] ?? ''));
    if ($fromEmail === $smtpUsername) {
        return;
    }

    throw new RuntimeException('Para este provedor SMTP, o Endereco de remetente deve ser igual ao Login SMTP.');
}

function plugin_dashglpi_notification_expected_mail_settings_snapshot(array $settings, array $beforeSnapshot): array
{
    $mode = (string) max(0, (int) ($settings['smtp_mode'] ?? 0));
    $isOauth = (int) $mode === MAIL_SMTPOAUTH;
    $oauthOptions = plugin_dashglpi_notification_decode_oauth_options((string) ($settings['smtp_oauth_options_json'] ?? '{}'));
    $oauthOptionsJson = plugin_dashglpi_notification_encode_oauth_options($oauthOptions);
    $oauthSettingsChanged = $isOauth && (
        (string) ($beforeSnapshot['smtp_oauth_provider'] ?? '') !== (string) ($settings['smtp_oauth_provider'] ?? '')
        || (string) ($beforeSnapshot['smtp_oauth_client_id'] ?? '') !== (string) ($settings['smtp_oauth_client_id'] ?? '')
        || (string) ($beforeSnapshot['smtp_oauth_options_json'] ?? '{}') !== $oauthOptionsJson
    );

    $smtpUsername = (string) ($settings['smtp_username'] ?? '');
    if ($isOauth && $oauthSettingsChanged) {
        $smtpUsername = '';
    }

    $smtpPasswordConfigured = (bool) ($beforeSnapshot['smtp_password_configured'] ?? false);
    if ($isOauth || !empty($settings['smtp_clear_password'])) {
        $smtpPasswordConfigured = false;
    } elseif ((string) ($settings['smtp_passwd'] ?? '') !== '') {
        $smtpPasswordConfigured = true;
    }

    $oauthSecretConfigured = $isOauth
        ? (bool) ($beforeSnapshot['smtp_oauth_client_secret_configured'] ?? false)
        : false;
    if ($isOauth && (string) ($settings['smtp_oauth_client_secret'] ?? '') !== '') {
        $oauthSecretConfigured = true;
    }

    $oauthRefreshConfigured = $isOauth
        ? (bool) ($beforeSnapshot['smtp_oauth_refresh_token_configured'] ?? false)
        : false;
    if ($isOauth && $oauthSettingsChanged) {
        $oauthRefreshConfigured = false;
    }

    return [
        'admin_email' => (string) ($settings['admin_email'] ?? ''),
        'admin_email_name' => (string) ($settings['admin_email_name'] ?? ''),
        'from_email' => (string) ($settings['from_email'] ?? ''),
        'from_email_name' => (string) ($settings['from_email_name'] ?? ''),
        'replyto_email' => (string) ($settings['replyto_email'] ?? ''),
        'replyto_email_name' => (string) ($settings['replyto_email_name'] ?? ''),
        'noreply_email' => (string) ($settings['noreply_email'] ?? ''),
        'noreply_email_name' => (string) ($settings['noreply_email_name'] ?? ''),
        'attach_ticket_documents_to_mail' => (string) min(2, max(0, (int) ($settings['attach_ticket_documents_to_mail'] ?? 0))),
        'attach_documents_to_notifications_for_anonymous' => (string) min(1, max(0, (int) ($settings['attach_documents_to_notifications_for_anonymous'] ?? 0))),
        'mailing_signature' => (string) ($settings['mailing_signature'] ?? ''),
        'smtp_mode' => $mode,
        'smtp_max_retries' => (string) max(0, (int) ($settings['smtp_max_retries'] ?? 0)),
        'smtp_retry_time' => (string) max(0, (int) ($settings['smtp_retry_time'] ?? 0)),
        'smtp_check_certificate' => $isOauth
            ? '1'
            : (!empty($settings['smtp_check_certificate']) ? '1' : '0'),
        'smtp_host' => (string) ($settings['smtp_host'] ?? ''),
        'smtp_port' => (string) max(0, (int) ($settings['smtp_port'] ?? 0)),
        'smtp_username' => $smtpUsername,
        'smtp_sender' => (string) ($settings['smtp_sender'] ?? ''),
        'smtp_oauth_provider' => $isOauth ? (string) ($settings['smtp_oauth_provider'] ?? '') : '',
        'smtp_oauth_client_id' => $isOauth ? (string) ($settings['smtp_oauth_client_id'] ?? '') : '',
        'smtp_oauth_options_json' => $isOauth ? $oauthOptionsJson : '{}',
        'smtp_password_configured' => $smtpPasswordConfigured,
        'smtp_oauth_client_secret_configured' => $oauthSecretConfigured,
        'smtp_oauth_refresh_token_configured' => $oauthRefreshConfigured,
    ];
}

function plugin_dashglpi_notification_assert_mail_settings_persisted(array $settings, array $beforeSnapshot, array $afterSnapshot): void
{
    $expected = plugin_dashglpi_notification_expected_mail_settings_snapshot($settings, $beforeSnapshot);
    $actual = [];
    foreach (array_keys($expected) as $key) {
        $actual[$key] = $afterSnapshot[$key] ?? null;
    }

    if ($actual === $expected) {
        return;
    }

    $mismatchedKeys = [];
    foreach ($expected as $key => $value) {
        if (($actual[$key] ?? null) !== $value) {
            $mismatchedKeys[] = $key;
        }
    }

    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[DashGLPI mail save verification failed] keys=%s expected=%s actual=%s\n",
            implode(',', $mismatchedKeys),
            json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        )
    );

    throw new RuntimeException('Falha ao confirmar configuracao de e-mail salva no GLPI.');
}

function plugin_dashglpi_notification_mail_log_context(?array $mailSettings = null): array
{
    $settings = $mailSettings ?? plugin_dashglpi_notification_mail_settings_snapshot();

    return [
        'smtp_mode' => (string) ($settings['smtp_mode'] ?? '0'),
        'smtp_host' => (string) ($settings['smtp_host'] ?? ''),
        'smtp_port' => (string) ($settings['smtp_port'] ?? '0'),
        'smtp_username' => (string) ($settings['smtp_username'] ?? ''),
        'smtp_sender' => (string) ($settings['smtp_sender'] ?? ''),
        'admin_email' => (string) ($settings['admin_email'] ?? ''),
    ];
}

function plugin_dashglpi_notification_log_smtp_failure(string $message): void
{
    $context = plugin_dashglpi_notification_mail_log_context();
    $encodedContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[DashGLPI SMTP test] message=%s context=%s\n",
            $message !== '' ? $message : '(empty)',
            is_string($encodedContext) ? $encodedContext : '{}'
        )
    );
}

function plugin_dashglpi_notification_find_ticket_followup_notifications(): array
{
    global $DB;

    $rows = [];
    foreach ($DB->request([
        'FROM' => 'glpi_notifications',
        'WHERE' => [
            'itemtype' => 'Ticket',
            'event' => ['add_followup', 'update_followup'],
        ],
        'ORDER' => ['event ASC', 'id ASC'],
    ]) as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function plugin_dashglpi_notification_link_mail_template(int $notificationId, int $templateId): void
{
    global $DB;

    if ($notificationId <= 0) {
        throw new RuntimeException('Notificacao invalida para vinculo de modelo.');
    }

    plugin_dashglpi_admin_bridge_require_item(Notification::class, $notificationId, 'Notificacao nao encontrada.');
    plugin_dashglpi_admin_bridge_require_item(NotificationTemplate::class, $templateId, 'Modelo de notificacao nao encontrado.');

    $DB->delete(
        'glpi_notifications_notificationtemplates',
        [
            'notifications_id' => $notificationId,
            'mode' => Notification_NotificationTemplate::MODE_MAIL,
        ]
    );

    if (!$DB->insert(
        'glpi_notifications_notificationtemplates',
        [
            'notifications_id' => $notificationId,
            'notificationtemplates_id' => $templateId,
            'mode' => Notification_NotificationTemplate::MODE_MAIL,
        ]
    )) {
        throw new RuntimeException('Falha ao religar o modelo de e-mail para a notificacao.');
    }
}

function plugin_dashglpi_notification_save(array $payload): array
{
    global $DB;

    $catalog = plugin_dashglpi_notification_catalog();
    $itemtype = trim((string) ($payload['itemtype'] ?? ''));
    $event = trim((string) ($payload['event'] ?? ''));
    $availableEvents = $catalog['events_by_itemtype'][$itemtype] ?? [];
    if (!is_array($availableEvents) || !array_key_exists($event, $availableEvents)) {
        throw new RuntimeException('Evento de notificacao invalido para o tipo selecionado.');
    }

    $availableTargets = $catalog['targets_by_itemtype'][$itemtype][$event] ?? [];
    $recipientKeys = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['recipients'] ?? [])))));
    foreach ($recipientKeys as $key) {
        if (!array_key_exists($key, $availableTargets)) {
            throw new RuntimeException('Destinatario invalido para o gatilho selecionado.');
        }
    }

    $notificationId = max(0, (int) ($payload['id'] ?? 0));
    $notification = new Notification();
    if ($notificationId > 0 && !$notification->getFromDB($notificationId)) {
        throw new RuntimeException('Notificacao nao encontrada.');
    }

    $entitiesId = max(0, (int) ($payload['entities_id'] ?? 0));
    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade nao encontrada.');

    $templateId = max(0, (int) ($payload['template_id'] ?? 0));
    if ($templateId > 0) {
        plugin_dashglpi_admin_bridge_require_item(NotificationTemplate::class, $templateId, 'Modelo de notificacao nao encontrado.');
    }

    $input = [
        'name' => plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome da notificacao.'),
        'itemtype' => $itemtype,
        'event' => $event,
        'entities_id' => $entitiesId,
        'is_recursive' => !empty($payload['is_recursive']) ? 1 : 0,
        'is_active' => !empty($payload['is_active']) ? 1 : 0,
    ];

    $isUpdate = $notificationId > 0;
    if ($isUpdate) {
        $input['id'] = $notificationId;
        if (!$notification->update($input)) {
            throw new RuntimeException('Falha ao atualizar notificacao.');
        }
    } else {
        $input['comment'] = 'Criado pelo cadastro de Notificacoes do DashGLPI.';
        $notificationId = (int) $notification->add($input);
        if ($notificationId <= 0) {
            throw new RuntimeException('Falha ao criar notificacao.');
        }
    }

    $excludedTargets = [];
    if ($isUpdate) {
        foreach ($DB->request([
            'FROM' => 'glpi_notificationtargets',
            'WHERE' => [
                'notifications_id' => $notificationId,
                'is_exclusion' => 1,
            ],
        ]) as $targetRow) {
            $excludedTargets[] = [
                'type' => (int) ($targetRow['type'] ?? 0),
                'items_id' => (int) ($targetRow['items_id'] ?? 0),
            ];
        }
    }

    $DB->delete(
        'glpi_notifications_notificationtemplates',
        [
            'notifications_id' => $notificationId,
            'mode' => Notification_NotificationTemplate::MODE_MAIL,
        ]
    );
    if ($templateId > 0) {
        if (!$DB->insert(
            'glpi_notifications_notificationtemplates',
            [
                'notifications_id' => $notificationId,
                'notificationtemplates_id' => $templateId,
                'mode' => Notification_NotificationTemplate::MODE_MAIL,
            ]
        )) {
            throw new RuntimeException('Falha ao vincular o modelo de e-mail.');
        }
    }

    $DB->delete('glpi_notificationtargets', ['notifications_id' => $notificationId]);
    foreach ($recipientKeys as $key) {
        [$type, $itemsId] = plugin_dashglpi_notification_parse_target_key($key);
        if (!$DB->insert(
            'glpi_notificationtargets',
            [
                'notifications_id' => $notificationId,
                'type' => $type,
                'items_id' => $itemsId,
                'is_exclusion' => 0,
            ]
        )) {
            throw new RuntimeException('Falha ao salvar destinatarios da notificacao.');
        }
    }
    foreach ($excludedTargets as $excludedTarget) {
        if (!$DB->insert(
            'glpi_notificationtargets',
            [
                'notifications_id' => $notificationId,
                'type' => (int) ($excludedTarget['type'] ?? 0),
                'items_id' => (int) ($excludedTarget['items_id'] ?? 0),
                'is_exclusion' => 1,
            ]
        )) {
            throw new RuntimeException('Falha ao preservar destinatarios de exclusao da notificacao.');
        }
    }

    return ['notification' => ['id' => $notificationId, 'status' => $isUpdate ? 'updated' : 'created']];
}

function plugin_dashglpi_notification_catalog(): array
{
    $itemtypes = [
        'Ticket' => 'Chamado',
        'Problem' => 'Problema',
        'Change' => 'Mudança',
        'MailCollector' => 'Coletor de e-mail',
        'User' => 'Usuário',
    ];

    $eventsByItemtype = [];
    $targetsByItemtype = [];
    foreach ($itemtypes as $itemtype => $label) {
        $targetClass = NotificationTarget::getInstanceClass($itemtype);
        if (!class_exists($targetClass)) {
            continue;
        }

        /** @var NotificationTarget $target */
        $target = new $targetClass(0, '', null, []);
        $events = $target->getAllEvents();
        if (!is_array($events) || $events === []) {
            continue;
        }

        $eventsByItemtype[$itemtype] = $events;
        foreach (array_keys($events) as $event) {
            /** @var NotificationTarget $eventTarget */
            $eventTarget = new $targetClass(0, (string) $event, null, []);
            $targetsByItemtype[$itemtype][(string) $event] = plugin_dashglpi_notification_targets_map($eventTarget);
        }
    }

    $oauthProviders = [];
    $oauthParameters = [];
    if (class_exists(OauthConfig::class)) {
        foreach (OauthConfig::getInstance()->getSupportedProviders() as $providerClass) {
            $oauthProviders[] = [
                'value' => $providerClass,
                'label' => $providerClass::getName(),
            ];
            $oauthParameters[$providerClass] = array_values(array_map(
                static function (array $spec): array {
                    return [
                        'key' => (string) ($spec['key'] ?? ''),
                        'label' => (string) ($spec['label'] ?? ''),
                        'helper' => (string) ($spec['helper'] ?? ''),
                        'default' => (string) ($spec['default'] ?? ''),
                    ];
                },
                $providerClass::getAdditionalParameters()
            ));
        }
    }

    $mailSettings = plugin_dashglpi_notification_mail_settings_snapshot();
    $specialEvents = [
        'ticket' => [
            'sla_reminder' => plugin_dashglpi_notification_special_event($eventsByItemtype['Ticket'] ?? [], 'recall'),
        ],
    ];

    return [
        'itemtypes' => $itemtypes,
        'events_by_itemtype' => $eventsByItemtype,
        'targets_by_itemtype' => $targetsByItemtype,
        'special_events' => $specialEvents,
        'automation' => [
            'queuednotification' => plugin_dashglpi_notification_crontask_snapshot('QueuedNotification', 'queuednotification'),
            'slaticket' => plugin_dashglpi_notification_crontask_snapshot('SlaLevel_Ticket', 'slaticket'),
        ],
        'mail_settings' => $mailSettings,
        'oauth' => [
            'providers' => $oauthProviders,
            'additional_parameters' => $oauthParameters,
        ],
    ];
}

function plugin_dashglpi_notification_targets_map(NotificationTarget $target): array
{
    $items = [];
    foreach ($target->notification_targets as $key => $value) {
        [$type, $itemsId] = array_pad(explode('_', (string) $key, 2), 2, '');
        $label = (string) ($target->notification_targets_labels[$type][$itemsId] ?? $key);
        $items[(string) $key] = $label;
    }

    asort($items, SORT_NATURAL | SORT_FLAG_CASE);
    return $items;
}

function plugin_dashglpi_notification_parse_target_key(string $key): array
{
    if (preg_match('/^(\d+)_(\d+)$/', $key, $matches) !== 1) {
        throw new RuntimeException('Destinatario invalido.');
    }

    return [(int) $matches[1], (int) $matches[2]];
}

function plugin_dashglpi_notification_decode_oauth_options(string $value): array
{
    $decoded = json_decode($value, true);
    if (!is_array($decoded) || array_is_list($decoded)) {
        return [];
    }

    return $decoded;
}

function plugin_dashglpi_notification_encode_oauth_options(array $value): string
{
    if ($value === [] || array_is_list($value)) {
        return '{}';
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function plugin_dashglpi_notification_special_event(array $events, string $preferredKey): array
{
    if (isset($events[$preferredKey])) {
        return [
            'available' => true,
            'event_key' => $preferredKey,
            'event_label' => (string) $events[$preferredKey],
        ];
    }

    foreach ($events as $key => $label) {
        $normalized = mb_strtolower(trim((string) $label));
        if (str_contains($normalized, 'automatic reminders of sla')) {
            return [
                'available' => true,
                'event_key' => (string) $key,
                'event_label' => (string) $label,
            ];
        }
    }

    return [
        'available' => false,
        'event_key' => '',
        'event_label' => '',
    ];
}

function plugin_dashglpi_notification_crontask_snapshot(string $itemtype, string $name): array
{
    $task = new CronTask();
    if (!$task->getFromDBbyName($itemtype, $name)) {
        return [
            'available' => false,
            'itemtype' => $itemtype,
            'name' => $name,
            'state' => 0,
            'mode' => 0,
            'allowmode' => 0,
            'frequency' => 0,
            'lastrun' => '',
            'lastcode' => '',
        ];
    }

    return [
        'available' => true,
        'id' => (int) ($task->fields['id'] ?? 0),
        'itemtype' => (string) ($task->fields['itemtype'] ?? $itemtype),
        'name' => (string) ($task->fields['name'] ?? $name),
        'state' => (int) ($task->fields['state'] ?? 0),
        'mode' => (int) ($task->fields['mode'] ?? 0),
        'allowmode' => (int) ($task->fields['allowmode'] ?? 0),
        'frequency' => (int) ($task->fields['frequency'] ?? 0),
        'lastrun' => (string) ($task->fields['lastrun'] ?? ''),
        'lastcode' => (string) ($task->fields['lastcode'] ?? ''),
    ];
}

function plugin_dashglpi_notification_update_crontask(string $itemtype, string $name): array
{
    $task = new CronTask();
    if (!$task->getFromDBbyName($itemtype, $name)) {
        throw new RuntimeException('Acao automatica ' . $name . ' nao encontrada.');
    }

    $input = [
        'id' => (int) $task->fields['id'],
        'state' => CronTask::STATE_WAITING,
        'mode' => CronTask::MODE_EXTERNAL,
        'allowmode' => CronTask::MODE_INTERNAL | CronTask::MODE_EXTERNAL,
        'frequency' => 60,
    ];

    if (!$task->update($input)) {
        throw new RuntimeException('Falha ao atualizar a acao automatica ' . $name . '.');
    }

    return plugin_dashglpi_notification_crontask_snapshot($itemtype, $name) + ['status' => 'updated'];
}

function plugin_dashglpi_notification_find_translation(int $templateId, string $language): ?array
{
    global $DB;

    $row = $DB->request([
        'FROM' => 'glpi_notificationtemplatetranslations',
        'WHERE' => [
            'notificationtemplates_id' => $templateId,
            'language' => $language,
        ],
        'ORDER' => ['id ASC'],
        'LIMIT' => 1,
    ])->current();

    return is_array($row) ? $row : null;
}

function plugin_dashglpi_notification_translation_update(int $translationId, int $templateId, array $fields): void
{
    global $DB;

    $existing = $DB->request([
        'FROM' => 'glpi_notificationtemplatetranslations',
        'WHERE' => [
            'id' => $translationId,
            'notificationtemplates_id' => $templateId,
        ],
        'LIMIT' => 1,
    ])->current();

    if (!$existing) {
        throw new RuntimeException('Traducao do modelo nao encontrada.');
    }

    if (!$DB->update('glpi_notificationtemplatetranslations', $fields, ['id' => $translationId])) {
        throw new RuntimeException('Falha ao salvar traducao do modelo.');
    }
}

function plugin_dashglpi_notification_translation_insert(array $fields): int
{
    global $DB;

    if (!$DB->insert('glpi_notificationtemplatetranslations', $fields)) {
        throw new RuntimeException('Falha ao criar traducao do modelo.');
    }

    return (int) $DB->insertId();
}

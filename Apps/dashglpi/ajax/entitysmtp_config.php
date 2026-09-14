<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/../inc/entitysmtpqueuednotification.class.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('entitysmtp', function (array $payload): array {
    $action = trim((string) ($payload['action'] ?? 'status'));

    return match ($action) {
        'load' => PluginDashglpiEntitysmtpQueuednotification::load($payload),
        'save_entity_smtp' => PluginDashglpiEntitysmtpQueuednotification::saveEntitySmtp($payload),
        'test_entity_smtp' => PluginDashglpiEntitysmtpQueuednotification::testEntitySmtp($payload),
        'activate_owner' => PluginDashglpiEntitysmtpQueuednotification::activateOwner(),
        'restore_glpi_owner' => PluginDashglpiEntitysmtpQueuednotification::restoreGlpiOwner(),
        'status' => PluginDashglpiEntitysmtpQueuednotification::status(),
        default => throw new RuntimeException('Acao SMTP por entidade invalida.'),
    };
}, 'Erro interno no bridge SMTP por entidade.');

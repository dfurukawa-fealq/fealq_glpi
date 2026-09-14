<?php

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';

plugin_dashglpi_admin_bridge_handle('mailcollector', function (array $payload): array {
    $action = (string) ($payload['action'] ?? 'create');

    if ($action === 'toggle') {
        $id = max(0, (int) ($payload['id'] ?? 0));
        $collector = new MailCollector();
        if (!$collector->getFromDB($id)) {
            throw new RuntimeException('Destinatario/coletor nao encontrado.');
        }

        $isActive = !empty($payload['is_active']) ? 1 : 0;
        if (!$collector->update(['id' => $id, 'is_active' => $isActive])) {
            throw new RuntimeException('Falha ao atualizar status do coletor.');
        }

        $collectorResult = ['id' => $id, 'status' => 'updated', 'is_active' => $isActive];
    } else {
        $name = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o nome do coletor.');
        $host = plugin_dashglpi_admin_bridge_name((string) ($payload['host'] ?? ''), 'Informe o host do coletor.');
        $login = plugin_dashglpi_admin_bridge_name((string) ($payload['login'] ?? ''), 'Informe o login do coletor.');
        $password = (string) ($payload['password'] ?? '');

        $collector = new MailCollector();
        $id = (int) $collector->add([
            'name' => $name,
            'mail_server' => $host,
            'server_port' => 993,
            'server_type' => '/imap',
            'server_ssl' => '/ssl',
            'server_tls' => '',
            'server_cert' => '/validate-cert',
            'server_rsh' => '',
            'server_secure' => '',
            'server_debug' => '',
            'server_mailbox' => 'INBOX',
            'login' => $login,
            'passwd' => $password,
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'filesize_max' => min(104857600, max(1024, (int) ($payload['filesize_max'] ?? 2097152))),
            'collect_only_unread' => !empty($payload['collect_only_unread']) ? 1 : 0,
            'comment' => 'Criado pelo cadastro de Destinatarios do DashGLPI.',
        ]);

        if ($id <= 0) {
            throw new RuntimeException('Falha ao criar destinatario/coletor.');
        }

        $collectorResult = ['id' => $id, 'status' => 'created', 'is_active' => !empty($payload['is_active']) ? 1 : 0];
    }

    return [
        'collector' => $collectorResult,
        'message' => 'Destinatario/coletor processado no GLPI.',
    ];
}, 'Erro interno no bridge de destinatarios.');

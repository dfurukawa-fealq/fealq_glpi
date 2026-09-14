<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/push.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

$user = dashglpi_current_user();
$userId = (int) ($user['id'] ?? 0);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'status');

try {
    if ($action === 'status') {
        dashglpi_push_ensure_tables();
        $row = dashglpi_fetch_one(
            "SELECT COUNT(*) AS total FROM " . DASHGLPI_PUSH_SUBSCRIPTIONS_TABLE . "
              WHERE users_id = ? AND is_active = 1",
            [$userId]
        );
        dashglpi_json([
            'ok' => true,
            'configured' => dashglpi_push_is_configured(),
            'subscribed' => ((int) ($row['total'] ?? 0)) > 0,
            'public_key' => dashglpi_push_public_key(),
        ]);
    }

    $payload = dashglpi_push_read_request_payload();
    if (!dashglpi_validate_csrf($payload['csrf_token'] ?? null)) {
        dashglpi_json(['ok' => false, 'error' => 'Token de segurança inválido.'], 419);
    }

    if ($action === 'subscribe') {
        if (!dashglpi_push_is_configured()) {
            dashglpi_json(['ok' => false, 'error' => 'Push ainda não foi configurado no servidor.'], 503);
        }
        $subscriptionPayload = $payload['subscription'] ?? $payload;
        if (is_string($subscriptionPayload)) {
            $subscriptionPayload = json_decode($subscriptionPayload, true);
        }
        if (!is_array($subscriptionPayload)) {
            throw new InvalidArgumentException('Dados da subscription ausentes.');
        }
        dashglpi_push_save_subscription(
            $userId,
            dashglpi_push_subscription_from_request($subscriptionPayload)
        );
        dashglpi_json(['ok' => true, 'subscribed' => true]);
    }

    if ($action === 'unsubscribe') {
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            throw new InvalidArgumentException('Endpoint Push ausente.');
        }
        dashglpi_push_remove_subscription($userId, $endpoint);
        dashglpi_json(['ok' => true, 'subscribed' => false]);
    }

    dashglpi_json(['ok' => false, 'error' => 'Ação Push inválida.'], 400);
} catch (InvalidArgumentException $e) {
    dashglpi_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[DashGLPI][push] ' . $e->getMessage());
    dashglpi_json(['ok' => false, 'error' => 'Não foi possível atualizar as notificações Push.'], 500);
}

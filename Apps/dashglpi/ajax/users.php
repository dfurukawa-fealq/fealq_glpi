<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/rules_engine.php';

dashglpi_require_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        dashglpi_json([
            'ok' => true,
            'entities' => dashglpi_admin_entities(),
            'groups' => dashglpi_admin_groups(),
            'profiles' => dashglpi_admin_profiles(),
            'users' => dashglpi_admin_glpi_users(),
            // PLAN-20260714-023: mapa users_id => telegram_user_id, pra pré-preencher
            // o campo no formulário sem alterar a query grande de dashglpi_admin_glpi_users().
            'telegram_user_ids' => dashglpi_user_channel_ids_map('telegram'),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dashglpi_json(['error' => 'Metodo nao permitido.'], 405);
        exit;
    }

    if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
        dashglpi_json(['error' => 'Token CSRF invalido.'], 403);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'preview_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $preview = dashglpi_admin_user_import_preview(
            $_FILES['csv_file'] ?? [],
            max(0, (int) ($_POST['default_profile_id'] ?? 0))
        );
        $_SESSION['dashglpi_user_import_preview'] = $preview;

        dashglpi_json([
            'ok' => true,
            'preview' => dashglpi_admin_import_preview_response($preview),
        ]);
        exit;
    }

    if ($action === 'save_telegram_id') {
        // PLAN-20260714-023: grava direto na tabela local do plugin — NÃO passa pelo
        // bridge GLPI (dashglpi_admin_bridge_request), porque não é campo de glpi_users.
        $usersId = max(0, (int) ($_POST['users_id'] ?? 0));
        if ($usersId <= 0) {
            dashglpi_json(['error' => 'Usuário inválido.'], 400);
            exit;
        }

        // Aceita 2 formatos (ver DashglpiTelegramChannel::buildMentionPayload()):
        // ID numérico (ex.: 123456789) ou @usuário (ex.: @joaosilva / joaosilva, o
        // "@" é normalizado automaticamente na gravação) — username é o que a
        // maioria das pessoas sabe de cabeça, sem precisar falar com @userinfobot.
        $telegramUserId = trim((string) ($_POST['telegram_user_id'] ?? ''));
        if ($telegramUserId !== '' && !ctype_digit($telegramUserId)) {
            $usernameCandidate = ltrim($telegramUserId, '@');
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $usernameCandidate)) {
                dashglpi_json([
                    'error' => 'Telegram user ID inválido — use o ID numérico (ex.: 123456789) '
                        . 'ou o @usuário (ex.: @joaosilva, 5-32 letras/números/_, começando com letra).',
                ], 400);
                exit;
            }
            $telegramUserId = '@' . $usernameCandidate;
        }

        dashglpi_user_channel_id_set($usersId, 'telegram', $telegramUserId);

        dashglpi_json([
            'ok' => true,
            'action' => 'save_telegram_id',
            'telegram_user_ids' => dashglpi_user_channel_ids_map('telegram'),
        ]);
        exit;
    }

    if ($action === 'confirm_import') {
        require_once __DIR__ . '/../inc/glpi_admin_import.php';
        $items = dashglpi_admin_import_confirm_items((string) ($_POST['preview_token'] ?? ''), 'dashglpi_user_import_preview');
        $result = dashglpi_admin_import_dispatch('user_config.php', $items);
        unset($_SESSION['dashglpi_user_import_preview']);

        dashglpi_json([
            'ok' => true,
            'result' => $result,
            'entities' => dashglpi_admin_entities(),
            'groups' => dashglpi_admin_groups(),
            'profiles' => dashglpi_admin_profiles(),
            'users' => dashglpi_admin_glpi_users(),
        ]);
        exit;
    }

    $payload = dashglpi_admin_user_payload($_POST);
    $result = dashglpi_admin_bridge_request('user_config.php', $payload);

    dashglpi_json([
        'ok' => true,
        'result' => $result,
        'entities' => dashglpi_admin_entities(),
        'groups' => dashglpi_admin_groups(),
        'profiles' => dashglpi_admin_profiles(),
        'users' => dashglpi_admin_glpi_users(),
    ]);
} catch (Throwable $e) {
    $message = trim((string) $e->getMessage());
    error_log('[DashGLPI] user admin error: ' . ($message !== '' ? $message : get_class($e)));
    dashglpi_json(['error' => $message !== '' ? $message : 'Erro interno no cadastro de usuario.'], 500);
}

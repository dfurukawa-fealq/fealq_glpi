<?php

require_once __DIR__ . '/../inc/bootstrap.php';

dashglpi_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dashglpi_json(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
}

if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
    dashglpi_json(['ok' => false, 'error' => 'Token de seguranca invalido.'], 403);
}

$currentUser = dashglpi_current_user();
$userId = (int) ($currentUser['id'] ?? 0);
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($userId <= 0) {
    dashglpi_json(['ok' => false, 'error' => 'Sessao expirada.'], 401);
}

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    dashglpi_json(['ok' => false, 'error' => 'Preencha todos os campos.'], 422);
}

if (strlen($newPassword) < 8) {
    dashglpi_json(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
}

if ($newPassword !== $confirmPassword) {
    dashglpi_json(['ok' => false, 'error' => 'A confirmacao da senha nao confere.'], 422);
}

$user = dashglpi_fetch_one(
    "SELECT id, password
     FROM glpi_users
     WHERE id = ?
       AND is_active = 1
       AND is_deleted = 0
     LIMIT 1",
    [$userId]
);

if (!$user || !password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
    dashglpi_json(['ok' => false, 'error' => 'Senha atual incorreta.'], 422);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = dashglpi_db()->prepare('UPDATE glpi_users SET password = ?, date_mod = NOW() WHERE id = ?');
$stmt->execute([$hash, $userId]);

dashglpi_json(['ok' => true, 'message' => 'Senha alterada com sucesso.']);
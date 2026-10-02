<?php

function dashglpi_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function dashglpi_timezone_name(): string
{
    static $timezone = null;
    if ($timezone !== null) {
        return $timezone;
    }

    $candidate = trim((string) dashglpi_env('TZ', dashglpi_env('TIMEZONE', 'America/Sao_Paulo')));
    if ($candidate === '') {
        $candidate = 'America/Sao_Paulo';
    }

    try {
        new DateTimeZone($candidate);
        $timezone = $candidate;
    } catch (Throwable $e) {
        $timezone = 'America/Sao_Paulo';
    }

    return $timezone;
}

function dashglpi_configure_timezone(): void
{
    date_default_timezone_set(dashglpi_timezone_name());
}

function dashglpi_mysql_timezone_offset(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone(dashglpi_timezone_name()));
    return $now->format('P');
}

function dashglpi_apply_mysql_timezone(PDO $pdo): void
{
    $stmt = $pdo->prepare('SET time_zone = ?');

    try {
        $stmt->execute([dashglpi_timezone_name()]);
        return;
    } catch (PDOException $e) {
        // MySQL images often ship without named timezone tables; offsets always work.
    }

    $stmt->execute([dashglpi_mysql_timezone_offset()]);
}

dashglpi_configure_timezone();

function dashglpi_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('DASHGLPISESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function dashglpi_require_auth(): void
{
    dashglpi_start_session();

    if (!empty($_SESSION['dashglpi_user_id'])) {
        return;
    }

    if (dashglpi_is_ajax_request()) {
        dashglpi_json(['error' => 'Authentication required.'], 401);
        exit;
    }

    dashglpi_redirect_to_login();
}

function dashglpi_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = dashglpi_env('GLPI_DB_HOST');
    $port = dashglpi_env('GLPI_DB_PORT', '3306');
    $name = dashglpi_env('GLPI_DB_NAME');
    $user = dashglpi_env('GLPI_DB_USER');
    $password = dashglpi_env('GLPI_DB_PASSWORD');

    if (!$host || !$name || !$user || $password === null) {
        throw new RuntimeException('Database environment is incomplete.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    dashglpi_apply_mysql_timezone($pdo);

    return $pdo;
}

function dashglpi_is_ajax_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($uri, '/ajax/') || str_contains($accept, 'application/json');
}

function dashglpi_login(string $login, string $password): bool
{
    dashglpi_start_session();

    $login = trim($login);
    if ($login === '' || $password === '') {
        return false;
    }

    $user = dashglpi_fetch_one(
        "SELECT id, name, realname, firstname, password
         FROM glpi_users
         WHERE name = ?
           AND is_active = 1
           AND is_deleted = 0
           AND password IS NOT NULL
         LIMIT 1",
        [$login]
    );

    if (!$user || !password_verify($password, (string) $user['password'])) {
        return false;
    }

    dashglpi_record_successful_login((int) $user['id']);

    session_regenerate_id(true);
    $_SESSION['dashglpi_user_id'] = (int) $user['id'];
    $_SESSION['dashglpi_user_name'] = (string) $user['name'];
    $_SESSION['dashglpi_user_display'] = dashglpi_user_display_name($user);

    return true;
}

function dashglpi_record_successful_login(int $usersId): void
{
    if ($usersId <= 0) {
        return;
    }

    try {
        $stmt = dashglpi_db()->prepare(
            'UPDATE glpi_users
             SET last_login = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$usersId]);
    } catch (Throwable $e) {
        error_log('[DashGLPI] failed to update user last_login: ' . $e->getMessage());
    }
}

function dashglpi_logout(): void
{
    dashglpi_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function dashglpi_current_user(): ?array
{
    dashglpi_start_session();

    if (empty($_SESSION['dashglpi_user_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['dashglpi_user_id'],
        'name' => (string) ($_SESSION['dashglpi_user_name'] ?? ''),
        'display' => (string) ($_SESSION['dashglpi_user_display'] ?? $_SESSION['dashglpi_user_name'] ?? 'DashGLPI'),
    ];
}

function dashglpi_redirect_to_login(): void
{
    $next = $_SERVER['REQUEST_URI'] ?? '/front/dashboard.php';
    header('Location: /front/login.php?next=' . rawurlencode($next));
    exit;
}

function dashglpi_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function dashglpi_csrf_token(): string
{
    dashglpi_start_session();

    if (empty($_SESSION['dashglpi_csrf'])) {
        $_SESSION['dashglpi_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['dashglpi_csrf'];
}

function dashglpi_validate_csrf(?string $token): bool
{
    dashglpi_start_session();
    return is_string($token)
        && isset($_SESSION['dashglpi_csrf'])
        && hash_equals((string) $_SESSION['dashglpi_csrf'], $token);
}

function dashglpi_user_display_name(array $user): string
{
    $display = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['realname'] ?? ''));
    return $display !== '' ? $display : (string) ($user['name'] ?? 'DashGLPI');
}

function dashglpi_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= substr($word, 0, 1);
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return strtoupper($initials !== '' ? $initials : 'DG');
}

function dashglpi_fetch_all(string $sql, array $params = []): array
{
    $stmt = dashglpi_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dashglpi_fetch_one(string $sql, array $params = []): ?array
{
    $stmt = dashglpi_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function dashglpi_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // Dados dinâmicos nunca devem ser cacheados pela borda (ex.: Akamai EAA): sem no-store,
    // abas de vida longa recebem listas obsoletas e arrastam "chamados fantasma" no Kanban.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    try {
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        error_log('[DashGLPI] JSON serialization error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(
            ['ok' => false, 'error' => 'Falha ao serializar resposta JSON.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
    exit;
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/itil_types.php';
require_once __DIR__ . '/access.php';

<?php
require_once __DIR__ . '/../inc/bootstrap.php';

dashglpi_start_session();

if (dashglpi_current_user()) {
    dashglpi_redirect('/front/dashboard.php');
}

$error = false;
$next = $_GET['next'] ?? '/front/dashboard.php';
if (!is_string($next) || !str_starts_with($next, '/')) {
    $next = '/front/dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = $_POST['next'] ?? '/front/dashboard.php';
    if (!is_string($next) || !str_starts_with($next, '/')) {
        $next = '/front/dashboard.php';
    }

    if (
        dashglpi_validate_csrf($_POST['csrf_token'] ?? null)
        && dashglpi_login((string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''))
    ) {
        dashglpi_redirect($next);
    }

    $error = true;
}

$csrf = dashglpi_csrf_token();

$settings = dashglpi_get_settings('reports');
$appName = (string) $settings['app_name'];
$appLogoLight = (string) ($settings['logo_light_url'] ?? $settings['logo_url']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar no <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="/vendor/css/fontawesome.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body class="dashglpi-login-page light-mode">
    <main class="dashglpi-login-shell">
        <section class="dashglpi-login-panel">
            <div class="dashglpi-login-brand">
                <div class="sidebar-logo">
                    <img src="<?= htmlspecialchars($appLogoLight, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>" onerror="this.hidden=true;">
                    <i class="fas fa-terminal"></i>
                </div>
                <div>
                    <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <!-- <p>Acesse com seu usuário GLPI</p> -->
                </div>
            </div>

            <?php if ($error): ?>
                <div class="dashglpi-login-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Usuário ou senha inválidos.</span>
                </div>
            <?php endif; ?>

            <form method="post" class="dashglpi-login-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">

                <label for="login">Usuário</label>
                <div class="dashglpi-login-field">
                    <i class="fas fa-user"></i>
                    <input id="login" name="login" type="text" autocomplete="username" required autofocus>
                </div>

                <label for="password">Senha</label>
                <div class="dashglpi-login-field">
                    <i class="fas fa-lock"></i>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="dashglpi-login-submit">
                    <span>Entrar</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </section>
    </main>
</body>
</html>

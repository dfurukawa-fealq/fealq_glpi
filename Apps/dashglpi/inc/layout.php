<?php

function dashglpi_asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pluginPrefix = '/plugins/dashglpi/';
    $url = str_contains($scriptName, $pluginPrefix)
        ? $pluginPrefix . $path
        : '/' . $path;
    $file = __DIR__ . '/../public/' . $path;

    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }

    return $url;
}

function dashglpi_render_sidebar(string $active, string $context = 'dashboard', string $role = 'Dashboard', string $settingsSection = 'sla'): void
{
    $settings = dashglpi_get_settings('reports');
    $appName = (string) $settings['app_name'];
    $appLogoLight = (string) ($settings['logo_light_url'] ?? $settings['logo_url']);
    $appLogoDark = (string) ($settings['logo_dark_url'] ?? $settings['logo_url']);
    $glpiRoot = rtrim(dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
    $currentUser = dashglpi_current_user();
    $userContext = dashglpi_current_user_context();
    $userName = $currentUser['display'] ?? 'DashGLPI';
    $userInitials = dashglpi_initials($userName);
    $isAdmin = dashglpi_current_user_is_admin();
    $showGlpiBackLink = empty($userContext['has_profile_rule']);
    $glpiCentralUrl = $glpiRoot ? $glpiRoot . '/front/central.php' : '';
    $dashNewTicketUrl = '/front/dashboard.php#ticketNew';
    $dashNewTicketAttrs = '';
    $dashNewTicketOnclick = '';
    if ($context === 'dashboard') {
        $dashNewTicketUrl = '#ticketNew';
        $dashNewTicketAttrs = 'data-page="ticketNew"';
        $dashNewTicketOnclick = "showPage('ticketNew', this); return false;";
    }

    $items = [
        ['key' => 'dashboard', 'label' => 'Visão Geral', 'icon' => 'fa-th-large', 'page' => 'dashboard'],
        ['key' => 'tickets', 'label' => 'Chamados', 'icon' => 'fa-ticket-alt', 'page' => 'tickets'],
        ['key' => 'ticketsKanban', 'label' => 'Kanban', 'icon' => 'fa-columns', 'page' => 'ticketsKanban', 'access_page' => 'tickets'],
        ['key' => 'sla', 'label' => 'Monitor SLA', 'icon' => 'fa-clock', 'page' => 'sla'],
        ['key' => 'ranking', 'label' => 'Ranking Técnicos', 'icon' => 'fa-trophy', 'page' => 'ranking'],
    ];
    $items = array_values(array_filter(
        $items,
        static fn(array $item): bool => dashglpi_current_user_can_access_page((string) ($item['access_page'] ?? $item['page'] ?? ''))
    ));

    if ($isAdmin) {
        $items[] = ['key' => 'health', 'label' => 'Saúde do GLPI', 'icon' => 'fa-heartbeat', 'page' => 'health'];
    }

    $registrationItems = [
        ['key' => 'entities', 'label' => 'Cliente/Entidade', 'icon' => 'fa-building', 'page' => 'entities'],
        ['key' => 'groups', 'label' => 'Grupo', 'icon' => 'fa-users', 'page' => 'groups'],
        ['key' => 'categories', 'label' => 'Categorias ITIL', 'icon' => 'fa-sitemap', 'page' => 'categories'],
        ['key' => 'users', 'label' => 'Usuários', 'icon' => 'fa-user-plus', 'page' => 'users'],
        ['key' => 'profiles', 'label' => 'Perfil', 'icon' => 'fa-id-badge', 'page' => 'profiles'],
    ];
    $registrationsOpen = $isAdmin && in_array($active, ['entities', 'groups', 'categories', 'users', 'profiles'], true);

    $importItems = [
        ['key' => 'computerImport', 'label' => 'Computadores', 'icon' => 'fa-desktop', 'page' => 'computerImport'],
        ['key' => 'monitorImport', 'label' => 'Monitores', 'icon' => 'fa-tv', 'page' => 'monitorImport'],
        ['key' => 'ticketImport', 'label' => 'Tickets', 'icon' => 'fa-ticket-alt', 'page' => 'ticketImport'],
    ];
    $importsOpen = $isAdmin && in_array($active, ['computerImport', 'monitorImport', 'ticketImport'], true);

    $settingsItems = [
        ['key' => 'sla', 'label' => 'SLA Simples', 'icon' => 'fa-business-time', 'section' => 'sla'],
        ['key' => 'profile_access', 'label' => 'Acesso por Perfil', 'icon' => 'fa-user-shield', 'section' => 'profile_access'],
        ['key' => 'reports', 'label' => 'Relatórios', 'icon' => 'fa-file-alt', 'section' => 'reports'],
        ['key' => 'notifications', 'label' => 'Notificações', 'icon' => 'fa-bell', 'section' => 'notifications'],
        ['key' => 'alerting', 'label' => 'Canais de Alerta', 'icon' => 'fa-satellite-dish', 'section' => 'alerting'],
        ['key' => 'rules', 'label' => 'Regras de Automação', 'icon' => 'fa-diagram-project', 'section' => 'rules'],
        ['key' => 'recipients', 'label' => 'Destinatários', 'icon' => 'fa-user-tag', 'section' => 'recipients'],
        ['key' => 'sql_console', 'label' => 'Console SQL', 'icon' => 'fa-database', 'href' => '/front/sql-console.php'],
        ['key' => 'general', 'label' => 'Gerais', 'icon' => 'fa-sliders-h', 'section' => 'general'],
    ];
    $settingsOpen = $isAdmin && ($active === 'settings' || $active === 'sql_console');
    ?>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img class="sidebar-logo-img sidebar-logo-img-dark" src="<?= htmlspecialchars($appLogoDark, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>" width="46" height="40" onerror="this.hidden=true;">
                <img class="sidebar-logo-img sidebar-logo-img-light" src="<?= htmlspecialchars($appLogoLight, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>" width="46" height="40" onerror="this.hidden=true;">
                <i class="fas fa-terminal"></i>
            </div>
            <div class="sidebar-title"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></div>
            <button class="sidebar-close" onclick="toggleMenu()">
                <i class="fas fa-chevron-left sidebar-close-icon-desktop"></i>
                <i class="fas fa-xmark sidebar-close-icon-mobile"></i>
            </button>
        </div>

        <div class="sidebar-quick-actions">
            <a href="<?= htmlspecialchars($dashNewTicketUrl, ENT_QUOTES, 'UTF-8') ?>" class="menu-link menu-link-primary menu-link-ticket-new sidebar-ticket-cta"<?= $dashNewTicketAttrs !== '' ? ' ' . $dashNewTicketAttrs : '' ?><?= $dashNewTicketOnclick !== '' ? ' onclick="' . $dashNewTicketOnclick . '"' : '' ?>>
                <i class="fas fa-plus-circle"></i>
                <span>Novo Chamado</span>
            </a>
        </div>

        <nav class="menu-nav">
            <?php foreach ($items as $item): ?>
                <?php
                $classes = 'menu-link' . ($active === $item['key'] ? ' active' : '');
                if (!empty($item['href'])) {
                    $href = (string) $item['href'];
                    $attrs = '';
                    $onclick = '';
                } elseif ($context === 'dashboard') {
                    $href = '#' . $item['page'];
                    $attrs = 'data-page="' . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . '"';
                    $onclick = "showPage('" . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . "', this); return false;";
                } else {
                    $href = '/front/dashboard.php#' . $item['page'];
                    $attrs = '';
                    $onclick = '';
                }
                ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"<?= $attrs ? ' ' . $attrs : '' ?><?= $onclick !== '' ? ' onclick="' . $onclick . '"' : '' ?>>
                    <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($isAdmin): ?>
                <details class="menu-group<?= $registrationsOpen ? ' open' : '' ?>"<?= $registrationsOpen ? ' open' : '' ?>>
                    <summary class="menu-link menu-group-summary<?= $registrationsOpen ? ' active' : '' ?>">
                        <i class="fas fa-folder-open"></i>
                        <span>Cadastros</span>
                        <i class="fas fa-chevron-down menu-group-caret"></i>
                    </summary>
                    <div class="menu-subnav">
                        <?php foreach ($registrationItems as $item): ?>
                            <?php
                            $subClasses = 'menu-sublink' . ($active === $item['key'] ? ' active' : '');
                            if ($context === 'dashboard') {
                                $href = '#' . $item['page'];
                                $attrs = 'data-page="' . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . '"';
                                $onclick = "showPage('" . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . "', this); return false;";
                            } else {
                                $href = '/front/dashboard.php#' . $item['page'];
                                $attrs = '';
                                $onclick = '';
                            }
                            ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($subClasses, ENT_QUOTES, 'UTF-8') ?>"<?= $attrs ? ' ' . $attrs : '' ?><?= $onclick !== '' ? ' onclick="' . $onclick . '"' : '' ?>>
                                <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
                <details class="menu-group<?= $importsOpen ? ' open' : '' ?>"<?= $importsOpen ? ' open' : '' ?>>
                    <summary class="menu-link menu-group-summary<?= $importsOpen ? ' active' : '' ?>">
                        <i class="fas fa-file-import"></i>
                        <span>Importações</span>
                        <i class="fas fa-chevron-down menu-group-caret"></i>
                    </summary>
                    <div class="menu-subnav">
                        <?php foreach ($importItems as $item): ?>
                            <?php
                            $subClasses = 'menu-sublink' . ($active === $item['key'] ? ' active' : '');
                            if ($context === 'dashboard') {
                                $href = '#' . $item['page'];
                                $attrs = 'data-page="' . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . '"';
                                $onclick = "showPage('" . htmlspecialchars($item['page'], ENT_QUOTES, 'UTF-8') . "', this); return false;";
                            } else {
                                $href = '/front/dashboard.php#' . $item['page'];
                                $attrs = '';
                                $onclick = '';
                            }
                            ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($subClasses, ENT_QUOTES, 'UTF-8') ?>"<?= $attrs ? ' ' . $attrs : '' ?><?= $onclick !== '' ? ' onclick="' . $onclick . '"' : '' ?>>
                                <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
                <details class="menu-group<?= $settingsOpen ? ' open' : '' ?>"<?= $settingsOpen ? ' open' : '' ?>>
                    <summary class="menu-link menu-group-summary<?= $settingsOpen ? ' active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                        <i class="fas fa-chevron-down menu-group-caret"></i>
                    </summary>
                    <div class="menu-subnav">
                        <?php foreach ($settingsItems as $item): ?>
                            <?php
                            $isLinkActive = !empty($item['href'])
                                ? $active === $item['key']
                                : ($active === 'settings' && $settingsSection === $item['section']);
                            $subClasses = 'menu-sublink' . ($isLinkActive ? ' active' : '');
                            $href = !empty($item['href'])
                                ? (string) $item['href']
                                : '/front/settings.php?section=' . rawurlencode((string) $item['section']);
                            ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($subClasses, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fas <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
            <a href="#changePassword" class="menu-link" onclick="openChangePasswordModal(); return false;">
                <i class="fas fa-key"></i>
                <span>Alterar Senha</span>
            </a>
            <div style="flex: 1;"></div>
            <?php if ($showGlpiBackLink): ?>
            <a href="<?= htmlspecialchars($glpiCentralUrl ?: '#', ENT_QUOTES, 'UTF-8') ?>" class="menu-link" title="Voltar ao GLPI">
                <i class="fas fa-arrow-left"></i>
                <span>Voltar ao GLPI</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar"><?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="user-role"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="user-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a class="logout-btn" href="/front/logout.php" title="Sair">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <div class="mobile-menu-backdrop" id="mobileMenuBackdrop" onclick="closeMobileMenu()" aria-hidden="true"></div>

    <div class="mobile-footer-shell" aria-label="Atalhos móveis">
        <a class="mobile-new-ticket-btn" id="mobileNewTicketBtn" href="<?= htmlspecialchars($dashNewTicketUrl, ENT_QUOTES, 'UTF-8') ?>" title="Novo Chamado" data-default-href="<?= htmlspecialchars($dashNewTicketUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Novo Chamado</span>
        </a>
        <div class="mobile-footer-bar">
            <?php if ($context === 'dashboard' && dashglpi_current_user_can_access_page('tickets')): ?>
            <button class="mobile-footer-action mobile-footer-push" id="mobilePushNotifications" type="button" onclick="togglePushNotifications()" title="Ativar notificações Push" aria-label="Ativar notificações Push">
                <i class="fas fa-broadcast-tower"></i>
                <span>Push</span>
            </button>
            <?php endif; ?>
            <?php if ($context === 'dashboard'): ?>
            <button class="mobile-footer-action mobile-footer-ticket-back" type="button" onclick="showPage(defaultLandingPage()); return false;" title="Voltar">
                <i class="fas fa-arrow-left"></i>
                <span>Voltar</span>
            </button>
            <?php endif; ?>
            <button class="mobile-footer-action mobile-footer-search" type="button" data-mobile-ticket-search aria-expanded="false" aria-controls="ticketsMobileSearchPanel" title="Buscar chamados">
                <i class="fas fa-search"></i>
                <span>Buscar</span>
            </button>
            <button class="mobile-footer-action mobile-footer-menu" type="button" onclick="toggleMenu()" title="Menu">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
            <button class="mobile-footer-action mobile-footer-theme theme-toggle" type="button" onclick="toggleTheme()" title="Alternar tema">
                <i class="fas fa-moon"></i>
                <span>Tema</span>
            </button>
            <a class="mobile-footer-action mobile-footer-logout" href="/front/logout.php" title="Sair">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </div>

    <div class="dash-modal" id="changePasswordModal" hidden>
        <div class="dash-modal-backdrop" onclick="closeChangePasswordModal()" aria-hidden="true"></div>
        <form class="dash-modal-dialog admin-form" id="changePasswordForm" autocomplete="off">
            <div class="dash-modal-header">
                <div>
                    <h3 class="admin-card-title">Alterar Senha</h3>
                    <p class="admin-help-text">Atualize a senha usada para acessar o DashGLPI.</p>
                </div>
                <button class="icon-btn" type="button" onclick="closeChangePasswordModal()" title="Fechar">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <label class="admin-field">
                <span>Senha atual</span>
                <div class="admin-password-control">
                    <input type="password" name="current_password" autocomplete="current-password" required>
                    <button type="button" class="admin-password-btn" data-change-password-toggle title="Mostrar senha" aria-label="Mostrar senha atual" aria-pressed="false">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </label>
            <label class="admin-field">
                <span>Nova senha</span>
                <div class="admin-password-control">
                    <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="admin-password-btn" data-change-password-toggle title="Mostrar senha" aria-label="Mostrar nova senha" aria-pressed="false">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small>Use pelo menos 8 caracteres.</small>
            </label>
            <label class="admin-field">
                <span>Confirmar nova senha</span>
                <div class="admin-password-control">
                    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="admin-password-btn" data-change-password-toggle title="Mostrar senha" aria-label="Mostrar confirmação da nova senha" aria-pressed="false">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </label>
            <button class="admin-submit" type="submit">
                <i class="fas fa-save"></i>
                <span>Salvar senha</span>
            </button>
            <div class="admin-status" id="changePasswordStatus"></div>
        </form>
    </div>
    <?php
}

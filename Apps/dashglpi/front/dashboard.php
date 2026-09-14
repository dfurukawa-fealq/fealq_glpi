<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sla_simple.php';
require_once __DIR__ . '/../inc/push.php';
require_once __DIR__ . '/../inc/layout.php';
dashglpi_require_auth();

$pluginRoot = '';
$currentUser = dashglpi_current_user();
$userContext = dashglpi_current_user_context();
$isAdmin = dashglpi_current_user_is_admin();
$allowedPages = array_values($userContext['allowed_pages'] ?? []);
$defaultPage = (string) ($userContext['default_page'] ?? dashglpi_first_allowed_page($allowedPages));
$showRestrictedDashboardMetrics = empty($userContext['has_profile_rule']);
$canTicketReports = empty($userContext['has_profile_rule']);
$isHelpdeskView = !empty($userContext['is_helpdesk_profile']);
$canDashboard = dashglpi_current_user_can_access_page('dashboard');
$canTickets = dashglpi_current_user_can_access_page('tickets');
$canSla = dashglpi_current_user_can_access_page('sla');
$canRanking = dashglpi_current_user_can_access_page('ranking');
$canAssets = dashglpi_current_user_can_access_page('assets');
$visibleItilTypeKeys = dashglpi_current_user_visible_itil_types();
$extraItilTypes = [];
$itilTypeCatalog = [];
foreach ($visibleItilTypeKeys as $itilTypeKey) {
    $itilType = dashglpi_itil_type($itilTypeKey);
    if (!$itilType) {
        continue;
    }
    $itilTypeCatalog[] = [
        'key' => $itilTypeKey,
        'label' => (string) $itilType['label'],
        'label_plural' => (string) $itilType['label_plural'],
        'has_sla' => (bool) $itilType['has_sla'],
    ];
    if ($itilTypeKey !== 'ticket') {
        $extraItilTypes[$itilTypeKey] = $itilType;
    }
}
$settings = dashglpi_get_settings('reports');
$slaSettings = dashglpi_get_settings('sla_simple');
$slaDisplayLabels = dashglpi_sla_display_labels($slaSettings);
$appName = (string) $settings['app_name'];
$glpiPublicUrl = rtrim((string) dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($appName); ?></title>
    <meta name="theme-color" content="#101827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="<?= htmlspecialchars(dashglpi_asset_url('manifest.webmanifest'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(dashglpi_asset_url('pwa-icons/icon-192.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(dashglpi_asset_url('pwa-icons/icon-192.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/fontawesome.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(dashglpi_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script>
        // Passa o root do plugin para o JS
        const DASHGLPI_ROOT = '<?php echo $pluginRoot; ?>';
        const DASHGLPI_SCREEN_TYPE = 'dashboard';
        const DASHGLPI_CSRF_TOKEN = '<?php echo dashglpi_csrf_token(); ?>';
        const DASHGLPI_PUSH_ENABLED = <?= dashglpi_push_is_configured() ? 'true' : 'false' ?>;
        const DASHGLPI_PUSH_PUBLIC_KEY = <?= json_encode(dashglpi_push_public_key(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_GLPI_ROOT = <?= json_encode($glpiPublicUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_ALLOWED_PAGES = <?= json_encode($allowedPages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_DEFAULT_PAGE = <?= json_encode($defaultPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_ADMIN_BYPASS = <?= !empty($userContext['is_admin_bypass']) ? 'true' : 'false' ?>;
        const DASHGLPI_TICKET_REPORTS_ENABLED = <?= $canTicketReports ? 'true' : 'false' ?>;
        const DASHGLPI_IS_RESTRICTED_VIEW = <?= !empty($userContext['has_profile_rule']) ? 'true' : 'false' ?>;
        const DASHGLPI_IS_HELPDESK_VIEW = <?= !empty($userContext['is_helpdesk_profile']) ? 'true' : 'false' ?>;
        const DASHGLPI_CURRENT_USER_ID = <?= (int) ($userContext['user_id'] ?? 0) ?>;
        const DASHGLPI_CURRENT_USER_DISPLAY = <?= json_encode((string) ($currentUser['display'] ?? 'usuário atual'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_SLA_DISPLAY_LABELS = <?= json_encode($slaDisplayLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_ITIL_TYPES = <?= json_encode($itilTypeCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>
    <div class="tv-indicator">
        <i class="fas fa-tv"></i> MODO TV ATIVO
    </div>

    <button class="floating-menu-btn" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <?php dashglpi_render_sidebar('dashboard', 'dashboard', 'Dashboard'); ?>

    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3 class="notification-title">Notificações</h3>
            <button class="notification-clear" onclick="clearNotifications()">Limpar Tudo</button>
        </div>
        <div class="notification-list" id="notificationList">
        </div>
    </div>

    <div class="edit-mode-indicator" id="editModeIndicator">
        <i class="fas fa-edit"></i>
        <span>Modo de Edição Ativo</span>
        <button class="edit-mode-btn" onclick="saveLayout()">Salvar Layout</button>
        <button class="edit-mode-btn" onclick="toggleEditMode()">Sair</button>
    </div>

    <main class="main-content" id="mainContent">
        <?php if ($canDashboard): ?>
        <div class="page-section<?= $defaultPage === 'dashboard' ? ' active' : '' ?>" id="dashboardSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Visão Geral do Serviço</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-clock"></i>
                        <span>Última atualização: <span id="clock">Carregando...</span></span>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="search-box dashboard-search-box">
                        <input type="text" placeholder="Busca rápida...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="dashboard-period-tabs dashboard-overview-period-tabs" aria-label="Período da visão geral">
                        <button class="dashboard-period-btn" type="button" data-dashboard-period="7">7 dias</button>
                        <button class="dashboard-period-btn active" type="button" data-dashboard-period="30">30 dias</button>
                        <button class="dashboard-period-btn" type="button" data-dashboard-period="90">90 dias</button>
                        <button class="dashboard-period-btn" type="button" data-dashboard-period="180">6 meses</button>
                    </div>
                    <button class="icon-btn dashboard-action-btn dashboard-action-tv" onclick="toggleTVMode()" title="Modo TV">
                        <i class="fas fa-tv"></i>
                    </button>
                    <button class="icon-btn dashboard-action-btn dashboard-action-bell" onclick="toggleNotifications()" title="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="badge-notification" id="notificationBadge" hidden>0</span>
                    </button>
                    <?php if ($canTickets): ?>
                    <button class="icon-btn dashboard-action-btn dashboard-action-push" id="pushNotificationsBtn" onclick="togglePushNotifications()" title="Ativar notificações Push" aria-label="Ativar notificações Push">
                        <i class="fas fa-bell"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (!$showRestrictedDashboardMetrics): ?>
            <div class="grid-kpi dashboard-limited-kpi-grid">
                <div class="glass-card kpi-card glow-success">
                    <div>
                        <span class="kpi-icon success"><i class="fas fa-inbox"></i></span>
                        <div class="kpi-value" id="top-total"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Total de Chamados</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-tasks"></i></span>
                        <div class="kpi-value" id="top-andamento"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Em Andamento</div>
                </div>
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-plus-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-abertos">0</div>
                    <div class="detail-label">Novos Chamados</div>
                </div>
                <div class="glass-card detail-card glow-primary">
                    <div class="detail-card-header">
                        <span class="kpi-icon primary"><i class="fas fa-user-check"></i></span>
                    </div>
                    <div class="detail-value" id="bot-atribuidos">0</div>
                    <div class="detail-label">Com Técnico</div>
                </div>
                <div class="glass-card detail-card glow-warning">
                    <div class="detail-card-header">
                        <span class="kpi-icon warning"><i class="fas fa-pause-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-pendentes">0</div>
                    <div class="detail-label">Aguardando</div>
                </div>
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-check-double"></i></span>
                    </div>
                    <div class="detail-value" id="bot-finalizados">0</div>
                    <div class="detail-label">Resolvidos</div>
                </div>
            </div>
            <?php else: ?>
            <div class="grid-kpi">
                <div class="glass-card kpi-card glow-success">
                    <div>
                        <span class="kpi-icon success"><i class="fas fa-inbox"></i></span>
                        <div class="kpi-value" id="top-total"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Total de Chamados</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-tasks"></i></span>
                        <div class="kpi-value" id="top-andamento"><span class="skeleton">00</span></div>
                    </div>
                    <div class="kpi-label">Em Andamento</div>
                </div>
                <div class="glass-card kpi-card glow-success">
                    <div>
                        <span class="kpi-icon success"><i class="fas fa-check-circle"></i></span>
                        <div class="kpi-value" id="top-taxa"><span class="skeleton">00%</span></div>
                    </div>
                    <div class="kpi-label">Taxa de Conclusão</div>
                </div>
                <div class="glass-card kpi-card glow-danger">
                    <div>
                        <span class="kpi-icon danger"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="kpi-value" id="top-sla"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">SLA Vencido</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-hourglass-half"></i></span>
                        <div class="kpi-value" id="top-tempo"><span class="skeleton">0h</span></div>
                    </div>
                    <div class="kpi-label">Tempo Médio</div>
                </div>
                <div class="glass-card kpi-card glow-warning">
                    <div>
                        <span class="kpi-icon warning"><i class="fas fa-redo"></i></span>
                        <div class="kpi-value" id="top-reabertos"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Reabertos</div>
                </div>
            </div>

            <div class="grid-detail">
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-plus-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-abertos">0</div>
                    <div class="detail-label">Novos Chamados</div>
                </div>
                <div class="glass-card detail-card glow-primary">
                    <div class="detail-card-header">
                        <span class="kpi-icon primary"><i class="fas fa-user-check"></i></span>
                    </div>
                    <div class="detail-value" id="bot-atribuidos">0</div>
                    <div class="detail-label">Com Técnico</div>
                </div>
                <div class="glass-card detail-card glow-warning">
                    <div class="detail-card-header">
                        <span class="kpi-icon warning"><i class="fas fa-pause-circle"></i></span>
                    </div>
                    <div class="detail-value" id="bot-pendentes">0</div>
                    <div class="detail-label">Aguardando</div>
                </div>
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-check-double"></i></span>
                    </div>
                    <div class="detail-value" id="bot-finalizados">0</div>
                    <div class="detail-label">Resolvidos</div>
                </div>
                <div class="glass-card detail-card glow-warning queue-attention-card" id="queuePendingCard">
                    <div class="detail-card-header">
                        <span class="kpi-icon warning"><i class="fas fa-envelope"></i></span>
                    </div>
                    <div class="detail-value" id="queue-pending-count"><span class="skeleton">0</span></div>
                    <div class="detail-label">Quantidade Pendente</div>
                </div>
                <div class="glass-card detail-card glow-primary queue-attention-card" id="queueLastSentCard">
                    <div class="detail-card-header">
                        <span class="kpi-icon primary"><i class="fas fa-paper-plane"></i></span>
                    </div>
                    <div class="detail-value detail-value-text" id="queue-last-sent"><span class="skeleton">00/00/0000 - 00:00:00</span></div>
                    <div class="detail-label">Última Notificação Enviada</div>
                </div>
            </div>

            <?php if ($extraItilTypes): ?>
            <!-- KPIs segmentados de Problema/Manutenção (PLAN-20260709-019, Decisão 5:
                 contadores próprios, sem alterar os números de chamados acima). -->
            <div class="grid-detail" id="itilObjectsKpis">
                <?php foreach ($extraItilTypes as $itilKey => $itilType): ?>
                <?php $itilIcon = $itilKey === 'problem' ? 'fa-bug' : 'fa-wrench'; ?>
                <div class="glass-card detail-card glow-primary">
                    <div class="detail-card-header">
                        <span class="kpi-icon primary"><i class="fas <?= $itilIcon ?>"></i></span>
                    </div>
                    <div class="detail-value" id="itil-<?= htmlspecialchars($itilKey) ?>-abertos">0</div>
                    <div class="detail-label"><?= htmlspecialchars($itilType['label_plural']) ?> em Aberto</div>
                </div>
                <div class="glass-card detail-card glow-danger">
                    <div class="detail-card-header">
                        <span class="kpi-icon danger"><i class="fas fa-exclamation-triangle"></i></span>
                    </div>
                    <div class="detail-value" id="itil-<?= htmlspecialchars($itilKey) ?>-vencidos">0</div>
                    <div class="detail-label"><?= htmlspecialchars($itilType['label_plural']) ?> com Prazo Vencido</div>
                </div>
                <div class="glass-card detail-card glow-success">
                    <div class="detail-card-header">
                        <span class="kpi-icon success"><i class="fas fa-check-double"></i></span>
                    </div>
                    <div class="detail-value" id="itil-<?= htmlspecialchars($itilKey) ?>-finalizados">0</div>
                    <div class="detail-label"><?= htmlspecialchars($itilType['label_plural']) ?> Solucionados</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="grid-charts">
                <?php if ($showRestrictedDashboardMetrics): ?>
                <div class="glass-card chart-card chart-card-with-tabs">
                    <div class="chart-header">
                        <h3 class="chart-title">Tickets Criados</h3>
                        <div class="dashboard-period-tabs chart-range-tabs" aria-label="Intervalo do gráfico de tickets criados">
                            <button class="dashboard-period-btn active" type="button" data-created-tickets-range="1">1H</button>
                            <button class="dashboard-period-btn" type="button" data-created-tickets-range="3">3H</button>
                            <button class="dashboard-period-btn" type="button" data-created-tickets-range="6">6H</button>
                            <button class="dashboard-period-btn" type="button" data-created-tickets-range="12">12H</button>
                            <button class="dashboard-period-btn" type="button" data-created-tickets-range="24">24H</button>
                            <button class="dashboard-period-btn" type="button" data-created-tickets-range="48">48H</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="lineChart"></canvas></div>
                </div>
                <div class="glass-card chart-card chart-card-with-tabs">
                    <div class="chart-header">
                        <h3 class="chart-title">Notificações Enviadas</h3>
                        <div class="dashboard-period-tabs chart-range-tabs" aria-label="Intervalo do gráfico de notificações">
                            <button class="dashboard-period-btn active" type="button" data-notification-range="1">1H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="3">3H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="6">6H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="12">12H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="24">24H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="48">48H</button>
                        </div>
                    </div>
                    <div class="chart-container"><canvas id="notificationChart"></canvas></div>
                </div>
                <?php endif; ?>
                <div class="glass-card chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title" id="monthlyChartTitle">Abertos vs Solucionados</h3>
                    </div>
                    <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
                </div>
                <div class="glass-card chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Categorias</h3>
                    </div>
                    <div class="chart-container"><canvas id="barChart"></canvas></div>
                </div>
            </div>

        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="page-section" id="healthSection">
            <header class="page-header health-page-header">
                <div class="page-title-wrapper">
                    <h1>Saúde do GLPI</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-heartbeat"></i>
                        <span>Última leitura: <span id="healthRefreshedAt">Carregando...</span></span>
                    </div>
                </div>
                <div class="header-actions health-header-actions">
                    <span class="health-overall-pill is-loading" id="healthOverallStatus">Carregando</span>
                    <span class="health-overall-counts" id="healthOverallCounts">0 críticos • 0 alertas • 0 info</span>
                    <a class="page-action-btn page-action-btn--compact" id="healthLinkCrontask" href="#" target="_blank" rel="noopener">
                        <i class="fas fa-stopwatch"></i>
                        <span>Ações automáticas</span>
                    </a>
                    <a class="page-action-btn page-action-btn--compact" id="healthLinkEmail" href="#" target="_blank" rel="noopener">
                        <i class="fas fa-envelope"></i>
                        <span>E-mail</span>
                    </a>
                    <a class="page-action-btn page-action-btn--compact" id="healthLinkNotifications" href="#" target="_blank" rel="noopener">
                        <i class="fas fa-bell"></i>
                        <span>Notificações</span>
                    </a>
                    <a class="page-action-btn page-action-btn--compact" id="healthLinkCollectors" href="#" target="_blank" rel="noopener">
                        <i class="fas fa-inbox"></i>
                        <span>Coletores</span>
                    </a>
                </div>
            </header>

            <div class="grid-kpi health-kpi-grid">
                <div class="glass-card kpi-card glow-warning">
                    <div>
                        <span class="kpi-icon warning"><i class="fas fa-envelope-open-text"></i></span>
                        <div class="kpi-value" id="healthKpiQueuePending"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Pendências da fila</div>
                </div>
                <div class="glass-card kpi-card glow-danger">
                    <div>
                        <span class="kpi-icon danger"><i class="fas fa-hourglass-end"></i></span>
                        <div class="kpi-value" id="healthKpiQueueDelay"><span class="skeleton">0 min</span></div>
                    </div>
                    <div class="kpi-label">Atraso da fila</div>
                </div>
                <div class="glass-card kpi-card glow-primary">
                    <div>
                        <span class="kpi-icon primary"><i class="fas fa-cogs"></i></span>
                        <div class="kpi-value" id="healthKpiCrontaskIssues"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Ações automáticas com problema</div>
                </div>
                <div class="glass-card kpi-card glow-warning">
                    <div>
                        <span class="kpi-icon warning"><i class="fas fa-inbox"></i></span>
                        <div class="kpi-value" id="healthKpiCollectorErrors"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Coletores com erro</div>
                </div>
                <div class="glass-card kpi-card glow-danger">
                    <div>
                        <span class="kpi-icon danger"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="kpi-value" id="healthKpiSlaOverdue"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">SLA vencido</div>
                </div>
                <div class="glass-card kpi-card glow-warning">
                    <div>
                        <span class="kpi-icon warning"><i class="fas fa-user-slash"></i></span>
                        <div class="kpi-value" id="healthKpiUnassigned"><span class="skeleton">0</span></div>
                    </div>
                    <div class="kpi-label">Sem atribuição</div>
                </div>
            </div>

            <div class="health-crontask-row">
                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico operacional</div>
                            <h3 class="table-title">Ações Automáticas</h3>
                        </div>
                        <a class="page-action-btn page-action-btn--compact" id="healthLinkSettingsSla" href="/front/settings.php?section=sla">
                            <i class="fas fa-business-time"></i>
                            <span>Config. SLA</span>
                        </a>
                    </div>
                    <div class="health-callout" id="healthCrontaskCallout">
                        <strong id="healthCrontaskCalloutTitle">Verificando crontasks</strong>
                        <span id="healthCrontaskCalloutText">Lendo `queuednotification` e `slaticket`.</span>
                    </div>
                    <div class="health-crontask-grid" id="healthCrontaskCards">
                        <div class="health-empty-state">Carregando ações automáticas...</div>
                    </div>
                </section>
            </div>

            <div class="health-panel-grid">
                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico operacional</div>
                            <h3 class="table-title">Fila de Notificação</h3>
                        </div>
                        <div class="dashboard-period-tabs chart-range-tabs health-chart-tabs" aria-label="Intervalo do gráfico da fila de notificações">
                            <button class="dashboard-period-btn active" type="button" data-notification-range="1">1H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="3">3H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="6">6H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="12">12H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="24">24H</button>
                            <button class="dashboard-period-btn" type="button" data-notification-range="48">48H</button>
                        </div>
                    </div>
                    <div class="health-callout" id="healthQueueCallout">
                        <strong id="healthQueueCalloutTitle">Aguardando leitura da fila</strong>
                        <span id="healthQueueCalloutText">Buscando métricas operacionais do queuednotification.</span>
                    </div>
                    <div class="health-stat-grid">
                        <div class="health-stat-card">
                            <span class="health-stat-label">Pendentes</span>
                            <strong class="health-stat-value" id="healthQueuePendingCount">0</strong>
                        </div>
                        <div class="health-stat-card">
                            <span class="health-stat-label">Atraso</span>
                            <strong class="health-stat-value" id="healthQueueDelaySeconds">0 min</strong>
                        </div>
                        <div class="health-stat-card">
                            <span class="health-stat-label">Mais antigo</span>
                            <strong class="health-stat-value" id="healthQueueOldestSend">-</strong>
                        </div>
                        <div class="health-stat-card">
                            <span class="health-stat-label">Último envio</span>
                            <strong class="health-stat-value" id="healthQueueLastSent">-</strong>
                        </div>
                    </div>
                    <div class="chart-container health-chart-container">
                        <canvas id="healthQueueChart"></canvas>
                    </div>
                </section>
            </div>

            <div class="health-config-grid">
                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico de configuração</div>
                            <h3 class="table-title">E-mail / SMTP</h3>
                        </div>
                        <a class="page-action-btn page-action-btn--compact" id="healthLinkSettingsNotifications" href="/front/settings.php?section=notifications">
                            <i class="fas fa-sliders-h"></i>
                            <span>Config. Dash</span>
                        </a>
                    </div>
                    <div class="health-callout" id="healthMailCallout">
                        <strong id="healthMailCalloutTitle">Analisando credenciais</strong>
                        <span id="healthMailCalloutText">Checando modo de entrega, remetente e credenciais persistidas.</span>
                    </div>
                    <div class="health-definition-list" id="healthMailDetails">
                        <div><dt>Modo</dt><dd id="healthMailMode">-</dd></div>
                        <div><dt>Admin</dt><dd id="healthMailAdmin">-</dd></div>
                        <div><dt>Remetente</dt><dd id="healthMailFrom">-</dd></div>
                        <div><dt>Host / Porta</dt><dd id="healthMailHostPort">-</dd></div>
                        <div><dt>Login SMTP</dt><dd id="healthMailUsername">-</dd></div>
                        <div><dt>Senha SMTP</dt><dd id="healthMailPasswordFlag">-</dd></div>
                        <div><dt>Client secret OAuth</dt><dd id="healthMailOauthSecretFlag">-</dd></div>
                        <div><dt>Refresh token OAuth</dt><dd id="healthMailOauthRefreshFlag">-</dd></div>
                    </div>
                </section>

                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico de configuração</div>
                            <h3 class="table-title">Notificações</h3>
                        </div>
                        <div class="health-action-links">
                            <a class="page-action-btn page-action-btn--compact" id="healthLinkTemplates" href="#" target="_blank" rel="noopener">
                                <i class="fas fa-file-alt"></i>
                                <span>Templates</span>
                            </a>
                        </div>
                    </div>
                    <div class="health-callout" id="healthNotificationCallout">
                        <strong id="healthNotificationCalloutTitle">Conferindo catálogo</strong>
                        <span id="healthNotificationCalloutText">Validando notificações ativas, templates e evento de SLA.</span>
                    </div>
                    <div class="health-definition-list" id="healthNotificationDetails">
                        <div><dt>Notificações ativas</dt><dd id="healthNotificationActive">0</dd></div>
                        <div><dt>Templates</dt><dd id="healthNotificationTemplates">0</dd></div>
                        <div><dt>Fila registrada</dt><dd id="healthNotificationQueued">0</dd></div>
                        <div><dt>Evento SLA</dt><dd id="healthNotificationEvent">-</dd></div>
                    </div>
                </section>

                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico de configuração</div>
                            <h3 class="table-title">Coletores</h3>
                        </div>
                    </div>
                    <div class="health-callout" id="healthCollectorCallout">
                        <strong id="healthCollectorCalloutTitle">Lendo coletores</strong>
                        <span id="healthCollectorCalloutText">Buscando erros, ativação e última coleta conhecida.</span>
                    </div>
                    <div class="health-definition-list" id="healthCollectorDetails">
                        <div><dt>Total</dt><dd id="healthCollectorsTotal">0</dd></div>
                        <div><dt>Ativos</dt><dd id="healthCollectorsActive">0</dd></div>
                        <div><dt>Com erro</dt><dd id="healthCollectorsErrors">0</dd></div>
                    </div>
                    <div class="table-responsive health-collector-table-wrap">
                        <table class="custom-table health-collector-table">
                            <thead>
                                <tr>
                                    <th>Coletor</th>
                                    <th>Status</th>
                                    <th>Erros</th>
                                    <th>Última coleta</th>
                                </tr>
                            </thead>
                            <tbody id="healthCollectorsTableBody">
                                <tr><td colspan="4" class="table-empty">Carregando coletores...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="glass-card health-panel">
                    <div class="health-panel-header">
                        <div>
                            <div class="health-kicker">Diagnóstico de configuração</div>
                            <h3 class="table-title">PHP do GLPI</h3>
                        </div>
                    </div>
                    <div class="health-callout" id="healthPhpConfigCallout">
                        <strong id="healthPhpConfigCalloutTitle">Lendo diretivas do PHP</strong>
                        <span id="healthPhpConfigCalloutText">Consultando memory_limit e upload_max_filesize no container do GLPI.</span>
                    </div>
                    <div class="health-definition-list" id="healthPhpConfigDetails">
                        <div><dt>memory_limit</dt><dd id="healthPhpMemoryLimit">-</dd></div>
                        <div><dt>upload_max_filesize</dt><dd id="healthPhpUploadMaxFilesize">-</dd></div>
                        <div><dt>post_max_size</dt><dd id="healthPhpPostMaxSize">-</dd></div>
                        <div><dt>max_execution_time</dt><dd id="healthPhpMaxExecutionTime">-</dd></div>
                    </div>
                </section>
            </div>

            <div class="health-table-grid">
                <section class="glass-card table-card health-table-card is-collapsed" id="healthCriticalTicketsCard">
                    <div class="table-header">
                        <h3 class="table-title">Chamados Críticos</h3>
                        <div class="table-header-actions">
                            <div class="tickets-count" id="healthCriticalTicketsCount">0 chamados</div>
                            <button class="table-toggle-btn" type="button" id="toggleHealthCriticalTickets" onclick="toggleHealthTableSection('CriticalTickets')" aria-expanded="false" aria-controls="healthCriticalTicketsPanel">
                                <i class="fas fa-chevron-down"></i>
                                <span>Expandir</span>
                            </button>
                        </div>
                    </div>
                    <div class="health-collapsible-body" id="healthCriticalTicketsPanel" hidden>
                        <div class="table-responsive">
                            <table class="custom-table health-ticket-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Risco</th>
                                        <th>Atribuição</th>
                                        <th>SLA</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="healthCriticalTicketsBody">
                                    <tr><td colspan="6" class="table-empty">Carregando chamados críticos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="glass-card table-card health-table-card is-collapsed" id="healthRecentEventsCard">
                    <div class="table-header">
                        <h3 class="table-title">Últimos Eventos das Ações Automáticas</h3>
                        <div class="table-header-actions">
                            <div class="tickets-count" id="healthRecentEventsCount">0 eventos</div>
                            <button class="table-toggle-btn" type="button" id="toggleHealthRecentEvents" onclick="toggleHealthTableSection('RecentEvents')" aria-expanded="false" aria-controls="healthRecentEventsPanel">
                                <i class="fas fa-chevron-down"></i>
                                <span>Expandir</span>
                            </button>
                        </div>
                    </div>
                    <div class="health-collapsible-body" id="healthRecentEventsPanel" hidden>
                        <div class="table-responsive">
                            <table class="custom-table health-events-table">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Data</th>
                                        <th>Estado</th>
                                        <th>Execução</th>
                                        <th>Volume</th>
                                        <th>Conteúdo</th>
                                    </tr>
                                </thead>
                                <tbody id="healthRecentEventsBody">
                                    <tr><td colspan="6" class="table-empty">Carregando histórico...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="glass-card table-card health-table-card is-collapsed" id="healthMailgateLoopCard">
                    <div class="table-header">
                        <h3 class="table-title">Possível Loop do Mailgate</h3>
                        <div class="table-header-actions">
                            <div class="tickets-count" id="healthMailgateLoopCount">0 pares</div>
                            <button class="table-toggle-btn" type="button" id="toggleHealthMailgateLoop" onclick="toggleHealthTableSection('MailgateLoop')" aria-expanded="false" aria-controls="healthMailgateLoopPanel">
                                <i class="fas fa-chevron-down"></i>
                                <span>Expandir</span>
                            </button>
                        </div>
                    </div>
                    <div class="health-collapsible-body" id="healthMailgateLoopPanel" hidden>
                        <div class="table-responsive">
                            <table class="custom-table health-mailgate-loop-table">
                                <thead>
                                    <tr>
                                        <th>Chamado</th>
                                        <th>Duplicado</th>
                                        <th>Título</th>
                                        <th>Criado em</th>
                                    </tr>
                                </thead>
                                <tbody id="healthMailgateLoopBody">
                                    <tr><td colspan="4" class="table-empty">Carregando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canSla): ?>
        <!-- SLA Section -->
        <div class="page-section<?= $defaultPage === 'sla' ? ' active' : '' ?>" id="slaSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Monitor de SLA</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-stopwatch"></i>
                        <span>Acompanhamento em tempo real</span>
                    </div>
                </div>
            </header>
            <div class="sla-summary-grid">
                <div class="glass-card sla-summary-card">
                    <div class="sla-summary-icon ok">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="sla-summary-value ok" id="slaOk">0</div>
                        <div class="sla-summary-label">No Prazo</div>
                    </div>
                </div>
                <div class="glass-card sla-summary-card">
                    <div class="sla-summary-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="sla-summary-value warning" id="slaWarning">0</div>
                        <div class="sla-summary-label">Atenção</div>
                    </div>
                </div>
                <div class="glass-card sla-summary-card">
                    <div class="sla-summary-icon critical">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="sla-summary-value critical" id="slaCritical">0</div>
                        <div class="sla-summary-label">Crítico</div>
                    </div>
                </div>
                <div class="glass-card sla-summary-card">
                    <div class="sla-summary-icon unassigned">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div>
                        <div class="sla-summary-value unassigned" id="slaUnassigned">0</div>
                        <div class="sla-summary-label">Sem Atribuição</div>
                    </div>
                </div>
                <div class="glass-card sla-summary-card">
                    <div class="sla-summary-icon average">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                    <div>
                        <div class="sla-summary-value average" id="slaAverageTime">0h</div>
                        <div class="sla-summary-label">Tempo Médio</div>
                    </div>
                </div>
            </div>
            <div class="glass-card table-card">
                <div class="table-header">
                    <h3 class="table-title">Triagem de Atribuição e SLA Inicial</h3>
                </div>
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="slaSearchInput" placeholder="Buscar por ID, título, técnico, grupo, requerente, categoria ou SLA...">
                    </div>
                    <div class="sla-filter-bar" aria-label="Filtros do Monitor SLA">
                        <button type="button" class="sla-filter-btn active" data-sla-filter="all">Todos</button>
                        <button type="button" class="sla-filter-btn" data-sla-filter="unassigned">Sem atribuição</button>
                        <button type="button" class="sla-filter-btn" data-sla-filter="overdue">SLA vencido</button>
                        <button type="button" class="sla-filter-btn" data-sla-filter="high">Risco alto</button>
                        <button type="button" class="sla-filter-btn sla-filter-toggle" id="slaAdvancedFilterToggle" aria-expanded="false" aria-controls="slaAdvancedFilters">
                            <i class="fas fa-filter"></i>
                            Filtros
                        </button>
                    </div>
                    <div class="tickets-count" id="slaCount">0 chamados</div>
                    <div class="sla-advanced-filters" id="slaAdvancedFilters" hidden>
                        <div class="sla-filter-panel">
                            <div class="sla-filter-panel-section">
                                <span class="sla-filter-panel-title">Status</span>
                                <label><input type="checkbox" class="sla-status-filter" value="1" checked> Novo</label>
                                <label><input type="checkbox" class="sla-status-filter" value="2" checked> Em atendimento</label>
                                <label><input type="checkbox" class="sla-status-filter" value="3" checked> Planejado</label>
                                <label><input type="checkbox" class="sla-status-filter" value="4" checked> Pendente</label>
                                <label><input type="checkbox" class="sla-status-filter" value="5"> Solucionado</label>
                                <label><input type="checkbox" class="sla-status-filter" value="6"> Fechado</label>
                            </div>
                            <div class="sla-filter-panel-section compact">
                                <label>
                                    <span class="sla-filter-panel-title">Aberto de</span>
                                    <input type="date" id="slaDateFrom">
                                </label>
                                <label>
                                    <span class="sla-filter-panel-title">Aberto até</span>
                                    <input type="date" id="slaDateTo">
                                </label>
                            </div>
                            <div class="sla-filter-panel-actions">
                                <button type="button" class="sla-filter-btn" id="slaApplyAdvancedFilters">Aplicar</button>
                                <button type="button" class="sla-filter-btn" id="slaClearAdvancedFilters">Limpar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table sla-table responsive-table responsive-table--pin-status">
                        <thead>
                            <tr>
                                <th class="sortable-th" data-sla-sort="id">ID <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sla-sort="name">Título <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sla-sort="risk_score">Risco <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sla-sort="technician_name">Atribuição <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sla-sort="active_sla_percent">Progresso <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sla-sort="open_seconds">Aberto há <i class="fas fa-sort"></i></th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="slaList">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-muted);"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="assignment-overlay" id="slaAssignmentModal" aria-hidden="true">
            <div class="assignment-dialog" role="dialog" aria-modal="true" aria-labelledby="slaAssignmentTitle">
                <div class="assignment-header">
                    <div>
                        <h3 id="slaAssignmentTitle">Atribuir chamado</h3>
                        <p id="slaAssignmentSubtitle">Selecione um técnico, um grupo ou ambos.</p>
                    </div>
                    <button type="button" class="assignment-close" data-assignment-close aria-label="Fechar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="slaAssignmentForm" class="assignment-form">
                    <input type="hidden" id="assignmentTicketId" name="ticket_id" value="">
                    <label>
                        <span>Técnico</span>
                        <select id="assignmentUserId" name="users_id">
                            <option value="0">Não atribuir técnico</option>
                        </select>
                    </label>
                    <label>
                        <span>Grupo</span>
                        <select id="assignmentGroupId" name="groups_id">
                            <option value="0">Não atribuir grupo</option>
                        </select>
                    </label>
                    <div class="assignment-status" id="assignmentStatus"></div>
                    <div class="assignment-actions">
                        <button type="button" class="page-action-btn" data-assignment-close>
                            <i class="fas fa-arrow-left"></i> Cancelar
                        </button>
                        <button type="submit" class="page-action-btn primary">
                            <i class="fas fa-user-check"></i> Atribuir
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canTickets): ?>
        <div class="assignment-overlay" id="ticketDetailModal" aria-hidden="true">
            <div class="assignment-dialog ticket-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="ticketDetailTitle">
                <div class="assignment-header">
                    <div>
                        <h3 id="ticketDetailTitle">Chamado</h3>
                        <p id="ticketDetailSubtitle">Detalhes, histórico e ações do chamado.</p>
                    </div>
                    <button type="button" class="page-action-btn" id="attendanceExpand" aria-label="Expandir chamado" aria-pressed="false"><i class="fas fa-expand" aria-hidden="true"></i> Expandir</button>
                    <button type="button" class="assignment-close ticket-detail-close" data-modal-close aria-label="Fechar">
                        <i class="fas fa-times ticket-detail-close-icon-desktop"></i>
                        <i class="fas fa-arrow-left ticket-detail-close-icon-mobile"></i>
                    </button>
                </div>
                <input type="hidden" id="ticketDetailTicketId" value="">
                <div id="ticketDetailBody" class="ticket-detail-columns">
                    <div class="ticket-detail-col ticket-detail-col-main">
                        <div class="ticket-detail-meta" id="ticketDetailMeta"></div>
                        <div class="ticket-detail-section">
                            <div class="attendance-section-heading"><h4>Descrição do chamado</h4><button type="button" class="page-action-btn is-hidden" id="attendanceEditDescription">Editar</button></div>
                            <div class="ticket-detail-content" id="ticketDetailContent"></div>
                            <div id="attendanceDescriptionDocuments" class="attendance-documents"></div>
                            <form id="attendanceDescriptionForm" class="assignment-form is-hidden"><label>Descrição do chamado<textarea id="attendanceDescription" rows="7" required></textarea></label><div class="ticket-detail-inline-actions"><button type="button" class="page-action-btn" id="attendanceCancelDescription">Cancelar edição</button><button type="submit" class="page-action-btn primary">Salvar descrição</button></div><div id="attendanceDescriptionStatus" class="assignment-status" role="status"></div></form>
                        </div>

                        <div class="ticket-detail-section">
                            <h4>Histórico do chamado</h4>
                            <div class="followup-timeline" id="followupTimeline"></div>
                            <button type="button" id="attendanceMore" class="page-action-btn is-hidden">Carregar anteriores</button>
                        </div>

                        <div class="ticket-detail-section is-hidden" id="ticketDetailCancelSection">
                            <h4>Cancelar chamado</h4>
                            <form id="ticketDetailCancelForm" class="assignment-form">
                                <label>
                                    <span>Motivo (opcional)</span>
                                    <textarea id="ticketDetailCancelReason" rows="2" placeholder="Explique por que deseja cancelar..."></textarea>
                                </label>
                                <div class="assignment-status" id="ticketDetailCancelStatus"></div>
                                <div class="ticket-detail-inline-actions">
                                    <button type="submit" class="page-action-btn primary">
                                        <i class="fas fa-paper-plane"></i> Confirmar cancelamento
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="ticket-detail-section is-hidden" id="solutionSection">
                            <h4>Validar a solução</h4>
                            <p>Aprove a solução ou informe o motivo da recusa.</p>
                            <div class="assignment-status" id="solutionStatus"></div>
                            <div class="ticket-detail-inline-actions">
                                <button type="button" class="page-action-btn" id="solutionRefuseBtn">
                                    <i class="fas fa-times"></i> Recusar solução
                                </button>
                                <button type="button" class="page-action-btn primary" id="solutionApproveBtn">
                                    <i class="fas fa-check"></i> Aprovar solução
                                </button>
                            </div>
                            <form id="solutionRefuseForm" class="assignment-form is-hidden">
                                <label>
                                    <span>Motivo da recusa</span>
                                    <textarea id="solutionRefuseReason" rows="3" placeholder="Explique por que o problema ainda persiste..."></textarea>
                                </label>
                                <div class="ticket-detail-inline-actions">
                                    <button type="submit" class="page-action-btn primary">
                                        <i class="fas fa-paper-plane"></i> Enviar recusa
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="ticket-detail-section is-hidden" id="satisfactionSection">
                            <h4>Pesquisa de satisfação</h4>
                            <p>Avalie o atendimento recebido neste chamado.</p>
                            <form id="satisfactionForm" class="assignment-form">
                                <div class="satisfaction-stars" id="satisfactionStars" data-selected="0"></div>
                                <label>
                                    <span>Comentário (opcional)</span>
                                    <textarea id="satisfactionComment" rows="3" placeholder="Conte como foi sua experiência..."></textarea>
                                </label>
                                <div class="assignment-status" id="satisfactionStatus"></div>
                                <div class="ticket-detail-inline-actions">
                                    <button type="submit" class="page-action-btn primary">
                                        <i class="fas fa-paper-plane"></i> Enviar avaliação
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="ticket-detail-col ticket-detail-col-followup">
                        <h4 id="attendanceComposerTitle">Novo acompanhamento</h4>
                        <form id="followupForm" class="assignment-form">
                            <label id="attendanceKindLabel" class="is-hidden"><span>Tipo de atendimento</span><select id="attendanceKind"></select></label>
                            <div id="attendanceTaskFields" class="attendance-form-grid is-hidden">
                                <label>Duração (minutos)<input id="attendanceDuration" type="number" min="0" max="525600" value="0"></label>
                                <label>Estado<select id="attendanceTaskState"><option value="1">A fazer</option><option value="2">Feita</option><option value="0">Informação</option></select></label>
                                <label>Responsável<select id="attendanceTaskUser" data-attendance-catalog="technicians"></select></label>
                                <label>Grupo<select id="attendanceTaskGroup" data-attendance-catalog="groups"></select></label>
                                <label>Categoria da tarefa<select id="attendanceTaskCategory" data-attendance-catalog="taskcategories"></select></label>
                                <label>Início planejado<input id="attendanceTaskBegin" type="datetime-local"></label>
                                <label>Fim planejado<input id="attendanceTaskEnd" type="datetime-local"></label>
                            </div>
                            <label id="attendanceSolutionFields" class="is-hidden">Tipo de solução<select id="attendanceSolutionType" data-attendance-catalog="solutiontypes"></select></label>
                            <label>
                                <span id="attendanceContentLabel">Mensagem</span>
                                <textarea id="followupContent" rows="3" placeholder="Escreva uma atualização sobre o seu chamado..."></textarea>
                            </label>
                            <label>
                                <span>Anexos e imagens</span>
                                <div class="ticket-create-upload-shell">
                                    <input class="ticket-create-file-input" type="file" name="attachments[]" id="followupAttachments" multiple hidden>
                                    <div class="ticket-create-upload-dropzone" id="followupUploadZone" tabindex="0" role="button" aria-label="Adicionar anexos ao acompanhamento">
                                        <div class="ticket-create-upload-main">
                                            <span class="ticket-create-upload-icon"><i class="fas fa-images"></i></span>
                                            <div>
                                                <div class="ticket-create-upload-title">Adicione varias imagens e arquivos</div>
                                                <div class="ticket-create-upload-copy">Arraste aqui, cole prints com Ctrl+V ou clique para selecionar varios anexos.</div>
                                            </div>
                                        </div>
                                        <button class="page-action-btn" type="button" id="followupPickFiles">
                                            <i class="fas fa-paperclip"></i>
                                            <span>Selecionar arquivos</span>
                                        </button>
                                    </div>
                                    <div class="ticket-create-attachments-list" id="followupAttachmentsList">
                                        <div class="ticket-create-attachments-empty">Nenhum anexo selecionado.</div>
                                    </div>
                                </div>
                                <small class="ticket-create-file-help" id="followupUploadHelp">Carregando limite de anexos...</small>
                            </label>
                            <div class="assignment-status" id="followupStatus" role="status"></div>
                            <div class="ticket-detail-inline-actions">
                                <button type="submit" class="page-action-btn primary">
                                    <i class="fas fa-paper-plane"></i> Enviar acompanhamento
                                </button>
                            </div>
                        </form>
                    </div>
                    <details id="attendanceProperties" class="ticket-detail-col attendance-properties is-hidden" open>
                        <summary>Propriedades do chamado</summary>
                        <details class="attendance-context"><summary>Entidade, datas e SLA</summary><div id="attendancePropertiesMeta"></div></details>
                        <label>Buscar opções e responsáveis<input type="search" id="attendanceCatalogSearch" placeholder="Digite para filtrar os catálogos"></label>
                        <div id="attendanceCatalogStatus" class="assignment-status" role="status"></div>
                        <form id="attendancePropertiesForm" class="assignment-form">
                            <label>Título<input id="attendanceName" maxlength="255" required></label>
                            <label>Tipo<select id="attendanceType"><option value="1">Incidente</option><option value="2">Requisição</option></select></label>
                            <label>Categoria<select id="attendanceCategory" data-attendance-catalog="categories"></select></label>
                            <label>Urgência<select id="attendanceUrgency"></select></label>
                            <label>Impacto<select id="attendanceImpact"></select></label>
                            <label>Prioridade<select id="attendancePriority"></select></label>
                            <button type="submit" class="page-action-btn primary">Salvar propriedades</button>
                            <div id="attendancePropertiesStatus" class="assignment-status" role="status"></div>
                        </form>
                        <div id="attendanceActors"></div>
                        <button type="button" id="attendanceTake" class="page-action-btn is-hidden">Assumir chamado</button>
                        <form id="attendanceActorForm" class="assignment-form is-hidden">
                            <label>Papel<select id="attendanceActorRole"></select></label>
                            <label>Ator<select id="attendanceActorType"><option value="User">Usuário</option><option value="Group">Grupo técnico</option></select></label>
                            <label>Selecionar<select id="attendanceActorId" data-attendance-catalog="technicians"></select></label>
                            <button type="submit" class="page-action-btn">Adicionar ator</button>
                            <div id="attendanceActorStatus" class="assignment-status" role="status"></div>
                        </form>
                        <form id="attendanceStatusForm" class="assignment-form is-hidden">
                            <label>Status<select id="attendanceStatus"></select></label>
                            <div id="attendancePendingFields" class="is-hidden">
                                <label>Motivo da pendência<textarea id="attendancePendingReason" rows="2"></textarea></label>
                                <label>Tipo de pendência<select id="attendancePendingType" data-attendance-catalog="pendingreasons"></select></label>
                            </div>
                            <button type="submit" class="page-action-btn">Atualizar status</button>
                            <div id="attendanceStateStatus" class="assignment-status" role="status"></div>
                        </form>
                    </details>
                </div>
                <div class="assignment-actions ticket-detail-footer">
                    <button type="button" class="page-action-btn is-hidden" id="ticketDetailCancelToggle">
                        <i class="fas fa-ban"></i> Cancelar este chamado
                    </button>
                    <button type="button" class="page-action-btn primary is-hidden" id="attendanceGoToComposer">Atender</button>
                    <button type="button" class="page-action-btn" id="attendanceReload">Atualizar</button>
                    <button type="button" class="page-action-btn" data-modal-close>
                        <i class="fas fa-arrow-left"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canRanking): ?>
        <!-- Ranking Section -->
        <div class="page-section<?= $defaultPage === 'ranking' ? ' active' : '' ?>" id="rankingSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Ranking de Técnicos</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-trophy"></i>
                        <span>Performance e Gamificação</span>
                    </div>
                </div>
            </header>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="glass-card h-100">
                        <div class="chart-header">
                            <h3 class="chart-title">Líderes de Atendimento</h3>
                        </div>
                        <div id="leaderboard-container" class="leaderboard-container mt-3">
                            <div class="text-center p-5">
                                <i class="fas fa-spinner fa-spin fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="glass-card text-center mb-4" style="border-color: var(--gold) !important;">
                        <i class="fas fa-crown fa-3x mb-3" style="color: var(--warning);"></i>
                        <h5 style="color: var(--text-main);">Técnico do Mês</h5>
                        <h2 id="top-tech-name" class="fw-bold mt-2" style="color: var(--text-main);">--</h2>
                        <p style="color: var(--text-sec); font-size: 0.85rem;">Maior pontuação acumulada</p>
                    </div>
                    <div class="glass-card">
                        <h5 class="mb-3" style="color: var(--text-main);">Como pontuar?</h5>
                        <ul class="list-unstyled" style="color: var(--text-sec); font-size: 0.85rem;">
                            <li class="mb-2"><i class="fas fa-check me-2" style="color: var(--success);"></i> Chamado Resolvido: +10 pts</li>
                            <li class="mb-2"><i class="fas fa-clock me-2" style="color: var(--primary);"></i> SLA no Prazo: +5 pts</li>
                            <li class="mb-2"><i class="fas fa-star me-2" style="color: var(--warning);"></i> Avaliação 5 estrelas: +20 pts</li>
                            <li><i class="fas fa-times me-2" style="color: var(--danger);"></i> Reabertura: -15 pts</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canTickets): ?>
        <!-- Tickets Section -->
        <div class="page-section<?= $defaultPage === 'tickets' ? ' active' : '' ?>" id="ticketsSection" data-screen-type="tickets">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Todos os Chamados</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Pesquisa, ordenação e acompanhamento por estágio</span>
                    </div>
                </div>
            </header>
            <div class="glass-card table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="ticketsSearchInput" placeholder="Buscar por ID, título, técnico, requerente, categoria ou status...">
                    </div>
                    <?php if (count($itilTypeCatalog) > 1): ?>
                    <!-- Filtro por objeto ITIL (PLAN-20260709-019, Fase C) — só aparece para
                         perfis com direito GLPI em Problema/Mudança. -->
                    <div class="tickets-type-tabs" id="ticketsTypeFilters" role="tablist" aria-label="Tipo de atendimento">
                        <?php foreach ($itilTypeCatalog as $itilEntry): ?>
                        <?php $itilIcon = ['ticket' => 'fa-ticket-alt', 'problem' => 'fa-triangle-exclamation', 'change' => 'fa-arrows-rotate'][$itilEntry['key']] ?? 'fa-layer-group'; ?>
                        <button type="button" role="tab" aria-selected="<?= $itilEntry['key'] === 'ticket' ? 'true' : 'false' ?>" class="tickets-type-tab<?= $itilEntry['key'] === 'ticket' ? ' active' : '' ?>" data-tickets-itemtype="<?= htmlspecialchars($itilEntry['key'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas <?= $itilIcon ?>" aria-hidden="true"></i><span><?= htmlspecialchars($itilEntry['label_plural']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="tickets-count" id="ticketsCount">0 chamados</div>
                        <div class="view-toggle" id="ticketsViewToggle" role="group" aria-label="Visualização dos chamados">
                            <button type="button" class="view-toggle-btn is-active" data-view="list" aria-pressed="true" title="Visualização em lista">
                                <i class="fas fa-list" aria-hidden="true"></i><span>Lista</span>
                            </button>
                            <button type="button" class="view-toggle-btn" data-view="kanban" aria-pressed="false" title="Visualização Kanban">
                                <i class="fas fa-columns" aria-hidden="true"></i><span>Kanban</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table responsive-table">
                        <thead>
                            <tr>
                                <th class="sortable-th" data-sort="id">ID <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sort="name">Título <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sort="stage">Stage <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sort="technician_name">Técnico <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sort="requester_name">Requerente <i class="fas fa-sort"></i></th>
                                <th class="sortable-th" data-sort="date">Criado em <i class="fas fa-sort"></i></th>
                                <?php if ($isHelpdeskView): ?>
                                <th>Ações</th>
                                <?php elseif ($canTicketReports): ?>
                                <th>Ação</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tickets-full-body">
                            <tr>
                                <td colspan="<?= ($isHelpdeskView || $canTicketReports) ? 8 : 7 ?>" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-muted);"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="tickets-pagination" id="ticketsPagination" hidden></div>
                <div id="ticketsKanban" class="kanban-container" hidden></div>
                <div id="ticketsCards" class="tickets-cards" hidden aria-live="polite"></div>
                <div class="tickets-mobile-search-panel" id="ticketsMobileSearchPanel" hidden>
                    <label for="ticketsSearchInputMobile">Pesquisar chamados</label>
                    <div class="tickets-search tickets-search-mobile">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="ticketsSearchInputMobile" placeholder="ID, título, técnico ou status..." autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-section" id="ticketNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Abertura de Chamado</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-plus-circle"></i>
                        <span>Registre uma nova solicitacao para atendimento</span>
                    </div>
                </div>
                <div class="header-actions ticket-create-header-actions">
                    <button class="page-action-btn" type="button" onclick="showPage(defaultLandingPage()); return false;">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>

            <div class="ticket-create-shell">
                <div class="ticket-create-stack">
                    <form class="glass-card admin-form admin-form-page" id="ticketCreateForm" enctype="multipart/form-data">
                        <h3 class="admin-card-title">Dados do Chamado</h3>
                        <div class="ticket-create-grid">
                            <label class="admin-field full">
                                <span>Titulo</span>
                                <input type="text" name="name" id="ticketCreateName" maxlength="255" required>
                            </label>
                            <label class="admin-field">
                                <span>Entidade</span>
                                <select name="entities_id" id="ticketCreateEntity"></select>
                            </label>
                            <label class="admin-field">
                                <span>Tipo</span>
                                <select name="type" id="ticketCreateType"></select>
                            </label>
                            <label class="admin-field">
                                <span>Categoria</span>
                                <select name="itilcategories_id" id="ticketCreateCategory">
                                    <option value="0">Carregando categorias...</option>
                                </select>
                            </label>
                            <label class="admin-field">
                                <span>Urgencia</span>
                                <select name="urgency" id="ticketCreateUrgency"></select>
                            </label>
                            <label class="admin-field full">
                                <span>Descricao</span>
                                <textarea name="content" id="ticketCreateContent" rows="7" required></textarea>
                            </label>
                            <label class="admin-field full">
                                <span>Anexos e imagens</span>
                                <div class="ticket-create-upload-shell">
                                    <input class="ticket-create-file-input" type="file" name="attachments[]" id="ticketCreateAttachments" multiple hidden>
                                    <div class="ticket-create-upload-dropzone" id="ticketCreateUploadZone" tabindex="0" role="button" aria-label="Adicionar anexos ao chamado">
                                        <div class="ticket-create-upload-main">
                                            <span class="ticket-create-upload-icon"><i class="fas fa-images"></i></span>
                                            <div>
                                                <div class="ticket-create-upload-title">Adicione varias imagens e arquivos</div>
                                                <div class="ticket-create-upload-copy">Arraste aqui, cole prints com Ctrl+V ou clique para selecionar varios anexos.</div>
                                            </div>
                                        </div>
                                        <button class="page-action-btn" type="button" id="ticketCreatePickFiles">
                                            <i class="fas fa-paperclip"></i>
                                            <span>Selecionar arquivos</span>
                                        </button>
                                    </div>
                                    <div class="ticket-create-attachments-list" id="ticketCreateAttachmentsList">
                                        <div class="ticket-create-attachments-empty">Nenhum anexo selecionado.</div>
                                    </div>
                                </div>
                                <small class="ticket-create-file-help" id="ticketCreateUploadHelp">Carregando limite de anexos...</small>
                            </label>
                        </div>
                        <div class="ticket-create-status admin-status" id="ticketCreateStatus"></div>
                        <div class="ticket-create-actions">
                            <button class="page-action-btn primary" type="submit" id="ticketCreateSubmit">
                                <i class="fas fa-paper-plane"></i>
                                <span>Abrir Chamado</span>
                            </button>
                            <button class="page-action-btn" type="button" id="ticketCreateReset">
                                <i class="fas fa-undo"></i>
                                <span>Limpar</span>
                            </button>
                        </div>
                    </form>

                </div>

                <aside class="ticket-create-side">
                    <div class="ticket-create-success glass-card" id="ticketCreateSuccess" hidden>
                        <div class="ticket-create-success-title">
                            <i class="fas fa-check-circle"></i>
                            <span>Chamado criado com sucesso</span>
                        </div>
                        <div class="ticket-create-success-copy" id="ticketCreateSuccessCopy"></div>
                        <div class="ticket-create-actions">
                            <?php if ($canTickets): ?>
                            <button class="page-action-btn primary" type="button" onclick="showPage('tickets'); return false;">
                                <i class="fas fa-ticket-alt"></i>
                                <span>Ver Chamados</span>
                            </button>
                            <?php endif; ?>
                            <button class="page-action-btn" type="button" id="ticketCreateAgain">
                                <i class="fas fa-plus"></i>
                                <span>Novo Chamado</span>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($userContext['has_profile_rule'])): ?>
                    <div class="glass-card ticket-create-scope" id="ticketCreateScopeCard">
                    <div>
                        <div class="settings-block-kicker">Contexto de abertura</div>
                        <h3 class="admin-card-title">Abertura vinculada ao usuario logado</h3>
                    </div>
                    <div class="ticket-create-meta">
                        <div class="ticket-create-meta-item">
                            <span class="ticket-create-meta-label">Solicitante</span>
                            <span class="ticket-create-meta-value" id="ticketCreateRequester">Carregando...</span>
                        </div>
                        <div class="ticket-create-meta-item">
                            <span class="ticket-create-meta-label">Perfil efetivo</span>
                            <span class="ticket-create-meta-value" id="ticketCreateProfile">Carregando...</span>
                        </div>
                        <div class="ticket-create-meta-item">
                            <span class="ticket-create-meta-label">Escopo de entidade</span>
                            <span class="ticket-create-meta-value" id="ticketCreateEntityScope">Carregando...</span>
                        </div>
                    </div>
                    <p class="ticket-create-note">
                        O Dash usa suas permissoes de acesso para sugerir a entidade do chamado. Quando houver mais de uma entidade valida no seu perfil de atendimento, o seletor sera exibido para a sua escolha.
                    </p>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Entities Section -->
        <div class="page-section" id="entitiesSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Cliente/Entidade</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-building"></i>
                        <span>Cadastro de entidades do GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-new="entityImport">
                        <i class="fas fa-file-import"></i>
                        <span>Importar CSV</span>
                    </button>
                    <button class="page-action-btn primary" type="button" data-admin-new="entity">
                        <i class="fas fa-plus"></i>
                        <span>Novo</span>
                    </button>
                </div>
            </header>
            <div class="glass-card table-card admin-table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="entitiesSearchInput" placeholder="Buscar entidade...">
                    </div>
                    <div class="tickets-count" id="entitiesCount">0 entidades</div>
                </div>
                <div class="table-responsive">
	                    <table class="custom-table admin-table responsive-table">
	                        <thead>
	                            <tr>
	                                <th>ID</th>
	                                <th>Nome</th>
	                                <th>Entidade pai</th>
	                                <th>Prefixo notificações</th>
	                                <th>SMTP</th>
	                                <th>Política SLA</th>
	                                <th>Fechamento pós-solução</th>
	                                <th>Ações</th>
	                            </tr>
	                        </thead>
	                        <tbody id="entitiesTableBody">
	                            <tr><td colspan="8" class="table-empty">Carregando entidades...</td></tr>
	                        </tbody>
	                    </table>
	                </div>
	            </div>
	        </div>

        <div class="page-section" id="entityNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1 id="entityFormPageTitle">Nova Entidade</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-building"></i>
                        <span id="entityFormPageSubtitleText">Criar cliente na árvore de entidades do GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="entities">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page" id="entityForm">
                <input type="hidden" name="id" value="0">
                <h3 class="admin-card-title" id="entityFormCardTitle">Dados do Cliente</h3>
                <label class="admin-field">
                    <span>Nome da entidade</span>
                    <input type="text" name="name" maxlength="255" required>
                </label>
                <label class="admin-field">
                    <span>Entidade pai</span>
                    <select name="parent_id" data-admin-select="entities">
                        <option value="0">Entidade raiz</option>
                    </select>
                </label>
                <div data-entity-create-only>
                    <label class="admin-check">
                        <input type="checkbox" name="create_group" value="1" checked>
                        <span>Criar Grupo também</span>
                    </label>
                    <label class="admin-check">
                        <input type="checkbox" name="create_sla_policy" value="1" checked>
                        <span>Criar política SLA padrão</span>
                    </label>
                    <div class="sla-policy-panel">
                        <h3 class="admin-card-title">Fechamento pós-solução</h3>
                        <p class="sla-policy-help">Defina como os chamados dessa nova entidade devem se comportar depois que uma solução for registrada.</p>
                        <label class="admin-field">
                            <span>Modo</span>
                            <select name="solution_closure_mode" id="entitySolutionClosureCreateMode">
                                <option value="inherit">Herdar da entidade pai</option>
                                <option value="immediate">Fechar imediatamente</option>
                                <option value="days">Fechar após X dias</option>
                                <option value="never">Nunca fechar automaticamente</option>
                            </select>
                        </label>
                        <label class="admin-field muted-field" id="entitySolutionClosureCreateDaysField">
                            <span>Dias</span>
                            <input type="number" name="solution_closure_days" id="entitySolutionClosureCreateDays" min="1" max="99" value="5" inputmode="numeric">
                        </label>
                        <p class="sla-policy-help" id="entitySolutionClosureCreateHelp">Por padrão a nova entidade herda o comportamento configurado na entidade pai.</p>
                    </div>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-save"></i>
                    <span id="entitySubmitButtonText">Cadastrar Cliente</span>
                </button>
                <div class="admin-status" id="entityStatus"></div>
            </form>
        </div>

        <div class="page-section" id="entityImportSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Importar Entidades</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-file-import"></i>
                        <span>Modelo: exportação CSV do GLPI (separador ;)</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="entities">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-import-form" id="entityImportForm" enctype="multipart/form-data">
                <h3 class="admin-card-title">Arquivo CSV</h3>
                <p class="admin-help-text">Use o CSV exportado pelo GLPI (separador ponto e vírgula) com a coluna "Nome completo". A hierarquia usa "&gt;" entre níveis; o segmento da entidade raiz é removido automaticamente.</p>
                <div class="ticket-create-upload-shell" data-csv-upload>
                    <input class="ticket-create-file-input" type="file" name="csv_file" accept=".csv,text/csv" hidden>
                    <div class="ticket-create-upload-dropzone" role="button" tabindex="0" aria-label="Selecionar arquivo CSV" data-csv-dropzone>
                        <div class="ticket-create-upload-main">
                            <span class="ticket-create-upload-icon"><i class="fas fa-file-csv"></i></span>
                            <div>
                                <div class="ticket-create-upload-title">Adicione o arquivo CSV</div>
                                <div class="ticket-create-upload-copy">Arraste aqui ou clique para selecionar o CSV exportado do GLPI.</div>
                            </div>
                        </div>
                        <button class="page-action-btn" type="button" data-csv-pick>
                            <i class="fas fa-paperclip"></i>
                            <span>Selecionar arquivo</span>
                        </button>
                    </div>
                    <div class="ticket-create-attachments-list" data-csv-file-label>
                        <div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>
                    </div>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-eye"></i>
                    <span>Gerar Prévia</span>
                </button>
                <div class="admin-status" id="entityImportStatus"></div>
            </form>

            <div class="glass-card table-card admin-import-preview" id="entityImportPreview" hidden>
                <div class="category-preview-header">
                    <div>
                        <h3 class="admin-card-title">Prévia da importação</h3>
                        <p id="entityImportPreviewFile">Nenhum arquivo selecionado.</p>
                    </div>
                    <button class="page-action-btn primary" type="button" id="entityImportConfirm" hidden>
                        <i class="fas fa-check"></i>
                        <span>Confirmar importação</span>
                    </button>
                </div>
                <div class="import-summary-grid" id="entityImportSummary"></div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-import-table">
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Status</th>
                                <th>Entidade</th>
                                <th>Destino</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody id="entityImportPreviewBody">
                            <tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

	        <div class="page-section" id="entitySlaPolicySection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Política SLA</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-business-time"></i>
                        <span>TTO/TTR por cliente e subentidades</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="entities">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page sla-policy-form" id="entitySlaPolicyForm">
                <input type="hidden" name="entities_id" id="slaPolicyEntityId" value="0">
                <input type="hidden" name="is_recursive" value="1">

                <div class="sla-policy-heading">
                    <div>
                        <h3 class="admin-card-title">Cliente/Entidade</h3>
                        <p id="slaPolicyEntityName">Selecione uma entidade na lista.</p>
                    </div>
                    <label class="admin-check compact">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Política ativa</span>
                    </label>
                </div>

                <label class="admin-check compact disabled">
                    <input type="checkbox" checked disabled>
                    <span>Recursivo para subentidades</span>
                </label>

                <div class="sla-policy-grid">
                    <section class="sla-policy-panel">
                        <h3 class="admin-card-title">TTO</h3>
                        <p class="sla-policy-help">Tempo máximo para assumir/atribuir chamados sem técnico usuário.</p>
                        <label class="admin-field">
                            <span>Modo</span>
                            <select name="tto_mode" id="slaPolicyTtoMode">
                                <option value="fixed">Fixo por cliente</option>
                                <option value="priority">Por prioridade</option>
                            </select>
                        </label>
                        <label class="admin-field" data-policy-fixed="tto">
                            <span>SLA fixo</span>
                            <select name="tto_fixed_key" id="slaPolicyTtoFixed">
                                <?php foreach (dashglpi_sla_keys_from_settings($slaSettings, 'TTO') as $key): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(dashglpi_sla_display_label($slaSettings, $key), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </section>

                    <section class="sla-policy-panel">
                        <h3 class="admin-card-title">TTR</h3>
                        <p class="sla-policy-help">Tempo máximo para resolver chamados do cliente.</p>
                        <label class="admin-field">
                            <span>Modo</span>
                            <select name="ttr_mode" id="slaPolicyTtrMode">
                                <option value="priority">Por prioridade</option>
                                <option value="fixed">Fixo por cliente</option>
                            </select>
                        </label>
                        <label class="admin-field" data-policy-fixed="ttr">
                            <span>SLA fixo</span>
                            <select name="ttr_fixed_key" id="slaPolicyTtrFixed">
                                <?php foreach (dashglpi_sla_keys_from_settings($slaSettings, 'TTR') as $key): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(dashglpi_sla_display_label($slaSettings, $key), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </section>
                </div>

                <div class="sla-policy-actions">
                    <button class="page-action-btn primary" type="submit">
                        <i class="fas fa-wrench"></i>
                        <span>Criar/Atualizar no GLPI</span>
                    </button>
                    <button class="page-action-btn" type="button" id="reapplyEntitySlaPolicy">
                        <i class="fas fa-sync"></i>
                        <span>Reaplicar em abertos</span>
                    </button>
                </div>
                <div class="admin-status" id="entitySlaPolicyStatus"></div>
	            </form>
	        </div>

	        <div class="page-section" id="entityNotificationPrefixSection">
	            <header class="page-header">
	                <div class="page-title-wrapper">
	                    <h1>Prefixo de Notificações</h1>
	                    <div class="page-subtitle">
	                        <i class="fas fa-tag"></i>
	                        <span>Prefixo por cliente antes do assunto do e-mail</span>
	                    </div>
	                </div>
	                <div class="header-actions">
	                    <button class="page-action-btn" type="button" data-admin-back="entities">
	                        <i class="fas fa-arrow-left"></i>
	                        <span>Voltar</span>
	                    </button>
	                </div>
	            </header>
	            <form class="glass-card admin-form admin-form-page" id="entityNotificationPrefixForm">
	                <input type="hidden" name="entities_id" id="entityNotificationPrefixEntityId" value="0">

	                <div class="sla-policy-heading">
	                    <div>
	                        <h3 class="admin-card-title">Cliente/Entidade</h3>
	                        <p id="entityNotificationPrefixEntityName">Selecione uma entidade na lista.</p>
	                    </div>
	                    <span class="table-badge primary">Assunto do GLPI</span>
	                </div>

	                <div class="sla-policy-grid entity-prefix-grid">
	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Configuração</h3>
	                        <p class="sla-policy-help">O GLPI sempre adiciona esse prefixo antes do assunto do template de e-mail.</p>
	                        <label class="admin-field">
	                            <span>Modo</span>
	                            <select name="mode" id="entityNotificationPrefixMode">
	                                <option value="inherit">Usar herdado/padrão</option>
	                                <option value="custom">Definir prefixo próprio</option>
	                            </select>
	                        </label>
	                        <label class="admin-field" id="entityNotificationPrefixCustomField">
	                            <span>Prefixo próprio</span>
	                            <input type="text" name="notification_subject_tag" id="entityNotificationPrefixValue" maxlength="255" placeholder="Ex.: KAWA">
	                        </label>
	                        <p class="sla-policy-help" id="entityNotificationPrefixModeHelp">Se nada estiver definido na hierarquia, o GLPI usa o prefixo padrão.</p>
	                    </section>

	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Prévia</h3>
	                        <p class="sla-policy-help entity-preview-note">
	                            <i class="fas fa-lock"></i>
	                            <span>Somente leitura. O conteúdo abaixo é gerado automaticamente.</span>
	                        </p>
	                        <label class="admin-field">
	                            <span>Valor efetivo</span>
	                            <input class="admin-preview-field" type="text" id="entityNotificationPrefixEffectiveValue" value="GLPI" readonly disabled>
	                        </label>
	                        <label class="admin-field">
	                            <span>Origem</span>
	                            <input class="admin-preview-field" type="text" id="entityNotificationPrefixOrigin" value="Padrao GLPI" readonly disabled>
	                        </label>
	                        <label class="admin-field">
	                            <span>Assunto gerado</span>
	                            <textarea class="admin-preview-field admin-preview-textarea" id="entityNotificationPrefixSubjectPreview" rows="3" readonly disabled>[GLPI #0000025] &lt;assunto do template&gt;</textarea>
	                        </label>
	                    </section>
	                </div>

	                <div class="sla-policy-actions">
	                    <button class="page-action-btn primary" type="submit">
	                        <i class="fas fa-save"></i>
	                        <span>Salvar no GLPI</span>
	                    </button>
	                </div>
	                <div class="admin-status" id="entityNotificationPrefixStatus"></div>
	            </form>
	        </div>

	        <div class="page-section" id="entitySmtpSection">
	            <header class="page-header">
	                <div class="page-title-wrapper">
	                    <h1>SMTP da Entidade</h1>
	                    <div class="page-subtitle">
	                        <i class="fas fa-envelope"></i>
	                        <span>Transporte de e-mail por cliente sem alterar o core do GLPI</span>
	                    </div>
	                </div>
	                <div class="header-actions">
	                    <button class="page-action-btn" type="button" id="entitySmtpFaqToggle">
	                        <i class="fas fa-circle-info"></i>
	                        <span>Como funciona</span>
	                    </button>
	                    <button class="page-action-btn" type="button" data-admin-back="entities">
	                        <i class="fas fa-arrow-left"></i>
	                        <span>Voltar</span>
	                    </button>
	                </div>
	            </header>

	            <section class="glass-card admin-form admin-form-page entitysmtp-faq" id="entitySmtpFaq" hidden>
	                <h3 class="admin-card-title">FAQ operacional</h3>
	                <details open>
	                    <summary>Quem é o dono da fila?</summary>
	                    <p>Quando o recurso está inativo, o GLPI envia pela ação automática nativa queuednotification. Quando ativo, o Dash assume pela ação dashglpi_entitysmtp_queuednotification.</p>
	                </details>
	                <details>
	                    <summary>Como reverter?</summary>
	                    <p>Use o botão Devolver fila ao GLPI. O Dash restaura o snapshot da ação queuednotification salvo na ativação e desativa a tarefa própria.</p>
	                </details>
	                <details>
	                    <summary>Onde ficam os logs?</summary>
	                    <p>O GLPI continua escrevendo em mail e mail-error. O Dash também registra entidade, host, origem e status em glpi_plugin_dashglpi_entitysmtp_logs.</p>
	                </details>
	                <details>
	                    <summary>O que acontece sem SMTP próprio?</summary>
	                    <p>O Dash procura um SMTP ativo na entidade, depois nas entidades pai. Se nada estiver ativo na hierarquia, a notificação usa o SMTP global do GLPI.</p>
	                </details>
	                <details>
	                    <summary>O core do GLPI é alterado?</summary>
	                    <p>Não. O recurso usa tabelas e ação automática do plugin DashGLPI.</p>
	                </details>
	            </section>

	            <form class="glass-card admin-form admin-form-page" id="entitySmtpForm">
	                <input type="hidden" name="entitysmtp_action" value="save_entity_smtp">
	                <input type="hidden" name="entities_id" id="entitySmtpEntityId" value="0">

	                <div class="sla-policy-heading">
	                    <div>
	                        <h3 class="admin-card-title">Cliente/Entidade</h3>
	                        <p id="entitySmtpEntityName">Selecione uma entidade na lista.</p>
	                    </div>
	                    <span class="table-badge primary" id="entitySmtpOwnerBadge">Dono: GLPI</span>
	                </div>

	                <section class="sla-policy-panel entitysmtp-owner-panel">
	                    <div>
	                        <h3 class="admin-card-title">Dono da fila</h3>
	                        <p class="sla-policy-help" id="entitySmtpOwnerSummary">Carregando estado das ações automáticas...</p>
	                    </div>
	                    <div class="sla-policy-actions">
	                        <button class="page-action-btn primary" type="button" id="entitySmtpActivateOwner">
	                            <i class="fas fa-toggle-on"></i>
	                            <span>Ativar Dash como dono</span>
	                        </button>
	                        <button class="page-action-btn" type="button" id="entitySmtpRestoreOwner">
	                            <i class="fas fa-rotate-left"></i>
	                            <span>Devolver fila ao GLPI</span>
	                        </button>
	                    </div>
	                </section>

	                <div class="sla-policy-grid entitysmtp-grid">
	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Identidade do e-mail</h3>
	                        <p class="sla-policy-help">Campos nativos da aba Notificações da entidade no GLPI.</p>
	                        <label class="admin-field">
	                            <span>E-mail do administrador</span>
	                            <input type="email" name="admin_email" id="entitySmtpAdminEmail" maxlength="255" required>
	                        </label>
	                        <label class="admin-field">
	                            <span>Nome do administrador</span>
	                            <input type="text" name="admin_email_name" id="entitySmtpAdminName" maxlength="255">
	                        </label>
	                        <label class="admin-field">
	                            <span>E-mail remetente</span>
	                            <input type="email" name="from_email" id="entitySmtpFromEmail" maxlength="255" required>
	                        </label>
	                        <label class="admin-field">
	                            <span>Nome remetente</span>
	                            <input type="text" name="from_email_name" id="entitySmtpFromName" maxlength="255">
	                        </label>
	                        <label class="admin-field">
	                            <span>E-mail de resposta</span>
	                            <input type="email" name="replyto_email" id="entitySmtpReplyEmail" maxlength="255" required>
	                        </label>
	                        <label class="admin-field">
	                            <span>Nome de resposta</span>
	                            <input type="text" name="replyto_email_name" id="entitySmtpReplyName" maxlength="255">
	                        </label>
	                        <label class="admin-field">
	                            <span>Assinatura</span>
	                            <textarea name="mailing_signature" id="entitySmtpSignature" rows="4"></textarea>
	                        </label>
	                    </section>

	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Transporte SMTP</h3>
	                        <p class="sla-policy-help">A senha não é exibida. Deixe em branco para manter a senha já gravada.</p>
	                        <div class="entitysmtp-effective">
	                            <span class="table-badge muted" id="entitySmtpEffectiveBadge">SMTP global</span>
	                            <p class="sla-policy-help" id="entitySmtpEffectiveSummary">Sem SMTP próprio ativo nesta entidade.</p>
	                        </div>
	                        <label class="admin-check compact">
	                            <input type="hidden" name="is_active" value="0">
	                            <input type="checkbox" name="is_active" id="entitySmtpIsActive" value="1">
	                            <span>Usar SMTP próprio nesta entidade</span>
	                        </label>
	                        <label class="admin-field">
	                            <span>Host SMTP</span>
	                            <input type="text" name="host" id="entitySmtpHost" maxlength="255" placeholder="smtp.exemplo.com">
	                        </label>
	                        <div class="admin-form-grid">
	                            <label class="admin-field">
	                                <span>Porta</span>
	                                <input type="number" name="port" id="entitySmtpPort" min="1" max="65535" value="587" inputmode="numeric">
	                            </label>
	                            <label class="admin-field">
	                                <span>Criptografia</span>
	                                <select name="encryption" id="entitySmtpEncryption">
	                                    <option value="tls">TLS / STARTTLS</option>
	                                    <option value="ssl">SSL / SMTPS</option>
	                                    <option value="none">Sem criptografia</option>
	                                </select>
	                            </label>
	                        </div>
	                        <label class="admin-field">
	                            <span>SMTP Login</span>
	                            <input type="text" name="smtp_username" id="entitySmtpUsername" maxlength="255">
	                        </label>
	                        <label class="admin-field">
	                            <span>SMTP Senha</span>
	                            <input type="password" name="smtp_passwd" id="entitySmtpPassword" autocomplete="new-password">
	                            <small id="entitySmtpPasswordHint">Nenhuma senha configurada.</small>
	                        </label>
	                        <label class="admin-check compact">
	                            <input type="hidden" name="smtp_check_certificate" value="0">
	                            <input type="checkbox" name="smtp_check_certificate" id="entitySmtpCheckCertificate" value="1" checked>
	                            <span>Validar certificado SMTP</span>
	                        </label>
	                    </section>
	                </div>

	                <div class="sla-policy-actions">
	                    <button class="page-action-btn primary" type="submit">
	                        <i class="fas fa-save"></i>
	                        <span>Salvar no GLPI</span>
	                    </button>
	                    <button class="page-action-btn" type="button" id="entitySmtpTestButton">
	                        <i class="fas fa-paper-plane"></i>
	                        <span>Testar envio</span>
	                    </button>
	                </div>
	                <div class="admin-status" id="entitySmtpStatus"></div>
	            </form>
	        </div>

	        <div class="page-section" id="entitySolutionClosureSection">
	            <header class="page-header">
	                <div class="page-title-wrapper">
	                    <h1>Fechamento pós-solução</h1>
	                    <div class="page-subtitle">
	                        <i class="fas fa-check-double"></i>
	                        <span>Comportamento do ticket depois que a solução é registrada</span>
	                    </div>
	                </div>
	                <div class="header-actions">
	                    <button class="page-action-btn" type="button" data-admin-back="entities">
	                        <i class="fas fa-arrow-left"></i>
	                        <span>Voltar</span>
	                    </button>
	                </div>
	            </header>
	            <form class="glass-card admin-form admin-form-page" id="entitySolutionClosureForm">
	                <input type="hidden" name="entities_id" id="entitySolutionClosureEntityId" value="0">

	                <div class="sla-policy-heading">
	                    <div>
	                        <h3 class="admin-card-title">Cliente/Entidade</h3>
	                        <p id="entitySolutionClosureEntityName">Selecione uma entidade na lista.</p>
	                    </div>
	                    <span class="table-badge success">Ciclo de vida do ticket</span>
	                </div>

	                <div class="sla-policy-grid entity-prefix-grid">
	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Configuração</h3>
	                        <p class="sla-policy-help">Essa opção define se o chamado fecha na hora, fica solucionado aguardando aprovação ou fecha sozinho depois de alguns dias.</p>
	                        <label class="admin-field">
	                            <span>Modo</span>
	                            <select name="mode" id="entitySolutionClosureMode">
	                                <option value="inherit">Herdar da entidade pai</option>
	                                <option value="immediate">Fechar imediatamente</option>
	                                <option value="days">Fechar após X dias</option>
	                                <option value="never">Nunca fechar automaticamente</option>
	                            </select>
	                        </label>
	                        <label class="admin-field" id="entitySolutionClosureDaysField">
	                            <span>Dias para fechar</span>
	                            <input type="number" name="days" id="entitySolutionClosureDays" min="1" max="99" value="5" inputmode="numeric">
	                        </label>
	                        <p class="sla-policy-help" id="entitySolutionClosureModeHelp">Se nada for definido localmente, a entidade usará a mesma configuração da hierarquia.</p>
	                    </section>

	                    <section class="sla-policy-panel">
	                        <h3 class="admin-card-title">Resumo efetivo</h3>
	                        <p class="sla-policy-help entity-preview-note">
	                            <i class="fas fa-lock"></i>
	                            <span>Somente leitura. O Dash mostra abaixo o comportamento que o GLPI aplicará no ticket.</span>
	                        </p>
	                        <label class="admin-field">
	                            <span>Configuração efetiva</span>
	                            <input class="admin-preview-field" type="text" id="entitySolutionClosureEffectiveValue" value="Nunca" readonly disabled>
	                        </label>
	                        <label class="admin-field">
	                            <span>Origem</span>
	                            <input class="admin-preview-field" type="text" id="entitySolutionClosureOrigin" value="Padrao GLPI" readonly disabled>
	                        </label>
	                        <label class="admin-field">
	                            <span>Resultado ao solucionar</span>
	                            <textarea class="admin-preview-field admin-preview-textarea" id="entitySolutionClosureBehaviorPreview" rows="3" readonly disabled>Ao adicionar a solução, o ticket fica solucionado aguardando fechamento manual.</textarea>
	                        </label>
	                    </section>
	                </div>

	                <div class="sla-policy-actions">
	                    <button class="page-action-btn primary" type="submit">
	                        <i class="fas fa-save"></i>
	                        <span>Salvar no GLPI</span>
	                    </button>
	                </div>
	                <div class="admin-status" id="entitySolutionClosureStatus"></div>
	            </form>
	        </div>

	        <!-- Groups Section -->
	        <div class="page-section" id="groupsSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Grupo</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-users"></i>
                        <span>Cadastro de grupos por entidade</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-new="groupImport">
                        <i class="fas fa-file-import"></i>
                        <span>Importar CSV</span>
                    </button>
                    <button class="page-action-btn primary" type="button" data-admin-new="group">
                        <i class="fas fa-plus"></i>
                        <span>Novo</span>
                    </button>
                </div>
            </header>
            <div class="glass-card table-card admin-table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="groupsSearchInput" placeholder="Buscar grupo ou entidade...">
                    </div>
                    <div class="tickets-count" id="groupsCount">0 grupos</div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table admin-table responsive-table responsive-table--pin-status">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Grupo</th>
                                <th>Entidade</th>
                                <th>Recursivo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="groupsTableBody">
                            <tr><td colspan="5" class="table-empty">Carregando grupos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="page-section" id="groupNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1 id="groupFormPageTitle">Novo Grupo</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-users"></i>
                        <span id="groupFormPageSubtitleText">Criar grupo posicionado em uma entidade</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="groups">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page" id="groupForm">
                <input type="hidden" name="id" value="0">
                <h3 class="admin-card-title">Dados do Grupo</h3>
                <label class="admin-field">
                    <span>Nome do grupo</span>
                    <input type="text" name="name" maxlength="255" required>
                </label>
                <label class="admin-field">
                    <span>Entidade</span>
                    <select name="entities_id" data-admin-select="entities" required>
                        <option value="0">Entidade raiz</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span>Comentário</span>
                    <textarea name="comment" rows="3"></textarea>
                </label>
                <label class="admin-check">
                    <input type="checkbox" name="is_recursive" value="1">
                    <span>Recursivo nas subentidades</span>
                </label>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-save"></i>
                    <span id="groupSubmitButtonText">Cadastrar Grupo</span>
                </button>
                <div class="admin-status" id="groupStatus"></div>
            </form>
        </div>

        <div class="page-section" id="groupImportSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Importar Grupos</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-file-import"></i>
                        <span>Modelo: exportação CSV do GLPI (separador ;)</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="groups">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-import-form" id="groupImportForm" enctype="multipart/form-data">
                <h3 class="admin-card-title">Arquivo CSV</h3>
                <p class="admin-help-text">Use o CSV exportado pelo GLPI (separador ponto e vírgula) com a coluna "Nome completo" (ou "Nome"). Colunas opcionais: "Entidade", "Recursivo" e "Comentários". A hierarquia usa "&gt;" entre níveis; entidade não encontrada usa a raiz (marcada na prévia).</p>
                <div class="ticket-create-upload-shell" data-csv-upload>
                    <input class="ticket-create-file-input" type="file" name="csv_file" accept=".csv,text/csv" hidden>
                    <div class="ticket-create-upload-dropzone" role="button" tabindex="0" aria-label="Selecionar arquivo CSV" data-csv-dropzone>
                        <div class="ticket-create-upload-main">
                            <span class="ticket-create-upload-icon"><i class="fas fa-file-csv"></i></span>
                            <div>
                                <div class="ticket-create-upload-title">Adicione o arquivo CSV</div>
                                <div class="ticket-create-upload-copy">Arraste aqui ou clique para selecionar o CSV exportado do GLPI.</div>
                            </div>
                        </div>
                        <button class="page-action-btn" type="button" data-csv-pick>
                            <i class="fas fa-paperclip"></i>
                            <span>Selecionar arquivo</span>
                        </button>
                    </div>
                    <div class="ticket-create-attachments-list" data-csv-file-label>
                        <div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>
                    </div>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-eye"></i>
                    <span>Gerar Prévia</span>
                </button>
                <div class="admin-status" id="groupImportStatus"></div>
            </form>

            <div class="glass-card table-card admin-import-preview" id="groupImportPreview" hidden>
                <div class="category-preview-header">
                    <div>
                        <h3 class="admin-card-title">Prévia da importação</h3>
                        <p id="groupImportPreviewFile">Nenhum arquivo selecionado.</p>
                    </div>
                    <button class="page-action-btn primary" type="button" id="groupImportConfirm" hidden>
                        <i class="fas fa-check"></i>
                        <span>Confirmar importação</span>
                    </button>
                </div>
                <div class="import-summary-grid" id="groupImportSummary"></div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-import-table">
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Status</th>
                                <th>Grupo</th>
                                <th>Entidade destino</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody id="groupImportPreviewBody">
                            <tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Profiles Section -->
        <div class="page-section" id="profilesSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Perfil</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-id-badge"></i>
                        <span>Cadastro de perfis de acesso do GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-new="profileImport">
                        <i class="fas fa-file-import"></i>
                        <span>Importar CSV</span>
                    </button>
                    <button class="page-action-btn primary" type="button" data-admin-new="profile">
                        <i class="fas fa-plus"></i>
                        <span>Novo</span>
                    </button>
                </div>
            </header>
            <div class="glass-card table-card admin-table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="profilesSearchInput" placeholder="Buscar perfil...">
                    </div>
                    <div class="tickets-count" id="profilesCount">0 perfis</div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table admin-table responsive-table responsive-table--pin-status">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Interface</th>
                                <th>Comentário</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="profilesTableBody">
                            <tr><td colspan="5" class="table-empty">Carregando perfis...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="page-section" id="profileNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1 id="profileFormPageTitle">Novo Perfil</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-id-badge"></i>
                        <span id="profileFormPageSubtitleText">Criar perfil de acesso do GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="profiles">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page" id="profileForm">
                <input type="hidden" name="id" value="0">
                <h3 class="admin-card-title">Dados do Perfil</h3>
                <label class="admin-field">
                    <span>Nome do perfil</span>
                    <input type="text" name="name" maxlength="255" required>
                </label>
                <label class="admin-field">
                    <span>Interface</span>
                    <select name="interface" required>
                        <option value="helpdesk">Helpdesk</option>
                        <option value="central">Central</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span>Comentário</span>
                    <textarea name="comment" rows="3"></textarea>
                </label>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-save"></i>
                    <span id="profileSubmitButtonText">Cadastrar Perfil</span>
                </button>
                <div class="admin-status" id="profileStatus"></div>
            </form>
        </div>

        <!-- ITIL Categories Section -->
        <div class="page-section" id="profileImportSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Importar Perfis</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-file-import"></i>
                        <span>Modelo: exportação CSV do GLPI (separador ;)</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="profiles">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-import-form" id="profileImportForm" enctype="multipart/form-data">
                <h3 class="admin-card-title">Arquivo CSV</h3>
                <p class="admin-help-text">Use o CSV exportado pelo GLPI (separador ponto e vírgula) com a coluna "Nome". Colunas opcionais: "Interface" (Central ou Helpdesk; padrão Helpdesk) e "Comentários".</p>
                <div class="ticket-create-upload-shell" data-csv-upload>
                    <input class="ticket-create-file-input" type="file" name="csv_file" accept=".csv,text/csv" hidden>
                    <div class="ticket-create-upload-dropzone" role="button" tabindex="0" aria-label="Selecionar arquivo CSV" data-csv-dropzone>
                        <div class="ticket-create-upload-main">
                            <span class="ticket-create-upload-icon"><i class="fas fa-file-csv"></i></span>
                            <div>
                                <div class="ticket-create-upload-title">Adicione o arquivo CSV</div>
                                <div class="ticket-create-upload-copy">Arraste aqui ou clique para selecionar o CSV exportado do GLPI.</div>
                            </div>
                        </div>
                        <button class="page-action-btn" type="button" data-csv-pick>
                            <i class="fas fa-paperclip"></i>
                            <span>Selecionar arquivo</span>
                        </button>
                    </div>
                    <div class="ticket-create-attachments-list" data-csv-file-label>
                        <div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>
                    </div>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-eye"></i>
                    <span>Gerar Prévia</span>
                </button>
                <div class="admin-status" id="profileImportStatus"></div>
            </form>

            <div class="glass-card table-card admin-import-preview" id="profileImportPreview" hidden>
                <div class="category-preview-header">
                    <div>
                        <h3 class="admin-card-title">Prévia da importação</h3>
                        <p id="profileImportPreviewFile">Nenhum arquivo selecionado.</p>
                    </div>
                    <button class="page-action-btn primary" type="button" id="profileImportConfirm" hidden>
                        <i class="fas fa-check"></i>
                        <span>Confirmar importação</span>
                    </button>
                </div>
                <div class="import-summary-grid" id="profileImportSummary"></div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-import-table">
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Status</th>
                                <th>Perfil</th>
                                <th>Interface</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody id="profileImportPreviewBody">
                            <tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="page-section" id="categoriesSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Categorias ITIL</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-sitemap"></i>
                        <span>Cadastro e importação de categorias do GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-new="categoryImport">
                        <i class="fas fa-file-import"></i>
                        <span>Importar CSV</span>
                    </button>
                    <button class="page-action-btn primary" type="button" data-admin-new="category">
                        <i class="fas fa-plus"></i>
                        <span>Nova Categoria</span>
                    </button>
                </div>
            </header>
            <div class="glass-card table-card admin-table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="categoriesSearchInput" placeholder="Buscar por ID, categoria, entidade, pai ou flags...">
                    </div>
                    <div class="tickets-count" id="categoriesCount">0 categorias</div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-table responsive-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Categoria</th>
                                <th>Entidade</th>
                                <th>Pai</th>
                                <th>Uso</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody">
                            <tr><td colspan="5" class="table-empty">Carregando categorias...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="page-section" id="categoryNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Nova Categoria ITIL</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-sitemap"></i>
                        <span>Criar categoria manual no GLPI</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="categories">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page" id="categoryForm">
                <h3 class="admin-card-title">Dados da Categoria</h3>
                <label class="admin-field">
                    <span>Nome da categoria</span>
                    <input type="text" name="name" maxlength="255" required>
                </label>
                <div class="admin-form-grid">
                    <label class="admin-field">
                        <span>Entidade</span>
                        <select name="entities_id" data-admin-select="entities" required>
                            <option value="0">Entidade raiz</option>
                        </select>
                    </label>
                    <label class="admin-field">
                        <span>Categoria pai</span>
                        <select name="parent_id" id="categoryParentSelect">
                            <option value="0">Sem categoria pai</option>
                        </select>
                    </label>
                </div>
                <label class="admin-field">
                    <span>Comentário</span>
                    <textarea name="comment" rows="3"></textarea>
                </label>
                <div class="admin-form-grid">
                    <label class="admin-check">
                        <input type="hidden" name="is_recursive" value="0">
                        <input type="checkbox" name="is_recursive" value="1" checked>
                        <span>Recursivo</span>
                    </label>
                    <label class="admin-check">
                        <input type="hidden" name="is_helpdeskvisible" value="0">
                        <input type="checkbox" name="is_helpdeskvisible" value="1" checked>
                        <span>Visível no helpdesk</span>
                    </label>
                    <label class="admin-check">
                        <input type="hidden" name="is_incident" value="0">
                        <input type="checkbox" name="is_incident" value="1" checked>
                        <span>Incidente</span>
                    </label>
                    <label class="admin-check">
                        <input type="hidden" name="is_request" value="0">
                        <input type="checkbox" name="is_request" value="1" checked>
                        <span>Requisição</span>
                    </label>
                    <label class="admin-check">
                        <input type="hidden" name="is_problem" value="0">
                        <input type="checkbox" name="is_problem" value="1">
                        <span>Problema</span>
                    </label>
                    <label class="admin-check">
                        <input type="hidden" name="is_change" value="0">
                        <input type="checkbox" name="is_change" value="1">
                        <span>Mudança</span>
                    </label>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-save"></i>
                    <span>Cadastrar Categoria</span>
                </button>
                <div class="admin-status" id="categoryStatus"></div>
            </form>
        </div>

        <div class="page-section" id="categoryImportSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Importar Categorias ITIL</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-file-import"></i>
                        <span>Modelo: glpi-categoria-ITL.csv</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="categories">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-import-form" id="categoryImportForm" enctype="multipart/form-data">
                <h3 class="admin-card-title">Arquivo CSV</h3>
                <p class="admin-help-text">Use separador ponto e vírgula com as colunas "Nome completo" e "Entidade". A hierarquia deve usar ">" entre níveis.</p>
                <div class="ticket-create-upload-shell" data-csv-upload>
                    <input class="ticket-create-file-input" type="file" name="csv_file" accept=".csv,text/csv" hidden>
                    <div class="ticket-create-upload-dropzone" role="button" tabindex="0" aria-label="Selecionar arquivo CSV" data-csv-dropzone>
                        <div class="ticket-create-upload-main">
                            <span class="ticket-create-upload-icon"><i class="fas fa-file-csv"></i></span>
                            <div>
                                <div class="ticket-create-upload-title">Adicione o arquivo CSV</div>
                                <div class="ticket-create-upload-copy">Arraste aqui ou clique para selecionar o CSV exportado do GLPI.</div>
                            </div>
                        </div>
                        <button class="page-action-btn" type="button" data-csv-pick>
                            <i class="fas fa-paperclip"></i>
                            <span>Selecionar arquivo</span>
                        </button>
                    </div>
                    <div class="ticket-create-attachments-list" data-csv-file-label>
                        <div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>
                    </div>
                </div>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-eye"></i>
                    <span>Gerar Prévia</span>
                </button>
                <div class="admin-status" id="categoryImportStatus"></div>
            </form>

            <div class="glass-card table-card admin-import-preview" id="categoryImportPreview" hidden>
                <div class="category-preview-header">
                    <div>
                        <h3 class="admin-card-title">Prévia da importação</h3>
                        <p id="categoryImportPreviewFile">Nenhum arquivo selecionado.</p>
                    </div>
                    <button class="page-action-btn primary" type="button" id="confirmCategoryImport" hidden>
                        <i class="fas fa-check"></i>
                        <span>Confirmar importação</span>
                    </button>
                </div>
                <div class="import-summary-grid" id="categoryImportSummary"></div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-import-table">
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Status</th>
                                <th>Categoria</th>
                                <th>Entidade destino</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody id="categoryImportPreviewBody">
                            <tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div class="page-section" id="usersSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Usuários</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-user-plus"></i>
                        <span>Cadastro com entidade e perfil</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-new="userImport">
                        <i class="fas fa-file-import"></i>
                        <span>Importar CSV</span>
                    </button>
                    <button class="page-action-btn primary" type="button" data-admin-new="user">
                        <i class="fas fa-plus"></i>
                        <span>Novo</span>
                    </button>
                </div>
            </header>
            <div class="glass-card table-card admin-table-card">
                <div class="tickets-toolbar">
                    <div class="tickets-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="usersSearchInput" placeholder="Buscar usuário, e-mail, entidade ou perfil...">
                    </div>
                    <div class="tickets-count" id="usersCount">0 usuários</div>
                </div>
                <div class="admin-filters" id="usersFilters">
                    <label class="admin-filter">
                        <span>Entidade</span>
                        <select id="usersFilterEntity">
                            <option value="">Todas</option>
                        </select>
                    </label>
                    <label class="admin-filter">
                        <span>Perfil</span>
                        <select id="usersFilterProfile">
                            <option value="">Todos</option>
                        </select>
                    </label>
                    <label class="admin-filter">
                        <span>Status</span>
                        <select id="usersFilterStatus">
                            <option value="">Todos</option>
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </label>
                    <button type="button" class="admin-filter-clear" id="usersFilterClear">
                        <i class="fas fa-rotate-left"></i>
                        <span>Limpar filtros</span>
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="custom-table admin-table responsive-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Entidade</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr><td colspan="7" class="table-empty">Carregando usuários...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="page-section" id="userNewSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1 id="userFormPageTitle">Novo Usuário</h1>
                    <div class="page-subtitle" id="userFormPageSubtitle">
                        <i class="fas fa-user-plus"></i>
                        <span id="userFormPageSubtitleText">Criar usuário com entidade e perfil</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="users">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-wizard" id="userForm">
                <input type="hidden" name="id" value="0">

                <ol class="admin-wizard-steps" id="userWizardSteps">
                    <li class="admin-wizard-step is-active" data-step-indicator="1"><span class="admin-wizard-num">1</span><span class="admin-wizard-label">Identidade</span></li>
                    <li class="admin-wizard-step" data-step-indicator="2"><span class="admin-wizard-num">2</span><span class="admin-wizard-label">Acesso</span></li>
                    <li class="admin-wizard-step" data-step-indicator="3"><span class="admin-wizard-num">3</span><span class="admin-wizard-label">Vínculos</span></li>
                </ol>

                <!-- Passo 1: Identidade -->
                <section class="admin-wizard-panel is-active" data-step-panel="1">
                    <h3 class="admin-card-title" id="userFormCardTitle">Identidade do Usuário</h3>
                    <div class="admin-form-grid">
                        <label class="admin-field">
                            <span>Login</span>
                            <input type="text" name="login" maxlength="255" required>
                            <small>Usado para entrar no GLPI. Deve ser único (ex.: joao.silva).</small>
                        </label>
                        <label class="admin-field">
                            <span>E-mail</span>
                            <input type="email" name="email" maxlength="255">
                            <small>Recebe notificações de chamados. Opcional, mas recomendado.</small>
                        </label>
                        <label class="admin-field">
                            <span>Telegram (@usuário ou ID)</span>
                            <input type="text" name="telegram_user_id" id="userTelegramId" maxlength="33" placeholder="Ex.: @joaosilva ou 123456789">
                            <small>Opcional — usado para menção real no Telegram quando um chamado é atribuído a este usuário. Use seu <strong>@usuário</strong> do Telegram (mais fácil) ou o ID numérico (descubra falando com <strong>@userinfobot</strong>).</small>
                        </label>
                        <label class="admin-field">
                            <span>Nome</span>
                            <input type="text" name="firstname" maxlength="255">
                            <small>Primeiro nome exibido no GLPI.</small>
                        </label>
                        <label class="admin-field">
                            <span>Sobrenome</span>
                            <input type="text" name="realname" maxlength="255">
                            <small>Sobrenome exibido junto ao nome.</small>
                        </label>
                    </div>
                </section>

                <!-- Passo 2: Acesso -->
                <section class="admin-wizard-panel" data-step-panel="2">
                    <h3 class="admin-card-title">Acesso e Senha</h3>
                    <label class="admin-field">
                        <span id="userPasswordLabel">Senha temporária</span>
                        <div class="admin-password-control">
                            <input type="password" name="password" id="userPasswordInput" required>
                            <button type="button" class="admin-password-btn" id="userPasswordToggle" title="Mostrar/ocultar senha" aria-label="Mostrar ou ocultar senha">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="admin-password-btn" id="userPasswordGenerate" title="Gerar senha temporária forte">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                <span>Gerar</span>
                            </button>
                        </div>
                        <div class="password-meter" id="userPasswordMeter" hidden>
                            <div class="password-meter-bar"><span class="password-meter-fill" id="userPasswordMeterFill"></span></div>
                            <span class="password-meter-label" id="userPasswordMeterLabel"></span>
                        </div>
                        <small id="userPasswordHelp">Obrigatória para novos usuários.</small>
                    </label>
                    <label class="admin-check">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Usuário ativo</span>
                    </label>
                </section>

                <!-- Passo 3: Vínculos -->
                <section class="admin-wizard-panel" data-step-panel="3">
                    <h3 class="admin-card-title">Entidade, Perfil e Grupo</h3>
                    <div class="admin-form-grid">
                        <label class="admin-field">
                            <span>Entidade</span>
                            <select name="entities_id" data-admin-select="entities" required>
                                <option value="0">Entidade raiz</option>
                            </select>
                            <small>Define o escopo onde o usuário atuará.</small>
                        </label>
                        <label class="admin-field">
                            <span>Perfil</span>
                            <select name="profiles_id" id="userProfileSelect" required></select>
                            <small>Conjunto de permissões (ex.: Technician, Self-Service).</small>
                        </label>
                    </div>
                    <label class="admin-field">
                        <span>Grupo opcional</span>
                        <select name="groups_id" id="userGroupSelect">
                            <option value="0">Sem grupo</option>
                        </select>
                        <small>Associe a uma equipe para roteamento de chamados.</small>
                    </label>
                </section>

                <div class="admin-wizard-nav">
                    <button class="page-action-btn" type="button" id="userWizardPrev" hidden>
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                    <button class="page-action-btn primary" type="button" id="userWizardNext">
                        <span>Avançar</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button class="admin-submit" type="submit" id="userSubmitButton" hidden>
                        <i class="fas fa-save"></i>
                        <span id="userSubmitButtonText">Cadastrar Usuário</span>
                    </button>
                </div>
                <div class="admin-status" id="userStatus"></div>
            </form>
        </div>

        <div class="page-section" id="userImportSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Importar Usuários</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-file-import"></i>
                        <span>Modelo: exportação CSV do GLPI (separador ;)</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="page-action-btn" type="button" data-admin-back="users">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                </div>
            </header>
            <form class="glass-card admin-form admin-form-page admin-import-form" id="userImportForm" enctype="multipart/form-data">
                <h3 class="admin-card-title">Arquivo CSV</h3>
                <p class="admin-help-text">Use o CSV exportado pelo GLPI (separador ponto e vírgula) com a coluna "Usuário" (ou "Login"). Colunas opcionais: "Último nome"/"Sobrenome", "Nome", "E-mails", "Telefone", "Entidade", "Perfil", "Grupo", "Ativo" e "Senha". Linhas sem a coluna "Perfil" usam o perfil selecionado abaixo; sem a coluna "Senha", uma senha temporária forte é gerada automaticamente (redefina depois).</p>
                <div class="ticket-create-upload-shell" data-csv-upload>
                    <input class="ticket-create-file-input" type="file" name="csv_file" accept=".csv,text/csv" hidden>
                    <div class="ticket-create-upload-dropzone" role="button" tabindex="0" aria-label="Selecionar arquivo CSV" data-csv-dropzone>
                        <div class="ticket-create-upload-main">
                            <span class="ticket-create-upload-icon"><i class="fas fa-file-csv"></i></span>
                            <div>
                                <div class="ticket-create-upload-title">Adicione o arquivo CSV</div>
                                <div class="ticket-create-upload-copy">Arraste aqui ou clique para selecionar o CSV exportado do GLPI.</div>
                            </div>
                        </div>
                        <button class="page-action-btn" type="button" data-csv-pick>
                            <i class="fas fa-paperclip"></i>
                            <span>Selecionar arquivo</span>
                        </button>
                    </div>
                    <div class="ticket-create-attachments-list" data-csv-file-label>
                        <div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>
                    </div>
                </div>
                <label class="admin-field">
                    <span>Perfil para linhas sem coluna "Perfil"</span>
                    <select name="default_profile_id" id="userImportProfileSelect"></select>
                </label>
                <button class="admin-submit" type="submit">
                    <i class="fas fa-eye"></i>
                    <span>Gerar Prévia</span>
                </button>
                <div class="admin-status" id="userImportStatus"></div>
            </form>

            <div class="glass-card table-card admin-import-preview" id="userImportPreview" hidden>
                <div class="category-preview-header">
                    <div>
                        <h3 class="admin-card-title">Prévia da importação</h3>
                        <p id="userImportPreviewFile">Nenhum arquivo selecionado.</p>
                    </div>
                    <button class="page-action-btn primary" type="button" id="userImportConfirm" hidden>
                        <i class="fas fa-check"></i>
                        <span>Confirmar importação</span>
                    </button>
                </div>
                <div class="import-summary-grid" id="userImportSummary"></div>
                <div class="table-responsive">
                    <table class="custom-table admin-table categories-import-table">
                        <thead>
                            <tr>
                                <th>Linha</th>
                                <th>Status</th>
                                <th>Usuário</th>
                                <th>Entidade · Perfil</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody id="userImportPreviewBody">
                            <tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canAssets): ?>
        <!-- Assets Section -->
        <div class="page-section<?= $defaultPage === 'assets' ? ' active' : '' ?>" id="assetsSection">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Inventário de Ativos</h1>
                    <div class="page-subtitle">
                        <i class="fas fa-network-wired"></i>
                        <span>Monitoramento de Hardware & Grid</span>
                    </div>
                </div>
            </header>
            <div id="assets-grid" class="assets-grid-container">
                <div class="text-center p-5">
                    <i class="fas fa-circle-notch fa-spin fa-2x"></i> Carregando grid...
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php if ($canAssets): ?>
    <!-- Cyberpunk Asset Modal -->
    <div id="cyber-modal" class="cyber-overlay">
        <div class="cyber-hud">
            <button class="cyber-close" onclick="closeCyberModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="cyber-header">
                <div class="cyber-id-badge">ID: <span id="modal-id">000</span></div>
                <h2 id="modal-name" class="cyber-glitch-text">ASSET_NAME</h2>
                <div class="cyber-sub" id="modal-sub">MODELO / TIPO</div>
            </div>
            <div class="cyber-grid-layout">
                <div class="cyber-col-left">
                    <div class="cyber-box">
                        <div class="cyber-label">SYSTEM STATUS</div>
                        <div class="cyber-status-text" id="modal-status">ONLINE</div>
                    </div>
                    <div class="cyber-box">
                        <div class="cyber-label">OPERATING SYSTEM</div>
                        <div class="cyber-os-display" id="modal-os">
                            <i class="fab fa-windows"></i> Windows 11
                        </div>
                    </div>
                    <div class="cyber-box">
                        <div class="cyber-label">LOCATION DATA</div>
                        <div class="cyber-loc-text" id="modal-loc">TI > SERVIDOR</div>
                    </div>
                </div>
                <div class="cyber-col-right">
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-microchip"></i> CPU CORE</div>
                        <div class="stat-value-text" id="modal-cpu">Intel Core i7</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" style="width: 80%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-memory"></i> MEMORY MODULE</div>
                        <div class="stat-value-text" id="modal-ram">16 GB</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" id="bar-ram" style="width: 40%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-hdd"></i> STORAGE UNIT</div>
                        <div class="stat-value-text" id="modal-hdd">512 GB SSD</div>
                        <div class="cyber-progress-bg">
                            <div class="cyber-progress-fill" id="bar-hdd" style="width: 60%"></div>
                        </div>
                    </div>
                    <div class="cyber-stat-row">
                        <div class="stat-label"><i class="fas fa-barcode"></i> SERIAL KEY</div>
                        <div class="stat-value-text mono" id="modal-serial">CN-0X554-...</div>
                    </div>
                </div>
            </div>
            <div class="cyber-footer">
                <div class="cyber-scanline"></div>
                <span>SECURE CONNECTION ESTABLISHED // ACCESS GRANTED</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?= htmlspecialchars(dashglpi_asset_url('vendor/js/chart.umd.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.ticket-create.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.charts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.kanban.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.self-service.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.ticket-attendance.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.sla-monitor.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/script.admin.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.DASHGLPI_SERVICE_WORKER_REGISTRATION = navigator.serviceWorker.register('<?= htmlspecialchars(dashglpi_asset_url('service-worker.js'), ENT_QUOTES, 'UTF-8') ?>')
                .catch(function (error) { console.warn('DashGLPI PWA indisponível:', error); return null; });
        }
    </script>
</body>
</html>

<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/glpi_admin.php';
require_once __DIR__ . '/../inc/notification_admin.php';
require_once __DIR__ . '/../inc/dashboard.class.php';

dashglpi_require_auth();

$action = $_GET['action'] ?? '';
if (in_array($action, ['dashboard_data', 'tickets_list'], true) && !empty(dashglpi_current_user_context()['lock_my_tasks'])) {
    $_GET['my_tasks'] = '1';
}

try {
    switch ($action) {
        case 'dashboard_data':
            dashglpi_assert_page_access('dashboard');
            dashglpi_json(PluginDashglpiDashboard::getDashboardData($_GET));
            break;

        case 'get_ranking':
            dashglpi_assert_page_access('ranking');
            dashglpi_json(PluginDashglpiDashboard::getRanking());
            break;

        case 'tickets_list':
            dashglpi_assert_page_access('tickets');
            dashglpi_json(PluginDashglpiDashboard::getTicketsList($_GET));
            break;

        case 'sla_list':
            dashglpi_assert_page_access('sla');
            dashglpi_json(PluginDashglpiDashboard::getSlaList($_GET));
            break;

        case 'assets_list':
            dashglpi_assert_page_access('assets');
            dashglpi_json(PluginDashglpiDashboard::getAssetsList());
            break;

        case 'glpi_health':
            dashglpi_require_admin();
            dashglpi_json(PluginDashglpiDashboard::getGlpiHealthData($_GET));
            break;

        case 'technicians_list':
            dashglpi_assert_page_access('tickets');
            dashglpi_json(PluginDashglpiDashboard::getTechniciansList());
            break;

        default:
            dashglpi_json(['error' => 'Acao invalida.'], 400);
            break;
    }
} catch (Throwable $e) {
    error_log('[DashGLPI] AJAX error: ' . $e->getMessage());
    dashglpi_json(['error' => 'Erro interno do servidor.'], 500);
}

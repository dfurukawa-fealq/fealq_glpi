<?php

/**
 * Plugin DashGLPI - Dashboard avançado para GLPI
 *
 * @author  Fealq
 * @license AGPLv3+
 */

define('PLUGIN_DASHGLPI_VERSION', '1.0.1');
define('PLUGIN_DASHGLPI_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_DASHGLPI_MAX_GLPI_VERSION', '11.0.99');

require_once __DIR__ . '/inc/entitysmtpqueuednotification.class.php';

/**
 * Retorna informações da versão do plugin
 */

function plugin_version_dashglpi()
{
    global $CFG_GLPI, $TRANSLATE;

    $lang = '';
    if (class_exists('Session') && method_exists('Session', 'getLanguage')) {
        $lang = Session::getLanguage() ?? '';
    }
    if (!$lang && isset($CFG_GLPI['language'])) {
        $lang = $CFG_GLPI['language'];
    }

    if (
        method_exists('Plugin', 'loadLang')
        && isset($TRANSLATE)
        && is_object($TRANSLATE)
    ) {
        Plugin::loadLang('dashglpi', $lang);
    }

    return [
        'name'     => 'Fealq - GLPI',
        'version'  => PLUGIN_DASHGLPI_VERSION,
        'author'   => '<a href="https://fealq.org.br/">Fealq</a>',
        'license'  => 'AGPLv3+',
        'homepage' => 'https://fealq.org.br/',
        'readme'   => 'https://fealq.org.br/',
        'issues'   => 'https://fealq.org.br/',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_DASHGLPI_MIN_GLPI_VERSION,
                'max' => PLUGIN_DASHGLPI_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => '7.4'
            ]
        ],
    ];
}

/**
 * Inicialização do plugin — registra hooks, menu, CSS e JS
 */
function plugin_init_dashglpi()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dashglpi'] = true;
    // Bridges de atendimento com contexto do ator (PLAN-20260905-001).
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_attendance_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_task_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/sla_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/entity_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/group_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/user_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/notification_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/mailcollector_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_assignment_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_update_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/health_diagnostics_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_create_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_followup_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_cancel_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_solution_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/ticket_satisfaction_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/entity_notification_prefix_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/entity_solution_closure_config\.php$#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('dashglpi', '#^/ajax/entitysmtp_config\.php$#');

    if (Session::getLoginUserID()) {
        // Menu
        $PLUGIN_HOOKS['redefine_menus']['dashglpi'] = 'plugin_dashglpi_redefine_menus';

        // JS mínimo: força o link do menu a abrir em nova aba
        $PLUGIN_HOOKS['add_javascript']['dashglpi'] = 'js/menu.js';
    }
}

/**
 * Verifica pré-requisitos do plugin
 */
function plugin_dashglpi_check_prerequisites()
{
    return true;
}

/**
 * Verifica configuração do plugin
 */
function plugin_dashglpi_check_config()
{
    return true;
}

<?php

/**
 * Plugin DashGLPI - Hook functions
 */

require_once __DIR__ . '/inc/entitysmtpqueuednotification.class.php';

/**
 * Instalação do plugin
 */
function plugin_dashglpi_install()
{
    global $DB;

    if (isset($DB)) {
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS glpi_plugin_dashglpi_sla_client_policies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entities_id INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_recursive TINYINT(1) NOT NULL DEFAULT 1,
                tto_mode VARCHAR(20) NOT NULL DEFAULT 'fixed',
                tto_fixed_key VARCHAR(20) NOT NULL DEFAULT 'TTO-P1',
                ttr_mode VARCHAR(20) NOT NULL DEFAULT 'priority',
                ttr_fixed_key VARCHAR(20) NOT NULL DEFAULT 'TTR-P1',
                managed_ids JSON NULL,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_mod TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dashglpi_sla_policy_entity (entities_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Motor de SLA ativo (Step 5): tabela de deduplicação de alertas enviados.
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS glpi_plugin_dashglpi_sla_alerts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tickets_id INT UNSIGNED NOT NULL,
                level VARCHAR(10) NOT NULL DEFAULT 'breach',
                minutes_overdue INT NOT NULL DEFAULT 0,
                channels VARCHAR(191) NOT NULL DEFAULT '',
                sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dashglpi_sla_alert_ticket_level (tickets_id, level),
                KEY idx_dashglpi_sla_alert_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Orquestrador de eventos (PLAN-007): dedupe genérico para TaskWorker/SolutionWorker.
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS glpi_plugin_dashglpi_event_alerts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(30) NOT NULL,
                event_id INT UNSIGNED NOT NULL,
                level VARCHAR(20) NOT NULL DEFAULT 'info',
                channels VARCHAR(191) NOT NULL DEFAULT '',
                sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dashglpi_event_alert (event_type, event_id, level),
                KEY idx_dashglpi_event_alert_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        PluginDashglpiEntitysmtpQueuednotification::install();
    }

    return true;
}

/**
 * Desinstalação do plugin
 */
function plugin_dashglpi_uninstall()
{
    return true;
}

/**
 * Adiciona o Dashboard no menu de navegação do GLPI
 */
function plugin_dashglpi_redefine_menus($menus)
{
    global $CFG_GLPI;

    if (Session::getLoginUserID()) {
        $menus['helpdesk']['content']['dashglpi'] = [
            'title' => __('Fealq - GLPI', 'dashglpi'),
            'page'  => $CFG_GLPI['root_doc'] . '/plugins/dashglpi/front/dashboard.php',
            'icon'  => 'ti ti-chart-dots',
        ];
    }

    return $menus;
}

<?php

/**
 * ------------------------------------------------------------------------
 * Brandkit Plugin (Community Edition)
 * Copyright (C) 2026 Marcati
 * https://github.com/juniormarcati
 * ------------------------------------------------------------------------
 * This file is part of Brandkit Plugin.
 *
 * Brandkit Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Brandkit Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Brandkit Plugin. If not, see <https://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
 *
 * Provides branding customization for GLPI (login page and system logos).
 *
 * @package   Brandkit Plugin
 * @author    Marcati
 * @copyright 2026 Marcati
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/juniormarcati/brandkit
 * @since     2026
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

include_once __DIR__ . '/inc/login.css.php';

function plugin_brandkit_install()
{
    if (method_exists('Plugin', 'loadLang')) {
        Plugin::loadLang('brandkit');
    }

    return true;
}

function plugin_brandkit_uninstall()
{
    if (function_exists('brandkit_restore_original_glpi_assets')) {
        brandkit_restore_original_glpi_assets();
    }

    return true;
}

function plugin_brandkit_update($current_version)
{
    return true;
}

function plugin_brandkit_display_login()
{
    $pluginDocDir = null;
    if (defined('GLPI_VAR_DIR')) {
        $base = rtrim(GLPI_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '_plugins';
        if (is_dir($base)) {
            $pluginDocDir = rtrim($base, DIRECTORY_SEPARATOR);
        }
    }
    if (!$pluginDocDir && defined('GLPI_PLUGIN_DOC_DIR')) {
        $pluginDocDir = rtrim(GLPI_PLUGIN_DOC_DIR, DIRECTORY_SEPARATOR);
    }

    brandkit_write_login_css($pluginDocDir);
}

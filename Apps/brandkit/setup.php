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

define('PLUGIN_BRANDKIT_VERSION', '0.1.9');
define('PLUGIN_BRANDKIT_MIN_GLPI', '10.0.0');
define('PLUGIN_BRANDKIT_MAX_GLPI', '11.0.99');

include_once __DIR__ . '/hook.php';
include_once __DIR__ . '/inc/config.class.php';
include_once __DIR__ . '/inc/login.css.php';

if (!function_exists('brandkit_is_glpi11')) {
    function brandkit_is_glpi11(): bool
    {
        if (defined('GLPI_VERSION')) {
            return version_compare(GLPI_VERSION, '11.0.0', '>=');
        }

        if (defined('GLPI_ROOT')) {
            $root = rtrim(GLPI_ROOT, DIRECTORY_SEPARATOR);
            $publicPics = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'pics';
            $legacyPics = $root . DIRECTORY_SEPARATOR . 'pics';
            if (is_dir($legacyPics) && !is_dir($publicPics)) {
                return false;
            }
            if (is_dir($publicPics) && !is_dir($legacyPics)) {
                return true;
            }
        }

        return false;
    }
}

function brandkit_relative_path(string $from, string $to): string
{
    $from = rtrim(str_replace('\\', '/', $from), '/');
    $to = rtrim(str_replace('\\', '/', $to), '/');

    $fromParts = $from === '' ? [] : explode('/', $from);
    $toParts = $to === '' ? [] : explode('/', $to);

    while (!empty($fromParts) && !empty($toParts) && $fromParts[0] === $toParts[0]) {
        array_shift($fromParts);
        array_shift($toParts);
    }

    $rel = str_repeat('../', count($fromParts)) . implode('/', $toParts);
    return $rel === '' ? '.' : $rel;
}

function plugin_init_brandkit()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;
    $PLUGIN_HOOKS['csrf_compliant']['brandkit'] = true;

    $PLUGIN_HOOKS['display_login']['brandkit'] = 'plugin_brandkit_display_login';

    $PLUGIN_HOOKS['add_javascript']['brandkit'] = 'js/brandkit-error.js';

    $pluginDocDir = brandkit_get_plugin_storage_dir();
    if ($pluginDocDir) {
        brandkit_write_login_css($pluginDocDir);
    }

    $docCssUrl = brandkit_get_generated_login_css_public_url();
    $faviconUrl = brandkit_get_configured_logo_url('favicon.ico');

    $loginHeaderTags = [];
    if ($docCssUrl) {
        $loginHeaderTags[] = [
            'tag' => 'link',
            'properties' => [
                'rel' => 'stylesheet',
                'href' => $docCssUrl,
            ],
        ];
    }
    if ($faviconUrl) {
        $loginHeaderTags[] = [
            'tag' => 'link',
            'properties' => [
                'rel' => 'icon',
                'href' => $faviconUrl,
            ],
        ];
        $loginHeaderTags[] = [
            'tag' => 'link',
            'properties' => [
                'rel' => 'shortcut icon',
                'href' => $faviconUrl,
            ],
        ];
        $PLUGIN_HOOKS['add_header_tag']['brandkit'] = [
            [
                'tag' => 'link',
                'properties' => [
                    'rel' => 'icon',
                    'href' => $faviconUrl,
                ],
            ],
            [
                'tag' => 'link',
                'properties' => [
                    'rel' => 'shortcut icon',
                    'href' => $faviconUrl,
                ],
            ],
        ];
    }
    $isGlpi11 = brandkit_is_glpi11();
    if (!empty($loginHeaderTags)) {
        $PLUGIN_HOOKS['add_header_tag_anonymous_page']['brandkit'] = $loginHeaderTags;
    }
    if (!$isGlpi11) {
        $PLUGIN_HOOKS['add_javascript_anonymous_page']['brandkit'] = 'js/brandkit-login.js';
    }

    if (!Session::getLoginUserID()) {
        return true;
    }

    $domain = 'brandkit';

    if (method_exists('Plugin', 'loadLang')) {
        Plugin::loadLang($domain);
    }

    $PLUGIN_HOOKS['add_css']['brandkit'][] = 'front/brandkit.css.php';


    if (
        !isset($_SESSION['brandkit_menu_version'])
        || $_SESSION['brandkit_menu_version'] !== PLUGIN_BRANDKIT_VERSION
    ) {
        unset($_SESSION['glpimenu']);
        $_SESSION['brandkit_menu_version'] = PLUGIN_BRANDKIT_VERSION;
    }

    Plugin::registerClass('PluginBrandkitConfig');

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['brandkit'] = ['plugins' => 'PluginBrandkitConfig'];
        $PLUGIN_HOOKS['config_page']['brandkit'] = 'front/index.php';
        if (!$isGlpi11) {
            $PLUGIN_HOOKS['menu_entry']['brandkit'] = 'front/index.php';
            $PLUGIN_HOOKS['submenu_entry']['brandkit']['search'] = 'front/index.php';
        }
    }

    return true;
}

function plugin_version_brandkit()
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
        Plugin::loadLang('brandkit', $lang);
    }

    return [
        'name' => 'Fealq - BrandKit',
        'version' => PLUGIN_BRANDKIT_VERSION,
        'author' => '<a href="https://fealq.org.br/">Fealq</a>',
        'license' => 'AGPLv3+',
        'homepage' => 'https://fealq.org.br/',
        'readme' => 'https://fealq.org.br/',
        'issues' => 'https://fealq.org.br/',
        'requirements' => [
            'glpi' => [
                  'min' => PLUGIN_BRANDKIT_MIN_GLPI,
                  'max' => PLUGIN_BRANDKIT_MAX_GLPI,
            ],
            'php' => [
                'min' => '7.4'
            ]
        ]
    ];
}

function plugin_check_prerequisites_brandkit()
{
    if (
          version_compare(GLPI_VERSION, PLUGIN_BRANDKIT_MIN_GLPI, 'lt') ||
          version_compare(GLPI_VERSION, PLUGIN_BRANDKIT_MAX_GLPI, 'gt')
    ) {
        echo sprintf(
            __('Plugin Brandkit requires GLPI version %s to %s.', 'brandkit'),
            PLUGIN_BRANDKIT_MIN_GLPI,
            PLUGIN_BRANDKIT_MAX_GLPI
        );
        return false;
    }

      if (version_compare(PHP_VERSION, '7.4', 'lt')) {
          echo __('Plugin Brandkit requires PHP version 7.4 or higher.', 'brandkit');
        return false;
    }

    $required_extensions = ['json', 'curl'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
              echo sprintf(__('Plugin Brandkit requires PHP extension: %s', 'brandkit'), $ext);
            return false;
        }
    }

    return true;
}

function plugin_check_config_brandkit()
{
    return true;
}

function plugin_install_brandkit()
{
    return plugin_brandkit_install();
}

function plugin_uninstall_brandkit()
{
    return plugin_brandkit_uninstall();
}

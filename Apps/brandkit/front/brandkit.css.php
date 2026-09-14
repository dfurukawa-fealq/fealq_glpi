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

include_once '../../../inc/includes.php';
include_once __DIR__ . '/../inc/login.css.php';

header('Content-Type: text/css; charset=UTF-8');

if (!function_exists('brandkit_clamp_int')) {
    function brandkit_clamp_int($value, int $min, int $max, int $default): int
    {
        if (is_array($value) || is_object($value)) {
            return $default;
        }
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        $value = (int) $value;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}

if (!function_exists('brandkit_config_scalar')) {
    function brandkit_config_scalar($value, $default = '')
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('brandkit_normalize_hex_color')) {
    function brandkit_normalize_hex_color($color, string $default): string
    {
        if (is_array($color) || is_object($color)) {
            return $default;
        }

        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches)) {
            return '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2];
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        return $default;
    }
}

if (!function_exists('brandkit_hex_to_rgb')) {
    function brandkit_hex_to_rgb(string $color): string
    {
        $color = ltrim($color, '#');
        return hexdec(substr($color, 0, 2)) . ', ' . hexdec(substr($color, 2, 2)) . ', ' . hexdec(substr($color, 4, 2));
    }
}

if (!function_exists('brandkit_get_contrast_color')) {
    function brandkit_get_contrast_color(string $color, string $light = '#ffffff', string $dark = '#0f172a'): string
    {
        $color = ltrim($color, '#');
        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));
        $luminance = (($red * 0.299) + ($green * 0.587) + ($blue * 0.114)) / 255;

        return $luminance > 0.58 ? $dark : $light;
    }
}

if (!function_exists('brandkit_get_advanced_defaults_for_css')) {
    function brandkit_get_advanced_defaults_for_css(): array
    {
        return [
            'advanced_theme_preset' => 'glpi',
            'advanced_menu_color' => '#2f3f64',
            'advanced_menu_text_color' => '#f8fafc',
            'advanced_accent_color' => '#066fd1',
            'advanced_link_color' => '#066fd1',
            'advanced_page_bg' => '#f9fafb',
            'advanced_surface_bg' => '#ffffff',
            'advanced_border_color' => '#e5e7eb',
            'advanced_button_bg_color' => '#066fd1',
            'advanced_button_text_color' => '#ffffff',
            'advanced_radius' => '6',
            'advanced_density' => 'comfortable',
            'advanced_menu_width' => '240',
            'advanced_shadow' => 'none',
        ];
    }
}

if (!function_exists('brandkit_get_advanced_settings_for_css')) {
    function brandkit_get_advanced_settings_for_css(): array
    {
        $defaults = brandkit_get_advanced_defaults_for_css();
        $config = Config::getConfigurationValues('plugin:brandkit', array_keys($defaults));
        $config = array_intersect_key($config, $defaults);
        $config = array_map(static fn($value) => brandkit_config_scalar($value, null), $config);
        $settings = array_merge($defaults, array_filter($config, static fn($value) => $value !== null && $value !== ''));

        foreach ([
            'advanced_menu_color',
            'advanced_menu_text_color',
            'advanced_accent_color',
            'advanced_link_color',
            'advanced_page_bg',
            'advanced_surface_bg',
            'advanced_border_color',
            'advanced_button_bg_color',
            'advanced_button_text_color',
        ] as $key) {
            $settings[$key] = brandkit_normalize_hex_color($settings[$key] ?? $defaults[$key], $defaults[$key]);
        }

        if (!in_array($settings['advanced_theme_preset'] ?? '', ['glpi', 'fealq', 'light', 'dark', 'neutral'], true)) {
            $settings['advanced_theme_preset'] = $defaults['advanced_theme_preset'];
        }
        if (
            ($settings['advanced_theme_preset'] ?? '') === 'fealq'
            && ($settings['advanced_page_bg'] ?? '') === '#28306d'
            && ($settings['advanced_surface_bg'] ?? '') === '#303a7a'
            && ($settings['advanced_border_color'] ?? '') === '#4a5798'
        ) {
            foreach ([
                'advanced_menu_color' => '#20275b',
                'advanced_menu_text_color' => '#ffffff',
                'advanced_accent_color' => '#d7264a',
                'advanced_link_color' => '#20275b',
                'advanced_page_bg' => '#f4f6fb',
                'advanced_surface_bg' => '#ffffff',
                'advanced_border_color' => '#d7dcec',
                'advanced_button_bg_color' => '#d7264a',
                'advanced_button_text_color' => '#ffffff',
            ] as $key => $value) {
                $settings[$key] = $value;
            }
        }
        $settings['advanced_radius'] = (string) brandkit_clamp_int($settings['advanced_radius'] ?? null, 0, 24, 6);
        $settings['advanced_menu_width'] = (string) brandkit_clamp_int($settings['advanced_menu_width'] ?? null, 220, 340, 240);
        if (!in_array($settings['advanced_density'] ?? '', ['compact', 'comfortable', 'spacious'], true)) {
            $settings['advanced_density'] = $defaults['advanced_density'];
        }
        if (!in_array($settings['advanced_shadow'] ?? '', ['none', 'soft', 'strong'], true)) {
            $settings['advanced_shadow'] = $defaults['advanced_shadow'];
        }

        return $settings;
    }
}

$glpiFrontBase = brandkit_get_front_web_base();
if ($glpiFrontBase === '') {
    $glpiFrontBase = '/front';
}
$glpiPicsBase = brandkit_get_pics_web_base();

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
$logosDir = $pluginDocDir ? $pluginDocDir . '/brandkit/logos' : null;
$publicLogosDir = brandkit_get_pics_logos_dir();

$logoFiles = [
    'logo-GLPI-100-white.png' => '--glpi-logo-light',
    'logo-G-100-white.png' => '--glpi-logo-light-reduced',
    'logo-GLPI-100-black.png' => '--glpi-logo-dark',
    'logo-G-100-black.png' => '--glpi-logo-dark-reduced',
];

$baseUrl = $glpiFrontBase . '/pluginimage.send.php?plugin=brandkit&folder=logos&name=';
$configuredLogoUrls = brandkit_get_configured_logo_urls();
$logoScaleDefaults = [
    'logo_header_scale' => '100',
    'logo_reduced_scale' => '100',
];
$logoScaleLegacyDefaults = [
    'logo_header_light_scale' => '100',
    'logo_reduced_light_scale' => '100',
    'logo_header_dark_scale' => '100',
    'logo_reduced_dark_scale' => '100',
];
$logoScaleConfig = Config::getConfigurationValues(
    'plugin:brandkit',
    array_merge(array_keys($logoScaleDefaults), array_keys($logoScaleLegacyDefaults))
);
$logoScaleConfig = array_merge(
    $logoScaleLegacyDefaults,
    $logoScaleDefaults,
    array_filter($logoScaleConfig, static fn($value) => $value !== null && $value !== '')
);
$logoHeaderScale = brandkit_clamp_int(
    $logoScaleConfig['logo_header_scale']
        ?? $logoScaleConfig['logo_header_light_scale']
        ?? $logoScaleConfig['logo_header_dark_scale']
        ?? null,
    40,
    200,
    100
);
$logoReducedScale = brandkit_clamp_int(
    $logoScaleConfig['logo_reduced_scale']
        ?? $logoScaleConfig['logo_reduced_light_scale']
        ?? $logoScaleConfig['logo_reduced_dark_scale']
        ?? null,
    40,
    200,
    100
);

$css = ":root {\n";
foreach ($logoFiles as $file => $variable) {
    if (!empty($configuredLogoUrls[$file])) {
        $css .= "  {$variable}: " . brandkit_css_url($configuredLogoUrls[$file]) . " !important;\n";
        continue;
    }

    $publicPath = $publicLogosDir ? $publicLogosDir . DIRECTORY_SEPARATOR . $file : null;
    if ($publicPath && is_file($publicPath)) {
        $picsBase = $glpiPicsBase === '' ? '/pics' : $glpiPicsBase;
        $picsBase = rtrim($picsBase, '/');
        $css .= "  {$variable}: " . brandkit_css_url("{$picsBase}/logos/{$file}") . " !important;\n";
        continue;
    }

    $path = $logosDir ? $logosDir . '/' . $file : null;
    if ($path && is_file($path)) {
        $css .= "  {$variable}: " . brandkit_css_url("{$baseUrl}{$file}") . " !important;\n";
    }
}
$css .= "  --brandkit-logo-scale-light: {$logoHeaderScale};\n";
$css .= "  --brandkit-logo-scale-light-reduced: {$logoReducedScale};\n";
$css .= "  --brandkit-logo-scale-dark: {$logoHeaderScale};\n";
$css .= "  --brandkit-logo-scale-dark-reduced: {$logoReducedScale};\n";
$css .= "  --brandkit-logo-scale: var(--brandkit-logo-scale-light);\n";
$css .= "  --brandkit-logo-scale-reduced: var(--brandkit-logo-scale-light-reduced);\n";
$css .= "  --brandkit-logo-image: var(--glpi-logo-light, var(--logo));\n";
$css .= "  --brandkit-logo-image-reduced: var(--glpi-logo-light-reduced, var(--logo));\n";
$css .= "}\n";
$css .= ":root[data-glpi-theme-dark=\"1\"],\n";
$css .= ":root[glpi-theme-dark=\"1\"] {\n";
$css .= "  --brandkit-logo-scale: var(--brandkit-logo-scale-dark);\n";
$css .= "  --brandkit-logo-scale-reduced: var(--brandkit-logo-scale-dark-reduced);\n";
$css .= "  --brandkit-logo-image: var(--glpi-logo-dark, var(--logo));\n";
$css .= "  --brandkit-logo-image-reduced: var(--glpi-logo-dark-reduced, var(--logo));\n";
$css .= "}\n";
$css .= "body.navbar-collapsed .navbar-brand {\n";
$css .= "  display: flex;\n";
$css .= "  justify-content: center;\n";
$css .= "  align-items: center;\n";
$css .= "  margin: 0 !important;\n";
$css .= "  padding: 0 !important;\n";
$css .= "  width: 70px !important;\n";
$css .= "  min-width: 70px !important;\n";
$css .= "  max-width: 70px !important;\n";
$css .= "  overflow: hidden;\n";
$css .= "}\n";
$css .= "body.navbar-collapsed .navbar-brand .glpi-logo {\n";
$css .= "  display: block;\n";
$css .= "  background-image: var(--brandkit-logo-image-reduced);\n";
$css .= "  background-size: contain !important;\n";
$css .= "  background-repeat: no-repeat !important;\n";
$css .= "  background-position: center !important;\n";
$css .= "  margin: 0 auto !important;\n";
$css .= "  width: min(calc(180px * var(--brandkit-logo-scale-reduced) / 100), 44px) !important;\n";
$css .= "  height: min(calc(48px * var(--brandkit-logo-scale-reduced) / 100), 44px) !important;\n";
$css .= "  max-width: 44px !important;\n";
$css .= "  max-height: 44px !important;\n";
$css .= "  overflow: hidden !important;\n";
$css .= "}\n";
$css .= "@media (max-width: 991.98px) {\n";
$css .= "  body.navbar-collapsed .navbar-brand .glpi-logo {\n";
$css .= "    background-image: var(--brandkit-logo-image);\n";
$css .= "    width: min(calc(180px * var(--brandkit-logo-scale) / 100), 180px) !important;\n";
$css .= "    height: min(calc(48px * var(--brandkit-logo-scale) / 100), 48px) !important;\n";
$css .= "  }\n";
$css .= "}\n";
$css .= ".navbar-brand {\n";
$css .= "  display: flex;\n";
$css .= "  align-items: center;\n";
$css .= "  justify-content: center;\n";
$css .= "  overflow: hidden !important;\n";
$css .= "}\n";
$css .= ".navbar-brand .glpi-logo {\n";
$css .= "  display: block !important;\n";
$css .= "  background-image: var(--brandkit-logo-image);\n";
$css .= "  background-size: contain !important;\n";
$css .= "  background-repeat: no-repeat !important;\n";
$css .= "  background-position: center !important;\n";
$css .= "  flex: 0 0 auto !important;\n";
$css .= "  width: min(calc(180px * var(--brandkit-logo-scale) / 100), 180px) !important;\n";
$css .= "  height: min(calc(48px * var(--brandkit-logo-scale) / 100), 48px) !important;\n";
$css .= "  max-width: 180px !important;\n";
$css .= "  max-height: 48px !important;\n";
$css .= "  overflow: hidden !important;\n";
$css .= "}\n";
$css .= ".navbar-brand .glpi-logo img,\n";
$css .= ".navbar-brand img.glpi-logo {\n";
$css .= "  display: block !important;\n";
$css .= "  width: 100% !important;\n";
$css .= "  height: 100% !important;\n";
$css .= "  max-width: 100% !important;\n";
$css .= "  max-height: 100% !important;\n";
$css .= "  object-fit: contain !important;\n";
$css .= "}\n";

$advanced = brandkit_get_advanced_settings_for_css();
if (($advanced['advanced_theme_preset'] ?? 'glpi') === 'glpi') {
    $css .= "/* BrandKit advanced preset: GLPI factory defaults; visual overrides disabled. */\n";
    echo $css;
    exit;
}
$densityMap = [
    'compact' => ['padding' => '0.55rem', 'font' => '0.9rem'],
    'comfortable' => ['padding' => '0.75rem', 'font' => '0.95rem'],
    'spacious' => ['padding' => '1rem', 'font' => '1rem'],
];
$shadowMap = [
    'none' => 'none',
    'soft' => '0 8px 22px rgba(15, 23, 42, 0.08)',
    'strong' => '0 16px 34px rgba(15, 23, 42, 0.16)',
];
$density = $densityMap[$advanced['advanced_density']] ?? $densityMap['comfortable'];
$shadow = $shadowMap[$advanced['advanced_shadow']] ?? $shadowMap['soft'];
$accentRgb = brandkit_hex_to_rgb($advanced['advanced_accent_color']);
$linkRgb = brandkit_hex_to_rgb($advanced['advanced_link_color']);
$menuRgb = brandkit_hex_to_rgb($advanced['advanced_menu_color']);
$pageTextColor = brandkit_get_contrast_color($advanced['advanced_page_bg']);
$surfaceTextColor = brandkit_get_contrast_color($advanced['advanced_surface_bg']);
$accentTextColor = brandkit_get_contrast_color($advanced['advanced_accent_color']);
$buttonRgb = brandkit_hex_to_rgb($advanced['advanced_button_bg_color']);
$menuAccentTextColor = brandkit_get_contrast_color($advanced['advanced_accent_color'], $advanced['advanced_menu_text_color'], '#0f172a');

$css .= ":root,\n";
$css .= "body:not(.page-anonymous) {\n";
$css .= "  --brandkit-advanced-css-version: \"0.2.0\";\n";
$css .= "  --brandkit-menu-color: {$advanced['advanced_menu_color']};\n";
$css .= "  --brandkit-menu-text-color: {$advanced['advanced_menu_text_color']};\n";
$css .= "  --brandkit-accent-color: {$advanced['advanced_accent_color']};\n";
$css .= "  --brandkit-link-color: {$advanced['advanced_link_color']};\n";
$css .= "  --brandkit-page-bg: {$advanced['advanced_page_bg']};\n";
$css .= "  --brandkit-surface-bg: {$advanced['advanced_surface_bg']};\n";
$css .= "  --brandkit-border-color: {$advanced['advanced_border_color']};\n";
$css .= "  --brandkit-button-bg-color: {$advanced['advanced_button_bg_color']};\n";
$css .= "  --brandkit-button-text-color: {$advanced['advanced_button_text_color']};\n";
$css .= "  --brandkit-button-bg-rgb: {$buttonRgb};\n";
$css .= "  --brandkit-page-text-color: {$pageTextColor};\n";
$css .= "  --brandkit-surface-text-color: {$surfaceTextColor};\n";
$css .= "  --brandkit-muted-text-color: color-mix(in srgb, var(--brandkit-surface-text-color), var(--brandkit-surface-bg) 34%);\n";
$css .= "  --brandkit-accent-text-color: {$accentTextColor};\n";
$css .= "  --brandkit-menu-accent-text-color: {$menuAccentTextColor};\n";
$css .= "  --brandkit-radius: {$advanced['advanced_radius']}px;\n";
$css .= "  --brandkit-menu-width: {$advanced['advanced_menu_width']}px;\n";
$css .= "  --brandkit-density-padding: {$density['padding']};\n";
$css .= "  --brandkit-density-font-size: {$density['font']};\n";
$css .= "  --brandkit-shadow: {$shadow};\n";
$css .= "  --glpi-mainmenu-bg: var(--brandkit-menu-color) !important;\n";
$css .= "  --glpi-mainmenu-fg: var(--brandkit-menu-text-color) !important;\n";
$css .= "  --glpi-mainmenu-fg-muted: color-mix(in srgb, var(--brandkit-menu-text-color), transparent 35%) !important;\n";
$css .= "  --glpi-mainmenu-active-bg: color-mix(in srgb, var(--brandkit-menu-color), var(--brandkit-accent-color) 34%) !important;\n";
$css .= "  --glpi-mainmenu-active-fg: var(--brandkit-menu-text-color) !important;\n";
$css .= "  --glpi-search-bg: var(--brandkit-surface-bg) !important;\n";
$css .= "  --glpi-search-fg: var(--brandkit-menu-color) !important;\n";
$css .= "  --glpi-search-border-color: var(--brandkit-border-color) !important;\n";
$css .= "  --tblr-primary: var(--brandkit-accent-color) !important;\n";
$css .= "  --tblr-primary-rgb: {$accentRgb} !important;\n";
$css .= "  --tblr-btn-color: var(--brandkit-button-text-color) !important;\n";
$css .= "  --tblr-link-color: var(--brandkit-link-color) !important;\n";
$css .= "  --tblr-link-hover-color: color-mix(in srgb, var(--brandkit-link-color), #000 18%) !important;\n";
$css .= "  --tblr-link-color-rgb: {$linkRgb} !important;\n";
$css .= "  --tblr-body-bg: var(--brandkit-page-bg) !important;\n";
$css .= "  --tblr-body-color: var(--brandkit-page-text-color) !important;\n";
$css .= "  --tblr-secondary-color: var(--brandkit-muted-text-color) !important;\n";
$css .= "  --tblr-bg-surface: var(--brandkit-surface-bg) !important;\n";
$css .= "  --tblr-bg-surface-secondary: color-mix(in srgb, var(--brandkit-surface-bg), var(--brandkit-page-bg) 45%) !important;\n";
$css .= "  --tblr-border-color: var(--brandkit-border-color) !important;\n";
$css .= "  --tblr-border-radius: var(--brandkit-radius) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous),\n";
$css .= "body:not(.page-anonymous) .page,\n";
$css .= "body:not(.page-anonymous) .page-wrapper,\n";
$css .= "body:not(.page-anonymous) .page-body {\n";
$css .= "  background: var(--brandkit-page-bg) !important;\n";
$css .= "  color: var(--brandkit-page-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) a:not(.btn):not(.nav-link) {\n";
$css .= "  color: var(--brandkit-link-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .main-sidebar,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical,\n";
$css .= "body:not(.page-anonymous) aside.navbar,\n";
$css .= "body:not(.page-anonymous) #navbar-menu {\n";
$css .= "  background: var(--brandkit-menu-color) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .sidebar ~ .navbar,\n";
$css .= "body:not(.page-anonymous) .page-wrapper > .navbar,\n";
$css .= "body:not(.page-anonymous) header.navbar:not(.navbar-vertical) {\n";
$css .= "  background: var(--brandkit-menu-color) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-menu-color), #000 10%) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .sidebar ~ .navbar a,\n";
$css .= "body:not(.page-anonymous) .page-wrapper > .navbar a,\n";
$css .= "body:not(.page-anonymous) header.navbar:not(.navbar-vertical) a,\n";
$css .= "body:not(.page-anonymous) .sidebar ~ .navbar .text-secondary,\n";
$css .= "body:not(.page-anonymous) .page-wrapper > .navbar .text-secondary {\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .sidebar ~ .navbar .form-control,\n";
$css .= "body:not(.page-anonymous) .page-wrapper > .navbar .form-control,\n";
$css .= "body:not(.page-anonymous) header.navbar:not(.navbar-vertical) .form-control {\n";
$css .= "  background: color-mix(in srgb, var(--brandkit-menu-color), #fff 8%) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-menu-text-color), transparent 72%) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous):not(.navbar-collapsed) .navbar-vertical,\n";
$css .= "body:not(.page-anonymous):not(.navbar-collapsed) aside.navbar-vertical {\n";
$css .= "  width: var(--brandkit-menu-width) !important;\n";
$css .= "}\n";
$css .= "@media (min-width: 992px) {\n";
$css .= "  body:not(.page-anonymous).navbar-collapsed .sidebar,\n";
$css .= "  body:not(.page-anonymous).navbar-collapsed .navbar-vertical,\n";
$css .= "  body:not(.page-anonymous).navbar-collapsed aside.navbar-vertical {\n";
$css .= "    width: 70px !important;\n";
$css .= "    max-width: 70px !important;\n";
$css .= "  }\n";
$css .= "  body:not(.page-anonymous):not(.navbar-collapsed) .sidebar ~ .navbar,\n";
$css .= "  body:not(.page-anonymous):not(.navbar-collapsed) .sidebar ~ .page-wrapper {\n";
$css .= "    margin-inline-start: var(--brandkit-menu-width) !important;\n";
$css .= "  }\n";
$css .= "  body:not(.page-anonymous).navbar-collapsed .sidebar ~ .navbar,\n";
$css .= "  body:not(.page-anonymous).navbar-collapsed .sidebar ~ .page-wrapper {\n";
$css .= "    margin-inline-start: 70px !important;\n";
$css .= "  }\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .main-sidebar a,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical a,\n";
$css .= "body:not(.page-anonymous) aside.navbar a,\n";
$css .= "body:not(.page-anonymous) .main-sidebar button,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical button,\n";
$css .= "body:not(.page-anonymous) aside.navbar button,\n";
$css .= "body:not(.page-anonymous) .main-sidebar .nav-link,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-link,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-link {\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-link.active,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-link.active,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-item.active > .nav-link,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-item.active > .nav-link {\n";
$css .= "  background: var(--glpi-mainmenu-active-bg) !important;\n";
$css .= "  border-color: var(--brandkit-accent-color) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-link:hover,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-link:hover,\n";
$css .= "body:not(.page-anonymous) .main-sidebar .nav-link:hover,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical button:hover,\n";
$css .= "body:not(.page-anonymous) aside.navbar button:hover,\n";
$css .= "body:not(.page-anonymous) .main-sidebar button:hover,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-item.show > .nav-link,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-item.show > .nav-link {\n";
$css .= "  background: color-mix(in srgb, var(--brandkit-menu-text-color), transparent 88%) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-link.active,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-link.active,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-item.active > .nav-link,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-item.active > .nav-link,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-item.selected > .nav-link,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-item.selected > .nav-link {\n";
$css .= "  background: var(--brandkit-accent-color) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-accent-color), #fff 22%) !important;\n";
$css .= "  color: var(--brandkit-menu-accent-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .nav-link .nav-link-icon,\n";
$css .= "body:not(.page-anonymous) aside.navbar .nav-link .nav-link-icon,\n";
$css .= "body:not(.page-anonymous) .main-sidebar .nav-link i,\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .dropdown-toggle::after,\n";
$css .= "body:not(.page-anonymous) aside.navbar .dropdown-toggle::after {\n";
$css .= "  color: inherit !important;\n";
$css .= "  opacity: 1 !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .dropdown-menu,\n";
$css .= "body:not(.page-anonymous) aside.navbar .dropdown-menu,\n";
$css .= "body:not(.page-anonymous) .main-sidebar .submenu {\n";
$css .= "  background: color-mix(in srgb, var(--brandkit-menu-color), #000 10%) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-menu-text-color), transparent 82%) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical .dropdown-menu a,\n";
$css .= "body:not(.page-anonymous) aside.navbar .dropdown-menu a,\n";
$css .= "body:not(.page-anonymous) .main-sidebar .submenu a {\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .card,\n";
$css .= "body:not(.page-anonymous) .search-card,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_fixe,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_pager,\n";
$css .= "body:not(.page-anonymous) .tab_cadre,\n";
$css .= "body:not(.page-anonymous) .modal-content,\n";
$css .= "body:not(.page-anonymous) .dropdown-menu {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) !important;\n";
$css .= "  border-radius: var(--brandkit-radius) !important;\n";
$css .= "  box-shadow: var(--brandkit-shadow);\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .card-header,\n";
$css .= "body:not(.page-anonymous) .card-footer,\n";
$css .= "body:not(.page-anonymous) .list-group-item,\n";
$css .= "body:not(.page-anonymous) .table,\n";
$css .= "body:not(.page-anonymous) table,\n";
$css .= "body:not(.page-anonymous) .tab_bg_1,\n";
$css .= "body:not(.page-anonymous) .tab_bg_2,\n";
$css .= "body:not(.page-anonymous) .tab_bg_1_2,\n";
$css .= "body:not(.page-anonymous) .tab_bg_2_2,\n";
$css .= "body:not(.page-anonymous) .tab_bg_3 {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .card h1,\n";
$css .= "body:not(.page-anonymous) .card h2,\n";
$css .= "body:not(.page-anonymous) .card h3,\n";
$css .= "body:not(.page-anonymous) .card h4,\n";
$css .= "body:not(.page-anonymous) .card h5,\n";
$css .= "body:not(.page-anonymous) .card h6,\n";
$css .= "body:not(.page-anonymous) .card .card-title,\n";
$css .= "body:not(.page-anonymous) .card .text-secondary,\n";
$css .= "body:not(.page-anonymous) .card .text-muted,\n";
$css .= "body:not(.page-anonymous) .tab_cadre .text-muted,\n";
$css .= "body:not(.page-anonymous) .dropdown-menu .dropdown-item {\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .btn-primary,\n";
$css .= "body:not(.page-anonymous) button.btn-primary,\n";
$css .= "body:not(.page-anonymous) input[type=\"submit\"].submit,\n";
$css .= "body:not(.page-anonymous) .submit.btn,\n";
$css .= "body:not(.page-anonymous) .vsubmit,\n";
$css .= "body:not(.page-anonymous) a.btn-primary {\n";
$css .= "  background-color: var(--brandkit-button-bg-color) !important;\n";
$css .= "  border-color: var(--brandkit-button-bg-color) !important;\n";
$css .= "  color: var(--brandkit-button-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .btn-primary:hover,\n";
$css .= "body:not(.page-anonymous) button.btn-primary:hover,\n";
$css .= "body:not(.page-anonymous) input[type=\"submit\"].submit:hover,\n";
$css .= "body:not(.page-anonymous) .submit.btn:hover,\n";
$css .= "body:not(.page-anonymous) .vsubmit:hover,\n";
$css .= "body:not(.page-anonymous) a.btn-primary:hover {\n";
$css .= "  background-color: color-mix(in srgb, var(--brandkit-button-bg-color), #000 12%) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-button-bg-color), #000 12%) !important;\n";
$css .= "  color: var(--brandkit-button-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .badge.bg-primary,\n";
$css .= "body:not(.page-anonymous) .text-primary {\n";
$css .= "  --tblr-primary-rgb: {$accentRgb} !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .table,\n";
$css .= "body:not(.page-anonymous) table,\n";
$css .= "body:not(.page-anonymous) .form-control,\n";
$css .= "body:not(.page-anonymous) .form-select {\n";
$css .= "  font-size: var(--brandkit-density-font-size);\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .form-control,\n";
$css .= "body:not(.page-anonymous) .form-select,\n";
$css .= "body:not(.page-anonymous) .select2-container .select2-selection,\n";
$css .= "body:not(.page-anonymous) input[type=\"text\"],\n";
$css .= "body:not(.page-anonymous) input[type=\"search\"],\n";
$css .= "body:not(.page-anonymous) input[type=\"email\"],\n";
$css .= "body:not(.page-anonymous) input[type=\"number\"],\n";
$css .= "body:not(.page-anonymous) textarea {\n";
$css .= "  background-color: color-mix(in srgb, var(--brandkit-surface-bg), var(--brandkit-page-bg) 18%) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .form-control::placeholder,\n";
$css .= "body:not(.page-anonymous) input::placeholder,\n";
$css .= "body:not(.page-anonymous) textarea::placeholder {\n";
$css .= "  color: var(--brandkit-muted-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .form-control:focus,\n";
$css .= "body:not(.page-anonymous) .form-select:focus,\n";
$css .= "body:not(.page-anonymous) input:focus,\n";
$css .= "body:not(.page-anonymous) textarea:focus {\n";
$css .= "  border-color: var(--brandkit-accent-color) !important;\n";
$css .= "  box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--brandkit-accent-color), transparent 78%) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .nav-tabs,\n";
$css .= "body:not(.page-anonymous) .nav-tabs .nav-link,\n";
$css .= "body:not(.page-anonymous) .nav-pills .nav-link,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_fixe .tab_bg_1,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_fixe .tab_bg_2 {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .nav-tabs .nav-link.active,\n";
$css .= "body:not(.page-anonymous) .nav-pills .nav-link.active,\n";
$css .= "body:not(.page-anonymous) .nav-tabs .nav-item.show .nav-link {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) var(--brandkit-border-color) var(--brandkit-surface-bg) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "  font-weight: 600;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .nav-tabs .nav-link.active,\n";
$css .= "body:not(.page-anonymous) .nav-pills .nav-link.active {\n";
$css .= "  box-shadow: inset 0 -3px 0 var(--brandkit-accent-color);\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .dashboard-card,\n";
$css .= "body:not(.page-anonymous) .dashboard .card,\n";
$css .= "body:not(.page-anonymous) .grid-stack-item-content,\n";
$css .= "body:not(.page-anonymous) .tabler-dashboard-card,\n";
$css .= "body:not(.page-anonymous) .widget,\n";
$css .= "body:not(.page-anonymous) .dashcard {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border: 1px solid var(--brandkit-border-color) !important;\n";
$css .= "  border-radius: var(--brandkit-radius) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "  box-shadow: var(--brandkit-shadow) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .dashboard-card h1,\n";
$css .= "body:not(.page-anonymous) .dashboard-card h2,\n";
$css .= "body:not(.page-anonymous) .dashboard-card h3,\n";
$css .= "body:not(.page-anonymous) .dashboard-card h4,\n";
$css .= "body:not(.page-anonymous) .dashboard .card .card-title,\n";
$css .= "body:not(.page-anonymous) .grid-stack-item-content .card-title,\n";
$css .= "body:not(.page-anonymous) .tabler-dashboard-card .card-title,\n";
$css .= "body:not(.page-anonymous) .widget .card-title,\n";
$css .= "body:not(.page-anonymous) .dashcard .card-title {\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .card-body,\n";
$css .= "body:not(.page-anonymous) .search-card,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_fixe,\n";
$css .= "body:not(.page-anonymous) .tab_cadre_pager,\n";
$css .= "body:not(.page-anonymous) .tab_cadre {\n";
$css .= "  padding: var(--brandkit-density-padding);\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .navbar-vertical::before {\n";
$css .= "  background: rgba({$menuRgb}, 0.92) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .sidebar ~ .navbar .form-control,\n";
$css .= "body:not(.page-anonymous) .page-wrapper > .navbar .form-control,\n";
$css .= "body:not(.page-anonymous) header.navbar:not(.navbar-vertical) .form-control {\n";
$css .= "  background: color-mix(in srgb, var(--brandkit-menu-color), #fff 8%) !important;\n";
$css .= "  border-color: color-mix(in srgb, var(--brandkit-menu-text-color), transparent 72%) !important;\n";
$css .= "  color: var(--brandkit-menu-text-color) !important;\n";
$css .= "}\n";
$css .= "body:not(.page-anonymous) .toast,\n";
$css .= "body:not(.page-anonymous) .toast-header,\n";
$css .= "body:not(.page-anonymous) .toast-body,\n";
$css .= "body:not(.page-anonymous) .alert-info {\n";
$css .= "  background: var(--brandkit-surface-bg) !important;\n";
$css .= "  border-color: var(--brandkit-border-color) !important;\n";
$css .= "  color: var(--brandkit-surface-text-color) !important;\n";
$css .= "}\n";

echo $css;

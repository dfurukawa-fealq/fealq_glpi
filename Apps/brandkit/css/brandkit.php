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
include_once __DIR__ . '/../inc/paths.php';

header('Content-Type: text/css; charset=UTF-8');

if (!function_exists('brandkit_clamp_int')) {
    function brandkit_clamp_int($value, int $min, int $max, int $default): int
    {
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
    $publicPath = $publicLogosDir ? $publicLogosDir . DIRECTORY_SEPARATOR . $file : null;
    if ($publicPath && is_file($publicPath)) {
        $picsBase = $glpiPicsBase === '' ? '/pics' : $glpiPicsBase;
        $picsBase = rtrim($picsBase, '/');
        $css .= "  {$variable}: url(\"{$picsBase}/logos/{$file}\") !important;\n";
        continue;
    }

    $path = $logosDir ? $logosDir . '/' . $file : null;
    if ($path && is_file($path)) {
        $css .= "  {$variable}: url(\"{$baseUrl}{$file}\") !important;\n";
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

echo $css;

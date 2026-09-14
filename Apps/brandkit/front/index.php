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

Session::checkRight('config', UPDATE);

$isGlpi11 = brandkit_is_glpi11();

function brandkit_is_light_color(string $hex): bool
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return false;
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    return $luma > 0.75;
}

function brandkit_get_upload_error_message(int $errorCode, string $fallbackMessage): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return __('Wallpaper upload failed. The file is larger than the allowed size.', 'brandkit');
        case UPLOAD_ERR_PARTIAL:
            return __('Wallpaper upload failed because the file was only partially uploaded.', 'brandkit');
        case UPLOAD_ERR_NO_TMP_DIR:
            return __('Wallpaper upload failed because the temporary upload folder is missing on the server.', 'brandkit');
        case UPLOAD_ERR_CANT_WRITE:
            return __('Wallpaper upload failed because the server could not write the temporary file.', 'brandkit');
        case UPLOAD_ERR_EXTENSION:
            return __('Wallpaper upload was blocked by a PHP extension on the server.', 'brandkit');
        default:
            return $fallbackMessage;
    }
}

function brandkit_config_scalar($value, $default = '')
{
    if ($value === null || $value === '' || is_array($value) || is_object($value)) {
        return $default;
    }

    return $value;
}

function brandkit_delete_uploaded_wallpaper_variants(string $baseName = 'wallpaperLogin'): void
{
    $extensions = ['png', 'jpg', 'jpeg', 'webp'];
    $paths = [];
    $picsDir = brandkit_get_pics_dir();
    if ($picsDir) {
        $publicDir = $picsDir . '/brandkit-wallpapers';
        foreach ($extensions as $ext) {
            $paths[] = $publicDir . '/' . $baseName . '.' . $ext;
        }
    }

    $pluginDir = brandkit_get_plugin_storage_dir();
    if ($pluginDir) {
        $legacyDir = rtrim($pluginDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wallpaper';
        foreach ($extensions as $ext) {
            $paths[] = $legacyDir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
        }
    }

    foreach (array_unique($paths) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
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

function brandkit_get_advanced_defaults(): array
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
        'brand_site_url' => 'https://fealq.org.br/',
        'brand_whatsapp' => '+5519985992882',
        'brand_linkedin_url' => 'https://www.linkedin.com/company/furufealq',
        'brand_instagram_handle' => 'fealq.co',
    ];
}

function brandkit_get_theme_presets(): array
{
    $defaults = brandkit_get_advanced_defaults();

    return [
        'glpi' => [
            'label' => 'GLPI',
            'description' => __('Factory GLPI appearance without BrandKit color overrides.', 'brandkit'),
            'values' => $defaults,
        ],
        'fealq' => [
            'label' => 'Fealq',
            'description' => __('Fealq identity with a blue shell, clean workspace, and red actions.', 'brandkit'),
            'values' => array_merge($defaults, [
                'advanced_theme_preset' => 'fealq',
                'advanced_menu_color' => '#20275b',
                'advanced_menu_text_color' => '#ffffff',
                'advanced_accent_color' => '#d7264a',
                'advanced_link_color' => '#20275b',
                'advanced_page_bg' => '#f4f6fb',
                'advanced_surface_bg' => '#ffffff',
                'advanced_border_color' => '#d7dcec',
                'advanced_button_bg_color' => '#d7264a',
                'advanced_button_text_color' => '#ffffff',
                'advanced_radius' => '8',
                'advanced_menu_width' => '255',
                'advanced_shadow' => 'soft',
            ]),
        ],
        'light' => [
            'label' => 'Claro',
            'description' => __('Bright interface with subtle borders and blue accents.', 'brandkit'),
            'values' => array_merge($defaults, [
                'advanced_theme_preset' => 'light',
                'advanced_menu_color' => '#ffffff',
                'advanced_menu_text_color' => '#0f172a',
                'advanced_accent_color' => '#2563eb',
                'advanced_link_color' => '#1d4ed8',
                'advanced_page_bg' => '#f8fafc',
                'advanced_surface_bg' => '#ffffff',
                'advanced_border_color' => '#e2e8f0',
                'advanced_button_bg_color' => '#2563eb',
                'advanced_button_text_color' => '#ffffff',
                'advanced_shadow' => 'none',
            ]),
        ],
        'dark' => [
            'label' => 'Escuro',
            'description' => __('Darker workspace with high contrast accents.', 'brandkit'),
            'values' => array_merge($defaults, [
                'advanced_theme_preset' => 'dark',
                'advanced_menu_color' => '#020617',
                'advanced_menu_text_color' => '#e2e8f0',
                'advanced_accent_color' => '#38bdf8',
                'advanced_link_color' => '#7dd3fc',
                'advanced_page_bg' => '#0f172a',
                'advanced_surface_bg' => '#111827',
                'advanced_border_color' => '#334155',
                'advanced_button_bg_color' => '#38bdf8',
                'advanced_button_text_color' => '#0f172a',
                'advanced_shadow' => 'strong',
            ]),
        ],
        'neutral' => [
            'label' => 'Neutro',
            'description' => __('Quiet gray palette for a restrained operational UI.', 'brandkit'),
            'values' => array_merge($defaults, [
                'advanced_theme_preset' => 'neutral',
                'advanced_menu_color' => '#374151',
                'advanced_menu_text_color' => '#f9fafb',
                'advanced_accent_color' => '#64748b',
                'advanced_link_color' => '#475569',
                'advanced_page_bg' => '#f3f4f6',
                'advanced_surface_bg' => '#ffffff',
                'advanced_border_color' => '#d1d5db',
                'advanced_button_bg_color' => '#64748b',
                'advanced_button_text_color' => '#ffffff',
                'advanced_shadow' => 'soft',
            ]),
        ],
    ];
}

function brandkit_get_advanced_config_keys(): array
{
    return array_keys(brandkit_get_advanced_defaults());
}

function brandkit_get_advanced_settings(): array
{
    $defaults = brandkit_get_advanced_defaults();
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

    $settings['advanced_radius'] = (string) brandkit_clamp_int($settings['advanced_radius'] ?? null, 0, 24, 8);
    $settings['advanced_menu_width'] = (string) brandkit_clamp_int($settings['advanced_menu_width'] ?? null, 220, 340, 255);

    if (!in_array($settings['advanced_density'] ?? '', ['compact', 'comfortable', 'spacious'], true)) {
        $settings['advanced_density'] = $defaults['advanced_density'];
    }
    if (!in_array($settings['advanced_shadow'] ?? '', ['none', 'soft', 'strong'], true)) {
        $settings['advanced_shadow'] = $defaults['advanced_shadow'];
    }
    if (!array_key_exists($settings['advanced_theme_preset'] ?? '', brandkit_get_theme_presets())) {
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

    $settings['brand_site_url'] = brandkit_normalize_image_url($settings['brand_site_url'] ?? '') ?: $defaults['brand_site_url'];
    $settings['brand_linkedin_url'] = brandkit_normalize_image_url($settings['brand_linkedin_url'] ?? '') ?: $defaults['brand_linkedin_url'];
    $settings['brand_whatsapp'] = trim((string) ($settings['brand_whatsapp'] ?? $defaults['brand_whatsapp']));
    $settings['brand_instagram_handle'] = ltrim(trim((string) ($settings['brand_instagram_handle'] ?? $defaults['brand_instagram_handle'])), '@');

    return $settings;
}

function brandkit_build_advanced_config_from_post(array $source, array $fallback): array
{
    $defaults = brandkit_get_advanced_defaults();
    $preset = (string) ($source['advanced_theme_preset'] ?? $fallback['advanced_theme_preset'] ?? $defaults['advanced_theme_preset']);
    if (!array_key_exists($preset, brandkit_get_theme_presets())) {
        $preset = $defaults['advanced_theme_preset'];
    }

    $density = (string) ($source['advanced_density'] ?? $fallback['advanced_density'] ?? $defaults['advanced_density']);
    if (!in_array($density, ['compact', 'comfortable', 'spacious'], true)) {
        $density = $defaults['advanced_density'];
    }

    $shadow = (string) ($source['advanced_shadow'] ?? $fallback['advanced_shadow'] ?? $defaults['advanced_shadow']);
    if (!in_array($shadow, ['none', 'soft', 'strong'], true)) {
        $shadow = $defaults['advanced_shadow'];
    }

    $siteUrl = brandkit_normalize_image_url($source['brand_site_url'] ?? $fallback['brand_site_url'] ?? $defaults['brand_site_url']);
    $linkedinUrl = brandkit_normalize_image_url($source['brand_linkedin_url'] ?? $fallback['brand_linkedin_url'] ?? $defaults['brand_linkedin_url']);

    return [
        'advanced_theme_preset' => $preset,
        'advanced_menu_color' => brandkit_normalize_hex_color($source['advanced_menu_color'] ?? $fallback['advanced_menu_color'] ?? null, $defaults['advanced_menu_color']),
        'advanced_menu_text_color' => brandkit_normalize_hex_color($source['advanced_menu_text_color'] ?? $fallback['advanced_menu_text_color'] ?? null, $defaults['advanced_menu_text_color']),
        'advanced_accent_color' => brandkit_normalize_hex_color($source['advanced_accent_color'] ?? $fallback['advanced_accent_color'] ?? null, $defaults['advanced_accent_color']),
        'advanced_link_color' => brandkit_normalize_hex_color($source['advanced_link_color'] ?? $fallback['advanced_link_color'] ?? null, $defaults['advanced_link_color']),
        'advanced_page_bg' => brandkit_normalize_hex_color($source['advanced_page_bg'] ?? $fallback['advanced_page_bg'] ?? null, $defaults['advanced_page_bg']),
        'advanced_surface_bg' => brandkit_normalize_hex_color($source['advanced_surface_bg'] ?? $fallback['advanced_surface_bg'] ?? null, $defaults['advanced_surface_bg']),
        'advanced_border_color' => brandkit_normalize_hex_color($source['advanced_border_color'] ?? $fallback['advanced_border_color'] ?? null, $defaults['advanced_border_color']),
        'advanced_button_bg_color' => brandkit_normalize_hex_color($source['advanced_button_bg_color'] ?? $fallback['advanced_button_bg_color'] ?? $source['advanced_accent_color'] ?? $fallback['advanced_accent_color'] ?? null, $defaults['advanced_button_bg_color']),
        'advanced_button_text_color' => brandkit_normalize_hex_color($source['advanced_button_text_color'] ?? $fallback['advanced_button_text_color'] ?? null, $defaults['advanced_button_text_color']),
        'advanced_radius' => (string) brandkit_clamp_int($source['advanced_radius'] ?? $fallback['advanced_radius'] ?? null, 0, 24, 8),
        'advanced_density' => $density,
        'advanced_menu_width' => (string) brandkit_clamp_int($source['advanced_menu_width'] ?? $fallback['advanced_menu_width'] ?? null, 220, 340, 255),
        'advanced_shadow' => $shadow,
        'brand_site_url' => $siteUrl ?: $defaults['brand_site_url'],
        'brand_whatsapp' => trim((string) ($source['brand_whatsapp'] ?? $fallback['brand_whatsapp'] ?? $defaults['brand_whatsapp'])),
        'brand_linkedin_url' => $linkedinUrl ?: $defaults['brand_linkedin_url'],
        'brand_instagram_handle' => ltrim(trim((string) ($source['brand_instagram_handle'] ?? $fallback['brand_instagram_handle'] ?? $defaults['brand_instagram_handle'])), '@'),
    ];
}

$currentStep = isset($_GET['step']) ? (int) $_GET['step'] : 0;
if ($currentStep < 0 || $currentStep > 3) {
    $currentStep = 0;
}

$baseUrl = Plugin::getWebDir('brandkit') . '/front/index.php';
$pluginWebBase = Plugin::getWebDir('brandkit', false);
if (is_string($pluginWebBase) && $pluginWebBase !== '' && $pluginWebBase[0] !== '/') {
    $pluginWebBase = '/' . $pluginWebBase;
}
$picsWebBase = brandkit_get_pics_web_base();
if ($picsWebBase === '') {
    $picsWebBase = '/pics';
}
$pluginDocDir = brandkit_get_plugin_storage_dir();
$logosDir = $pluginDocDir ? $pluginDocDir . '/logos' : null;
$publicLogosDir = brandkit_get_pics_logos_dir();
$configuredLogoUrls = brandkit_get_configured_logo_urls();
$logoFiles = [
    'logo-GLPI-100-white.png' => __('Expanded menu logo', 'brandkit'),
    'logo-G-100-white.png' => __('Collapsed menu logo', 'brandkit'),
    'logo-GLPI-100-black.png' => __('Expanded menu logo (dark mode)', 'brandkit'),
    'logo-G-100-black.png' => __('Collapsed menu logo (dark mode)', 'brandkit'),
    'logo-GLPI-250-black.png' => __('Login logo', 'brandkit'),
    'logo-GLPI-250-white.png' => __('Login logo (dark mode)', 'brandkit'),
    'favicon.ico' => __('Favicon (browser tab)', 'brandkit'),
];
$wallpaperPresets = brandkit_get_wallpaper_presets();
$wallpaperPresetDefault = brandkit_get_wallpaper_preset_default($wallpaperPresets);
$allowedWallpaperExtensions = ['png', 'jpg', 'jpeg', 'webp'];
$wallpaperPresetLabels = [];
foreach ($wallpaperPresets as $presetFile) {
    $labelBase = str_replace(['-', '_'], ' ', pathinfo($presetFile, PATHINFO_FILENAME));
    $wallpaperPresetLabels[$presetFile] = ucwords(trim($labelBase));
}

$loginDefaults = brandkit_get_login_defaults();
$loginConfig = brandkit_get_login_settings();
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
$logoScaleAllowedKeys = array_merge($logoScaleLegacyDefaults, $logoScaleDefaults);
$logoScaleConfig = array_intersect_key($logoScaleConfig, $logoScaleAllowedKeys);
$logoScaleConfig = array_map(static fn($value) => brandkit_config_scalar($value, null), $logoScaleConfig);
$logoScaleConfig = array_merge(
    $logoScaleLegacyDefaults,
    $logoScaleDefaults,
    array_filter($logoScaleConfig, static fn($value) => $value !== null && $value !== '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'reset_login_defaults') {
    $defaults = brandkit_get_login_defaults();
    $picsDir = brandkit_get_pics_dir();
    $wallpaperDir = $picsDir ? $picsDir . '/brandkit-wallpapers' : null;
    if ($wallpaperDir) {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $candidate = $wallpaperDir . '/wallpaperLogin.' . $ext;
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    Config::setConfigurationValues('plugin:brandkit', $defaults);
    $cssWritten = brandkit_write_login_css($pluginDocDir, $defaults);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login page settings saved.', 'brandkit'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Settings saved, but the login CSS file could not be written.', 'brandkit'), false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'save_login_settings') {
    $layout = $_POST['login_layout'] ?? $loginDefaults['login_layout'];
    if (!in_array($layout, ['split-left', 'split-right', 'classic'], true)) {
        $layout = $loginDefaults['login_layout'];
    }

    $panelTransparent = ($_POST['login_panel_transparent'] ?? '0') === '1' ? '1' : '0';
    $panelHoverEffect = ($_POST['login_panel_hover_effect'] ?? '1') === '1' ? '1' : '0';
    $panelOpacity = brandkit_clamp_int($_POST['login_panel_opacity'] ?? $loginDefaults['login_panel_opacity'], 0, 100, 65);
    $panelBlur = brandkit_clamp_int($_POST['login_panel_blur'] ?? $loginDefaults['login_panel_blur'], 0, 24, 6);
    $panelWidth = brandkit_clamp_int($_POST['login_panel_width'] ?? $loginDefaults['login_panel_width'], 280, 720, 520);
    $panelHeight = brandkit_clamp_int($_POST['login_panel_height'] ?? $loginDefaults['login_panel_height'], 360, 780, 560);
    $panelColor = brandkit_normalize_hex_color($_POST['login_panel_color'] ?? $loginDefaults['login_panel_color'], $loginDefaults['login_panel_color']);
    $textColor = brandkit_normalize_hex_color($_POST['login_text_color'] ?? $loginDefaults['login_text_color'], $loginDefaults['login_text_color']);
    $logoPosition = $_POST['login_logo_position'] ?? $loginDefaults['login_logo_position'];
    if (!in_array($logoPosition, ['outside', 'inside'], true)) {
        $logoPosition = $loginDefaults['login_logo_position'];
    }
    $splitOpacity = brandkit_clamp_int($_POST['login_split_opacity'] ?? $loginDefaults['login_split_opacity'], 0, 100, 55);
    $splitBlur = brandkit_clamp_int($_POST['login_split_blur'] ?? $loginDefaults['login_split_blur'], 0, 24, 10);
    $splitColor = brandkit_normalize_hex_color($_POST['login_split_color'] ?? $loginDefaults['login_split_color'], $loginDefaults['login_split_color']);
    $splitWidth = brandkit_clamp_int($_POST['login_split_width'] ?? $loginDefaults['login_split_width'], 0, 100, 50);
    $buttonStyle = $_POST['login_button_style'] ?? $loginDefaults['login_button_style'];
    if (!in_array($buttonStyle, brandkit_get_login_button_styles(), true)) {
        $buttonStyle = $loginDefaults['login_button_style'];
    }
    $buttonShape = $_POST['login_button_shape'] ?? $loginDefaults['login_button_shape'];
    if (!in_array($buttonShape, brandkit_get_login_button_shapes(), true)) {
        $buttonShape = $loginDefaults['login_button_shape'];
    }
    $buttonColorMode = $_POST['login_button_color_mode'] ?? $loginDefaults['login_button_color_mode'];
    if (!in_array($buttonColorMode, ['preset', 'custom'], true)) {
        $buttonColorMode = $loginDefaults['login_button_color_mode'];
    }
    $buttonCustomStyle = $_POST['login_button_custom_style'] ?? $loginDefaults['login_button_custom_style'];
    if (!in_array($buttonCustomStyle, ['default', 'outline'], true)) {
        $buttonCustomStyle = $loginDefaults['login_button_custom_style'];
    }
    $buttonHoverEffect = $_POST['login_button_hover_effect'] ?? $loginDefaults['login_button_hover_effect'];
    if (!in_array($buttonHoverEffect, ['press', 'fill'], true)) {
        $buttonHoverEffect = $loginDefaults['login_button_hover_effect'];
    }
    $buttonHoverFillMode = $_POST['login_button_hover_fill_mode'] ?? $loginDefaults['login_button_hover_fill_mode'];
    if (!in_array($buttonHoverFillMode, ['button', 'custom'], true)) {
        $buttonHoverFillMode = $loginDefaults['login_button_hover_fill_mode'];
    }
    $buttonTextSizeDefault = $isGlpi11 ? 14 : 15;
    $buttonTextSize = brandkit_clamp_int($_POST['login_button_text_size'] ?? $loginDefaults['login_button_text_size'], 10, 28, $buttonTextSizeDefault);
    $buttonColorRaw = $loginDefaults['login_button_color'];
    if (!empty($_POST['login_button_color_hex'])) {
        $buttonColorRaw = $_POST['login_button_color_hex'];
    } elseif (!empty($_POST['login_button_color'])) {
        $buttonColorRaw = $_POST['login_button_color'];
    }
    $buttonColor = brandkit_normalize_hex_color($buttonColorRaw, $loginDefaults['login_button_color']);
    $buttonHoverFillColor = brandkit_normalize_hex_color(
        $_POST['login_button_hover_fill_color'] ?? $loginDefaults['login_button_hover_fill_color'],
        $loginDefaults['login_button_hover_fill_color']
    );
    $buttonHoverTextColor = brandkit_normalize_hex_color(
        $_POST['login_button_hover_text_color'] ?? $loginDefaults['login_button_hover_text_color'],
        $loginDefaults['login_button_hover_text_color']
    );

    $fieldStyle = $_POST['login_field_style'] ?? $loginDefaults['login_field_style'];
    $fieldStyleOptions = ['solid', 'glass', 'soft', 'outline', 'underline', 'neon'];
    if (!in_array($fieldStyle, $fieldStyleOptions, true)) {
        $fieldStyle = $loginDefaults['login_field_style'];
    }
    $fieldBg = brandkit_normalize_hex_color($_POST['login_field_bg'] ?? $loginDefaults['login_field_bg'], $loginDefaults['login_field_bg']);
    $fieldBorder = brandkit_normalize_hex_color($_POST['login_field_border'] ?? $loginDefaults['login_field_border'], $loginDefaults['login_field_border']);
    $fieldBorderWidthDefault = $isGlpi11 ? 1 : 0;
    $fieldBorderWidth = brandkit_clamp_int($_POST['login_field_border_width'] ?? $loginDefaults['login_field_border_width'], 0, 6, $fieldBorderWidthDefault);
    $fieldText = brandkit_normalize_hex_color($_POST['login_field_text'] ?? $loginDefaults['login_field_text'], $loginDefaults['login_field_text']);
    $fieldTextSizeDefault = $isGlpi11 ? 14 : 15;
    $fieldHeightDefault = $isGlpi11 ? 44 : 40;
    $fieldTextSize = brandkit_clamp_int($_POST['login_field_text_size'] ?? $loginDefaults['login_field_text_size'], 10, 36, $fieldTextSizeDefault);
    $fieldHeight = brandkit_clamp_int($_POST['login_field_height'] ?? $loginDefaults['login_field_height'], 32, 72, $fieldHeightDefault);
    $fieldWidthDefault = 280;
    $fieldWidth = brandkit_clamp_int($_POST['login_field_width'] ?? $loginDefaults['login_field_width'], 260, 720, $fieldWidthDefault);
    $fieldSpacing = null;
    $titleSize = null;
    if (!$isGlpi11) {
        $fieldSpacing = brandkit_clamp_int($_POST['login_field_spacing'] ?? $loginDefaults['login_field_spacing'], 8, 48, 20);
        $titleSize = brandkit_clamp_int($_POST['login_title_size'] ?? $loginDefaults['login_title_size'], 16, 48, 20);
    }
    $textSizeDefault = $isGlpi11 ? 15 : 14;
    $textSize = brandkit_clamp_int($_POST['login_text_size'] ?? $loginDefaults['login_text_size'], 10, 30, $textSizeDefault);
    $fieldFocus = brandkit_normalize_hex_color($_POST['login_field_focus'] ?? $loginDefaults['login_field_focus'], $loginDefaults['login_field_focus']);
    $accentColor = brandkit_normalize_hex_color($_POST['login_accent_color'] ?? $loginDefaults['login_accent_color'], $loginDefaults['login_accent_color']);
    $iconUser = $_POST['login_icon_user'] ?? $loginDefaults['login_icon_user'];
    $iconPassword = $_POST['login_icon_password'] ?? $loginDefaults['login_icon_password'];
    $iconColor = brandkit_normalize_hex_color($_POST['login_icon_color'] ?? $loginDefaults['login_icon_color'], $loginDefaults['login_icon_color']);
    $iconPosition = $_POST['login_icon_position'] ?? $loginDefaults['login_icon_position'];
    $iconUserOptions = ['none', 'user', 'id', 'mail'];
    if (!in_array($iconUser, $iconUserOptions, true)) {
        $iconUser = $loginDefaults['login_icon_user'];
    }
    $iconPasswordOptions = ['none', 'lock', 'key', 'shield'];
    if (!in_array($iconPassword, $iconPasswordOptions, true)) {
        $iconPassword = $loginDefaults['login_icon_password'];
    }
    if (!in_array($iconPosition, ['left', 'right'], true)) {
        $iconPosition = $loginDefaults['login_icon_position'];
    }
    $fieldRadius = brandkit_clamp_int($_POST['login_field_radius'] ?? $loginDefaults['login_field_radius'], 0, 32, 12);
    $wallpaperSize = $_POST['login_wallpaper_size'] ?? $loginDefaults['login_wallpaper_size'];
    $wallpaperSizeOptions = ['cover', 'contain', 'stretch', 'original'];
    if (!in_array($wallpaperSize, $wallpaperSizeOptions, true)) {
        $wallpaperSize = $loginDefaults['login_wallpaper_size'];
    }
    $wallpaperPosition = $_POST['login_wallpaper_position'] ?? $loginDefaults['login_wallpaper_position'];
    $wallpaperPositionOptions = ['center', 'top', 'bottom', 'left', 'right'];
    if (!in_array($wallpaperPosition, $wallpaperPositionOptions, true)) {
        $wallpaperPosition = $loginDefaults['login_wallpaper_position'];
    }
    $wallpaperAttachment = $_POST['login_wallpaper_attachment'] ?? $loginDefaults['login_wallpaper_attachment'];
    $wallpaperAttachmentOptions = ['fixed', 'scroll'];
    if (!in_array($wallpaperAttachment, $wallpaperAttachmentOptions, true)) {
        $wallpaperAttachment = $loginDefaults['login_wallpaper_attachment'];
    }
    $wallpaperOverlayOpacity = brandkit_clamp_int(
        $_POST['login_wallpaper_overlay_opacity'] ?? $loginDefaults['login_wallpaper_overlay_opacity'],
        0,
        100,
        35
    );
    $wallpaperOverlayColor = brandkit_normalize_hex_color(
        $_POST['login_wallpaper_overlay_color'] ?? $loginDefaults['login_wallpaper_overlay_color'],
        $loginDefaults['login_wallpaper_overlay_color']
    );
    $hideLoginText = ($_POST['login_hide_login_text'] ?? '0') === '1' ? '1' : '0';
    $hideCopyright = ($_POST['login_hide_copyright'] ?? '0') === '1' ? '1' : '0';
    $loginLogoWidth = brandkit_clamp_int(
        $_POST['login_logo_width'] ?? ($loginConfig['login_logo_width'] ?? $loginDefaults['login_logo_width']),
        80,
        480,
        200
    );
    $loginLogoHeight = brandkit_clamp_int(
        $_POST['login_logo_height'] ?? ($loginConfig['login_logo_height'] ?? $loginDefaults['login_logo_height']),
        40,
        320,
        110
    );
    $loginLogoScale = brandkit_clamp_int(
        $_POST['login_logo_scale'] ?? ($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale']),
        80,
        480,
        200
    );
    $loginLogoOffset = brandkit_clamp_int($_POST['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);

    $loginWallpaper = $loginConfig['login_wallpaper'] ?? '';
    $wallpaperSource = $_POST['login_wallpaper_source'] ?? ($loginConfig['login_wallpaper_source'] ?? '');
    $wallpaperUrlRaw = trim((string) ($_POST['login_wallpaper_url'] ?? ($loginConfig['login_wallpaper_url'] ?? '')));
    $wallpaperUrl = brandkit_normalize_image_url($wallpaperUrlRaw);
    $wallpaperUrlError = $wallpaperUrlRaw !== '' && $wallpaperUrl === ''
        ? __('Wallpaper URL must use HTTPS or a same-origin path starting with /.', 'brandkit')
        : null;
    if ($wallpaperUrl !== '') {
        $wallpaperSource = 'url';
    }
    if ($wallpaperSource === '') {
        $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
    }
    if ($wallpaperSource === 'url' && $wallpaperUrl === '') {
        $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
    }
    if (!in_array($wallpaperSource, ['preset', 'custom', 'url'], true)) {
        $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
    }
    $wallpaperPreset = $_POST['login_wallpaper_preset'] ?? ($loginConfig['login_wallpaper_preset'] ?? $wallpaperPresetDefault);
    if (!in_array($wallpaperPreset, $wallpaperPresets, true)) {
        $wallpaperPreset = $wallpaperPresetDefault;
    }
    $wallpaperBaseName = 'wallpaperLogin';
    $picsDir = brandkit_get_pics_dir();
    $wallpaperDir = $picsDir ? $picsDir . '/brandkit-wallpapers' : null;
    $wallpaperPath = $wallpaperDir ? $wallpaperDir . '/' . $wallpaperBaseName . '.png' : null;
    $wallpaperError = null;

    if (!empty($_POST['login_wallpaper_remove'])) {
        brandkit_delete_uploaded_wallpaper_variants($wallpaperBaseName);
        $loginWallpaper = '';
        $wallpaperSource = 'custom';
        $wallpaperUrl = '';
    } elseif (!empty($_FILES['login_wallpaper']) && ($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errorCode = (int) ($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_OK);
            $wallpaperError = brandkit_get_upload_error_message(
                $errorCode,
                sprintf(__('Wallpaper upload failed due to an unexpected error (code %d).', 'brandkit'), $errorCode)
            );
        } else {
            $extension = strtolower(pathinfo($_FILES['login_wallpaper']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedWallpaperExtensions, true)) {
                $wallpaperError = __('Allowed formats: PNG, JPG, JPEG, WEBP.', 'brandkit');
            } elseif (!is_uploaded_file($_FILES['login_wallpaper']['tmp_name'])) {
                $wallpaperError = __('The upload could not be validated.', 'brandkit');
            } elseif (!$wallpaperPath) {
                $wallpaperError = __('Wallpaper upload failed. Public pics path is unavailable.', 'brandkit');
            } else {
                if ($wallpaperDir && !is_dir($wallpaperDir) && !@mkdir($wallpaperDir, 0755, true)) {
                    $wallpaperError = __('Unable to create wallpaper directory.', 'brandkit');
                }
                if (!$wallpaperError) {
                    if (is_file($wallpaperPath)) {
                        @unlink($wallpaperPath);
                    }
                    $storedFilename = $wallpaperBaseName . '.png';
                    if ($extension === 'png') {
                        if (!@move_uploaded_file($_FILES['login_wallpaper']['tmp_name'], $wallpaperPath)) {
                            $wallpaperError = __('Unable to save the uploaded wallpaper. Check file permissions.', 'brandkit');
                        }
                    } else {
                        if (brandkit_convert_image_to_png($_FILES['login_wallpaper']['tmp_name'], $wallpaperPath)) {
                            $storedFilename = $wallpaperBaseName . '.png';
                        } elseif ($extension === 'webp') {
                            $storedFilename = $wallpaperBaseName . '.webp';
                            $webpPath = $wallpaperDir ? $wallpaperDir . '/' . $storedFilename : null;
                            if (!$webpPath || !@move_uploaded_file($_FILES['login_wallpaper']['tmp_name'], $webpPath)) {
                                $wallpaperError = __('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'brandkit');
                            } else {
                                $wallpaperPath = $webpPath;
                            }
                        } else {
                            $wallpaperError = __('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'brandkit');
                        }
                    }
                }

                if (!$wallpaperError) {
                    if (!Document::isImage($wallpaperPath)) {
                        @unlink($wallpaperPath);
                        $wallpaperError = __('The uploaded file is not a valid image.', 'brandkit');
                    } else {
                        $optimizedPath = $wallpaperPath . '.tmp';
                        if (brandkit_resize_image($wallpaperPath, $optimizedPath, 1920, 1080, 80)) {
                            @rename($optimizedPath, $wallpaperPath);
                        } elseif (is_file($optimizedPath)) {
                            @unlink($optimizedPath);
                        }
                    }
                    if (!$wallpaperError) {
                        $loginWallpaper = $storedFilename;
                        $wallpaperSource = 'custom';
                        $wallpaperUrl = '';
                    }
                }
            }
        }
    }

    if ($wallpaperSource === 'custom' && $loginWallpaper && !$wallpaperError) {
        $wallpaperSource = 'custom';
    }

    $loginConfig = [
        'login_layout' => $layout,
        'login_panel_transparent' => $panelTransparent,
        'login_panel_hover_effect' => $panelHoverEffect,
        'login_panel_opacity' => (string) $panelOpacity,
        'login_panel_color' => $panelColor,
        'login_panel_blur' => (string) $panelBlur,
        'login_panel_width' => (string) $panelWidth,
        'login_panel_height' => (string) $panelHeight,
        'login_text_color' => $textColor,
        'login_logo_position' => $logoPosition,
        'login_split_opacity' => (string) $splitOpacity,
        'login_split_color' => $splitColor,
        'login_split_blur' => (string) $splitBlur,
        'login_split_width' => (string) $splitWidth,
        'login_button_style' => $buttonStyle,
        'login_button_shape' => $buttonShape,
        'login_button_color_mode' => $buttonColorMode,
        'login_button_color' => $buttonColor,
        'login_button_custom_style' => $buttonCustomStyle,
        'login_button_hover_effect' => $buttonHoverEffect,
        'login_button_hover_fill_mode' => $buttonHoverFillMode,
        'login_button_hover_fill_color' => $buttonHoverFillColor,
        'login_button_hover_text_color' => $buttonHoverTextColor,
        'login_button_text_size' => (string) $buttonTextSize,
        'login_field_style' => $fieldStyle,
        'login_field_bg' => $fieldBg,
        'login_field_border' => $fieldBorder,
        'login_field_border_width' => (string) $fieldBorderWidth,
        'login_field_text' => $fieldText,
        'login_field_text_size' => (string) $fieldTextSize,
        'login_field_height' => (string) $fieldHeight,
        'login_field_width' => (string) $fieldWidth,
        'login_text_size' => (string) $textSize,
        'login_field_focus' => $fieldFocus,
        'login_accent_color' => $accentColor,
        'login_icon_user' => $iconUser,
        'login_icon_password' => $iconPassword,
        'login_icon_color' => $iconColor,
        'login_icon_position' => $iconPosition,
        'login_field_radius' => (string) $fieldRadius,
        'login_wallpaper_size' => $wallpaperSize,
        'login_wallpaper_position' => $wallpaperPosition,
        'login_wallpaper_attachment' => $wallpaperAttachment,
        'login_wallpaper_overlay_color' => $wallpaperOverlayColor,
        'login_wallpaper_overlay_opacity' => (string) $wallpaperOverlayOpacity,
        'login_wallpaper_source' => $wallpaperSource,
        'login_wallpaper_preset' => $wallpaperPreset,
        'login_wallpaper_url' => $wallpaperUrl,
        'login_hide_login_text' => $hideLoginText,
        'login_hide_copyright' => $hideCopyright,
        'login_wallpaper' => $loginWallpaper,
        'login_logo_width' => (string) $loginLogoWidth,
        'login_logo_height' => (string) $loginLogoHeight,
        'login_logo_scale' => (string) $loginLogoScale,
        'login_logo_offset' => (string) $loginLogoOffset,
    ];
    if (!$isGlpi11) {
        $loginConfig['login_field_spacing'] = (string) $fieldSpacing;
        $loginConfig['login_title_size'] = (string) $titleSize;
    }

    Config::setConfigurationValues('plugin:brandkit', $loginConfig);

    $cssWritten = brandkit_write_login_css($pluginDocDir, $loginConfig);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login page settings saved.', 'brandkit'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Settings saved, but the login CSS file could not be written.', 'brandkit'), false, WARNING);
    }
    if ($wallpaperError) {
        Session::addMessageAfterRedirect($wallpaperError, false, WARNING);
    }
    if ($wallpaperUrlError) {
        Session::addMessageAfterRedirect($wallpaperUrlError, false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'save_logo_scales') {
    $logoHeaderScale = brandkit_clamp_int($_POST['logo_header_scale'] ?? null, 40, 200, 100);
    $logoReducedScale = brandkit_clamp_int($_POST['logo_reduced_scale'] ?? null, 40, 200, 100);

    $logoScaleConfig = [
        'logo_header_scale' => (string) $logoHeaderScale,
        'logo_reduced_scale' => (string) $logoReducedScale,
    ];

    Config::setConfigurationValues('plugin:brandkit', $logoScaleConfig);

    Session::addMessageAfterRedirect(__('Logo sizes updated.', 'brandkit'), false, INFO);
    Html::redirect($baseUrl . '?step=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'save_login_logo_sizes') {
    $loginLogoWidth = brandkit_clamp_int(
        $_POST['login_logo_width'] ?? ($loginConfig['login_logo_width'] ?? $loginDefaults['login_logo_width']),
        80,
        480,
        200
    );
    $loginLogoHeight = brandkit_clamp_int(
        $_POST['login_logo_height'] ?? ($loginConfig['login_logo_height'] ?? $loginDefaults['login_logo_height']),
        40,
        320,
        110
    );
    $loginLogoScale = brandkit_clamp_int(
        $_POST['login_logo_scale'] ?? ($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale']),
        80,
        480,
        200
    );
    $loginLogoOffset = brandkit_clamp_int($_POST['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);

    $logoConfig = [
        'login_logo_width' => (string) $loginLogoWidth,
        'login_logo_height' => (string) $loginLogoHeight,
        'login_logo_scale' => (string) $loginLogoScale,
        'login_logo_offset' => (string) $loginLogoOffset,
    ];

    Config::setConfigurationValues('plugin:brandkit', $logoConfig);

    $cssWritten = brandkit_write_login_css($pluginDocDir);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login logo size updated.', 'brandkit'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Login logo size saved, but the login CSS file could not be written.', 'brandkit'), false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

$advancedDefaults = brandkit_get_advanced_defaults();
$advancedPresets = brandkit_get_theme_presets();
$advancedSettings = brandkit_get_advanced_settings();
$advancedConfigKeys = brandkit_get_advanced_config_keys();
$supportedExportKeys = array_values(array_unique(array_merge(
    $advancedConfigKeys,
    array_keys($loginDefaults),
    array_keys($logoScaleDefaults),
    array_keys($logoScaleLegacyDefaults),
    array_values(brandkit_get_logo_url_config_keys())
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'apply_theme_preset') {
    $presetKey = (string) ($_POST['advanced_theme_preset'] ?? $advancedDefaults['advanced_theme_preset']);
    if (!isset($advancedPresets[$presetKey])) {
        $presetKey = $advancedDefaults['advanced_theme_preset'];
    }

    $presetValues = brandkit_build_advanced_config_from_post($advancedPresets[$presetKey]['values'], $advancedDefaults);
    Config::setConfigurationValues('plugin:brandkit', $presetValues);
    brandkit_write_login_css($pluginDocDir);

    Session::addMessageAfterRedirect(__('Theme preset applied.', 'brandkit'), false, INFO);
    Html::redirect($baseUrl . '?step=3');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'save_advanced_settings') {
    $advancedConfig = brandkit_build_advanced_config_from_post($_POST, $advancedSettings);
    Config::setConfigurationValues('plugin:brandkit', $advancedConfig);
    brandkit_write_login_css($pluginDocDir);

    Session::addMessageAfterRedirect(__('Advanced settings saved.', 'brandkit'), false, INFO);
    Html::redirect($baseUrl . '?step=3');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'reset_advanced_settings') {
    Config::setConfigurationValues('plugin:brandkit', $advancedDefaults);
    brandkit_write_login_css($pluginDocDir);

    Session::addMessageAfterRedirect(__('Advanced settings reset.', 'brandkit'), false, INFO);
    Html::redirect($baseUrl . '?step=3');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'export_config') {
    $config = Config::getConfigurationValues('plugin:brandkit', $supportedExportKeys);
    $cleanConfig = [];
    foreach ($supportedExportKeys as $key) {
        if (isset($config[$key]) && $config[$key] !== null && $config[$key] !== '') {
            $cleanConfig[$key] = (string) $config[$key];
        }
    }

    $payload = [
        'plugin' => 'brandkit',
        'name' => 'BrandKit - Custom(Fealq)',
        'version' => PLUGIN_BRANDKIT_VERSION,
        'exported_at' => gmdate('c'),
        'preset' => $advancedSettings['advanced_theme_preset'] ?? $advancedDefaults['advanced_theme_preset'],
        'supported_keys' => $supportedExportKeys,
        'config' => $cleanConfig,
    ];

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="brandkit-fealq-config.json"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['brandkit_action'] ?? '') === 'import_config') {
    $error = '';
    $importFile = $_FILES['brandkit_config_file'] ?? null;
    if (!$importFile || ($importFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = __('Please choose a valid BrandKit configuration JSON file.', 'brandkit');
    } elseif (!is_uploaded_file($importFile['tmp_name'])) {
        $error = __('The uploaded configuration could not be validated.', 'brandkit');
    } else {
        $raw = file_get_contents($importFile['tmp_name']);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || ($payload['plugin'] ?? '') !== 'brandkit' || !isset($payload['config']) || !is_array($payload['config'])) {
            $error = __('Invalid BrandKit configuration file.', 'brandkit');
        } elseif (isset($payload['version']) && version_compare((string) $payload['version'], '0.1.0', '<')) {
            $error = __('This BrandKit configuration file is too old to import safely.', 'brandkit');
        } else {
            $incoming = [];
            foreach ($payload['config'] as $key => $value) {
                if (in_array($key, $supportedExportKeys, true) && (is_string($value) || is_numeric($value))) {
                    $incoming[$key] = (string) $value;
                }
            }

            $advancedIncoming = array_intersect_key($incoming, array_flip($advancedConfigKeys));
            $configToSave = array_diff_key($incoming, array_flip($advancedConfigKeys));
            $configToSave = array_merge(
                $configToSave,
                brandkit_build_advanced_config_from_post($advancedIncoming, $advancedSettings)
            );

            Config::setConfigurationValues('plugin:brandkit', $configToSave);
            brandkit_write_login_css($pluginDocDir);
            Session::addMessageAfterRedirect(__('BrandKit configuration imported.', 'brandkit'), false, INFO);
            Html::redirect($baseUrl . '?step=3');
            exit;
        }
    }

    Session::addMessageAfterRedirect($error, false, ERROR);
    Html::redirect($baseUrl . '?step=3');
    exit;
}

if ($logosDir && !is_dir($logosDir)) {
    @mkdir($logosDir, 0755, true);
}

Html::header('BrandKit - Custom(Fealq)', $_SERVER['PHP_SELF'], 'plugins', 'brandkit');

$brandkitUiPrimary = ($advancedSettings['advanced_theme_preset'] ?? 'glpi') === 'glpi'
    ? '#066fd1'
    : ($advancedSettings['advanced_link_color'] ?? '#282b5f');
$brandkitUiSecondary = ($advancedSettings['advanced_theme_preset'] ?? 'glpi') === 'glpi'
    ? '#2f3f64'
    : ($advancedSettings['advanced_accent_color'] ?? '#d2314b');
$brandkitUiInk = ($advancedSettings['advanced_theme_preset'] ?? 'glpi') === 'fealq'
    ? '#282b5f'
    : '#1f2937';
$brandkitUiButtonBg = ($advancedSettings['advanced_theme_preset'] ?? 'glpi') === 'glpi'
    ? '#066fd1'
    : ($advancedSettings['advanced_button_bg_color'] ?? $brandkitUiSecondary);
$brandkitUiButtonText = ($advancedSettings['advanced_theme_preset'] ?? 'glpi') === 'glpi'
    ? '#ffffff'
    : ($advancedSettings['advanced_button_text_color'] ?? '#ffffff');

?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

    .brandkit-wrapper {
        margin: 12px 0 28px 0;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
        overflow: visible;
        font-family: 'Sora', 'Segoe UI', sans-serif;
        --brandkit-primary: <?php echo htmlescape($brandkitUiPrimary); ?>;
        --brandkit-secondary: <?php echo htmlescape($brandkitUiSecondary); ?>;
        --brandkit-button-bg: <?php echo htmlescape($brandkitUiButtonBg); ?>;
        --brandkit-button-text: <?php echo htmlescape($brandkitUiButtonText); ?>;
        --brandkit-ink: <?php echo htmlescape($brandkitUiInk); ?>;
        --brandkit-muted: #64748b;
        --brandkit-surface: #f8fafc;
        --brandkit-border: #e2e8f0;
    }

    .brandkit-hero {
        padding: 16px 22px;
        background: var(--brandkit-primary);
        color: #ffffff;
        position: relative;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        overflow: hidden;
    }

    .brandkit-hero-inner {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 8px;
    }

    .brandkit-hero::after {
        content: none;
    }

    .brandkit-hero h2 {
        margin: 0;
        font-weight: 600;
        font-size: 1.28rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .brandkit-hero-version {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.04em;
    }

    .brandkit-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.88rem;
    }

    .brandkit-hero-links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 10px;
        align-items: center;
    }

    .brandkit-hero-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff !important;
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 500;
        transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }

    .brandkit-hero-link:hover {
        color: #ffffff !important;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.26);
        border-color: rgba(255, 255, 255, 0.42);
        transform: translateY(-1px);
    }

    body:not(.page-anonymous) .brandkit-wrapper a.brandkit-hero-link,
    body:not(.page-anonymous) .brandkit-wrapper a.brandkit-hero-link:hover,
    body:not(.page-anonymous) .brandkit-wrapper a.brandkit-hero-link:focus {
        color: #ffffff !important;
    }

    .brandkit-hero-link i {
        font-size: 0.95rem;
    }

    .brandkit-steps {
        display: flex;
        gap: 12px;
        padding: 16px 24px 0 24px;
        flex-wrap: wrap;
    }

    .brandkit-step {
        flex: 1;
        min-width: 200px;
        border-radius: 10px;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        text-decoration: none;
        color: #0f172a;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .brandkit-step.active {
        border-color: #38bdf8;
        background: #e0f2fe;
        box-shadow: 0 6px 18px rgba(14, 165, 233, 0.18);
        transform: translateY(-2px);
    }

    .brandkit-step small {
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.68rem;
        color: #64748b;
        margin-bottom: 6px;
    }

    .brandkit-content {
        padding: 20px 24px 24px 24px;
    }

    .brandkit-timeline {
        display: flex;
        align-items: center;
        gap: 24px;
        margin: 0 24px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--brandkit-border);
    }

    .brandkit-timeline-step {
        display: inline-flex;
        align-items: center;
        padding: 12px 2px 14px;
        border-radius: 0;
        border: none;
        background: transparent;
        color: var(--brandkit-muted);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        position: relative;
        transition: color 0.2s ease;
    }

    .brandkit-timeline-step::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 3px;
        border-radius: 999px;
        background: transparent;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .brandkit-timeline-step.active {
        color: var(--brandkit-ink);
    }

    .brandkit-timeline-step.active::after {
        background: var(--brandkit-secondary);
    }

    .brandkit-timeline-step:hover {
        color: var(--brandkit-ink);
    }

    .brandkit-timeline-step:hover::after {
        background: rgba(14, 165, 233, 0.4);
    }

    .brandkit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .brandkit-card {
        position: relative;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        background: #ffffff;
        overflow: visible;
        z-index: 1;
    }

    .brandkit-card:hover {
        z-index: 5;
    }

    .brandkit-card h4 {
        margin: 0 0 8px 0;
        font-size: 0.95rem;
    }

    .brandkit-placeholder {
        border-radius: 8px;
        border: 1px dashed #cbd5f5;
        padding: 12px;
        color: #475569;
        background: #f8fafc;
        font-size: 0.9rem;
    }

    .brandkit-actions {
        margin-top: 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .brandkit-actions a,
    .brandkit-actions button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        background: var(--brandkit-primary);
        color: #ffffff;
        text-decoration: none;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .brandkit-actions a.secondary {
        background: #94a3b8;
    }

    .brandkit-actions .brandkit-save-logo {
        background: var(--brandkit-secondary);
        box-shadow: none;
    }

    .brandkit-actions .brandkit-save-logo:hover {
        background: var(--brandkit-secondary);
        filter: brightness(0.92);
    }

    .brandkit-actions .brandkit-save-logo:focus {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }

    .brandkit-actions-floating {
        position: fixed;
        right: 24px;
        bottom: 24px;
        margin: 0;
        padding: 0;
        border-radius: 0;
        background: transparent;
        border: none;
        box-shadow: none;
        z-index: 100;
        flex-wrap: wrap;
    }

    .brandkit-actions-floating .brandkit-save-login {
        padding: 10px 18px;
        border-radius: 8px;
        background: var(--brandkit-secondary);
        box-shadow: none;
        text-transform: none;
        letter-spacing: normal;
        font-size: 0.85rem;
        border: none;
    }

    .brandkit-actions-floating .brandkit-save-login:hover {
        background: var(--brandkit-secondary);
        filter: brightness(0.92);
    }

    .brandkit-actions-floating .brandkit-save-login:focus {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }

    .brandkit-actions-floating .brandkit-reset-login {
        padding: 10px 18px;
        border-radius: 8px;
        background: #64748b;
        box-shadow: none;
        font-size: 0.85rem;
        border: none;
    }

    .brandkit-actions-floating .brandkit-reset-login:hover {
        background: #475569;
    }

    .brandkit-actions-floating .brandkit-reset-login:focus {
        outline: 2px solid #cbd5f5;
        outline-offset: 2px;
    }

    @media (max-width: 720px) {
        .brandkit-actions-floating {
            left: 16px;
            right: 16px;
            bottom: 16px;
            border-radius: 0;
            justify-content: center;
        }

        .brandkit-actions-floating button {
            width: 100%;
            justify-content: center;
        }

        .brandkit-hero-links {
            gap: 8px;
        }

        .brandkit-hero-link {
            justify-content: center;
        }
    }

    .brandkit-intro {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
        gap: 24px;
        align-items: stretch;
    }

    .brandkit-intro-copy {
        display: grid;
        gap: 16px;
        align-content: start;
    }

    .brandkit-intro-copy > * {
        animation: brandkit-fade-up 0.5s ease both;
    }

    .brandkit-intro-copy > *:nth-child(2) {
        animation-delay: 0.05s;
    }

    .brandkit-intro-copy > *:nth-child(3) {
        animation-delay: 0.1s;
    }

    .brandkit-intro-copy > *:nth-child(4) {
        animation-delay: 0.15s;
    }

    .brandkit-intro-eyebrow {
        text-transform: uppercase;
        letter-spacing: 0.18em;
        font-size: 0.7rem;
        color: var(--brandkit-muted);
        font-weight: 600;
    }

    .brandkit-intro h3 {
        font-size: 1.6rem;
        margin: 0;
        color: var(--brandkit-ink);
    }

    .brandkit-intro p {
        margin: 0;
        color: #475569;
        line-height: 1.6;
    }

    .brandkit-intro-copy a {
        color: var(--brandkit-primary);
        font-weight: 600;
        text-decoration: none;
    }

    .brandkit-intro-copy a:hover {
        text-decoration: underline;
    }

    .brandkit-intro-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .brandkit-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .brandkit-cta.primary {
        background: var(--brandkit-button-bg);
        color: var(--brandkit-button-text) !important;
        box-shadow: 0 12px 24px rgba(14, 165, 233, 0.2);
    }

    .brandkit-wrapper .brandkit-save-login,
    .brandkit-wrapper button.submit,
    .brandkit-wrapper input[type="submit"].submit {
        background: var(--brandkit-button-bg) !important;
        border-color: var(--brandkit-button-bg) !important;
        color: var(--brandkit-button-text) !important;
    }

    .brandkit-cta.preset {
        background: var(--preset-button);
        color: var(--preset-button-text, #ffffff) !important;
        box-shadow: 0 12px 24px color-mix(in srgb, var(--preset-button), transparent 78%);
    }

    .brandkit-cta.secondary {
        background: #ffffff;
        color: var(--brandkit-ink) !important;
        border-color: var(--brandkit-border);
    }

    .brandkit-cta.ghost {
        background: rgba(14, 165, 233, 0.08);
        color: var(--brandkit-ink) !important;
    }

    .brandkit-intro-panel {
        position: relative;
        border-radius: 16px;
        padding: 18px;
        background: var(--brandkit-surface);
        border: 1px solid var(--brandkit-border);
        display: grid;
        gap: 12px;
        overflow: hidden;
    }

    .brandkit-intro-panel::before {
        content: none;
    }

    .brandkit-intro-card {
        position: relative;
        padding: 14px 16px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        animation: brandkit-fade-up 0.5s ease both;
    }

    .brandkit-intro-card:nth-child(2) {
        animation-delay: 0.08s;
    }

    .brandkit-intro-card:nth-child(3) {
        animation-delay: 0.16s;
    }

    .brandkit-intro-card span {
        display: inline-flex;
        font-weight: 700;
        color: var(--brandkit-primary);
        font-size: 0.9rem;
    }

    .brandkit-intro-card h4 {
        margin: 6px 0 6px 0;
        font-size: 1rem;
        color: var(--brandkit-ink);
    }

    .brandkit-intro-card p {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
    }

    .brandkit-preset-card {
        display: grid;
        align-items: start;
        text-align: left;
        gap: 8px;
        border-color: color-mix(in srgb, var(--preset-border), var(--preset-menu) 12%);
        background: var(--preset-surface);
        color: var(--preset-text);
    }

    .brandkit-preset-card.is-active {
        border-color: var(--preset-accent);
        box-shadow: 0 14px 26px color-mix(in srgb, var(--preset-accent), transparent 82%);
    }

    .brandkit-preset-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .brandkit-preset-card .brandkit-preset-check {
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        color: #ffffff;
        background: var(--preset-accent);
        font-size: 0.75rem;
        font-weight: 800;
        flex: 0 0 auto;
    }

    .brandkit-preset-card:not(.is-active) .brandkit-preset-check {
        color: var(--preset-menu);
        background: #ffffff;
        border: 1px solid var(--preset-border);
    }

    .brandkit-preset-swatches {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
    }

    .brandkit-preset-swatch {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: var(--swatch-color);
        border: 1px solid rgba(15, 23, 42, 0.18);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
    }

    .brandkit-preset-card h4 {
        margin: 0;
        color: var(--preset-heading) !important;
    }

    .brandkit-preset-card p {
        color: var(--preset-muted) !important;
    }

    .brandkit-preset-card.is-active .brandkit-preset-check {
        color: var(--preset-accent-text, #ffffff) !important;
    }

    .brandkit-preset-preview {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        min-height: 54px;
        border: 1px solid var(--preset-border);
        border-radius: 8px;
        overflow: hidden;
        background: var(--preset-page);
    }

    .brandkit-preset-preview-menu {
        background: var(--preset-menu);
    }

    .brandkit-preset-preview-body {
        display: grid;
        gap: 6px;
        padding: 8px;
        background: var(--preset-page);
    }

    .brandkit-preset-preview-surface {
        min-height: 18px;
        border: 1px solid var(--preset-border);
        border-radius: 6px;
        background: var(--preset-surface);
    }

    .brandkit-preset-preview-button {
        width: 54px;
        height: 12px;
        border-radius: 999px;
        background: var(--preset-button);
    }

    @keyframes brandkit-fade-up {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 960px) {
        .brandkit-intro {
            grid-template-columns: 1fr;
        }
    }

    .brandkit-logo-preview img {
        max-width: 100%;
        max-height: 80px;
        display: block;
        margin: 8px 0 0 0;
    }

    .brandkit-logo-card {
        position: relative;
        overflow: visible;
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .brandkit-logo-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin: 8px 0 12px 0;
    }

    .brandkit-logo-url-form {
        display: grid;
        gap: 8px;
        margin: 0 0 12px 0;
    }

    .brandkit-logo-url-form label {
        margin: 0;
        font-size: 0.74rem;
        color: #64748b;
        font-weight: 600;
    }

    .brandkit-logo-url-form input {
        width: 100%;
        border: 1px solid #dbe3ee;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 0.8rem;
    }

    .brandkit-logo-url-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .brandkit-logo-url-buttons button {
        border: 0;
        border-radius: 8px;
        padding: 7px 10px;
        background: #0f172a;
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
    }

    .brandkit-logo-url-buttons button[value="clear_logo_url"] {
        background: #e2e8f0;
        color: #0f172a;
    }

    .brandkit-logo-action {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        border: none;
        color: #ffffff;
        cursor: pointer;
        font-size: 13px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.35);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .brandkit-logo-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
        filter: saturate(1.05);
    }

    .brandkit-logo-remove {
        background: #dc3545;
    }

    .brandkit-logo-upload {
        background: var(--brandkit-primary);
    }

    .brandkit-logo-stage {
        border-radius: 12px;
        border: 1px solid #ffffff;
        background: #ffffff;
        box-shadow: inset 0 0 0 1px #e2e8f0;
        padding: 12px;
        display: grid;
        gap: 10px;
        min-height: 120px;
    }

    .brandkit-logo-stage.dark {
        background: #111827;
        border-color: rgba(255, 255, 255, 0.12);
    }

    .brandkit-logo-stage .logo-sample {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 4px 10px;
        border-radius: 8px;
        background: #e2e8f0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        position: relative;
        overflow: hidden;
        gap: 6px;
    }

    .brandkit-logo-stage.dark .logo-sample {
        background: rgba(15, 23, 42, 0.75);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .brandkit-logo-stage img {
        max-height: 26px;
        max-width: 100%;
        display: block;
        transform-origin: center;
    }

    .brandkit-logo-stage img[data-brandkit-logo-scale="header"] {
        transform: scale(var(--brandkit-logo-preview-header-scale, 1));
    }

    .brandkit-logo-stage img[data-brandkit-logo-scale="reduced"] {
        transform: scale(var(--brandkit-logo-preview-reduced-scale, 1));
    }

    .brandkit-logo-stage img.brandkit-scale-pulse {
        animation: brandkit-scale-pulse 260ms ease-out;
    }

    .brandkit-logo-preview-value {
        font-size: 0.62rem;
        padding: 2px 6px;
        border-radius: 999px;
        color: #0f172a;
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(148, 163, 184, 0.4);
        letter-spacing: 0.02em;
        opacity: 0;
        transform: translateY(2px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        white-space: nowrap;
    }

    .brandkit-logo-stage .logo-sample.brandkit-preview-active .brandkit-logo-preview-value {
        opacity: 1;
        transform: translateY(0);
    }

    .brandkit-logo-stage.dark .brandkit-logo-preview-value {
        color: #f8fafc;
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(148, 163, 184, 0.35);
    }

    .brandkit-save-logo.brandkit-save-pulse {
        animation: brandkit-save-pulse 480ms ease-out;
    }

    @keyframes brandkit-scale-pulse {
        0% {
            filter: brightness(1);
            opacity: 1;
        }
        60% {
            filter: brightness(1.08);
            opacity: 0.98;
        }
        100% {
            filter: brightness(1);
            opacity: 1;
        }
    }

    @keyframes brandkit-save-pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 rgba(34, 197, 94, 0);
        }
        50% {
            transform: scale(1.04);
            box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.25);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 rgba(34, 197, 94, 0);
        }
    }

    .brandkit-logo-header-sample,
    .brandkit-logo-login-sample {
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: inset 0 0 0 1px #f8fafc;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .brandkit-logo-stage.dark .brandkit-logo-header-sample,
    .brandkit-logo-stage.dark .brandkit-logo-login-sample {
        background: rgba(255, 255, 255, 0.08);
    }

    .brandkit-logo-login-sample {
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-height: 80px;
    }

    .brandkit-logo-login-field {
        width: 80%;
        height: 8px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.12);
    }

    .brandkit-logo-stage.dark .brandkit-logo-login-field {
        background: rgba(255, 255, 255, 0.2);
    }

    .brandkit-logo-stage.dark .brandkit-placeholder {
        color: #e2e8f0;
        border-color: rgba(226, 232, 240, 0.3);
        background: rgba(15, 23, 42, 0.4);
    }

    .brandkit-wallpaper-card {
        position: relative;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        min-height: 0;
        overflow: hidden;
        padding: 14px;
    }

    .brandkit-wallpaper-preview {
        position: relative;
        width: 100%;
        max-width: 360px;
        aspect-ratio: 16 / 9;
        height: auto;
        margin: 6px auto;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.45);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.35);
        background-color: #0f172a;
        background-image: var(--brandkit-wallpaper-background, none);
        background-size: var(--brandkit-wallpaper-preview-size, contain);
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: scroll;
        transition: background 0.2s ease, background-position 0.2s ease, background-size 0.2s ease;
    }

    .brandkit-wallpaper-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 10px;
        display: block;
    }

    .brandkit-wallpaper-placeholder {
        position: absolute;
        inset: 0;
        border-radius: 10px;
        border: 1px dashed rgba(226, 232, 240, 0.5);
        color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        background: rgba(15, 23, 42, 0.4);
    }

    .brandkit-wallpaper-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 8px;
        z-index: 2;
    }

    .brandkit-logo-input {
        display: none;
    }

    .brandkit-form {
        display: grid;
        gap: 12px;
    }

    .brandkit-form label {
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }

    .brandkit-inline-form {
        margin-top: 10px;
    }

    .brandkit-inline-form button {
        background: #ef4444;
        color: #ffffff;
        border: none;
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
    }

    .brandkit-option-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .brandkit-option {
        display: block;
        cursor: pointer;
    }

    .brandkit-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .brandkit-option-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 12px;
        background: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .brandkit-option input:checked + .brandkit-option-card {
        border-color: #0ea5e9;
        box-shadow: 0 8px 20px rgba(14, 165, 233, 0.18);
        transform: translateY(-2px);
    }

    .brandkit-option-card h4 {
        margin: 10px 0 4px 0;
        font-size: 0.95rem;
    }

    .brandkit-option-card p {
        margin: 0;
        color: #64748b;
        font-size: 0.85rem;
    }

    .brandkit-button-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .brandkit-button-stage {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        height: 120px;
        position: relative;
        overflow: hidden;
        padding: 12px;
        display: flex;
        align-items: flex-end;
    }

    .brandkit-button-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(14, 165, 233, 0.35), transparent 65%);
    }

    .brandkit-button-stage::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image: linear-gradient(120deg, rgba(255, 255, 255, 0.08) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.08) 50%, rgba(255, 255, 255, 0.08) 75%, transparent 75%, transparent);
        background-size: 18px 18px;
        opacity: 0.2;
    }

    .brandkit-button-panel {
        position: relative;
        z-index: 1;
        width: 100%;
        background: rgba(15, 23, 42, 0.7);
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 6px;
    }

    .brandkit-button-panel-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.6);
    }

    .brandkit-button-demo {
        border: none;
        color: #ffffff;
        background: #0ea5e9;
        padding: 6px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        cursor: default;
        justify-self: start;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .brandkit-button-demo--square {
        border-radius: 4px;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.24);
    }

    .brandkit-button-demo--rounded {
        border-radius: 10px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.25);
    }

    .brandkit-button-demo--pill {
        border-radius: 999px;
        padding-left: 18px;
        padding-right: 18px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.25);
    }

    .brandkit-button-demo--cut {
        border-radius: 6px 16px 6px 16px;
        clip-path: polygon(0 0, 92% 0, 100% 35%, 100% 100%, 8% 100%, 0 65%);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.28);
    }

    .brandkit-button-demo--soft {
        border-radius: 14px;
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }

    .brandkit-color-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .brandkit-color-stage {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1f2937);
        height: 120px;
        position: relative;
        overflow: hidden;
        padding: 12px;
        display: flex;
        align-items: flex-end;
    }

    .brandkit-color-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.3), transparent 60%);
    }

    .brandkit-color-panel {
        position: relative;
        z-index: 1;
        width: 100%;
        background: rgba(15, 23, 42, 0.75);
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 6px;
    }

    .brandkit-color-panel-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.6);
    }

    .brandkit-color-preview {
        border: 2px solid transparent;
    }

    .brandkit-color-preview--primary {
        background: #0d6efd;
    }

    .brandkit-color-preview--secondary {
        background: #6c757d;
    }

    .brandkit-color-preview--success {
        background: #198754;
    }

    .brandkit-color-preview--danger {
        background: #dc3545;
    }

    .brandkit-color-preview--warning {
        background: #ffc107;
        color: #111827;
    }

    .brandkit-color-preview--info {
        background: #0dcaf0;
        color: #0f172a;
    }

    .brandkit-color-preview--dark {
        background: #212529;
    }

    .brandkit-color-preview--light {
        background: #f8f9fa;
        color: #0f172a;
    }

    .brandkit-color-preview--outline-primary {
        background: transparent;
        border-color: #0d6efd;
        color: #0d6efd;
    }

    .brandkit-color-preview--outline-secondary {
        background: transparent;
        border-color: #6c757d;
        color: #6c757d;
    }

    .brandkit-color-preview--outline-success {
        background: transparent;
        border-color: #198754;
        color: #198754;
    }

    .brandkit-color-preview--outline-danger {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
    }

    .brandkit-color-preview--outline-warning {
        background: transparent;
        border-color: #ffc107;
        color: #ffc107;
    }

    .brandkit-color-preview--outline-info {
        background: transparent;
        border-color: #0dcaf0;
        color: #0dcaf0;
    }

    .brandkit-color-preview--outline-dark {
        background: transparent;
        border-color: #212529;
        color: #212529;
    }

    .brandkit-color-inputs {
        display: grid;
        gap: 10px;
    }

    .brandkit-color-inputs .brandkit-inline-field {
        display: grid;
        gap: 6px;
    }

    .brandkit-color-inputs input[type="text"] {
        text-transform: uppercase;
    }

    .brandkit-color-preview-custom {
        border-radius: 999px;
        height: 12px;
        width: 80%;
        background: var(--brandkit-custom-color, #0ea5e9);
    }

    .brandkit-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .brandkit-theme-groups {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .brandkit-theme-group {
        min-width: 0;
        margin: 0;
        padding: 14px;
        border: 1px solid #d8dee9;
        border-radius: 10px;
        background: #f8fafc;
    }

    .brandkit-theme-group legend {
        float: none;
        width: auto;
        margin: 0 0 12px 0;
        padding: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
    }

    .brandkit-theme-group .brandkit-form-grid {
        margin-top: 0;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }

    .brandkit-theme-group--links {
        grid-column: 1 / -1;
    }

    .brandkit-theme-group--links .brandkit-form-grid {
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 14px;
    }

    .brandkit-link-item {
        min-width: 0;
        display: grid;
        gap: 8px;
        padding: 12px;
        border: 1px solid #d8dee9;
        border-radius: 8px;
        background: #ffffff;
        color: var(--brandkit-ink);
    }

    .brandkit-link-item input {
        width: 100%;
        min-width: 0;
    }

    .brandkit-link-item label {
        margin-bottom: 0;
        color: var(--brandkit-ink);
    }

    .brandkit-field-link {
        display: inline-flex;
        justify-self: start;
        margin-top: 0;
        color: var(--brandkit-primary) !important;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
    }

    .brandkit-field-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 1100px) {
        .brandkit-theme-group--links .brandkit-form-grid {
            grid-template-columns: repeat(2, minmax(170px, 1fr));
        }
    }

    .brandkit-form-grid--button {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px 22px;
    }

    .brandkit-form-grid--button .brandkit-color-custom {
        grid-column: 1 / -1;
        padding-top: 4px;
    }

    .brandkit-form-grid--button .brandkit-color-inputs {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .brandkit-form-grid > div {
        min-width: 0;
    }

    .brandkit-form-grid .brandkit-grid-full {
        grid-column: 1 / -1;
    }

    .brandkit-form-grid label {
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }

    .brandkit-range {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        row-gap: 8px;
        padding: 6px 8px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid rgba(14, 165, 233, 0.25);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
    }

    .brandkit-range-value {
        margin-left: auto;
        white-space: nowrap;
    }

    .brandkit-wrapper input[type="range"] {
        flex: 1 1 160px;
        appearance: none;
        -webkit-appearance: none;
        height: 8px;
        border-radius: 999px;
        background: var(--brandkit-primary);
        background-repeat: no-repeat;
        background-size: 100% 100%;
        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.65);
        outline: none;
    }

    .brandkit-wrapper input[type="range"]::-webkit-slider-runnable-track {
        height: 8px;
        border-radius: 999px;
        background: var(--brandkit-primary);
    }

    .brandkit-wrapper input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffffff;
        border: 2px solid var(--brandkit-primary);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.16);
        cursor: pointer;
        margin-top: -4px;
    }

    .brandkit-wrapper input[type="range"]::-moz-range-track {
        height: 8px;
        border-radius: 999px;
        background: var(--brandkit-primary);
    }

    .brandkit-wrapper input[type="range"]::-moz-range-thumb {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffffff;
        border: 2px solid var(--brandkit-primary);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.16);
        cursor: pointer;
    }

    .brandkit-wrapper input[type="range"]::-moz-range-progress {
        height: 8px;
        border-radius: 999px;
        background: var(--brandkit-secondary);
    }

    .brandkit-range-value {
        min-width: 60px;
        text-align: center;
        font-weight: 600;
        color: #0f172a;
        padding: 4px 10px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.5);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .brandkit-step2-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
        align-items: stretch;
        margin-top: 16px;
    }

    .brandkit-step2-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 18px;
        align-items: stretch;
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    }

    .brandkit-step2-row .brandkit-section {
        border: none;
        padding: 0;
        background: transparent;
    }

    .brandkit-step2-preview {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 12px;
        min-height: 210px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brandkit-step2-grid .brandkit-section {
        min-width: 0;
    }

    .brandkit-section-wide {
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }

    .brandkit-section {
        position: relative;
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        overflow: visible;
        z-index: 0;
    }

    .brandkit-section h4 {
        margin: 0 0 12px 0;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .brandkit-section h4::before {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: var(--brandkit-secondary);
        box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.15);
    }

    .brandkit-info {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 1px solid #94a3b8;
        color: #334155;
        font-size: 0.7rem;
        font-weight: 700;
        background: #ffffff;
        cursor: help;
        line-height: 1;
        transform: translateY(-1px);
        z-index: 6;
    }

    .brandkit-info::after {
        content: attr(data-info);
        position: absolute;
        left: 50%;
        bottom: calc(100% + 10px);
        transform: translateX(-50%);
        min-width: 220px;
        max-width: 280px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #0f172a;
        color: #e2e8f0;
        font-size: 0.78rem;
        font-weight: 500;
        line-height: 1.3;
        text-align: left;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.25);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 60;
    }

    .brandkit-info::before {
        content: '';
        position: absolute;
        left: 50%;
        bottom: calc(100% + 4px);
        transform: translateX(-50%);
        border-width: 6px 6px 0 6px;
        border-style: solid;
        border-color: #0f172a transparent transparent transparent;
        opacity: 0;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 59;
    }

    .brandkit-info:hover::after,
    .brandkit-info:focus-visible::after {
        opacity: 1;
        transform: translateX(-50%) translateY(-2px);
    }

    .brandkit-info:hover::before,
    .brandkit-info:focus-visible::before {
        opacity: 1;
        transform: translateX(-50%) translateY(-2px);
    }

    .brandkit-section + .brandkit-section {
        margin-top: 20px;
    }

    .brandkit-section + form {
        margin-top: 20px;
    }

    form + .brandkit-section {
        margin-top: 20px;
    }

    .brandkit-step2-grid .brandkit-section + .brandkit-section {
        margin-top: 0;
    }

    @media (max-width: 900px) {
        .brandkit-step2-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {

        .brandkit-form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .brandkit-theme-group--links .brandkit-form-grid {
            grid-template-columns: 1fr;
        }

        .brandkit-range {
            flex-wrap: wrap;
        }

        .brandkit-range-value {
            width: 100%;
        }
    }

    .brandkit-section summary {
        cursor: pointer;
        font-weight: 600;
        list-style: none;
    }

    .brandkit-section summary::-webkit-details-marker {
        display: none;
    }

    .brandkit-toggle-hidden {
        display: none;
    }

    .brandkit-muted {
        color: #64748b;
        font-size: 0.85rem;
        margin-top: 6px;
    }

    .brandkit-preview-shell {
        width: 100%;
        height: 210px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        background: #0f172a;
        display: flex;
        align-items: center;
        justify-content: var(--brandkit-preview-panel-align, center);
        padding: 10px;
    }

    .brandkit-preview-shell.light {
        background: #eef2f7;
    }

    .brandkit-preview-split {
        position: absolute;
        top: 0;
        bottom: 0;
        width: var(--brandkit-preview-split-width, 50%);
        background: var(--brandkit-preview-split-color, #cbd5e1);
        left: 0;
        opacity: var(--brandkit-preview-split-visibility, 1);
        transition: opacity 0.2s ease, background 0.2s ease;
        backdrop-filter: blur(var(--brandkit-preview-split-blur, 0px));
        -webkit-backdrop-filter: blur(var(--brandkit-preview-split-blur, 0px));
    }

    .brandkit-preview-shell[data-layout="split-right"] .brandkit-preview-split {
        left: auto;
        right: 0;
    }

    .brandkit-preview-shell[data-layout="classic"] .brandkit-preview-split {
        opacity: 0;
    }

    .brandkit-preview-panel {
        position: relative;
        z-index: 2;
        width: var(--brandkit-preview-panel-width, 64%);
        height: var(--brandkit-preview-panel-height, 68%);
        background: var(--brandkit-preview-panel-color, rgba(15, 23, 42, 0.7));
        border-radius: 12px;
        display: grid;
        gap: 8px;
        padding: 10px;
        align-content: center;
        opacity: var(--brandkit-preview-panel-opacity, 1);
        box-shadow: var(--brandkit-preview-panel-shadow, 0 10px 24px rgba(15, 23, 42, 0.25));
        border: var(--brandkit-preview-panel-border, 1px solid rgba(148, 163, 184, 0.4));
    }

    .brandkit-preview-shell[data-logo-position="outside"] .brandkit-preview-logo {
        position: absolute;
        left: 50%;
        top: calc(12px + var(--brandkit-preview-logo-offset, 0px));
        transform: translateX(-50%);
        margin: 0;
    }

    .brandkit-preview-shell[data-logo-position="outside"] .brandkit-preview-panel {
        padding-top: 22px;
    }

    .brandkit-preview-shell[data-login-text-hidden="1"] .brandkit-preview-title {
        display: none;
    }

    .brandkit-preview-shell[data-logo-position="outside"] .brandkit-preview-login {
        margin-top: 6px;
    }

    .brandkit-preview-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 22px;
        width: var(--brandkit-preview-logo-width, 60%);
        border-radius: 6px;
        border: 1px solid rgba(148, 163, 184, 0.7);
        color: var(--brandkit-preview-text, #e2e8f0);
        font-size: 0.62rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: rgba(15, 23, 42, 0.25);
        justify-self: center;
    }

    .brandkit-preview-login {
        display: grid;
        gap: 8px;
    }

    .brandkit-preview-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--brandkit-preview-text, #e2e8f0);
    }

    .brandkit-preview-field-label {
        font-size: 0.55rem;
        color: var(--brandkit-preview-text, #e2e8f0);
        opacity: 0.85;
    }

    .brandkit-preview-field {
        width: min(100%, var(--brandkit-preview-field-width, 100%));
        max-width: var(--brandkit-preview-field-width, 100%);
        height: var(--brandkit-preview-field-height, 36px);
        border-radius: var(--brandkit-preview-field-radius, 10px);
        background: var(--brandkit-preview-field-bg, rgba(255, 255, 255, 0.85));
        border: var(--brandkit-preview-field-border-width, 1px) solid var(--brandkit-preview-field-border, rgba(148, 163, 184, 0.7));
        box-shadow: var(--brandkit-preview-field-shadow, none);
        display: flex;
        align-items: center;
        padding: 0 10px;
        transition: border-width 0.2s ease, background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, height 0.2s ease, transform 0.2s ease;
    }

    .brandkit-preview-field-text {
        font-size: var(--brandkit-preview-field-text-size, 14px);
        color: var(--brandkit-preview-field-text-color, #111827);
        opacity: 0.85;
        transition: font-size 0.2s ease;
    }

    .brandkit-preview-fields.brandkit-field-pulse .brandkit-preview-field,
    .brandkit-preview-fields.brandkit-field-pulse .brandkit-preview-field-text {
        animation: brandkit-field-bump 0.35s ease;
    }

    @keyframes brandkit-field-bump {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.02);
            opacity: 0.9;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .brandkit-preview-button {
        border: none;
        border-radius: 10px;
        padding: 6px 12px;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: var(--brandkit-preview-accent, #0ea5e9);
        color: #ffffff;
        width: fit-content;
    }

    .brandkit-preview-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(248, 250, 252, 0.6);
    }

    .brandkit-preview-line.small {
        width: 65%;
        justify-self: center;
    }

    .brandkit-preview-fields {
        background: var(--brandkit-preview-field-surface, #f1f5f9);
        display: grid;
        gap: 10px;
        padding: 12px;
        transition: background 0.2s ease;
    }

    .brandkit-preview-field {
        width: min(100%, var(--brandkit-preview-field-width, 100%));
        max-width: var(--brandkit-preview-field-width, 100%);
        height: var(--brandkit-preview-field-height, 36px);
        border-radius: var(--brandkit-preview-field-radius, 10px);
        background: var(--brandkit-preview-field-bg, #ffffff);
        border: var(--brandkit-preview-field-border-width, 1px) solid var(--brandkit-preview-field-border, #cbd5f5);
        box-shadow: var(--brandkit-preview-field-shadow, none);
    }

    .brandkit-preview-icons {
        background: var(--brandkit-preview-field-surface, #f1f5f9);
        padding: 12px;
        display: grid;
        gap: 10px;
        transition: background 0.2s ease;
    }

    .brandkit-preview-icon-field {
        display: grid;
    }

    .brandkit-preview-field--icon {
        position: relative;
        padding-left: 34px;
        padding-right: 10px;
    }

    .brandkit-preview-icons.icon-right .brandkit-preview-field--icon {
        padding-left: 10px;
        padding-right: 34px;
    }

    .brandkit-preview-field-icon {
        width: 18px;
        height: 18px;
        display: block;
        color: var(--brandkit-preview-icon-color, #0ea5e9);
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    }

    .brandkit-preview-icons.icon-right .brandkit-preview-field-icon {
        left: auto;
        right: 10px;
    }

    .brandkit-preview-field-icon svg {
        width: 18px;
        height: 18px;
        display: block;
    }

    .brandkit-preview-icons[data-login-icon="user"] .brandkit-preview-field[data-field="login"] .brandkit-preview-field-icon[data-brandkit-icon="user"],
    .brandkit-preview-icons[data-login-icon="id"] .brandkit-preview-field[data-field="login"] .brandkit-preview-field-icon[data-brandkit-icon="id"],
    .brandkit-preview-icons[data-login-icon="mail"] .brandkit-preview-field[data-field="login"] .brandkit-preview-field-icon[data-brandkit-icon="mail"],
    .brandkit-preview-icons[data-password-icon="lock"] .brandkit-preview-field[data-field="password"] .brandkit-preview-field-icon[data-brandkit-icon="lock"],
    .brandkit-preview-icons[data-password-icon="key"] .brandkit-preview-field[data-field="password"] .brandkit-preview-field-icon[data-brandkit-icon="key"],
    .brandkit-preview-icons[data-password-icon="shield"] .brandkit-preview-field[data-field="password"] .brandkit-preview-field-icon[data-brandkit-icon="shield"] {
        display: block;
    }

    .brandkit-preview-icons[data-login-icon="none"] .brandkit-preview-field[data-field="login"],
    .brandkit-preview-icons[data-password-icon="none"] .brandkit-preview-field[data-field="password"] {
        padding-left: 10px;
        padding-right: 10px;
    }

    .brandkit-preview-icons .brandkit-preview-field {
        flex: 1;
    }

    .brandkit-icon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }

    .brandkit-icon-option {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .brandkit-icon-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .brandkit-icon-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: var(--brandkit-icon-bg, #ffffff);
        display: grid;
        gap: 8px;
        justify-items: center;
        align-items: center;
        color: var(--brandkit-icon-color, #0ea5e9);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .brandkit-icon-card svg {
        width: 26px;
        height: 26px;
        display: block;
    }

    .brandkit-icon-placeholder {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        border: 2px dashed rgba(148, 163, 184, 0.7);
        opacity: 0.8;
    }

    .brandkit-icon-card span {
        font-size: 0.78rem;
        color: #64748b;
        text-align: center;
    }

    .brandkit-icon-option input:checked + .brandkit-icon-card {
        border-color: #38bdf8;
        box-shadow: 0 8px 18px rgba(14, 165, 233, 0.2);
        transform: translateY(-1px);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-wrapper,
    :root[glpi-theme-dark="1"] .brandkit-wrapper {
        background: #111827;
        border-color: #334155;
        box-shadow: 0 12px 28px rgba(2, 6, 23, 0.35);
        --brandkit-ink: #e5e7eb;
        --brandkit-muted: #94a3b8;
        --brandkit-surface: #0f172a;
        --brandkit-border: #334155;
        color: var(--brandkit-ink);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-wrapper h3,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper h4,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper label,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper legend,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper summary,
    :root[glpi-theme-dark="1"] .brandkit-wrapper h3,
    :root[glpi-theme-dark="1"] .brandkit-wrapper h4,
    :root[glpi-theme-dark="1"] .brandkit-wrapper label,
    :root[glpi-theme-dark="1"] .brandkit-wrapper legend,
    :root[glpi-theme-dark="1"] .brandkit-wrapper summary {
        color: var(--brandkit-ink);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-step,
    :root[data-glpi-theme-dark="1"] .brandkit-card,
    :root[data-glpi-theme-dark="1"] .brandkit-logo-card,
    :root[data-glpi-theme-dark="1"] .brandkit-option-card,
    :root[data-glpi-theme-dark="1"] .brandkit-icon-card,
    :root[data-glpi-theme-dark="1"] .brandkit-step2-row,
    :root[data-glpi-theme-dark="1"] .brandkit-section,
    :root[data-glpi-theme-dark="1"] .brandkit-section-wide,
    :root[data-glpi-theme-dark="1"] .brandkit-step2-preview,
    :root[data-glpi-theme-dark="1"] .brandkit-range,
    :root[data-glpi-theme-dark="1"] .brandkit-theme-group,
    :root[data-glpi-theme-dark="1"] .brandkit-link-item,
    :root[data-glpi-theme-dark="1"] .brandkit-cta.secondary,
    :root[data-glpi-theme-dark="1"] .brandkit-cta.ghost,
    :root[data-glpi-theme-dark="1"] .brandkit-info,
    :root[glpi-theme-dark="1"] .brandkit-step,
    :root[glpi-theme-dark="1"] .brandkit-card,
    :root[glpi-theme-dark="1"] .brandkit-logo-card,
    :root[glpi-theme-dark="1"] .brandkit-option-card,
    :root[glpi-theme-dark="1"] .brandkit-icon-card,
    :root[glpi-theme-dark="1"] .brandkit-step2-row,
    :root[glpi-theme-dark="1"] .brandkit-section,
    :root[glpi-theme-dark="1"] .brandkit-section-wide,
    :root[glpi-theme-dark="1"] .brandkit-step2-preview,
    :root[glpi-theme-dark="1"] .brandkit-range,
    :root[glpi-theme-dark="1"] .brandkit-theme-group,
    :root[glpi-theme-dark="1"] .brandkit-link-item,
    :root[glpi-theme-dark="1"] .brandkit-cta.secondary,
    :root[glpi-theme-dark="1"] .brandkit-cta.ghost,
    :root[glpi-theme-dark="1"] .brandkit-info {
        background: #0f172a;
        border-color: #334155;
        color: var(--brandkit-ink);
        box-shadow: 0 10px 24px rgba(2, 6, 23, 0.24);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-logo-card,
    :root[glpi-theme-dark="1"] .brandkit-logo-card {
        background: #0f172a;
        border-color: rgba(51, 65, 85, 0.95);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-step.active,
    :root[glpi-theme-dark="1"] .brandkit-step.active {
        background: rgba(14, 165, 233, 0.14);
        border-color: rgba(56, 189, 248, 0.5);
        box-shadow: 0 8px 20px rgba(14, 165, 233, 0.14);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-intro p,
    :root[data-glpi-theme-dark="1"] .brandkit-option-card p,
    :root[data-glpi-theme-dark="1"] .brandkit-icon-card span,
    :root[data-glpi-theme-dark="1"] .brandkit-muted,
    :root[data-glpi-theme-dark="1"] .brandkit-placeholder,
    :root[data-glpi-theme-dark="1"] .brandkit-content > p,
    :root[glpi-theme-dark="1"] .brandkit-intro p,
    :root[glpi-theme-dark="1"] .brandkit-option-card p,
    :root[glpi-theme-dark="1"] .brandkit-icon-card span,
    :root[glpi-theme-dark="1"] .brandkit-muted,
    :root[glpi-theme-dark="1"] .brandkit-placeholder,
    :root[glpi-theme-dark="1"] .brandkit-content > p {
        color: var(--brandkit-muted);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-placeholder,
    :root[glpi-theme-dark="1"] .brandkit-placeholder {
        background: rgba(15, 23, 42, 0.82);
        border-color: rgba(100, 116, 139, 0.55);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-intro-panel,
    :root[glpi-theme-dark="1"] .brandkit-intro-panel {
        background: #0f172a;
        border-color: rgba(56, 189, 248, 0.18);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-intro-card,
    :root[glpi-theme-dark="1"] .brandkit-intro-card {
        background: rgba(15, 23, 42, 0.82);
        border-color: rgba(51, 65, 85, 0.9);
        box-shadow: 0 12px 22px rgba(2, 6, 23, 0.28);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-range-value,
    :root[glpi-theme-dark="1"] .brandkit-range-value {
        background: #111827;
        border-color: rgba(100, 116, 139, 0.6);
        color: var(--brandkit-ink);
        box-shadow: 0 8px 16px rgba(2, 6, 23, 0.18);
    }

    :root[data-glpi-theme-dark="1"] .brandkit-wrapper input[type="text"],
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper input[type="number"],
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper input[type="url"],
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper input[type="password"],
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper select,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper textarea,
    :root[glpi-theme-dark="1"] .brandkit-wrapper input[type="text"],
    :root[glpi-theme-dark="1"] .brandkit-wrapper input[type="number"],
    :root[glpi-theme-dark="1"] .brandkit-wrapper input[type="url"],
    :root[glpi-theme-dark="1"] .brandkit-wrapper input[type="password"],
    :root[glpi-theme-dark="1"] .brandkit-wrapper select,
    :root[glpi-theme-dark="1"] .brandkit-wrapper textarea {
        background: #0f172a;
        color: var(--brandkit-ink);
        border-color: #334155;
    }

    :root[data-glpi-theme-dark="1"] .brandkit-wrapper input::placeholder,
    :root[data-glpi-theme-dark="1"] .brandkit-wrapper textarea::placeholder,
    :root[glpi-theme-dark="1"] .brandkit-wrapper input::placeholder,
    :root[glpi-theme-dark="1"] .brandkit-wrapper textarea::placeholder {
        color: #94a3b8;
    }

    :root[data-glpi-theme-dark="1"] .brandkit-info::after,
    :root[glpi-theme-dark="1"] .brandkit-info::after {
        background: #020617;
        color: #e2e8f0;
    }

    :root[data-glpi-theme-dark="1"] .brandkit-info::before,
    :root[glpi-theme-dark="1"] .brandkit-info::before {
        border-color: #020617 transparent transparent transparent;
    }
</style>

<div class="brandkit-wrapper">
    <div class="brandkit-hero">
        <div class="brandkit-hero-inner">
            <h2>
                BrandKit - Custom(Fealq)
                <?php if (defined('PLUGIN_BRANDKIT_VERSION')) : ?>
                    <span class="brandkit-hero-version">v<?php echo PLUGIN_BRANDKIT_VERSION; ?></span>
                <?php endif; ?>
            </h2>
            <p><?php echo __('Turn GLPI into a showcase for your brand in just a few clicks.', 'brandkit'); ?></p>
            <div class="brandkit-hero-links">
                <a class="brandkit-hero-link" href="https://fealq.org.br/" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-globe" aria-hidden="true"></i>
                    <span>Fealq</span>
                </a>
                <a class="brandkit-hero-link" href="https://wa.me/5519985992882" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i>
                    <span>WhatsApp</span>
                </a>
                <a class="brandkit-hero-link" href="https://www.linkedin.com/company/furufealq" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-linkedin-in" aria-hidden="true"></i>
                    <span>LinkedIn</span>
                </a>
                <a class="brandkit-hero-link" href="https://www.instagram.com/fealq.co" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-instagram" aria-hidden="true"></i>
                    <span>Instagram</span>
                </a>
            </div>
        </div>
    </div>

    <div class="brandkit-timeline">
        <a class="brandkit-timeline-step <?php echo $currentStep === 0 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>">
            <?php echo __('About', 'brandkit'); ?>
        </a>
        <a class="brandkit-timeline-step <?php echo $currentStep === 1 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=1">
            <?php echo __('GLPI logos', 'brandkit'); ?>
        </a>
        <a class="brandkit-timeline-step <?php echo $currentStep === 2 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=2">
            <?php echo __('Login Page', 'brandkit'); ?>
        </a>
        <a class="brandkit-timeline-step <?php echo $currentStep === 3 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=3">
            Avançado
        </a>
    </div>

    <div class="brandkit-content">
        <?php if ($currentStep === 0) : ?>
            <div class="brandkit-intro">
                <div class="brandkit-intro-copy">
                    <span class="brandkit-intro-eyebrow">BrandKit - Custom(Fealq)</span>
                    <h3><?php echo __('The visual evolution of your GLPI starts here.', 'brandkit'); ?></h3>
                    <p>BrandKit - Custom(Fealq) entrega logos, tela de login e cores alinhadas com a identidade da Fealq.</p>
                    <p>Fealq: <a href="https://fealq.org.br/" target="_blank" rel="noopener noreferrer">fealq.org.br</a>.</p>
                    <div class="brandkit-intro-actions">
                        <a class="brandkit-cta primary" href="<?php echo $baseUrl; ?>?step=1"><?php echo __('GLPI logos', 'brandkit'); ?></a>
                        <a class="brandkit-cta secondary" href="<?php echo $baseUrl; ?>?step=2"><?php echo __('Login Page', 'brandkit'); ?></a>
                        <a class="brandkit-cta ghost" href="<?php echo $baseUrl; ?>?step=3">Avançado</a>
                    </div>
                </div>
                <div class="brandkit-intro-panel">
                    <div class="brandkit-intro-card">
                        <span>01</span>
                        <h4><?php echo __('Consistent logos', 'brandkit'); ?></h4>
                        <p><?php echo __('Ready uploads for header, favicon, and login.', 'brandkit'); ?></p>
                    </div>
                    <div class="brandkit-intro-card">
                        <span>02</span>
                        <h4><?php echo __('Login with presence', 'brandkit'); ?></h4>
                        <p><?php echo __('Pick layouts, colors, and effects for an authentication screen that sells your brand.', 'brandkit'); ?></p>
                    </div>
                    <div class="brandkit-intro-card">
                        <span>03</span>
                        <h4><?php echo __('Fast delivery', 'brandkit'); ?></h4>
                        <p><?php echo __('Settings saved directly in GLPI to activate branding without rework.', 'brandkit'); ?></p>
                    </div>
                </div>
            </div>
        <?php elseif ($currentStep === 1) : ?>
            <h3><?php echo __('GLPI logo configuration', 'brandkit'); ?></h3>
            <p><?php echo __('Upload logos that will replace the default GLPI assets using CSS variables.', 'brandkit'); ?></p>
            <?php
                $logoHeaderScale = brandkit_clamp_int(
                    $logoScaleConfig['logo_header_scale']
                        ?? $logoScaleConfig['logo_header_light_scale']
                        ?? $logoScaleConfig['logo_header_dark_scale']
                        ?? $logoScaleDefaults['logo_header_scale'],
                    40,
                    200,
                    100
                );
                $logoReducedScale = brandkit_clamp_int(
                    $logoScaleConfig['logo_reduced_scale']
                        ?? $logoScaleConfig['logo_reduced_light_scale']
                        ?? $logoScaleConfig['logo_reduced_dark_scale']
                        ?? $logoScaleDefaults['logo_reduced_scale'],
                    40,
                    200,
                    100
                );
            ?>
            <?php
                $lightThemeFiles = [
                    'logo-GLPI-100-white.png',
                    'logo-G-100-white.png',
                    'logo-GLPI-250-black.png',
                ];
                $darkThemeFiles = [
                    'logo-GLPI-100-black.png',
                    'logo-G-100-black.png',
                    'logo-GLPI-250-white.png',
                ];
                $logoInfoMap = [
                    'logo-GLPI-100-white.png' => __('Expanded menu logo: the logo displayed when the sidebar menu is open, with more space available.', 'brandkit'),
                    'logo-G-100-white.png' => __('Collapsed menu logo: the logo displayed when the sidebar menu is collapsed, so it needs to be a more compact version.', 'brandkit'),
                    'logo-GLPI-100-black.png' => __('Expanded menu logo: the logo displayed when the sidebar menu is open, with more space available.', 'brandkit'),
                    'logo-G-100-black.png' => __('Collapsed menu logo: the logo displayed when the sidebar menu is collapsed, so it needs to be a more compact version.', 'brandkit'),
                    'logo-GLPI-250-black.png' => __('Login page logo: the logo displayed on the login screen before the user enters the system.', 'brandkit'),
                    'logo-GLPI-250-white.png' => __('Login page logo used by the dark theme on the login screen.', 'brandkit'),
                ];
                $logoGroups = [
                    [
                        'title' => __('Light mode logos', 'brandkit'),
                        'info' => __('Light mode logos: used when the system is in light mode, ensuring good visibility on white or light backgrounds.', 'brandkit'),
                        'files' => $lightThemeFiles,
                    ],
                    [
                        'title' => __('Dark mode logos', 'brandkit'),
                        'info' => __('Dark mode logos: used when the system is in dark mode, ensuring contrast and readability on black or dark backgrounds.', 'brandkit'),
                        'files' => $darkThemeFiles,
                    ],
                ];
                $faviconGroup = [
                    'title' => __('Favicon', 'brandkit'),
                    'info' => __('The favicon is the small icon that appears in the browser tab (and also in bookmarks).', 'brandkit'),
                    'files' => [
                        'favicon.ico',
                    ],
                ];
            ?>
            <?php foreach ($logoGroups as $group) : ?>
            <div class="brandkit-section">
                <h4>
                    <?php echo htmlescape($group['title']); ?>
                    <?php if (!empty($group['info'])) : ?>
                        <span
                            class="brandkit-info"
                            title="<?php echo htmlescape($group['info']); ?>"
                            data-info="<?php echo htmlescape($group['info']); ?>"
                            aria-label="<?php echo htmlescape($group['info']); ?>"
                            role="img"
                            tabindex="0"
                        >i</span>
                    <?php endif; ?>
                </h4>
                <div class="brandkit-grid">
                <?php foreach ($group['files'] as $file) : ?>
                    <?php $label = $logoFiles[$file] ?? $file; ?>
                    <?php
                        $logoPath = $logosDir ? $logosDir . '/' . $file : null;
                        $logoExists = $logoPath && is_file($logoPath);
                        $configuredLogoUrl = $configuredLogoUrls[$file] ?? '';
                        $logoIsRemote = $configuredLogoUrl !== '';
                        $publicLogoPath = $publicLogosDir ? $publicLogosDir . '/' . $file : null;
                        if ($logoIsRemote) {
                            $logoUrl = $configuredLogoUrl;
                        } elseif ($publicLogoPath && is_file($publicLogoPath)) {
                            $logoUrl = rtrim($picsWebBase, '/') . '/logos/' . rawurlencode($file);
                        } else {
                            $logoUrl = $pluginWebBase . '/front/logo_preview.php?name=' . rawurlencode($file);
                        }
                        $activeLogoPath = $logoExists ? $logoPath : (($publicLogoPath && is_file($publicLogoPath)) ? $publicLogoPath : null);
                        $logoVersion = $activeLogoPath && is_file($activeLogoPath)
                            ? (string) filemtime($activeLogoPath)
                            : (string) ($_GET['refresh'] ?? time());
                        if (!$logoIsRemote) {
                            $logoUrl .= (strpos($logoUrl, '?') === false ? '?' : '&') . 'v=' . rawurlencode($logoVersion);
                        }
                        $isWhiteLogo = strpos($file, 'white') !== false;
                        $isDarkLogo = strpos($file, 'black') !== false;
                        $isLoginLogo = strpos($file, '250') !== false;
                        $isCompactLogo = strpos($file, 'logo-G-100') !== false;
                        $isFavicon = $file === 'favicon.ico';
                        $previewScale = $isCompactLogo ? 'reduced' : ($isLoginLogo || $isFavicon ? null : 'header');
                        $acceptTypes = $isFavicon
                            ? '.ico,image/x-icon,image/vnd.microsoft.icon'
                            : 'image/png,image/jpeg,image/webp';
                        $stageClass = '';
                        $uploadId = 'brandkit_logo_' . md5($file);
                        $urlInputId = 'brandkit_logo_url_' . md5($file);
                    ?>
                    <div class="brandkit-card brandkit-logo-preview brandkit-logo-card">
                        <h4>
                            <?php echo htmlescape($label); ?>
                            <?php if (!empty($logoInfoMap[$file])) : ?>
                                <span
                                    class="brandkit-info"
                                    title="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    data-info="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    aria-label="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    role="img"
                                    tabindex="0"
                                >i</span>
                            <?php endif; ?>
                        </h4>
                        <div class="brandkit-logo-actions">
                            <form method="post" enctype="multipart/form-data" action="logo_upload.php">
                                <input type="hidden" name="brandkit_action" value="upload_logo">
                                <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                                <input class="brandkit-logo-input" type="file" id="<?php echo $uploadId; ?>" name="brandkit_logo" accept="<?php echo $acceptTypes; ?>">
                                <label class="brandkit-logo-action brandkit-logo-upload" for="<?php echo $uploadId; ?>" aria-label="<?php echo __('Upload logo', 'brandkit'); ?>">&#x2B06;</label>
                            </form>
                            <?php if ($logoExists) : ?>
                                <form method="post" action="logo_upload.php">
                                    <input type="hidden" name="brandkit_action" value="clear_logo">
                                    <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                                    <button type="submit" class="brandkit-logo-action brandkit-logo-remove" aria-label="<?php echo __('Remove logo', 'brandkit'); ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="logo_upload.php" class="brandkit-logo-url-form">
                            <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                            <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                            <label for="<?php echo $urlInputId; ?>"><?php echo __('Image URL', 'brandkit'); ?></label>
                            <input
                                type="text"
                                inputmode="url"
                                id="<?php echo $urlInputId; ?>"
                                name="logo_url"
                                value="<?php echo htmlescape($configuredLogoUrl); ?>"
                                placeholder="https://example.com/image.png"
                            >
                            <div class="brandkit-logo-url-buttons">
                                <button type="submit" name="brandkit_action" value="save_logo_url"><?php echo __('Save URL', 'brandkit'); ?></button>
                                <?php if ($logoIsRemote) : ?>
                                    <button type="submit" name="brandkit_action" value="clear_logo_url"><?php echo __('Clear URL', 'brandkit'); ?></button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if ($logoExists || $logoIsRemote) : ?>
                            <div class="brandkit-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="brandkit-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; background: rgba(15, 23, 42, 0.12); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #0f172a;">
                                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="width: 14px; height: 14px; border-radius: 4px; background: #22c55e; display: inline-block;"></span>
                                                <?php echo __('Favicon active', 'brandkit'); ?>
                                            </span>
                                            <span style="opacity: 0.7;">● ● ●</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="brandkit-logo-login-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-brandkit-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="brandkit-logo-preview-value" data-brandkit-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="brandkit-logo-login-field"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="brandkit-logo-header-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-brandkit-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="brandkit-logo-preview-value" data-brandkit-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="brandkit-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="brandkit-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; border: 1px dashed rgba(148, 163, 184, 0.7); background: rgba(248, 250, 252, 0.9); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #64748b;">
                                            <span><?php echo __('No favicon yet', 'brandkit'); ?></span>
                                            <span style="opacity: 0.7;">○ ○ ○</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="brandkit-logo-login-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-brandkit-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="brandkit-logo-preview-value" data-brandkit-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="brandkit-logo-login-field"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="brandkit-logo-header-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-brandkit-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="brandkit-logo-preview-value" data-brandkit-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="brandkit-placeholder">
                                    <?php echo $isFavicon ? __('No custom favicon uploaded yet.', 'brandkit') : __('No custom logo uploaded yet.', 'brandkit'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <form method="post" action="<?php echo $baseUrl; ?>?step=1">
                <div class="brandkit-section">
                    <h4><?php echo __('Logo size (%)', 'brandkit'); ?></h4>
                    <div class="brandkit-form-grid">
                        <div>
                            <label for="brandkit_logo_header_scale"><?php echo __('Expanded menu logo size (%)', 'brandkit'); ?></label>
                            <div class="brandkit-range">
                                <input type="range" id="brandkit_logo_header_scale" name="logo_header_scale" min="40" max="200" step="5" value="<?php echo htmlescape($logoHeaderScale); ?>" oninput="document.getElementById('brandkit_logo_header_scale_value').textContent = this.value + '%'">
                                <span class="brandkit-range-value" id="brandkit_logo_header_scale_value"><?php echo htmlescape($logoHeaderScale); ?>%</span>
                            </div>
                        </div>
                        <div>
                            <label for="brandkit_logo_reduced_scale"><?php echo __('Collapsed menu logo size (%)', 'brandkit'); ?></label>
                            <div class="brandkit-range">
                                <input type="range" id="brandkit_logo_reduced_scale" name="logo_reduced_scale" min="40" max="200" step="5" value="<?php echo htmlescape($logoReducedScale); ?>" oninput="document.getElementById('brandkit_logo_reduced_scale_value').textContent = this.value + '%'">
                                <span class="brandkit-range-value" id="brandkit_logo_reduced_scale_value"><?php echo htmlescape($logoReducedScale); ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="brandkit-actions">
                        <button type="submit" class="brandkit-save-logo"><?php echo __('Save logo sizes', 'brandkit'); ?></button>
                    </div>
                </div>
                <input type="hidden" name="brandkit_action" value="save_logo_scales">
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
            </form>
            <div class="brandkit-section">
                <h4>
                    <?php echo htmlescape($faviconGroup['title']); ?>
                    <?php if (!empty($faviconGroup['info'])) : ?>
                        <span
                            class="brandkit-info"
                            title="<?php echo htmlescape($faviconGroup['info']); ?>"
                            data-info="<?php echo htmlescape($faviconGroup['info']); ?>"
                            aria-label="<?php echo htmlescape($faviconGroup['info']); ?>"
                            role="img"
                            tabindex="0"
                        >i</span>
                    <?php endif; ?>
                </h4>
                <div class="brandkit-grid">
                <?php foreach ($faviconGroup['files'] as $file) : ?>
                    <?php $label = $logoFiles[$file] ?? $file; ?>
                    <?php
                        $logoPath = $logosDir ? $logosDir . '/' . $file : null;
                        $logoExists = $logoPath && is_file($logoPath);
                        $configuredLogoUrl = $configuredLogoUrls[$file] ?? '';
                        $logoIsRemote = $configuredLogoUrl !== '';
                        $publicLogoPath = $publicLogosDir ? $publicLogosDir . '/' . $file : null;
                        if ($logoIsRemote) {
                            $logoUrl = $configuredLogoUrl;
                        } elseif ($publicLogoPath && is_file($publicLogoPath)) {
                            $logoUrl = rtrim($picsWebBase, '/') . '/logos/' . rawurlencode($file);
                        } else {
                            $logoUrl = $pluginWebBase . '/front/logo_preview.php?name=' . rawurlencode($file);
                        }
                        $activeLogoPath = $logoExists ? $logoPath : (($publicLogoPath && is_file($publicLogoPath)) ? $publicLogoPath : null);
                        $logoVersion = $activeLogoPath && is_file($activeLogoPath)
                            ? (string) filemtime($activeLogoPath)
                            : (string) ($_GET['refresh'] ?? time());
                        if (!$logoIsRemote) {
                            $logoUrl .= (strpos($logoUrl, '?') === false ? '?' : '&') . 'v=' . rawurlencode($logoVersion);
                        }
                        $isWhiteLogo = strpos($file, 'white') !== false;
                        $isDarkLogo = strpos($file, 'black') !== false;
                        $isLoginLogo = strpos($file, '250') !== false;
                        $isCompactLogo = strpos($file, 'logo-G-100') !== false;
                        $isFavicon = $file === 'favicon.ico';
                        $previewScale = $isCompactLogo ? 'reduced' : ($isLoginLogo || $isFavicon ? null : 'header');
                        $stageClass = '';
                        $uploadId = 'brandkit_logo_' . md5($file);
                        $urlInputId = 'brandkit_logo_url_' . md5($file);
                    ?>
                    <div class="brandkit-card brandkit-logo-preview brandkit-logo-card">
                        <h4><?php echo htmlescape($label); ?></h4>
                        <div class="brandkit-logo-actions">
                            <form method="post" enctype="multipart/form-data" action="logo_upload.php">
                                <input type="hidden" name="brandkit_action" value="upload_logo">
                                <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                                <input class="brandkit-logo-input" type="file" id="<?php echo $uploadId; ?>" name="brandkit_logo" accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon">
                                <label class="brandkit-logo-action brandkit-logo-upload" for="<?php echo $uploadId; ?>" aria-label="<?php echo __('Upload logo', 'brandkit'); ?>">&#x2B06;</label>
                            </form>
                            <?php if ($logoExists) : ?>
                                <form method="post" action="logo_upload.php">
                                    <input type="hidden" name="brandkit_action" value="clear_logo">
                                    <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                                    <button type="submit" class="brandkit-logo-action brandkit-logo-remove" aria-label="<?php echo __('Remove logo', 'brandkit'); ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="logo_upload.php" class="brandkit-logo-url-form">
                            <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                            <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                            <label for="<?php echo $urlInputId; ?>"><?php echo __('Image URL', 'brandkit'); ?></label>
                            <input
                                type="text"
                                inputmode="url"
                                id="<?php echo $urlInputId; ?>"
                                name="logo_url"
                                value="<?php echo htmlescape($configuredLogoUrl); ?>"
                                placeholder="https://example.com/image.ico"
                            >
                            <div class="brandkit-logo-url-buttons">
                                <button type="submit" name="brandkit_action" value="save_logo_url"><?php echo __('Save URL', 'brandkit'); ?></button>
                                <?php if ($logoIsRemote) : ?>
                                    <button type="submit" name="brandkit_action" value="clear_logo_url"><?php echo __('Clear URL', 'brandkit'); ?></button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if ($logoExists || $logoIsRemote) : ?>
                            <div class="brandkit-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="brandkit-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; background: rgba(15, 23, 42, 0.12); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #0f172a;">
                                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="width: 14px; height: 14px; border-radius: 4px; background: #22c55e; display: inline-block;"></span>
                                                <?php echo __('Favicon active', 'brandkit'); ?>
                                            </span>
                                            <span style="opacity: 0.7;">● ● ●</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="brandkit-logo-login-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="brandkit-logo-header-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="brandkit-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="brandkit-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; border: 1px dashed rgba(148, 163, 184, 0.7); background: rgba(248, 250, 252, 0.9); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #64748b;">
                                            <span><?php echo __('No favicon yet', 'brandkit'); ?></span>
                                            <span style="opacity: 0.7;">○ ○ ○</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="brandkit-logo-login-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                        <div class="brandkit-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="brandkit-logo-header-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="brandkit-placeholder">
                                    <?php echo $isFavicon ? __('No custom favicon uploaded yet.', 'brandkit') : __('No custom logo uploaded yet.', 'brandkit'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var inputs = document.querySelectorAll('.brandkit-logo-input');
                    inputs.forEach(function (input) {
                        input.addEventListener('change', function () {
                            if (input.files && input.files.length) {
                                input.form.submit();
                            }
                        });
                    });

                    var headerScale = document.getElementById('brandkit_logo_header_scale');
                    var reducedScale = document.getElementById('brandkit_logo_reduced_scale');
                    var saveButton = document.querySelector('.brandkit-save-logo');
                    var root = document.documentElement;
                    var previewValues = document.querySelectorAll('[data-brandkit-logo-value]');
                    var previewImages = document.querySelectorAll('[data-brandkit-logo-scale]');
                    var previewTimers = new Map();
                    function capScale(value) {
                        return Math.min(1.15, Math.max(0.6, value / 100));
                    }
                    function updatePreviewScales() {
                        var headerValue = headerScale ? parseInt(headerScale.value || '100', 10) : 100;
                        var reducedValue = reducedScale ? parseInt(reducedScale.value || '100', 10) : 100;
                        root.style.setProperty('--brandkit-logo-preview-header-scale', capScale(headerValue));
                        root.style.setProperty('--brandkit-logo-preview-reduced-scale', capScale(reducedValue));
                        previewValues.forEach(function (node) {
                            var type = node.getAttribute('data-brandkit-logo-value');
                            if (type === 'header') {
                                node.textContent = headerValue + '%';
                            } else if (type === 'reduced') {
                                node.textContent = reducedValue + '%';
                            }
                            var sample = node.closest('.logo-sample');
                            if (sample) {
                                sample.classList.add('brandkit-preview-active');
                                var existing = previewTimers.get(sample);
                                if (existing) {
                                    clearTimeout(existing);
                                }
                                previewTimers.set(sample, setTimeout(function () {
                                    sample.classList.remove('brandkit-preview-active');
                                }, 1200));
                            }
                        });
                        previewImages.forEach(function (img) {
                            img.classList.remove('brandkit-scale-pulse');
                            void img.offsetWidth;
                            img.classList.add('brandkit-scale-pulse');
                        });
                        if (saveButton) {
                            saveButton.classList.remove('brandkit-save-pulse');
                            void saveButton.offsetWidth;
                            saveButton.classList.add('brandkit-save-pulse');
                        }
                    }
                    updatePreviewScales();
                    if (headerScale) {
                        headerScale.addEventListener('input', updatePreviewScales);
                    }
                    if (reducedScale) {
                        reducedScale.addEventListener('input', updatePreviewScales);
                    }
                });
            </script>
        <?php elseif ($currentStep === 2) : ?>
            <h3><?php echo __('Login page configuration', 'brandkit'); ?></h3>
            <p><?php echo __('Pick a layout and visual treatments for the GLPI login page.', 'brandkit'); ?></p>
            <?php
                $loginWallpaper = $loginConfig['login_wallpaper'] ?? '';
                if ($loginWallpaper) {
                    $resolvedWallpaper = brandkit_resolve_wallpaper_filename($loginWallpaper);
                    if ($resolvedWallpaper !== '') {
                        $loginWallpaper = $resolvedWallpaper;
                    }
                }
                $pluginStorageDir = brandkit_get_plugin_storage_dir();
                $wallpaperSource = $loginConfig['login_wallpaper_source'] ?? '';
                $loginWallpaperRemoteUrl = brandkit_normalize_image_url($loginConfig['login_wallpaper_url'] ?? '');
                if ($loginWallpaperRemoteUrl !== '') {
                    $wallpaperSource = 'url';
                }
                if ($wallpaperSource === '') {
                    $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
                }
                if ($wallpaperSource === 'url' && $loginWallpaperRemoteUrl === '') {
                    $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
                }
                if (!in_array($wallpaperSource, ['preset', 'custom', 'url'], true)) {
                    $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
                }
                if ($wallpaperSource === 'custom' && $loginWallpaper && brandkit_get_wallpaper_path($loginWallpaper)) {
                    $wallpaperSource = 'custom';
                }
                $wallpaperPreset = $loginConfig['login_wallpaper_preset'] ?? $wallpaperPresetDefault;
                if (!in_array($wallpaperPreset, $wallpaperPresets, true)) {
                    $wallpaperPreset = $wallpaperPresetDefault;
                }
                $customWallpaperUrl = null;
                $customWallpaperWebpUrl = null;
                if ($loginWallpaper) {
                    $resolvedWallpaper = brandkit_resolve_wallpaper_filename($loginWallpaper);
                    if ($resolvedWallpaper !== '') {
                        $loginWallpaper = $resolvedWallpaper;
                    }
                    $customWallpaperPath = brandkit_get_wallpaper_path($loginWallpaper);
                    $customWallpaperVersion = ($customWallpaperPath && is_file($customWallpaperPath))
                        ? (int) filemtime($customWallpaperPath)
                        : time();
                    $customWallpaperUrl = brandkit_get_wallpaper_image_url($loginWallpaper, $customWallpaperVersion);
                    $customWebpPath = preg_replace('/\.[^.]+$/', '.webp', $customWallpaperPath);
                    if ($customWebpPath && is_file($customWebpPath)) {
                        $customWallpaperWebpUrl = brandkit_get_wallpaper_image_url(basename($customWebpPath), (int) filemtime($customWebpPath));
                    }
                }
                $presetWallpaperUrl = null;
                $presetWallpaperWebpUrl = null;
                if ($wallpaperPreset) {
                    $presetAssets = brandkit_get_wallpaper_preset_assets($wallpaperPreset);
                    $presetWallpaperUrl = $presetAssets['url'] ?? null;
                    $presetWallpaperWebpUrl = $presetAssets['webp_url'] ?? null;
                }
                if ($wallpaperSource === 'url') {
                    $loginWallpaperUrl = $loginWallpaperRemoteUrl;
                    $loginWallpaperWebpUrl = null;
                } else {
                    $loginWallpaperUrl = $wallpaperSource === 'custom' ? $customWallpaperUrl : $presetWallpaperUrl;
                    $loginWallpaperWebpUrl = $wallpaperSource === 'custom' ? $customWallpaperWebpUrl : $presetWallpaperWebpUrl;
                }
                $loginLogoScale = brandkit_clamp_int($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale'], 80, 480, 200);
                $loginLogoOffset = brandkit_clamp_int($loginConfig['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);
                $loginPanelWidth = brandkit_clamp_int(
                    $loginConfig['login_panel_width'] ?? $loginDefaults['login_panel_width'],
                    280,
                    720,
                    520
                );
                $loginPanelHeight = brandkit_clamp_int(
                    $loginConfig['login_panel_height'] ?? $loginDefaults['login_panel_height'],
                    360,
                    780,
                    560
                );
                $loginPanelHoverEffect = ($loginConfig['login_panel_hover_effect'] ?? $loginDefaults['login_panel_hover_effect']) === '1';
                $loginButtonStyle = $loginConfig['login_button_style'] ?? $loginDefaults['login_button_style'];
                if (!in_array($loginButtonStyle, brandkit_get_login_button_styles(), true)) {
                    $loginButtonStyle = $loginDefaults['login_button_style'];
                }
                $loginButtonShape = $loginConfig['login_button_shape'] ?? $loginDefaults['login_button_shape'];
                if (!in_array($loginButtonShape, brandkit_get_login_button_shapes(), true)) {
                    $loginButtonShape = $loginDefaults['login_button_shape'];
                }
                $loginButtonColorMode = $loginConfig['login_button_color_mode'] ?? $loginDefaults['login_button_color_mode'];
                if (!in_array($loginButtonColorMode, ['preset', 'custom'], true)) {
                    $loginButtonColorMode = $loginDefaults['login_button_color_mode'];
                }
                $loginButtonCustomStyle = $loginConfig['login_button_custom_style'] ?? $loginDefaults['login_button_custom_style'];
                if (!in_array($loginButtonCustomStyle, ['default', 'outline'], true)) {
                    $loginButtonCustomStyle = $loginDefaults['login_button_custom_style'];
                }
                $loginButtonHoverEffect = $loginConfig['login_button_hover_effect'] ?? $loginDefaults['login_button_hover_effect'];
                if (!in_array($loginButtonHoverEffect, ['press', 'fill'], true)) {
                    $loginButtonHoverEffect = $loginDefaults['login_button_hover_effect'];
                }
                $loginButtonHoverFillMode = $loginConfig['login_button_hover_fill_mode'] ?? $loginDefaults['login_button_hover_fill_mode'];
                if (!in_array($loginButtonHoverFillMode, ['button', 'custom'], true)) {
                    $loginButtonHoverFillMode = $loginDefaults['login_button_hover_fill_mode'];
                }
                $loginButtonTextSize = brandkit_clamp_int(
                    $loginConfig['login_button_text_size'] ?? $loginDefaults['login_button_text_size'],
                    10,
                    28,
                    14
                );
                $loginButtonColor = brandkit_normalize_hex_color(
                    $loginConfig['login_button_color'] ?? $loginDefaults['login_button_color'],
                    $loginDefaults['login_button_color']
                );
                $loginButtonHoverFillColor = brandkit_normalize_hex_color(
                    $loginConfig['login_button_hover_fill_color'] ?? $loginDefaults['login_button_hover_fill_color'],
                    $loginDefaults['login_button_hover_fill_color']
                );
                $loginButtonHoverTextColor = brandkit_normalize_hex_color(
                    $loginConfig['login_button_hover_text_color'] ?? $loginDefaults['login_button_hover_text_color'],
                    $loginDefaults['login_button_hover_text_color']
                );
                $loginLogoPosition = $loginConfig['login_logo_position'] ?? $loginDefaults['login_logo_position'];
                if (!in_array($loginLogoPosition, ['outside', 'inside'], true)) {
                    $loginLogoPosition = $loginDefaults['login_logo_position'];
                }
                $loginWallpaperSize = $loginConfig['login_wallpaper_size'] ?? $loginDefaults['login_wallpaper_size'];
                $loginWallpaperPosition = $loginConfig['login_wallpaper_position'] ?? $loginDefaults['login_wallpaper_position'];
                $loginWallpaperAttachment = $loginConfig['login_wallpaper_attachment'] ?? $loginDefaults['login_wallpaper_attachment'];
                $loginWallpaperOverlayColor = brandkit_normalize_hex_color(
                    $loginConfig['login_wallpaper_overlay_color'] ?? $loginDefaults['login_wallpaper_overlay_color'],
                    $loginDefaults['login_wallpaper_overlay_color']
                );
                $loginWallpaperOverlayOpacity = brandkit_clamp_int(
                    $loginConfig['login_wallpaper_overlay_opacity'] ?? $loginDefaults['login_wallpaper_overlay_opacity'],
                    0,
                    100,
                    35
                );
                $loginTextColor = brandkit_normalize_hex_color(
                    $loginConfig['login_text_color'] ?? $loginDefaults['login_text_color'],
                    $loginDefaults['login_text_color']
                );
                $loginFieldStyle = $loginConfig['login_field_style'] ?? $loginDefaults['login_field_style'];
                if (!in_array($loginFieldStyle, ['solid', 'glass', 'soft', 'outline', 'underline', 'neon'], true)) {
                    $loginFieldStyle = $loginDefaults['login_field_style'];
                }
                $loginFieldBg = brandkit_normalize_hex_color(
                    $loginConfig['login_field_bg'] ?? $loginDefaults['login_field_bg'],
                    $loginDefaults['login_field_bg']
                );
                $loginFieldBorder = brandkit_normalize_hex_color(
                    $loginConfig['login_field_border'] ?? $loginDefaults['login_field_border'],
                    $loginDefaults['login_field_border']
                );
                $loginFieldBorderWidth = brandkit_clamp_int(
                    $loginConfig['login_field_border_width'] ?? $loginDefaults['login_field_border_width'],
                    0,
                    6,
                    1
                );
                $loginFieldTextSize = brandkit_clamp_int(
                    $loginConfig['login_field_text_size'] ?? $loginDefaults['login_field_text_size'],
                    10,
                    36,
                    14
                );
                $loginFieldHeight = brandkit_clamp_int(
                    $loginConfig['login_field_height'] ?? $loginDefaults['login_field_height'],
                    32,
                    72,
                    44
                );
                $loginFieldWidth = brandkit_clamp_int(
                    $loginConfig['login_field_width'] ?? $loginDefaults['login_field_width'],
                    260,
                    720,
                    520
                );
                $loginFieldSpacing = null;
                $loginTitleSize = null;
                if (!$isGlpi11) {
                    $loginFieldSpacing = brandkit_clamp_int(
                        $loginConfig['login_field_spacing'] ?? $loginDefaults['login_field_spacing'],
                        8,
                        48,
                        16
                    );
                    $loginTitleSize = brandkit_clamp_int(
                        $loginConfig['login_title_size'] ?? $loginDefaults['login_title_size'],
                        16,
                        48,
                        24
                    );
                }
                $loginTextSize = brandkit_clamp_int(
                    $loginConfig['login_text_size'] ?? $loginDefaults['login_text_size'],
                    10,
                    30,
                    15
                );
                $loginFieldText = brandkit_normalize_hex_color(
                    $loginConfig['login_field_text'] ?? $loginDefaults['login_field_text'],
                    $loginDefaults['login_field_text']
                );
                $loginFieldFocus = brandkit_normalize_hex_color(
                    $loginConfig['login_field_focus'] ?? $loginDefaults['login_field_focus'],
                    $loginDefaults['login_field_focus']
                );
                $loginAccentColor = brandkit_normalize_hex_color(
                    $loginConfig['login_accent_color'] ?? $loginDefaults['login_accent_color'],
                    $loginDefaults['login_accent_color']
                );
                $loginIconUser = $loginConfig['login_icon_user'] ?? $loginDefaults['login_icon_user'];
                $loginIconUserOptions = ['none', 'user', 'id', 'mail'];
                if (!in_array($loginIconUser, $loginIconUserOptions, true)) {
                    $loginIconUser = $loginDefaults['login_icon_user'];
                }
                $loginIconPassword = $loginConfig['login_icon_password'] ?? $loginDefaults['login_icon_password'];
                $loginIconPasswordOptions = ['none', 'lock', 'key', 'shield'];
                if (!in_array($loginIconPassword, $loginIconPasswordOptions, true)) {
                    $loginIconPassword = $loginDefaults['login_icon_password'];
                }
                $loginIconColor = brandkit_normalize_hex_color(
                    $loginConfig['login_icon_color'] ?? $loginDefaults['login_icon_color'],
                    $loginDefaults['login_icon_color']
                );
                $loginIconPosition = $loginConfig['login_icon_position'] ?? $loginDefaults['login_icon_position'];
                if (!in_array($loginIconPosition, ['left', 'right'], true)) {
                    $loginIconPosition = $loginDefaults['login_icon_position'];
                }
                $loginFieldRadius = brandkit_clamp_int(
                    $loginConfig['login_field_radius'] ?? $loginDefaults['login_field_radius'],
                    0,
                    32,
                    12
                );
                $loginHideLoginText = ($loginConfig['login_hide_login_text'] ?? $loginDefaults['login_hide_login_text']) === '1';
                $loginHideCopyright = ($loginConfig['login_hide_copyright'] ?? $loginDefaults['login_hide_copyright']) === '1';
                $buttonStyleLabels = [
                    'primary' => __('Primary (default)', 'brandkit'),
                    'secondary' => __('Secondary', 'brandkit'),
                    'success' => __('Success', 'brandkit'),
                    'danger' => __('Danger', 'brandkit'),
                    'warning' => __('Warning', 'brandkit'),
                    'info' => __('Info', 'brandkit'),
                    'dark' => __('Dark', 'brandkit'),
                    'light' => __('Light', 'brandkit'),
                    'outline-primary' => __('Outline primary', 'brandkit'),
                    'outline-secondary' => __('Outline secondary', 'brandkit'),
                    'outline-success' => __('Outline success', 'brandkit'),
                    'outline-danger' => __('Outline danger', 'brandkit'),
                    'outline-warning' => __('Outline warning', 'brandkit'),
                    'outline-info' => __('Outline info', 'brandkit'),
                    'outline-dark' => __('Outline dark', 'brandkit'),
                ];
                $buttonShapeLabels = [
                    'square' => __('Square', 'brandkit'),
                    'rounded' => __('Rounded', 'brandkit'),
                    'pill' => __('Pill', 'brandkit'),
                    'cut' => __('Cut corner', 'brandkit'),
                    'soft' => __('Soft depth', 'brandkit'),
                ];
                $buttonShapeDescriptions = [
                    'square' => __('Sharp edges with a confident shadow.', 'brandkit'),
                    'rounded' => __('Balanced corners for modern brands.', 'brandkit'),
                    'pill' => __('Friendly, fully rounded shape.', 'brandkit'),
                    'cut' => __('Dynamic cut for a bolder call-to-action.', 'brandkit'),
                    'soft' => __('Cushioned look with soft depth.', 'brandkit'),
                ];
                $fieldStyleLabels = [
                    'solid' => __('Solid', 'brandkit'),
                    'glass' => __('Glass blur', 'brandkit'),
                    'soft' => __('Soft shadow', 'brandkit'),
                    'outline' => __('Outline', 'brandkit'),
                    'underline' => __('Underline', 'brandkit'),
                    'neon' => __('Neon glow', 'brandkit'),
                ];
                $buttonColorModeLabels = [
                    'preset' => __('Preset palette', 'brandkit'),
                    'custom' => __('Custom hex color', 'brandkit'),
                ];
                $buttonCustomStyleLabels = [
                    'default' => __('Default fill', 'brandkit'),
                    'outline' => __('Outline only', 'brandkit'),
                ];
                $buttonHoverEffectLabels = [
                    'press' => __('Press on hover', 'brandkit'),
                    'fill' => __('Fill with color', 'brandkit'),
                ];
                $buttonHoverFillModeLabels = [
                    'button' => __('Use button color', 'brandkit'),
                    'custom' => __('Pick a custom color', 'brandkit'),
                ];
                $iconUserLabels = [
                    'none' => __('None', 'brandkit'),
                    'user' => __('User', 'brandkit'),
                    'id' => __('ID card', 'brandkit'),
                    'mail' => __('Email', 'brandkit'),
                ];
                $iconPasswordLabels = [
                    'none' => __('None', 'brandkit'),
                    'lock' => __('Lock', 'brandkit'),
                    'key' => __('Key', 'brandkit'),
                    'shield' => __('Shield', 'brandkit'),
                ];
                $iconPositionLabels = [
                    'right' => __('Right', 'brandkit'),
                    'left' => __('Left', 'brandkit'),
                ];
                $iconSvgMap = [
                    'user' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c2-4 6-6 8-6s6 2 8 6"/></svg>',
                    'id' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4"/><circle cx="10" cy="10" r="2.5"/></svg>',
                    'mail' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
                    'lock' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
                    'key' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="14.5" r="3.5"/><path d="M11 14h10l-2 2 2 2-2 2"/></svg>',
                    'shield' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6z"/><path d="M9.5 12.5 11 14l3.5-3.5"/></svg>',
                ];
                $wallpaperSizeLabels = [
                    'cover' => __('Cover', 'brandkit'),
                    'contain' => __('Contain', 'brandkit'),
                    'stretch' => __('Stretch', 'brandkit'),
                    'original' => __('Original size', 'brandkit'),
                ];
                $wallpaperPositionLabels = [
                    'center' => __('Center', 'brandkit'),
                    'top' => __('Top', 'brandkit'),
                    'bottom' => __('Bottom', 'brandkit'),
                    'left' => __('Left', 'brandkit'),
                    'right' => __('Right', 'brandkit'),
                ];
                $wallpaperAttachmentLabels = [
                    'fixed' => __('Fixed', 'brandkit'),
                    'scroll' => __('Scroll', 'brandkit'),
                ];
            ?>
            <?php
                $layoutLabels = [
                    'split-left' => __('Split left', 'brandkit'),
                    'classic' => __('Classic centered', 'brandkit'),
                    'split-right' => __('Split right', 'brandkit'),
                ];
                $selectedLayout = $loginConfig['login_layout'] ?? $loginDefaults['login_layout'];
                if (!in_array($selectedLayout, ['split-left', 'split-right', 'classic'], true)) {
                    $selectedLayout = $loginDefaults['login_layout'];
                }
            ?>
            <form id="brandkit-login-settings-form" method="post" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>?step=2">
                <div class="brandkit-step2-grid">
                    <div class="brandkit-step2-row">
                        <div class="brandkit-section">
                            <h4><?php echo __('Layout & logo', 'brandkit'); ?></h4>
                            <div class="brandkit-form-grid">
                                <div>
                                    <label for="brandkit_login_layout"><?php echo __('Layout', 'brandkit'); ?></label>
                                    <select id="brandkit_login_layout" name="login_layout">
                                        <?php foreach ($layoutLabels as $layoutValue => $layoutLabel) : ?>
                                            <option value="<?php echo htmlescape($layoutValue); ?>" <?php echo $selectedLayout === $layoutValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($layoutLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_login_transparent"><?php echo __('Transparent panel', 'brandkit'); ?></label>
                                    <select id="brandkit_login_transparent" name="login_panel_transparent">
                                        <option value="0" <?php echo $loginConfig['login_panel_transparent'] === '0' ? 'selected' : ''; ?>><?php echo __('No (show panel)', 'brandkit'); ?></option>
                                        <option value="1" <?php echo $loginConfig['login_panel_transparent'] === '1' ? 'selected' : ''; ?>><?php echo __('Yes (hide panel)', 'brandkit'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_login_panel_hover"><?php echo __('Panel hover effect', 'brandkit'); ?></label>
                                    <select id="brandkit_login_panel_hover" name="login_panel_hover_effect">
                                        <option value="1" <?php echo $loginPanelHoverEffect ? 'selected' : ''; ?>><?php echo __('Yes (enable)', 'brandkit'); ?></option>
                                        <option value="0" <?php echo !$loginPanelHoverEffect ? 'selected' : ''; ?>><?php echo __('No (disable)', 'brandkit'); ?></option>
                                    </select>
                                </div>
                                <div class="brandkit-panel-size">
                                    <label for="brandkit_login_panel_width"><?php echo __('Panel width (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_panel_width" name="login_panel_width" min="280" max="720" step="10" value="<?php echo htmlescape($loginPanelWidth); ?>" oninput="document.getElementById('brandkit_login_panel_width_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_panel_width_value"><?php echo htmlescape($loginPanelWidth); ?>px</span>
                                    </div>
                                </div>

                                <div class="brandkit-panel-size">
                                    <label for="brandkit_login_panel_height"><?php echo __('Panel height (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_panel_height" name="login_panel_height" min="360" max="780" step="10" value="<?php echo htmlescape($loginPanelHeight); ?>" oninput="document.getElementById('brandkit_login_panel_height_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_panel_height_value"><?php echo htmlescape($loginPanelHeight); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_logo_scale"><?php echo __('Login logo size (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_logo_scale" name="login_logo_scale" min="80" max="480" step="10" value="<?php echo htmlescape($loginLogoScale); ?>" oninput="document.getElementById('brandkit_login_logo_scale_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_logo_scale_value"><?php echo htmlescape($loginLogoScale); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_logo_position"><?php echo __('Logo position', 'brandkit'); ?></label>
                                    <select id="brandkit_login_logo_position" name="login_logo_position">
                                        <option value="outside" <?php echo $loginLogoPosition === 'outside' ? 'selected' : ''; ?>><?php echo __('Above panel', 'brandkit'); ?></option>
                                        <option value="inside" <?php echo $loginLogoPosition === 'inside' ? 'selected' : ''; ?>><?php echo __('Inside panel', 'brandkit'); ?></option>
                                    </select>
                                </div>
                                <div class="brandkit-logo-offset-field">
                                    <label for="brandkit_login_logo_offset"><?php echo __('Logo offset from top (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_logo_offset" name="login_logo_offset" min="0" max="240" step="5" value="<?php echo htmlescape($loginLogoOffset); ?>" oninput="document.getElementById('brandkit_login_logo_offset_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_logo_offset_value"><?php echo htmlescape($loginLogoOffset); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_accent_color"><?php echo __('Accent color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_accent_color" name="login_accent_color" value="<?php echo htmlescape($loginAccentColor); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_text_color"><?php echo __('Text color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_text_color" name="login_text_color" value="<?php echo htmlescape($loginTextColor); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_hide_login_text"><?php echo __('Hide login text', 'brandkit'); ?></label>
                                    <select id="brandkit_hide_login_text" name="login_hide_login_text">
                                        <option value="0" <?php echo !$loginHideLoginText ? 'selected' : ''; ?>><?php echo __('No', 'brandkit'); ?></option>
                                        <option value="1" <?php echo $loginHideLoginText ? 'selected' : ''; ?>><?php echo __('Yes', 'brandkit'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_hide_copyright"><?php echo __('Hide copyright', 'brandkit'); ?></label>
                                    <select id="brandkit_hide_copyright" name="login_hide_copyright">
                                        <option value="0" <?php echo !$loginHideCopyright ? 'selected' : ''; ?>><?php echo __('No', 'brandkit'); ?></option>
                                        <option value="1" <?php echo $loginHideCopyright ? 'selected' : ''; ?>><?php echo __('Yes', 'brandkit'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="brandkit-step2-preview">
                            <div class="brandkit-preview-shell" data-brandkit-preview="layout" data-layout="<?php echo htmlescape($selectedLayout); ?>">
                                <div class="brandkit-preview-split"></div>
                                <div class="brandkit-preview-panel">
                                    <div class="brandkit-preview-logo" data-brandkit-preview-logo>Logo</div>
                                    <div class="brandkit-preview-login">
                                        <div class="brandkit-preview-title" data-brandkit-preview-title><?php echo __('Sign in to your account', 'brandkit'); ?></div>
                                        <div>
                                            <div class="brandkit-preview-field-label" data-brandkit-preview-text><?php echo __('Login', 'brandkit'); ?></div>
                                            <div class="brandkit-preview-field"></div>
                                        </div>
                                        <div>
                                            <div class="brandkit-preview-field-label" data-brandkit-preview-text><?php echo __('Password', 'brandkit'); ?></div>
                                            <div class="brandkit-preview-field"></div>
                                        </div>
                                        <button type="button" class="brandkit-preview-button" data-brandkit-preview-button><?php echo __('Sign in', 'brandkit'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row" id="brandkit_panel_split_section">
                        <div class="brandkit-section">
                            <h4><?php echo __('Panel & split styling', 'brandkit'); ?></h4>
                            <div class="brandkit-form-grid">
                                <div class="brandkit-panel-style-controls">
                                    <label for="brandkit_login_opacity"><?php echo __('Panel opacity (%)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                    <input type="range" id="brandkit_login_opacity" name="login_panel_opacity" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_panel_opacity']); ?>" oninput="document.getElementById('brandkit_login_opacity_value').textContent = this.value + '%'">
                                        <span class="brandkit-range-value" id="brandkit_login_opacity_value"><?php echo htmlescape($loginConfig['login_panel_opacity']); ?>%</span>
                                    </div>
                                </div>
                                <div class="brandkit-panel-style-controls">
                                    <label for="brandkit_login_color"><?php echo __('Panel color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_color" name="login_panel_color" value="<?php echo htmlescape($loginConfig['login_panel_color']); ?>">
                                </div>
                                <div class="brandkit-panel-style-controls">
                                    <label for="brandkit_login_blur"><?php echo __('Panel blur (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_blur" name="login_panel_blur" min="0" max="24" value="<?php echo htmlescape($loginConfig['login_panel_blur']); ?>" oninput="document.getElementById('brandkit_login_blur_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_blur_value"><?php echo htmlescape($loginConfig['login_panel_blur']); ?>px</span>
                                    </div>
                                </div>
                            </div>
                            <div class="brandkit-form-grid brandkit-split-controls" id="brandkit_split_controls">
                                <div>
                                    <label for="brandkit_split_width"><?php echo __('Split width (%)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_split_width" name="login_split_width" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_split_width']); ?>" oninput="document.getElementById('brandkit_split_width_value').textContent = this.value + '%'">
                                        <span class="brandkit-range-value" id="brandkit_split_width_value"><?php echo htmlescape($loginConfig['login_split_width']); ?>%</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_split_opacity"><?php echo __('Split opacity (%)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                    <input type="range" id="brandkit_split_opacity" name="login_split_opacity" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_split_opacity']); ?>" oninput="document.getElementById('brandkit_split_opacity_value').textContent = this.value + '%'">
                                        <span class="brandkit-range-value" id="brandkit_split_opacity_value"><?php echo htmlescape($loginConfig['login_split_opacity']); ?>%</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_split_color"><?php echo __('Split color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_split_color" name="login_split_color" value="<?php echo htmlescape($loginConfig['login_split_color']); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_split_blur"><?php echo __('Split blur (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                    <input type="range" id="brandkit_split_blur" name="login_split_blur" min="0" max="24" value="<?php echo htmlescape($loginConfig['login_split_blur']); ?>" oninput="document.getElementById('brandkit_split_blur_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_split_blur_value"><?php echo htmlescape($loginConfig['login_split_blur']); ?>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="brandkit-step2-preview" id="brandkit_panel_split_preview">
                            <div class="brandkit-preview-shell" data-brandkit-preview="panel" data-layout="<?php echo htmlescape($selectedLayout); ?>">
                                <div class="brandkit-preview-split"></div>
                                <div class="brandkit-preview-panel">
                                    <div class="brandkit-preview-login">
                                        <div>
                                            <div class="brandkit-preview-field-label" data-brandkit-preview-text><?php echo __('Login', 'brandkit'); ?></div>
                                            <div class="brandkit-preview-field"></div>
                                        </div>
                                        <div>
                                            <div class="brandkit-preview-field-label" data-brandkit-preview-text><?php echo __('Password', 'brandkit'); ?></div>
                                            <div class="brandkit-preview-field"></div>
                                        </div>
                                        <button type="button" class="brandkit-preview-button" data-brandkit-preview-button><?php echo __('Sign in', 'brandkit'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row">
                        <div class="brandkit-section">
                            <h4><?php echo __('Button', 'brandkit'); ?></h4>
                            <div class="brandkit-form-grid brandkit-form-grid--button">
                                <div>
                                    <label for="brandkit_login_button_shape"><?php echo __('Button shape', 'brandkit'); ?></label>
                                    <select id="brandkit_login_button_shape" name="login_button_shape">
                                        <?php foreach (brandkit_get_login_button_shapes() as $shape) : ?>
                                            <option value="<?php echo htmlescape($shape); ?>" <?php echo $loginButtonShape === $shape ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($buttonShapeLabels[$shape] ?? $shape); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" id="brandkit_login_button_style" name="login_button_style" value="<?php echo htmlescape($loginButtonStyle); ?>">
                                <div>
                                    <label for="brandkit_login_button_custom_style"><?php echo __('Button fill style', 'brandkit'); ?></label>
                                    <select id="brandkit_login_button_custom_style" name="login_button_custom_style">
                                        <?php foreach ($buttonCustomStyleLabels as $styleValue => $styleLabel) : ?>
                                            <option value="<?php echo htmlescape($styleValue); ?>" <?php echo $loginButtonCustomStyle === $styleValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($styleLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_login_button_text_size"><?php echo __('Button text size (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_button_text_size" name="login_button_text_size" min="10" max="28" step="1" value="<?php echo htmlescape($loginButtonTextSize); ?>" oninput="document.getElementById('brandkit_login_button_text_size_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_button_text_size_value"><?php echo htmlescape($loginButtonTextSize); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_button_color_mode"><?php echo __('Color mode', 'brandkit'); ?></label>
                                    <select id="brandkit_login_button_color_mode" name="login_button_color_mode">
                                        <?php foreach ($buttonColorModeLabels as $colorModeValue => $colorModeLabel) : ?>
                                            <option value="<?php echo htmlescape($colorModeValue); ?>" <?php echo $loginButtonColorMode === $colorModeValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($colorModeLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="brandkit_button_color_custom" class="brandkit-color-custom">
                                    <div class="brandkit-color-inputs">
                                        <div class="brandkit-inline-field">
                                            <label for="brandkit_login_button_color_picker"><?php echo __('Custom color', 'brandkit'); ?></label>
                                            <input type="color" id="brandkit_login_button_color_picker" name="login_button_color" value="<?php echo htmlescape($loginButtonColor); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_button_hover_effect"><?php echo __('Hover effect', 'brandkit'); ?></label>
                                    <select id="brandkit_login_button_hover_effect" name="login_button_hover_effect">
                                        <?php foreach ($buttonHoverEffectLabels as $effectValue => $effectLabel) : ?>
                                            <option value="<?php echo htmlescape($effectValue); ?>" <?php echo $loginButtonHoverEffect === $effectValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($effectLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="brandkit_button_hover_options" class="brandkit-color-custom">
                                    <input type="hidden" id="brandkit_login_button_hover_fill_mode" name="login_button_hover_fill_mode" value="custom">
                                    <div id="brandkit_button_hover_fill_custom" class="brandkit-color-inputs">
                                        <div class="brandkit-inline-field">
                                            <label for="brandkit_login_button_hover_fill_color"><?php echo __('Hover fill custom color', 'brandkit'); ?></label>
                                            <input type="color" id="brandkit_login_button_hover_fill_color" name="login_button_hover_fill_color" value="<?php echo htmlescape($loginButtonHoverFillColor); ?>">
                                        </div>
                                        <div class="brandkit-inline-field">
                                            <label for="brandkit_login_button_hover_text_color"><?php echo __('Hover text color', 'brandkit'); ?></label>
                                            <input type="color" id="brandkit_login_button_hover_text_color" name="login_button_hover_text_color" value="<?php echo htmlescape($loginButtonHoverTextColor); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="brandkit-step2-preview">
                            <div class="brandkit-button-stage" data-brandkit-preview="button">
                                <div class="brandkit-button-panel">
                                    <button type="button" class="brandkit-button-demo brandkit-button-demo--rounded" data-brandkit-preview-button>
                                        <?php echo __('Sign in', 'brandkit'); ?>
                                    </button>
                                    <div class="brandkit-button-panel-line"></div>
                                    <div class="brandkit-button-panel-line" style="width: 70%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row">
                        <div class="brandkit-section">
                            <h4><?php echo __('Fields', 'brandkit'); ?></h4>
                            <div class="brandkit-form-grid">
                                <div>
                                    <label for="brandkit_login_field_style"><?php echo __('Field style', 'brandkit'); ?></label>
                                    <select id="brandkit_login_field_style" name="login_field_style">
                                        <?php foreach ($fieldStyleLabels as $styleValue => $styleLabel) : ?>
                                            <option value="<?php echo htmlescape($styleValue); ?>" <?php echo $loginFieldStyle === $styleValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($styleLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_login_field_bg"><?php echo __('Field background', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_field_bg" name="login_field_bg" value="<?php echo htmlescape($loginFieldBg); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_field_border"><?php echo __('Field border', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_field_border" name="login_field_border" value="<?php echo htmlescape($loginFieldBorder); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_field_border_width"><?php echo __('Field border width (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_border_width" name="login_field_border_width" min="0" max="6" step="1" value="<?php echo htmlescape($loginFieldBorderWidth); ?>" oninput="document.getElementById('brandkit_login_field_border_width_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_border_width_value"><?php echo htmlescape($loginFieldBorderWidth); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_field_text"><?php echo __('Field text', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_field_text" name="login_field_text" value="<?php echo htmlescape($loginFieldText); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_text_size"><?php echo __('Page text size (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_text_size" name="login_text_size" min="10" max="30" step="1" value="<?php echo htmlescape($loginTextSize); ?>" oninput="document.getElementById('brandkit_login_text_size_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_text_size_value"><?php echo htmlescape($loginTextSize); ?>px</span>
                                    </div>
                                </div>
                                <?php if (!$isGlpi11) : ?>
                                <div>
                                    <label for="brandkit_login_title_size"><?php echo __('Title text size (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_title_size" name="login_title_size" min="16" max="48" step="1" value="<?php echo htmlescape($loginTitleSize); ?>" oninput="document.getElementById('brandkit_login_title_size_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_title_size_value"><?php echo htmlescape($loginTitleSize); ?>px</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <label for="brandkit_login_field_text_size"><?php echo __('Field text size (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_text_size" name="login_field_text_size" min="10" max="36" step="1" value="<?php echo htmlescape($loginFieldTextSize); ?>" oninput="document.getElementById('brandkit_login_field_text_size_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_text_size_value"><?php echo htmlescape($loginFieldTextSize); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_field_height"><?php echo __('Field height (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_height" name="login_field_height" min="32" max="72" step="1" value="<?php echo htmlescape($loginFieldHeight); ?>" oninput="document.getElementById('brandkit_login_field_height_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_height_value"><?php echo htmlescape($loginFieldHeight); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_field_width"><?php echo __('Field width (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_width" name="login_field_width" min="260" max="720" step="10" value="<?php echo htmlescape($loginFieldWidth); ?>" oninput="document.getElementById('brandkit_login_field_width_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_width_value"><?php echo htmlescape($loginFieldWidth); ?>px</span>
                                    </div>
                                </div>
                                <?php if (!$isGlpi11) : ?>
                                <div>
                                    <label for="brandkit_login_field_spacing"><?php echo __('Field spacing (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_spacing" name="login_field_spacing" min="8" max="48" step="1" value="<?php echo htmlescape($loginFieldSpacing); ?>" oninput="document.getElementById('brandkit_login_field_spacing_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_spacing_value"><?php echo htmlescape($loginFieldSpacing); ?>px</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="brandkit-field-focus">
                                    <label for="brandkit_login_field_focus"><?php echo __('Field focus glow', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_field_focus" name="login_field_focus" value="<?php echo htmlescape($loginFieldFocus); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_field_radius"><?php echo __('Field radius (px)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                        <input type="range" id="brandkit_login_field_radius" name="login_field_radius" min="0" max="32" step="1" value="<?php echo htmlescape($loginFieldRadius); ?>" oninput="document.getElementById('brandkit_login_field_radius_value').textContent = this.value + 'px'">
                                        <span class="brandkit-range-value" id="brandkit_login_field_radius_value"><?php echo htmlescape($loginFieldRadius); ?>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="brandkit-step2-preview">
                            <div class="brandkit-preview-shell brandkit-preview-fields" data-brandkit-preview="fields">
                                <div class="brandkit-preview-field">
                                    <span class="brandkit-preview-field-text"><?php echo __('Login', 'brandkit'); ?></span>
                                </div>
                                <div class="brandkit-preview-field">
                                    <span class="brandkit-preview-field-text"><?php echo __('Password', 'brandkit'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row">
                        <div class="brandkit-section">
                            <h4><?php echo __('Field icons', 'brandkit'); ?></h4>
                            <?php $iconGridBg = brandkit_is_light_color($loginIconColor) ? '#0f172a' : '#ffffff'; ?>
                            <div class="brandkit-form-grid brandkit-icon-grid-scope" style="--brandkit-icon-color: <?php echo htmlescape($loginIconColor); ?>; --brandkit-icon-bg: <?php echo htmlescape($iconGridBg); ?>;">
                                <div>
                                    <label><?php echo __('Login icon', 'brandkit'); ?></label>
                                    <div class="brandkit-icon-grid">
                                        <?php foreach ($iconUserLabels as $value => $label) : ?>
                                            <label class="brandkit-icon-option">
                                                <input type="radio" name="login_icon_user" value="<?php echo htmlescape($value); ?>" <?php echo $loginIconUser === $value ? 'checked' : ''; ?>>
                                                <div class="brandkit-icon-card">
                                                    <?php if ($value === 'none') : ?>
                                                        <div class="brandkit-icon-placeholder"></div>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php else : ?>
                                                        <?php echo $iconSvgMap[$value] ?? ''; ?>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label><?php echo __('Password icon', 'brandkit'); ?></label>
                                    <div class="brandkit-icon-grid">
                                        <?php foreach ($iconPasswordLabels as $value => $label) : ?>
                                            <label class="brandkit-icon-option">
                                                <input type="radio" name="login_icon_password" value="<?php echo htmlescape($value); ?>" <?php echo $loginIconPassword === $value ? 'checked' : ''; ?>>
                                                <div class="brandkit-icon-card">
                                                    <?php if ($value === 'none') : ?>
                                                        <div class="brandkit-icon-placeholder"></div>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php else : ?>
                                                        <?php echo $iconSvgMap[$value] ?? ''; ?>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label for="brandkit_login_icon_color"><?php echo __('Icon color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_login_icon_color" name="login_icon_color" value="<?php echo htmlescape($loginIconColor); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_login_icon_position"><?php echo __('Icon position', 'brandkit'); ?></label>
                                    <select id="brandkit_login_icon_position" name="login_icon_position">
                                        <?php foreach ($iconPositionLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginIconPosition === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="brandkit-step2-preview">
                            <div class="brandkit-preview-shell brandkit-preview-icons<?php echo $loginIconPosition === 'right' ? ' icon-right' : ''; ?>" data-brandkit-preview="icons" data-login-icon="<?php echo htmlescape($loginIconUser); ?>" data-password-icon="<?php echo htmlescape($loginIconPassword); ?>">
                                <div class="brandkit-preview-icon-field">
                                    <div class="brandkit-preview-field brandkit-preview-field--icon" data-field="login">
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="user"><?php echo $iconSvgMap['user'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="id"><?php echo $iconSvgMap['id'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="mail"><?php echo $iconSvgMap['mail'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-text"><?php echo __('Login', 'brandkit'); ?></span>
                                    </div>
                                </div>
                                <div class="brandkit-preview-icon-field">
                                    <div class="brandkit-preview-field brandkit-preview-field--icon" data-field="password">
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="lock"><?php echo $iconSvgMap['lock'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="key"><?php echo $iconSvgMap['key'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-icon" data-brandkit-icon="shield"><?php echo $iconSvgMap['shield'] ?? ''; ?></span>
                                        <span class="brandkit-preview-field-text"><?php echo __('Password', 'brandkit'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row">
                        <div class="brandkit-section">
                            <h4>
                                <?php echo __('Wallpaper', 'brandkit'); ?>
                                <span
                                    class="brandkit-info"
                                    title="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'brandkit')); ?>"
                                    data-info="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'brandkit')); ?>"
                                    aria-label="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'brandkit')); ?>"
                                    role="img"
                                    tabindex="0"
                                >i</span>
                            </h4>
                            <div class="brandkit-form-grid" style="margin-top: 12px;">
                                <div class="brandkit-grid-full">
                                    <label for="brandkit_wallpaper_source"><?php echo __('Wallpaper source', 'brandkit'); ?></label>
                                    <select id="brandkit_wallpaper_source" name="login_wallpaper_source">
                                        <option value="preset" <?php echo $wallpaperSource === 'preset' ? 'selected' : ''; ?>><?php echo __('Preset wallpapers', 'brandkit'); ?></option>
                                        <option value="custom" <?php echo $wallpaperSource === 'custom' ? 'selected' : ''; ?>><?php echo __('Custom upload', 'brandkit'); ?></option>
                                        <option value="url" <?php echo $wallpaperSource === 'url' ? 'selected' : ''; ?>><?php echo __('Image URL', 'brandkit'); ?></option>
                                    </select>
                                </div>
                                <div id="brandkit_wallpaper_url_wrap" class="brandkit-grid-full">
                                    <label for="brandkit_wallpaper_url"><?php echo __('Wallpaper image URL', 'brandkit'); ?></label>
                                    <input
                                        type="text"
                                        inputmode="url"
                                        id="brandkit_wallpaper_url"
                                        name="login_wallpaper_url"
                                        value="<?php echo htmlescape($loginWallpaperRemoteUrl); ?>"
                                        placeholder="https://example.com/wallpaper.jpg"
                                    >
                                </div>
                                <div id="brandkit_wallpaper_preset_wrap" class="brandkit-grid-full">
                                    <label for="brandkit_wallpaper_preset"><?php echo __('Preset wallpaper', 'brandkit'); ?></label>
                                    <select id="brandkit_wallpaper_preset" name="login_wallpaper_preset">
                                        <?php if (empty($wallpaperPresets)) : ?>
                                            <option value="" selected><?php echo __('No preset wallpapers available.', 'brandkit'); ?></option>
                                        <?php else : ?>
                                            <?php foreach ($wallpaperPresets as $presetFile) : ?>
                                                <?php
                                                    $presetLabel = $wallpaperPresetLabels[$presetFile] ?? $presetFile;
                                                    $presetAssets = brandkit_get_wallpaper_preset_assets($presetFile);
                                                    $presetPreviewUrl = $presetAssets['url'] ?? '';
                                                ?>
                                                <?php $presetPreviewWebp = $presetAssets['webp_url'] ?? ''; ?>
                                                <option
                                                    value="<?php echo htmlescape($presetFile); ?>"
                                                    data-preview-url="<?php echo htmlescape($presetPreviewUrl); ?>"
                                                    data-preview-webp-url="<?php echo htmlescape($presetPreviewWebp); ?>"
                                                    <?php echo $wallpaperPreset === $presetFile ? 'selected' : ''; ?>
                                                >
                                                    <?php echo htmlescape($presetLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_wallpaper_size"><?php echo __('Size', 'brandkit'); ?></label>
                                    <select id="brandkit_wallpaper_size" name="login_wallpaper_size">
                                        <?php foreach ($wallpaperSizeLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperSize === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_wallpaper_position"><?php echo __('Position', 'brandkit'); ?></label>
                                    <select id="brandkit_wallpaper_position" name="login_wallpaper_position">
                                        <?php foreach ($wallpaperPositionLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperPosition === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_wallpaper_attachment"><?php echo __('Attachment', 'brandkit'); ?></label>
                                    <select id="brandkit_wallpaper_attachment" name="login_wallpaper_attachment">
                                        <?php foreach ($wallpaperAttachmentLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperAttachment === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="brandkit_wallpaper_overlay_color"><?php echo __('Overlay color', 'brandkit'); ?></label>
                                    <input type="color" id="brandkit_wallpaper_overlay_color" name="login_wallpaper_overlay_color" value="<?php echo htmlescape($loginWallpaperOverlayColor); ?>">
                                </div>
                                <div>
                                    <label for="brandkit_wallpaper_overlay_opacity"><?php echo __('Overlay opacity (%)', 'brandkit'); ?></label>
                                    <div class="brandkit-range">
                                    <input type="range" id="brandkit_wallpaper_overlay_opacity" name="login_wallpaper_overlay_opacity" min="0" max="100" value="<?php echo htmlescape($loginWallpaperOverlayOpacity); ?>" oninput="document.getElementById('brandkit_wallpaper_overlay_opacity_value').textContent = this.value + '%'">
                                        <span class="brandkit-range-value" id="brandkit_wallpaper_overlay_opacity_value"><?php echo htmlescape($loginWallpaperOverlayOpacity); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="brandkit-step2-row">
                        <div class="brandkit-section brandkit-section-wide">
                            <div class="brandkit-wallpaper-card" data-wallpaper-source="<?php echo htmlescape($wallpaperSource); ?>">
                                <div class="brandkit-wallpaper-actions">
                                    <label class="brandkit-logo-action brandkit-logo-upload" data-brandkit-wallpaper-upload for="brandkit_login_wallpaper" aria-label="<?php echo __('Upload wallpaper', 'brandkit'); ?>">&#x2B06;</label>
                                    <?php if ($customWallpaperUrl) : ?>
                                        <button type="button" class="brandkit-logo-action brandkit-logo-remove" id="brandkit_login_wallpaper_delete" aria-label="<?php echo __('Remove uploaded wallpaper', 'brandkit'); ?>">×</button>
                                    <?php endif; ?>
                                </div>
                                <input class="brandkit-logo-input" type="file" id="brandkit_login_wallpaper" name="login_wallpaper" accept="image/png,image/jpeg,image/webp">
                                <input class="brandkit-toggle-hidden" type="checkbox" id="brandkit_login_wallpaper_remove" name="login_wallpaper_remove" value="1">
                                <div
                                    class="brandkit-wallpaper-preview"
                                    data-brandkit-wallpaper-preview
                                    data-wallpaper-image="<?php echo htmlescape($loginWallpaperUrl); ?>"
                                    data-wallpaper-image-webp="<?php echo htmlescape($loginWallpaperWebpUrl ?? ''); ?>"
                                    data-wallpaper-custom="<?php echo htmlescape($customWallpaperUrl ?? ''); ?>"
                                    data-wallpaper-custom-webp="<?php echo htmlescape($customWallpaperWebpUrl ?? ''); ?>"
                                >
                                    <?php if (!$loginWallpaperUrl) : ?>
                                        <div class="brandkit-wallpaper-placeholder"><?php echo __('No wallpaper selected yet.', 'brandkit'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="brandkit-actions brandkit-actions-floating">
                    <button class="submit brandkit-save-login" type="submit" name="brandkit_action" value="save_login_settings"><?php echo __('Save login page', 'brandkit'); ?></button>
                    <button class="submit brandkit-reset-login" type="submit" name="brandkit_action" value="reset_login_defaults"><?php echo __('Restore defaults', 'brandkit'); ?></button>
                </div>
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var layoutSelect = document.getElementById('brandkit_login_layout');
                    var panelTransparent = document.getElementById('brandkit_login_transparent');
                    var logoPosition = document.getElementById('brandkit_login_logo_position');
                    var colorModeSelect = document.getElementById('brandkit_login_button_color_mode');
                    var colorPicker = document.getElementById('brandkit_login_button_color_picker');
                    var fieldStyleSelect = document.getElementById('brandkit_login_field_style');
                    var customSection = document.getElementById('brandkit_button_color_custom');
                    var hoverEffectSelect = document.getElementById('brandkit_login_button_hover_effect');
                    var hoverOptions = document.getElementById('brandkit_button_hover_options');
                    var hoverFillModeInput = document.getElementById('brandkit_login_button_hover_fill_mode');
                    var hoverFillColorInput = document.getElementById('brandkit_login_button_hover_fill_color');
                    var hoverTextColorInput = document.getElementById('brandkit_login_button_hover_text_color');
                    var panelSplitSection = document.getElementById('brandkit_panel_split_section');
                    var panelSplitPreview = document.getElementById('brandkit_panel_split_preview');
                    var splitControlsWrap = document.getElementById('brandkit_split_controls');
                    var iconColor = document.getElementById('brandkit_login_icon_color');
                    var splitControls = document.querySelectorAll('.brandkit-split-controls input, .brandkit-split-controls select');
                    var panelSizeFields = document.querySelectorAll('.brandkit-panel-size');
                    var panelStyleFields = document.querySelectorAll('.brandkit-panel-style-controls');
                    var logoOffsetFields = document.querySelectorAll('.brandkit-logo-offset-field');
                    var fieldFocusFields = document.querySelectorAll('.brandkit-field-focus');
                    var wallpaperInput = document.getElementById('brandkit_login_wallpaper');
                    var wallpaperRemove = document.getElementById('brandkit_login_wallpaper_remove');
                    var wallpaperDelete = document.getElementById('brandkit_login_wallpaper_delete');
                    var wallpaperPreview = document.querySelector('[data-brandkit-wallpaper-preview]');
                    var wallpaperSourceInput = document.getElementById('brandkit_wallpaper_source');
                    var wallpaperUrlInput = document.getElementById('brandkit_wallpaper_url');
                    var wallpaperUrlWrap = document.getElementById('brandkit_wallpaper_url_wrap');
                    var wallpaperPresetInput = document.getElementById('brandkit_wallpaper_preset');
                    var wallpaperPresetWrap = document.getElementById('brandkit_wallpaper_preset_wrap');
                    var wallpaperUploadAction = document.querySelector('[data-brandkit-wallpaper-upload]');
                    var wallpaperSizeInput = document.getElementById('brandkit_wallpaper_size');
                    var wallpaperPositionInput = document.getElementById('brandkit_wallpaper_position');
                    var wallpaperAttachmentInput = document.getElementById('brandkit_wallpaper_attachment');
                    var wallpaperOverlayColorInput = document.getElementById('brandkit_wallpaper_overlay_color');
                    var wallpaperOverlayOpacityInput = document.getElementById('brandkit_wallpaper_overlay_opacity');
                    var form = document.getElementById('brandkit-login-settings-form')
                        || document.querySelector('form[action*="step=2"]')
                        || document.querySelector('form');

                    var isLightColor = function (hex) {
                        if (!hex) {
                            return false;
                        }
                        var value = hex.replace('#', '');
                        if (value.length === 3) {
                            value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
                        }
                        var r = parseInt(value.substring(0, 2), 16) || 0;
                        var g = parseInt(value.substring(2, 4), 16) || 0;
                        var b = parseInt(value.substring(4, 6), 16) || 0;
                        var luma = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
                        return luma > 0.75;
                    };

                    var normalizeHex = function (value) {
                        if (!value) {
                            return '';
                        }
                        value = value.trim().toUpperCase();
                        if (value[0] !== '#') {
                            value = '#' + value;
                        }
                        if (/^#[0-9A-F]{3}$/.test(value)) {
                            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
                        }
                        if (!/^#[0-9A-F]{6}$/.test(value)) {
                            return '';
                        }
                        return value;
                    };

                    var toggleColorMode = function () {
                        var isCustom = colorModeSelect && colorModeSelect.value === 'custom';
                        if (customSection) {
                            customSection.classList.toggle('brandkit-toggle-hidden', !isCustom);
                        }
                    };

                    var toggleHoverOptions = function () {
                        var isFill = hoverEffectSelect && hoverEffectSelect.value === 'fill';
                        if (hoverOptions) {
                            hoverOptions.classList.toggle('brandkit-toggle-hidden', !isFill);
                            hoverOptions.querySelectorAll('input').forEach(function (input) {
                                if (isFill) {
                                    input.removeAttribute('disabled');
                                } else {
                                    input.setAttribute('disabled', 'disabled');
                                }
                            });
                        }
                    };

                    var toggleSplitControls = function () {
                        var isClassic = layoutSelect && layoutSelect.value === 'classic';
                        if (splitControlsWrap) {
                            splitControlsWrap.classList.toggle('brandkit-toggle-hidden', isClassic);
                        }
                        splitControls.forEach(function (control) {
                            control.disabled = isClassic;
                        });
                    };

                    var togglePanelSizeControls = function () {
                        var isTransparent = panelTransparent && panelTransparent.value === '1';
                        panelSizeFields.forEach(function (field) {
                            field.classList.toggle('brandkit-toggle-hidden', isTransparent);
                            var input = field.querySelector('input');
                            if (input) {
                                if (isTransparent) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            }
                        });
                        panelStyleFields.forEach(function (field) {
                            field.classList.toggle('brandkit-toggle-hidden', isTransparent);
                            field.querySelectorAll('input, select').forEach(function (input) {
                                if (isTransparent) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            });
                        });
                        if (logoPosition) {
                            var logoPositionField = logoPosition.closest('div');
                            if (isTransparent) {
                                logoPosition.setAttribute('disabled', 'disabled');
                                if (logoPositionField) {
                                    logoPositionField.classList.add('brandkit-toggle-hidden');
                                }
                            } else {
                                logoPosition.removeAttribute('disabled');
                                if (logoPositionField) {
                                    logoPositionField.classList.remove('brandkit-toggle-hidden');
                                }
                            }
                        }

                        var isClassic = layoutSelect && layoutSelect.value === 'classic';
                        var hidePanelSplit = isTransparent && isClassic;
                        if (panelSplitSection) {
                            panelSplitSection.classList.toggle('brandkit-toggle-hidden', hidePanelSplit);
                        }
                        if (panelSplitPreview) {
                            panelSplitPreview.classList.toggle('brandkit-toggle-hidden', hidePanelSplit);
                        }
                    };

                    var toggleLogoOffsetControls = function () {
                        var isInside = logoPosition && logoPosition.value === 'inside';
                        logoOffsetFields.forEach(function (field) {
                            field.classList.toggle('brandkit-toggle-hidden', isInside);
                            var input = field.querySelector('input');
                            if (input) {
                                if (isInside) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            }
                        });
                    };

                    var toggleFieldFocusControls = function () {
                        var isNeon = fieldStyleSelect && fieldStyleSelect.value === 'neon';
                        fieldFocusFields.forEach(function (field) {
                            field.classList.toggle('brandkit-toggle-hidden', !isNeon);
                        });
                    };

                    var hexToRgba = function (hex, opacity) {
                        var value = normalizeHex(hex);
                        if (!value) {
                            return '';
                        }
                        var r = parseInt(value.substring(1, 3), 16) || 0;
                        var g = parseInt(value.substring(3, 5), 16) || 0;
                        var b = parseInt(value.substring(5, 7), 16) || 0;
                        var alpha = typeof opacity === 'number' ? opacity : 1;
                        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
                    };

                    var scaleRange = function (value, min, max, outMin, outMax) {
                        if (max === min) {
                            return outMin;
                        }
                        var clamped = Math.min(max, Math.max(min, value));
                        var ratio = (clamped - min) / (max - min);
                        return outMin + (outMax - outMin) * ratio;
                    };

                    var previewLayout = document.querySelector('[data-brandkit-preview="layout"]');
                    var previewPanel = document.querySelector('[data-brandkit-preview="panel"]');
                    var previewButtonStage = document.querySelector('[data-brandkit-preview="button"]');
                    var previewButton = previewButtonStage ? previewButtonStage.querySelector('[data-brandkit-preview-button]') : null;
                    var previewFields = document.querySelector('[data-brandkit-preview="fields"]');
                    var previewIcons = document.querySelector('[data-brandkit-preview="icons"]');
                    var isPreviewButtonHovered = false;

                    var panelColorInput = document.getElementById('brandkit_login_color');
                    var panelOpacityInput = document.getElementById('brandkit_login_opacity');
                    var panelWidthInput = document.getElementById('brandkit_login_panel_width');
                    var panelHeightInput = document.getElementById('brandkit_login_panel_height');
                    var logoScaleInput = document.getElementById('brandkit_login_logo_scale');
                    var splitWidthInput = document.getElementById('brandkit_split_width');
                    var splitColorInput = document.getElementById('brandkit_split_color');
                    var splitOpacityInput = document.getElementById('brandkit_split_opacity');
                    var splitBlurInput = document.getElementById('brandkit_split_blur');
                    var accentColorInput = document.getElementById('brandkit_login_accent_color');
                    var loginTextColorInput = document.getElementById('brandkit_login_text_color');
                    var buttonShapeInput = document.getElementById('brandkit_login_button_shape');
                    var buttonStyleInput = document.getElementById('brandkit_login_button_custom_style');
                    var buttonTextSizeInput = document.getElementById('brandkit_login_button_text_size');
                    var fieldBgInput = document.getElementById('brandkit_login_field_bg');
                    var fieldBorderInput = document.getElementById('brandkit_login_field_border');
                    var fieldBorderWidthInput = document.getElementById('brandkit_login_field_border_width');
                    var fieldTextInput = document.getElementById('brandkit_login_field_text');
                    var fieldTextSizeInput = document.getElementById('brandkit_login_field_text_size');
                    var fieldRadiusInput = document.getElementById('brandkit_login_field_radius');
                    var fieldHeightInput = document.getElementById('brandkit_login_field_height');
                    var fieldWidthInput = document.getElementById('brandkit_login_field_width');
                    var fieldFocusInput = document.getElementById('brandkit_login_field_focus');
                    var logoOffsetInput = document.getElementById('brandkit_login_logo_offset');
                    var loginHideLoginTextInput = document.getElementById('brandkit_hide_login_text');
                    var iconPositionInput = document.getElementById('brandkit_login_icon_position');
                    var iconUserInputs = document.querySelectorAll('input[name="login_icon_user"]');

                    var updateLayoutPreview = function (preview) {
                        if (!preview) {
                            return;
                        }
                        var layoutValue = layoutSelect ? layoutSelect.value : 'classic';
                        preview.setAttribute('data-layout', layoutValue);
                        preview.style.setProperty('--brandkit-preview-panel-align', layoutValue === 'split-left' ? 'flex-start' : layoutValue === 'split-right' ? 'flex-end' : 'center');

                        var isLayoutPreview = preview.getAttribute('data-brandkit-preview') === 'layout';
                        var splitWidth = splitWidthInput ? parseInt(splitWidthInput.value || '50', 10) : 50;
                        preview.style.setProperty('--brandkit-preview-split-width', splitWidth + '%');

                        var splitOpacity = splitOpacityInput ? parseInt(splitOpacityInput.value || '55', 10) / 100 : 0.55;
                        var splitColor = isLayoutPreview ? '#cbd5e1' : (splitColorInput ? normalizeHex(splitColorInput.value) : '#cbd5e1');
                        var splitRgba = splitColor ? hexToRgba(splitColor, splitOpacity) : '';
                        if (splitRgba) {
                            preview.style.setProperty('--brandkit-preview-split-color', splitRgba);
                        }
                        if (!isLayoutPreview) {
                            preview.style.setProperty('--brandkit-preview-split-blur', splitBlurInput ? parseInt(splitBlurInput.value || '10', 10) + 'px' : '10px');
                        }

                        var panelOpacity = panelOpacityInput ? parseInt(panelOpacityInput.value || '65', 10) / 100 : 0.65;
                        if (isLayoutPreview && panelTransparent && panelTransparent.value === '0') {
                            panelOpacity = 0.9;
                        }
                        var panelHex = panelColorInput ? normalizeHex(panelColorInput.value) : '#111827';
                        if (isLayoutPreview && panelTransparent && panelTransparent.value === '0') {
                            panelHex = '#e2e8f0';
                        }
                        var panelRgba = panelHex ? hexToRgba(panelHex, panelOpacity) : '';
                        if (panelRgba) {
                            preview.style.setProperty('--brandkit-preview-panel-color', panelRgba);
                        }
                        if (panelTransparent && panelTransparent.value === '1') {
                            preview.style.setProperty('--brandkit-preview-panel-opacity', '1');
                            preview.style.setProperty('--brandkit-preview-panel-border', 'none');
                            if (!isLayoutPreview) {
                                preview.style.setProperty('--brandkit-preview-panel-color', 'transparent');
                                preview.style.setProperty('--brandkit-preview-panel-shadow', 'none');
                            }
                        } else {
                            preview.style.setProperty('--brandkit-preview-panel-opacity', '1');
                            preview.style.setProperty('--brandkit-preview-panel-border', '1px solid rgba(148, 163, 184, 0.6)');
                            if (!isLayoutPreview) {
                                preview.style.setProperty('--brandkit-preview-panel-shadow', '0 10px 24px rgba(15, 23, 42, 0.25)');
                            }
                        }

                        if (isLayoutPreview && (layoutValue === 'split-left' || layoutValue === 'split-right')) {
                            preview.style.setProperty('--brandkit-preview-panel-color', 'transparent');
                        }

                        var accentColor = accentColorInput ? normalizeHex(accentColorInput.value) : '';
                        if (accentColor) {
                            preview.style.setProperty('--brandkit-preview-accent', accentColor);
                        }
                        var textColor = loginTextColorInput ? normalizeHex(loginTextColorInput.value) : '';
                        if (textColor) {
                            preview.style.setProperty('--brandkit-preview-text', textColor);
                        }

                        var panelWidth = panelWidthInput ? parseInt(panelWidthInput.value || '520', 10) : 520;
                        var panelHeight = panelHeightInput ? parseInt(panelHeightInput.value || '560', 10) : 560;
                        var widthPct = scaleRange(panelWidth, 280, 720, 55, 85);
                        var heightPct = scaleRange(panelHeight, 360, 780, 55, 80);
                        if (isLayoutPreview && (layoutValue === 'split-left' || layoutValue === 'split-right')) {
                            widthPct = Math.min(widthPct, Math.max(45, splitWidth - 6));
                        }
                        preview.style.setProperty('--brandkit-preview-panel-width', widthPct + '%');
                        preview.style.setProperty('--brandkit-preview-panel-height', heightPct + '%');

                        if (!isLayoutPreview) {
                            var showSplit = layoutValue === 'split-left' || layoutValue === 'split-right';
                            preview.style.setProperty('--brandkit-preview-split-visibility', showSplit ? '1' : '0');
                        }

                        var logoScale = logoScaleInput ? parseInt(logoScaleInput.value || '200', 10) : 200;
                        var logoWidth = scaleRange(logoScale, 80, 480, 45, 85);
                        preview.style.setProperty('--brandkit-preview-logo-width', logoWidth + '%');

                        var logoOffset = logoOffsetInput ? parseInt(logoOffsetInput.value || '0', 10) : 0;
                        var logoOffsetPx = Math.min(4, Math.max(-12, logoOffset / 6));
                        var logoOutside = logoPosition ? logoPosition.value === 'inside' : true;
                        if (!logoOutside) {
                            logoOffsetPx = 0;
                        }
                        preview.style.setProperty('--brandkit-preview-logo-offset', logoOffsetPx + 'px');

                        if (logoPosition) {
                            preview.setAttribute('data-logo-position', logoOutside ? 'outside' : 'inside');
                        }
                        preview.setAttribute('data-login-text-hidden', loginHideLoginTextInput && loginHideLoginTextInput.value === '1' ? '1' : '0');

                        var previewButtonInline = preview.querySelector('[data-brandkit-preview-button]');
                        if (previewButtonInline) {
                            var buttonColor = accentColor || '#0ea5e9';
                            previewButtonInline.style.background = buttonColor;
                            previewButtonInline.style.color = isLightColor(buttonColor) ? '#0f172a' : '#ffffff';
                        }

                        preview.style.setProperty('--brandkit-preview-field-bg', 'rgba(255, 255, 255, 0.85)');
                        preview.style.setProperty('--brandkit-preview-field-border', 'rgba(148, 163, 184, 0.7)');
                        preview.style.setProperty('--brandkit-preview-field-border-width', '1px');
                    };

                    var applyButtonHoverState = function () {
                        if (!previewButton) {
                            return;
                        }
                        var effect = hoverEffectSelect ? hoverEffectSelect.value : 'press';
                        var baseBg = previewButton.dataset.brandkitBaseBg || '';
                        var baseBorder = previewButton.dataset.brandkitBaseBorder || '';
                        var baseText = previewButton.dataset.brandkitBaseText || '';
                        var baseShadow = previewButton.dataset.brandkitBaseShadow || '';

                        if (!isPreviewButtonHovered || !effect) {
                            previewButton.style.background = baseBg;
                            previewButton.style.border = '2px solid ' + baseBorder;
                            previewButton.style.color = baseText;
                            previewButton.style.transform = '';
                            if (baseShadow) {
                                previewButton.style.boxShadow = baseShadow;
                            }
                            return;
                        }

                        if (effect === 'press') {
                            previewButton.style.background = baseBg;
                            previewButton.style.border = '2px solid ' + baseBorder;
                            previewButton.style.color = baseText;
                            previewButton.style.transform = 'translateY(2px)';
                            previewButton.style.boxShadow = '0 2px 6px rgba(15, 23, 42, 0.35)';
                            return;
                        }

                        if (effect === 'fill') {
                            var hoverFill = previewButton.dataset.brandkitHoverFill || baseBorder;
                            var hoverText = previewButton.dataset.brandkitHoverText || baseText;
                            previewButton.style.background = hoverFill || baseBg;
                            previewButton.style.border = '2px solid ' + (hoverFill || baseBorder);
                            previewButton.style.color = hoverText;
                            previewButton.style.transform = '';
                            if (baseShadow) {
                                previewButton.style.boxShadow = baseShadow;
                            }
                        }
                    };

                    var updateButtonPreview = function () {
                        if (!previewButton) {
                            return;
                        }
                        var shape = buttonShapeInput ? buttonShapeInput.value : 'rounded';
                        previewButton.classList.remove(
                            'brandkit-button-demo--square',
                            'brandkit-button-demo--rounded',
                            'brandkit-button-demo--pill',
                            'brandkit-button-demo--cut',
                            'brandkit-button-demo--soft'
                        );
                        previewButton.classList.add('brandkit-button-demo--' + shape);

                        var color = '#0ea5e9';
                        var customColor = colorPicker ? normalizeHex(colorPicker.value) : '';
                        var accentColor = accentColorInput ? normalizeHex(accentColorInput.value) : '';
                        if (colorModeSelect && colorModeSelect.value === 'custom' && customColor) {
                            color = customColor;
                        } else if (accentColor) {
                            color = accentColor;
                        }

                        var isOutline = buttonStyleInput && buttonStyleInput.value === 'outline';
                        var baseBg = isOutline ? 'transparent' : color;
                        var baseBorder = color;
                        var baseText = isOutline ? color : (isLightColor(color) ? '#0f172a' : '#ffffff');
                        previewButton.style.background = baseBg;
                        previewButton.style.border = '2px solid ' + baseBorder;
                        previewButton.style.color = baseText;
                        previewButton.dataset.brandkitBaseBg = baseBg;
                        previewButton.dataset.brandkitBaseBorder = baseBorder;
                        previewButton.dataset.brandkitBaseText = baseText;

                        var hoverFillMode = hoverFillModeInput ? hoverFillModeInput.value : 'custom';
                        var hoverFillCustom = hoverFillColorInput ? normalizeHex(hoverFillColorInput.value) : '';
                        var hoverFillColor = hoverFillMode === 'button' ? color : (hoverFillCustom || color);
                        var hoverTextColor = hoverTextColorInput ? normalizeHex(hoverTextColorInput.value) : '';
                        if (!hoverTextColor) {
                            hoverTextColor = isLightColor(hoverFillColor) ? '#0f172a' : '#ffffff';
                        }
                        previewButton.dataset.brandkitHoverFill = hoverFillColor;
                        previewButton.dataset.brandkitHoverText = hoverTextColor;

                        var textSize = buttonTextSizeInput ? parseInt(buttonTextSizeInput.value || '14', 10) : 14;
                        previewButton.style.fontSize = Math.max(10, Math.min(18, textSize)) + 'px';

                        previewButton.style.transform = '';
                        previewButton.style.boxShadow = '';
                        previewButton.dataset.brandkitBaseShadow = window.getComputedStyle(previewButton).boxShadow;
                        applyButtonHoverState();
                    };

                    var applyFieldStyles = function (container) {
                        if (!container) {
                            return;
                        }
                        var fields = container.querySelectorAll('.brandkit-preview-field');
                        if (!fields.length) {
                            return;
                        }
                        var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                        var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                        var focus = fieldFocusInput ? normalizeHex(fieldFocusInput.value) : border;
                        var borderWidth = fieldBorderWidthInput ? parseInt(fieldBorderWidthInput.value || '1', 10) : 1;
                        var radius = fieldRadiusInput ? parseInt(fieldRadiusInput.value || '10', 10) : 10;
                        var height = fieldHeightInput ? parseInt(fieldHeightInput.value || '36', 10) : 36;
                        var width = fieldWidthInput ? parseInt(fieldWidthInput.value || '520', 10) : 520;
                        var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                        var textColor = fieldTextInput ? normalizeHex(fieldTextInput.value) : '#111827';
                        var textSize = fieldTextSizeInput ? parseInt(fieldTextSizeInput.value || '14', 10) : 14;

                        fields.forEach(function (field) {
                            field.style.height = height + 'px';
                            field.style.width = 'min(100%, ' + width + 'px)';
                            field.style.maxWidth = width + 'px';
                            field.style.borderRadius = radius + 'px';
                            field.style.borderWidth = borderWidth + 'px';
                            field.style.borderStyle = 'solid';
                            field.style.boxShadow = 'none';
                            field.style.borderColor = border || '#cbd5f5';
                            field.style.background = bg || '#ffffff';

                            if (style === 'glass') {
                                field.style.background = hexToRgba(bg, 0.35);
                                field.style.borderColor = hexToRgba(border, 0.6);
                                field.style.boxShadow = '0 8px 16px rgba(15, 23, 42, 0.12)';
                            } else if (style === 'soft') {
                                field.style.boxShadow = '0 10px 20px rgba(15, 23, 42, 0.15)';
                            } else if (style === 'outline') {
                                field.style.background = 'transparent';
                                field.style.boxShadow = 'none';
                            } else if (style === 'underline') {
                                field.style.background = 'transparent';
                                field.style.border = 'none';
                                field.style.borderBottom = borderWidth + 'px solid ' + (border || '#cbd5f5');
                                field.style.borderRadius = '0';
                            } else if (style === 'neon') {
                                field.style.boxShadow = '0 0 0 2px ' + hexToRgba(focus, 0.45) + ', 0 0 16px ' + hexToRgba(focus, 0.6);
                            }

                            var textNode = field.querySelector('.brandkit-preview-field-text');
                            if (textNode) {
                                textNode.style.color = textColor || '#111827';
                                textNode.style.fontSize = Math.max(10, Math.min(22, textSize)) + 'px';
                            }
                        });
                    };

                    var updateFieldPreview = function () {
                        if (previewFields) {
                            var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                            var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                            var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                            var artTone = bg || '#ffffff';
                            if (style === 'outline' || style === 'underline') {
                                artTone = border || bg || '#ffffff';
                            }
                            var surface = isLightColor(artTone) ? '#0f172a' : '#ffffff';
                            previewFields.style.setProperty('--brandkit-preview-field-surface', surface);
                            previewFields.style.setProperty('--brandkit-preview-field-text-size', (fieldTextSizeInput ? fieldTextSizeInput.value : 14) + 'px');
                            previewFields.style.setProperty('--brandkit-preview-field-text-color', fieldTextInput ? fieldTextInput.value : '#111827');
                            if (fieldWidthInput) {
                                previewFields.style.setProperty('--brandkit-preview-field-width', fieldWidthInput.value + 'px');
                            }
                        }
                        applyFieldStyles(previewFields);
                    };

                    var pulseFieldPreview = function () {
                        if (!previewFields) {
                            return;
                        }
                        previewFields.classList.remove('brandkit-field-pulse');
                        void previewFields.offsetWidth;
                        previewFields.classList.add('brandkit-field-pulse');
                    };

                    var updateIconPreview = function () {
                        if (!previewIcons) {
                            return;
                        }
                        var selectedLogin = document.querySelector('input[name="login_icon_user"]:checked');
                        var selectedPassword = document.querySelector('input[name="login_icon_password"]:checked');
                        var loginIconValue = selectedLogin ? selectedLogin.value : 'none';
                        var passwordIconValue = selectedPassword ? selectedPassword.value : 'none';
                        previewIcons.setAttribute('data-login-icon', loginIconValue);
                        previewIcons.setAttribute('data-password-icon', passwordIconValue);
                        if (iconPositionInput && iconPositionInput.value === 'right') {
                            previewIcons.classList.add('icon-right');
                        } else {
                            previewIcons.classList.remove('icon-right');
                        }
                        if (iconColor) {
                            previewIcons.style.setProperty('--brandkit-preview-icon-color', iconColor.value);
                        }
                        var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                        var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                        var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                        var artTone = bg || '#ffffff';
                        if (style === 'outline' || style === 'underline') {
                            artTone = border || bg || '#ffffff';
                        }
                        var iconTone = iconColor ? normalizeHex(iconColor.value) : '';
                        var surfaceSource = iconTone || artTone;
                        var surface = isLightColor(surfaceSource) ? '#0f172a' : '#ffffff';
                        previewIcons.style.setProperty('--brandkit-preview-field-surface', surface);
                        previewIcons.style.setProperty('--brandkit-preview-field-text-size', (fieldTextSizeInput ? fieldTextSizeInput.value : 14) + 'px');
                        previewIcons.style.setProperty('--brandkit-preview-field-text-color', fieldTextInput ? fieldTextInput.value : '#111827');
                        if (fieldWidthInput) {
                            previewIcons.style.setProperty('--brandkit-preview-field-width', fieldWidthInput.value + 'px');
                        }
                        applyFieldStyles(previewIcons);
                    };

                    var updateWallpaperPreview = function () {
                        if (!wallpaperPreview) {
                            return;
                        }
                        var imageUrl = wallpaperPreview.getAttribute('data-wallpaper-image') || '';
                        var imageWebpUrl = wallpaperPreview.getAttribute('data-wallpaper-image-webp') || '';
                        var overlayColor = wallpaperOverlayColorInput ? normalizeHex(wallpaperOverlayColorInput.value) : '';
                        if (!overlayColor) {
                            overlayColor = '#000000';
                        }
                        var overlayOpacity = wallpaperOverlayOpacityInput ? parseInt(wallpaperOverlayOpacityInput.value || '0', 10) / 100 : 0;
                        var overlayRgba = hexToRgba(overlayColor, isNaN(overlayOpacity) ? 0.35 : overlayOpacity);
                        var backgroundLayers = 'linear-gradient(' + overlayRgba + ', ' + overlayRgba + ')';
                        if (imageUrl) {
                            var fallbackMime = 'image/jpeg';
                            var match = imageUrl.toLowerCase().match(/\.(jpe?g|png|webp)(?:\?|#|$)/);
                            if (match && match[1] === 'png') {
                                fallbackMime = 'image/png';
                            } else if (match && match[1] === 'webp') {
                                fallbackMime = 'image/webp';
                            }
                            if (imageWebpUrl) {
                                backgroundLayers += ', image-set(url(\"' + imageWebpUrl + '\") type(\"image/webp\"), url(\"' + imageUrl + '\") type(\"' + fallbackMime + '\"))';
                            } else {
                                backgroundLayers += ', url(\"' + imageUrl + '\")';
                            }
                        }
                        wallpaperPreview.style.setProperty('--brandkit-wallpaper-background', backgroundLayers);

                        var sizeValue = wallpaperSizeInput ? wallpaperSizeInput.value : 'cover';
                        var previewSizeMap = {
                            cover: 'contain',
                            contain: 'contain',
                            stretch: 'contain',
                            original: 'contain'
                        };
                        wallpaperPreview.style.setProperty('--brandkit-wallpaper-preview-size', previewSizeMap[sizeValue] || 'contain');

                        if (wallpaperPreview.style.getPropertyValue('--brandkit-wallpaper-position')) {
                            wallpaperPreview.style.removeProperty('--brandkit-wallpaper-position');
                        }

                        wallpaperPreview.style.setProperty('background-attachment', 'scroll');

                        var placeholder = wallpaperPreview.querySelector('.brandkit-wallpaper-placeholder');
                        if (placeholder) {
                            placeholder.style.display = imageUrl ? 'none' : 'flex';
                        }
                    };

                    var getPresetPreviewUrl = function () {
                        if (!wallpaperPresetInput) {
                            return '';
                        }
                        var selected = wallpaperPresetInput.options[wallpaperPresetInput.selectedIndex];
                        return selected ? (selected.getAttribute('data-preview-url') || '') : '';
                    };

                    var getPresetPreviewWebpUrl = function () {
                        if (!wallpaperPresetInput) {
                            return '';
                        }
                        var selected = wallpaperPresetInput.options[wallpaperPresetInput.selectedIndex];
                        return selected ? (selected.getAttribute('data-preview-webp-url') || '') : '';
                    };

                    var toggleWallpaperSource = function () {
                        var source = wallpaperSourceInput ? wallpaperSourceInput.value : 'preset';
                        var isPreset = source === 'preset';
                        var isCustom = source === 'custom';
                        var isUrl = source === 'url';
                        if (wallpaperPresetWrap) {
                            wallpaperPresetWrap.classList.toggle('brandkit-toggle-hidden', !isPreset);
                        }
                        if (wallpaperPresetInput) {
                            wallpaperPresetInput.disabled = !isPreset;
                        }
                        if (wallpaperUrlWrap) {
                            wallpaperUrlWrap.classList.toggle('brandkit-toggle-hidden', !isUrl);
                        }
                        if (wallpaperUrlInput) {
                            wallpaperUrlInput.disabled = !isUrl;
                        }
                        if (wallpaperInput) {
                            if (isCustom) {
                                wallpaperInput.removeAttribute('disabled');
                            } else {
                                wallpaperInput.setAttribute('disabled', 'disabled');
                            }
                        }
                        if (wallpaperUploadAction) {
                            wallpaperUploadAction.classList.toggle('brandkit-toggle-hidden', !isCustom);
                        }
                        if (wallpaperPreview) {
                            var customUrl = wallpaperPreview.getAttribute('data-wallpaper-custom') || '';
                            var customWebp = wallpaperPreview.getAttribute('data-wallpaper-custom-webp') || '';
                            var presetUrl = getPresetPreviewUrl();
                            var presetWebp = getPresetPreviewWebpUrl();
                            var remoteUrl = wallpaperUrlInput ? wallpaperUrlInput.value.trim() : '';
                            wallpaperPreview.setAttribute('data-wallpaper-image', isUrl ? remoteUrl : (isCustom ? customUrl : presetUrl));
                            wallpaperPreview.setAttribute('data-wallpaper-image-webp', isUrl ? '' : (isCustom ? customWebp : presetWebp));
                            updateWallpaperPreview();
                        }
                    };

                    if (previewButton) {
                        previewButton.addEventListener('mouseenter', function () {
                            isPreviewButtonHovered = true;
                            applyButtonHoverState();
                        });
                        previewButton.addEventListener('mouseleave', function () {
                            isPreviewButtonHovered = false;
                            applyButtonHoverState();
                        });
                    }

                    var updateAllPreviews = function () {
                        updateLayoutPreview(previewLayout);
                        updateLayoutPreview(previewPanel);
                        updateButtonPreview();
                        updateFieldPreview();
                        updateIconPreview();
                        updateWallpaperPreview();
                    };
                    if (colorPicker) {
                        colorPicker.addEventListener('input', function (event) {
                            var value = normalizeHex(event.target.value);
                            if (value) {
                                if (colorModeSelect) {
                                    colorModeSelect.value = 'custom';
                                }
                                toggleColorMode();
                            }
                        });
                    }

                    if (wallpaperInput && form) {
                        wallpaperInput.addEventListener('change', function () {
                            if (wallpaperInput.files && wallpaperInput.files.length) {
                                if (wallpaperPreview) {
                                    var fileUrl = URL.createObjectURL(wallpaperInput.files[0]);
                                    wallpaperPreview.setAttribute('data-wallpaper-custom', fileUrl);
                                    wallpaperPreview.setAttribute('data-wallpaper-image', fileUrl);
                                    wallpaperPreview.setAttribute('data-wallpaper-custom-webp', '');
                                    wallpaperPreview.setAttribute('data-wallpaper-image-webp', '');
                                }
                                if (wallpaperSourceInput) {
                                    wallpaperSourceInput.value = 'custom';
                                }
                                updateWallpaperPreview();
                                if (wallpaperRemove) {
                                    wallpaperRemove.checked = false;
                                }
                            }
                        });
                    }

                    if (wallpaperDelete && wallpaperRemove && form) {
                        wallpaperDelete.addEventListener('click', function () {
                            wallpaperRemove.checked = true;
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'custom';
                            }
                            var saveActionButton = form.querySelector('button[name="brandkit_action"][value="save_login_settings"]');
                            if (typeof form.requestSubmit === 'function' && saveActionButton) {
                                form.requestSubmit(saveActionButton);
                                return;
                            }
                            var actionInput = form.querySelector('input[name="brandkit_action"]');
                            if (!actionInput) {
                                actionInput = document.createElement('input');
                                actionInput.type = 'hidden';
                                actionInput.name = 'brandkit_action';
                                form.appendChild(actionInput);
                            }
                            actionInput.value = 'save_login_settings';
                            form.submit();
                        });
                    }

                    [
                        wallpaperUrlInput,
                        wallpaperSizeInput,
                        wallpaperPositionInput,
                        wallpaperAttachmentInput,
                        wallpaperOverlayColorInput,
                        wallpaperOverlayOpacityInput
                    ].forEach(function (input) {
                        if (!input) {
                            return;
                        }
                        input.addEventListener('input', updateWallpaperPreview);
                        input.addEventListener('change', updateWallpaperPreview);
                    });

                    if (wallpaperUrlInput) {
                        wallpaperUrlInput.addEventListener('input', function () {
                            if (wallpaperUrlInput.value.trim() && wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'url';
                            }
                            toggleWallpaperSource();
                        });
                    }

                    if (wallpaperSourceInput) {
                        wallpaperSourceInput.addEventListener('change', toggleWallpaperSource);
                    }

                    if (wallpaperPresetInput) {
                        wallpaperPresetInput.addEventListener('change', function () {
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'preset';
                            }
                            toggleWallpaperSource();
                        });
                    }

                    if (form) {
                        form.addEventListener('submit', function () {
                            if (wallpaperUrlInput && wallpaperUrlInput.value.trim() && wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'url';
                                wallpaperUrlInput.removeAttribute('disabled');
                                wallpaperInput && wallpaperInput.setAttribute('disabled', 'disabled');
                                return;
                            }
                            if (!wallpaperInput || !wallpaperInput.files || !wallpaperInput.files.length) {
                                return;
                            }
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'custom';
                            }
                            wallpaperInput.removeAttribute('disabled');
                        });
                    }

                    if (layoutSelect) {
                        layoutSelect.addEventListener('change', function () {
                            toggleSplitControls();
                            togglePanelSizeControls();
                        });
                    }

                    if (panelTransparent) {
                        panelTransparent.addEventListener('change', function () {
                            togglePanelSizeControls();
                        });
                    }

                    if (logoPosition) {
                        logoPosition.addEventListener('change', function () {
                            toggleLogoOffsetControls();
                        });
                    }

                    if (fieldStyleSelect) {
                        fieldStyleSelect.addEventListener('change', function () {
                            toggleFieldFocusControls();
                        });
                    }

                    if (fieldBorderWidthInput) {
                        fieldBorderWidthInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (fieldTextSizeInput) {
                        fieldTextSizeInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (fieldWidthInput) {
                        fieldWidthInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (colorModeSelect) {
                        colorModeSelect.addEventListener('change', function () {
                            toggleColorMode();
                        });
                    }

                    if (hoverEffectSelect) {
                        hoverEffectSelect.addEventListener('change', function () {
                            toggleHoverOptions();
                        });
                    }

                    if (iconColor) {
                        iconColor.addEventListener('input', function () {
                            var iconScopes = document.querySelectorAll('.brandkit-icon-grid-scope');
                            var isLight = isLightColor(iconColor.value);
                            var bgColor = isLight ? '#0f172a' : '#ffffff';
                            iconScopes.forEach(function (scope) {
                                scope.style.setProperty('--brandkit-icon-color', iconColor.value);
                                scope.style.setProperty('--brandkit-icon-bg', bgColor);
                            });
                        });
                    }

                    if (form) {
                        form.addEventListener('input', updateAllPreviews);
                        form.addEventListener('change', updateAllPreviews);
                    }

                    toggleColorMode();
                    toggleHoverOptions();
                    toggleSplitControls();
                    togglePanelSizeControls();
                    toggleLogoOffsetControls();
                    toggleFieldFocusControls();
                    toggleWallpaperSource();
                    updateAllPreviews();
                    document.querySelectorAll('input[type="range"]').forEach(function (input) {
                        var output = document.getElementById(input.id + '_value');
                        if (!output) {
                            return;
                        }
                        var unitMatch = output.textContent.match(/[^0-9.]+$/);
                        var unit = unitMatch ? unitMatch[0] : '';
                        var updateValue = function () {
                            output.textContent = input.value + unit;
                        };
                        input.addEventListener('input', updateValue, { passive: true });
                        input.addEventListener('change', updateValue, { passive: true });
                        input.addEventListener('pointermove', updateValue, { passive: true });
                        input.addEventListener('mousemove', updateValue, { passive: true });
                        updateValue();
                    });
                });
            </script>
        <?php elseif ($currentStep === 3) : ?>
            <?php
                $advancedSettings = brandkit_get_advanced_settings();
                $advancedPresets = brandkit_get_theme_presets();
                $instagramUrl = 'https://www.instagram.com/' . rawurlencode($advancedSettings['brand_instagram_handle']);
                $whatsappUrl = 'https://wa.me/' . preg_replace('/\D+/', '', $advancedSettings['brand_whatsapp']);
            ?>
            <h3>Configuração avançada</h3>
            <p><?php echo __('Manage theme presets, GLPI layout colors, and portable BrandKit configuration files.', 'brandkit'); ?></p>

            <div class="brandkit-section">
                <h4><?php echo __('Theme presets', 'brandkit'); ?></h4>
                <div class="brandkit-form-grid">
                    <?php foreach ($advancedPresets as $presetKey => $preset) : ?>
                        <?php
                            $presetValues = $preset['values'];
                            $presetMenu = brandkit_normalize_hex_color($presetValues['advanced_menu_color'] ?? null, '#2f3f64');
                            $presetAccent = brandkit_normalize_hex_color($presetValues['advanced_accent_color'] ?? null, '#066fd1');
                            $presetLink = brandkit_normalize_hex_color($presetValues['advanced_link_color'] ?? null, $presetAccent);
                            $presetPage = brandkit_normalize_hex_color($presetValues['advanced_page_bg'] ?? null, '#f9fafb');
                            $presetSurface = brandkit_normalize_hex_color($presetValues['advanced_surface_bg'] ?? null, '#ffffff');
                            $presetBorder = brandkit_normalize_hex_color($presetValues['advanced_border_color'] ?? null, '#e5e7eb');
                            $presetButton = brandkit_normalize_hex_color($presetValues['advanced_button_bg_color'] ?? null, $presetAccent);
                            $presetButtonText = brandkit_normalize_hex_color($presetValues['advanced_button_text_color'] ?? null, brandkit_get_contrast_color($presetButton));
                            $presetText = brandkit_get_contrast_color($presetSurface);
                            $presetHeading = $presetText;
                            $presetMuted = $presetText === '#ffffff'
                                ? 'color-mix(in srgb, #ffffff, ' . $presetSurface . ' 28%)'
                                : '#475569';
                            $presetAccentText = brandkit_get_contrast_color($presetAccent);
                            $isPresetActive = $advancedSettings['advanced_theme_preset'] === $presetKey;
                            $presetStyle = sprintf(
                                '--preset-menu: %s; --preset-accent: %s; --preset-link: %s; --preset-page: %s; --preset-surface: %s; --preset-border: %s; --preset-button: %s; --preset-button-text: %s; --preset-text: %s; --preset-heading: %s; --preset-muted: %s; --preset-accent-text: %s;',
                                $presetMenu,
                                $presetAccent,
                                $presetLink,
                                $presetPage,
                                $presetSurface,
                                $presetBorder,
                                $presetButton,
                                $presetButtonText,
                                $presetText,
                                $presetHeading,
                                $presetMuted,
                                $presetAccentText
                            );
                        ?>
                        <form method="post" action="<?php echo $baseUrl; ?>?step=3" class="brandkit-intro-card brandkit-preset-card <?php echo $isPresetActive ? 'is-active' : ''; ?>" style="<?php echo htmlescape($presetStyle); ?>">
                            <input type="hidden" name="brandkit_action" value="apply_theme_preset">
                            <input type="hidden" name="advanced_theme_preset" value="<?php echo htmlescape($presetKey); ?>">
                            <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                            <div class="brandkit-preset-header">
                                <span class="brandkit-preset-check"><?php echo $isPresetActive ? '&#x2713;' : '&#x25CB;'; ?></span>
                                <div class="brandkit-preset-swatches" aria-hidden="true">
                                    <span class="brandkit-preset-swatch" style="--swatch-color: <?php echo htmlescape($presetMenu); ?>"></span>
                                    <span class="brandkit-preset-swatch" style="--swatch-color: <?php echo htmlescape($presetAccent); ?>"></span>
                                    <span class="brandkit-preset-swatch" style="--swatch-color: <?php echo htmlescape($presetSurface); ?>"></span>
                                </div>
                            </div>
                            <h4><?php echo htmlescape($preset['label']); ?></h4>
                            <p><?php echo htmlescape($preset['description']); ?></p>
                            <div class="brandkit-preset-preview" aria-hidden="true">
                                <span class="brandkit-preset-preview-menu"></span>
                                <span class="brandkit-preset-preview-body">
                                    <span class="brandkit-preset-preview-surface"></span>
                                    <span class="brandkit-preset-preview-button"></span>
                                </span>
                            </div>
                            <button class="brandkit-cta preset" type="submit"><?php echo __('Apply preset', 'brandkit'); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" action="<?php echo $baseUrl; ?>?step=3" class="brandkit-section brandkit-theme-form">
                <input type="hidden" name="brandkit_action" value="save_advanced_settings">
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                <h4><?php echo __('GLPI workspace colors', 'brandkit'); ?></h4>
                <div class="brandkit-theme-groups">
                    <fieldset class="brandkit-theme-group">
                        <legend>Menu</legend>
                        <div class="brandkit-form-grid">
                            <div>
                                <label for="brandkit_advanced_menu_color"><?php echo __('Menu color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_menu_color" name="advanced_menu_color" value="<?php echo htmlescape($advancedSettings['advanced_menu_color']); ?>">
                            </div>
                            <div>
                                <label for="brandkit_advanced_menu_text_color"><?php echo __('Menu text color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_menu_text_color" name="advanced_menu_text_color" value="<?php echo htmlescape($advancedSettings['advanced_menu_text_color']); ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="brandkit-theme-group">
                        <legend>Background</legend>
                        <div class="brandkit-form-grid">
                            <div>
                                <label for="brandkit_advanced_page_bg"><?php echo __('Page background', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_page_bg" name="advanced_page_bg" value="<?php echo htmlescape($advancedSettings['advanced_page_bg']); ?>">
                            </div>
                            <div>
                                <label for="brandkit_advanced_surface_bg"><?php echo __('Surface background', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_surface_bg" name="advanced_surface_bg" value="<?php echo htmlescape($advancedSettings['advanced_surface_bg']); ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="brandkit-theme-group">
                        <legend>Destaque</legend>
                        <div class="brandkit-form-grid">
                            <div>
                                <label for="brandkit_advanced_accent_color"><?php echo __('Accent color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_accent_color" name="advanced_accent_color" value="<?php echo htmlescape($advancedSettings['advanced_accent_color']); ?>">
                            </div>
                            <div>
                                <label for="brandkit_advanced_link_color"><?php echo __('Link color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_link_color" name="advanced_link_color" value="<?php echo htmlescape($advancedSettings['advanced_link_color']); ?>">
                            </div>
                            <div>
                                <label for="brandkit_advanced_border_color"><?php echo __('Border color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_border_color" name="advanced_border_color" value="<?php echo htmlescape($advancedSettings['advanced_border_color']); ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="brandkit-theme-group">
                        <legend>Button</legend>
                        <div class="brandkit-form-grid">
                            <div>
                                <label for="brandkit_advanced_button_bg_color"><?php echo __('Button background', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_button_bg_color" name="advanced_button_bg_color" value="<?php echo htmlescape($advancedSettings['advanced_button_bg_color']); ?>">
                            </div>
                            <div>
                                <label for="brandkit_advanced_button_text_color"><?php echo __('Button text color', 'brandkit'); ?></label>
                                <input type="color" id="brandkit_advanced_button_text_color" name="advanced_button_text_color" value="<?php echo htmlescape($advancedSettings['advanced_button_text_color']); ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="brandkit-theme-group">
                        <legend>Layout</legend>
                        <div class="brandkit-form-grid">
                            <div>
                                <label for="brandkit_advanced_density"><?php echo __('Density', 'brandkit'); ?></label>
                                <select id="brandkit_advanced_density" name="advanced_density">
                                    <option value="compact" <?php echo $advancedSettings['advanced_density'] === 'compact' ? 'selected' : ''; ?>><?php echo __('Compact', 'brandkit'); ?></option>
                                    <option value="comfortable" <?php echo $advancedSettings['advanced_density'] === 'comfortable' ? 'selected' : ''; ?>><?php echo __('Comfortable', 'brandkit'); ?></option>
                                    <option value="spacious" <?php echo $advancedSettings['advanced_density'] === 'spacious' ? 'selected' : ''; ?>><?php echo __('Spacious', 'brandkit'); ?></option>
                                </select>
                            </div>
                            <div>
                                <label for="brandkit_advanced_radius"><?php echo __('Border radius', 'brandkit'); ?></label>
                                <div class="brandkit-range">
                                    <input type="range" id="brandkit_advanced_radius" name="advanced_radius" min="0" max="24" step="1" value="<?php echo htmlescape($advancedSettings['advanced_radius']); ?>">
                                    <span class="brandkit-range-value" id="brandkit_advanced_radius_value"><?php echo htmlescape($advancedSettings['advanced_radius']); ?>px</span>
                                </div>
                            </div>
                            <div>
                                <label for="brandkit_advanced_menu_width"><?php echo __('Menu width', 'brandkit'); ?></label>
                                <div class="brandkit-range">
                                    <input type="range" id="brandkit_advanced_menu_width" name="advanced_menu_width" min="220" max="340" step="5" value="<?php echo htmlescape($advancedSettings['advanced_menu_width']); ?>">
                                    <span class="brandkit-range-value" id="brandkit_advanced_menu_width_value"><?php echo htmlescape($advancedSettings['advanced_menu_width']); ?>px</span>
                                </div>
                            </div>
                            <div>
                                <label for="brandkit_advanced_shadow"><?php echo __('Shadow', 'brandkit'); ?></label>
                                <select id="brandkit_advanced_shadow" name="advanced_shadow">
                                    <option value="none" <?php echo $advancedSettings['advanced_shadow'] === 'none' ? 'selected' : ''; ?>><?php echo __('None', 'brandkit'); ?></option>
                                    <option value="soft" <?php echo $advancedSettings['advanced_shadow'] === 'soft' ? 'selected' : ''; ?>><?php echo __('Soft', 'brandkit'); ?></option>
                                    <option value="strong" <?php echo $advancedSettings['advanced_shadow'] === 'strong' ? 'selected' : ''; ?>><?php echo __('Strong', 'brandkit'); ?></option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="brandkit-theme-group brandkit-theme-group--links">
                        <legend>Fealq links</legend>
                        <div class="brandkit-form-grid">
                            <div class="brandkit-link-item">
                                <label for="brandkit_brand_site_url"><?php echo __('Site', 'brandkit'); ?></label>
                                <input type="url" id="brandkit_brand_site_url" name="brand_site_url" value="<?php echo htmlescape($advancedSettings['brand_site_url']); ?>">
                                <a class="brandkit-field-link" href="<?php echo htmlescape($advancedSettings['brand_site_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo __('Open site', 'brandkit'); ?></a>
                            </div>
                            <div class="brandkit-link-item">
                                <label for="brandkit_brand_whatsapp"><?php echo __('WhatsApp', 'brandkit'); ?></label>
                                <input type="text" id="brandkit_brand_whatsapp" name="brand_whatsapp" value="<?php echo htmlescape($advancedSettings['brand_whatsapp']); ?>">
                                <a class="brandkit-field-link" href="<?php echo htmlescape($whatsappUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
                            </div>
                            <div class="brandkit-link-item">
                                <label for="brandkit_brand_linkedin_url"><?php echo __('LinkedIn', 'brandkit'); ?></label>
                                <input type="url" id="brandkit_brand_linkedin_url" name="brand_linkedin_url" value="<?php echo htmlescape($advancedSettings['brand_linkedin_url']); ?>">
                                <a class="brandkit-field-link" href="<?php echo htmlescape($advancedSettings['brand_linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir LinkedIn</a>
                            </div>
                            <div class="brandkit-link-item">
                                <label for="brandkit_brand_instagram_handle"><?php echo __('Instagram', 'brandkit'); ?></label>
                                <input type="text" id="brandkit_brand_instagram_handle" name="brand_instagram_handle" value="<?php echo htmlescape($advancedSettings['brand_instagram_handle']); ?>">
                                <a class="brandkit-field-link" href="<?php echo htmlescape($instagramUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir Instagram</a>
                            </div>
                        </div>
                    </fieldset>
                    <input type="hidden" name="advanced_theme_preset" value="<?php echo htmlescape($advancedSettings['advanced_theme_preset']); ?>">
                </div>

                <div class="brandkit-actions" style="margin-top: 18px;">
                    <button class="submit brandkit-save-login" type="submit"><?php echo __('Save advanced settings', 'brandkit'); ?></button>
                </div>
            </form>

            <div class="brandkit-section">
                <h4><?php echo __('Import / export', 'brandkit'); ?></h4>
                <div class="brandkit-form-grid">
                    <form method="post" action="<?php echo $baseUrl; ?>?step=3">
                        <input type="hidden" name="brandkit_action" value="export_config">
                        <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                        <button class="brandkit-cta primary" type="submit"><?php echo __('Export JSON', 'brandkit'); ?></button>
                    </form>
                    <form method="post" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>?step=3">
                        <input type="hidden" name="brandkit_action" value="import_config">
                        <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                        <label for="brandkit_config_file"><?php echo __('Import JSON file', 'brandkit'); ?></label>
                        <input type="file" id="brandkit_config_file" name="brandkit_config_file" accept="application/json,.json">
                        <button class="brandkit-cta secondary" type="submit"><?php echo __('Import', 'brandkit'); ?></button>
                    </form>
                    <form method="post" action="<?php echo $baseUrl; ?>?step=3">
                        <input type="hidden" name="brandkit_action" value="reset_advanced_settings">
                        <input type="hidden" name="_glpi_csrf_token" value="<?php echo brandkit_get_csrf_token(); ?>">
                        <button class="brandkit-cta secondary" type="submit"><?php echo __('Reset advanced settings', 'brandkit'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php

Html::footer();

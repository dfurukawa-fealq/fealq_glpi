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

include_once __DIR__ . '/paths.php';

if (!function_exists('htmlescape')) {
    function htmlescape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('brandkit_get_csrf_token')) {
    function brandkit_get_csrf_token(): string
    {
        if (!class_exists('Session') || !method_exists('Session', 'getNewCSRFToken')) {
            return '';
        }

        try {
            $method = new ReflectionMethod('Session', 'getNewCSRFToken');
            if ($method->getNumberOfParameters() >= 1) {
                return (string) Session::getNewCSRFToken(true);
            }
        } catch (ReflectionException $e) {
            return (string) Session::getNewCSRFToken();
        }

        return (string) Session::getNewCSRFToken();
    }
}


if (!function_exists('brandkit_get_plugin_storage_dir')) {
    function brandkit_get_plugin_storage_dir(): ?string
    {
        if (defined('GLPI_VAR_DIR')) {
            $base = rtrim(GLPI_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '_plugins';
            if (is_dir($base) || @mkdir($base, 0755, true)) {
                $forcedDir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'brandkit';
                if (is_dir($forcedDir) || @mkdir($forcedDir, 0755, true)) {
                    return $forcedDir;
                }
            }
        }

        if (defined('GLPI_PLUGIN_DOC_DIR')) {
            $base = rtrim(GLPI_PLUGIN_DOC_DIR, DIRECTORY_SEPARATOR);
            $dir = $base . DIRECTORY_SEPARATOR . 'brandkit';
            if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                return $dir;
            }
        }

        return null;
    }
}

if (!function_exists('brandkit_get_plugin_storage_dir_existing')) {
    function brandkit_get_plugin_storage_dir_existing(): ?string
    {
        if (defined('GLPI_VAR_DIR')) {
            $base = rtrim(GLPI_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '_plugins';
            if (is_dir($base)) {
                $forcedDir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'brandkit';
                if (is_dir($forcedDir)) {
                    return $forcedDir;
                }
            }
        }

        if (defined('GLPI_PLUGIN_DOC_DIR')) {
            $base = rtrim(GLPI_PLUGIN_DOC_DIR, DIRECTORY_SEPARATOR);
            $dir = $base . DIRECTORY_SEPARATOR . 'brandkit';
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }
}

if (!function_exists('brandkit_get_logo_targets')) {
    function brandkit_get_logo_targets(): array
    {
        return [
            'logo-GLPI-100-white.png',
            'logo-G-100-white.png',
            'logo-GLPI-100-black.png',
            'logo-G-100-black.png',
            'logo-GLPI-250-black.png',
            'logo-GLPI-250-white.png',
            'favicon.ico',
        ];
    }
}

if (!function_exists('brandkit_get_plugin_original_logo_path')) {
    function brandkit_get_plugin_original_logo_path(string $targetFile): ?string
    {
        $path = dirname(__DIR__) . '/pics/' . $targetFile;
        return is_file($path) ? $path : null;
    }
}

if (!function_exists('brandkit_get_native_login_logo_path')) {
    function brandkit_get_native_login_logo_path(string $targetFile): ?string
    {
        if (!defined('GLPI_ROOT')) {
            return null;
        }

        $map = [
            'logo-GLPI-250-black.png' => 'logo-GLPI-250-black.png',
            'logo-GLPI-250-white.png' => 'logo-GLPI-250-white.png',
        ];

        if (!isset($map[$targetFile])) {
            return null;
        }

        $logoDir = brandkit_get_pics_logos_dir();
        if (!$logoDir) {
            return null;
        }

        return $logoDir . '/' . $map[$targetFile];
    }
}

if (!function_exists('brandkit_get_native_favicon_path')) {
    function brandkit_get_native_favicon_path(): ?string
    {
        if (!defined('GLPI_ROOT')) {
            return null;
        }

        $root = rtrim(GLPI_ROOT, DIRECTORY_SEPARATOR);
        $legacyPics = $root . DIRECTORY_SEPARATOR . 'pics';
        $publicPics = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'pics';

        if (!brandkit_is_glpi11()) {
            if (is_dir($legacyPics)) {
                return $legacyPics . DIRECTORY_SEPARATOR . 'favicon.ico';
            }
        } else {
            if (is_dir($publicPics)) {
                return $publicPics . DIRECTORY_SEPARATOR . 'favicon.ico';
            }
        }

        $picsDir = brandkit_get_pics_dir();
        if ($picsDir) {
            return $picsDir . DIRECTORY_SEPARATOR . 'favicon.ico';
        }

        return null;
    }
}

if (!function_exists('brandkit_get_native_header_logo_path')) {
    function brandkit_get_native_header_logo_path(string $targetFile): ?string
    {
        if (!defined('GLPI_ROOT')) {
            return null;
        }

        $map = [
            'logo-GLPI-100-white.png' => 'logo-GLPI-100-white.png',
            'logo-G-100-white.png' => 'logo-G-100-white.png',
            'logo-GLPI-100-black.png' => 'logo-GLPI-100-black.png',
            'logo-G-100-black.png' => 'logo-G-100-black.png',
        ];

        if (!isset($map[$targetFile])) {
            return null;
        }

        $logoDir = brandkit_get_pics_logos_dir();
        if (!$logoDir) {
            return null;
        }

        return $logoDir . '/' . $map[$targetFile];
    }
}

if (!function_exists('brandkit_restore_original_logo_assets')) {
    function brandkit_restore_original_logo_assets(string $targetFile, ?string $logosDir = null): array
    {
        $errors = [];

        if ($logosDir) {
            $storedLogo = $logosDir . '/' . $targetFile;
            if (is_file($storedLogo)) {
                @unlink($storedLogo);
            }
        }

        if ($targetFile === 'favicon.ico') {
            $nativeFavicon = brandkit_get_native_favicon_path();
            $backupFavicon = $logosDir ? $logosDir . '/favicon.ico.bak' : null;
            $originalFavicon = brandkit_get_plugin_original_logo_path($targetFile);
            if ($nativeFavicon) {
                $restored = false;
                if ($originalFavicon && @copy($originalFavicon, $nativeFavicon)) {
                    $restored = true;
                } elseif ($backupFavicon && is_file($backupFavicon) && @copy($backupFavicon, $nativeFavicon)) {
                    $restored = true;
                }
                if (!$restored) {
                    $errors[] = 'favicon_restore_failed';
                }
            }
        }

        $nativeLoginLogo = brandkit_get_native_login_logo_path($targetFile);
        if ($nativeLoginLogo) {
            $originalLoginLogo = brandkit_get_plugin_original_logo_path($targetFile);
            if ($originalLoginLogo) {
                if (!@copy($originalLoginLogo, $nativeLoginLogo)) {
                    $errors[] = 'login_restore_failed';
                }
            } else {
                $errors[] = 'login_original_missing';
            }
        }

        $nativeHeaderLogo = brandkit_get_native_header_logo_path($targetFile);
        if ($nativeHeaderLogo) {
            $originalHeaderLogo = brandkit_get_plugin_original_logo_path($targetFile);
            if ($originalHeaderLogo) {
                if (!@copy($originalHeaderLogo, $nativeHeaderLogo)) {
                    $errors[] = 'header_restore_failed';
                }
            } else {
                $errors[] = 'header_original_missing';
            }
        }

        return $errors;
    }
}

if (!function_exists('brandkit_restore_original_glpi_assets')) {
    function brandkit_restore_original_glpi_assets(): void
    {
        $storageDir = brandkit_get_plugin_storage_dir_existing();
        $logosDir = $storageDir ? $storageDir . '/logos' : null;

        foreach (brandkit_get_logo_targets() as $targetFile) {
            brandkit_restore_original_logo_assets($targetFile, $logosDir);
        }
    }
}

if (!function_exists('brandkit_get_plugin_image_url')) {
    function brandkit_get_plugin_image_url(string $folder, string $name, ?int $version = null): string
    {
        $frontBase = brandkit_get_front_web_base();
        if ($frontBase === '') {
            $frontBase = '/front';
        }
        $url = rtrim($frontBase, '/') . '/pluginimage.send.php?plugin=brandkit&folder=' . rawurlencode($folder)
            . '&name=' . rawurlencode($name);
        if ($version !== null) {
            $url .= '&v=' . $version;
        }

        return $url;
    }
}

if (!function_exists('brandkit_get_wallpaper_image_url')) {
    function brandkit_get_wallpaper_image_url(string $name, ?int $version = null): string
    {
        $base = brandkit_get_pics_web_base();
        if ($base === '') {
            $base = '/pics';
        }
        $url = rtrim($base, '/') . '/brandkit-wallpapers/' . rawurlencode($name);
        if ($version !== null) {
            $url .= '?v=' . $version;
        }

        return $url;
    }
}

if (!function_exists('brandkit_normalize_image_url')) {
    function brandkit_normalize_image_url(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F"\'<>\\\\]/', $url)) {
            return '';
        }

        if (preg_match('/^https:\/\//i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        }

        if ($url[0] === '/' && substr($url, 0, 2) !== '//') {
            return $url;
        }

        return '';
    }
}

if (!function_exists('brandkit_css_url')) {
    function brandkit_css_url(?string $url): string
    {
        $url = brandkit_normalize_image_url($url);
        if ($url === '') {
            return 'none';
        }

        return 'url("' . str_replace(['\\', '"'], ['\\\\', '\\"'], $url) . '")';
    }
}

if (!function_exists('brandkit_get_logo_url_config_keys')) {
    function brandkit_get_logo_url_config_keys(): array
    {
        return [
            'logo-GLPI-100-white.png' => 'logo_url_glpi_100_white',
            'logo-G-100-white.png' => 'logo_url_g_100_white',
            'logo-GLPI-100-black.png' => 'logo_url_glpi_100_black',
            'logo-G-100-black.png' => 'logo_url_g_100_black',
            'logo-GLPI-250-black.png' => 'logo_url_glpi_250_black',
            'logo-GLPI-250-white.png' => 'logo_url_glpi_250_white',
            'favicon.ico' => 'logo_url_favicon',
        ];
    }
}

if (!function_exists('brandkit_get_logo_url_config_key')) {
    function brandkit_get_logo_url_config_key(string $targetFile): ?string
    {
        $keys = brandkit_get_logo_url_config_keys();
        return $keys[$targetFile] ?? null;
    }
}

if (!function_exists('brandkit_get_configured_logo_urls')) {
    function brandkit_get_configured_logo_urls(): array
    {
        $keys = brandkit_get_logo_url_config_keys();
        $config = Config::getConfigurationValues('plugin:brandkit', array_values($keys));
        $urls = [];

        foreach ($keys as $file => $key) {
            $urls[$file] = brandkit_normalize_image_url($config[$key] ?? '');
        }

        return $urls;
    }
}

if (!function_exists('brandkit_get_configured_logo_url')) {
    function brandkit_get_configured_logo_url(string $targetFile): string
    {
        $key = brandkit_get_logo_url_config_key($targetFile);
        if (!$key) {
            return '';
        }

        $config = Config::getConfigurationValues('plugin:brandkit', [$key]);
        return brandkit_normalize_image_url($config[$key] ?? '');
    }
}

if (!function_exists('brandkit_get_generated_login_css_path')) {
    function brandkit_get_generated_login_css_path(): ?string
    {
        $dir = brandkit_get_plugin_storage_dir();
        return $dir ? rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'brandkit-login.css' : null;
    }
}

if (!function_exists('brandkit_get_generated_login_css_public_url')) {
    function brandkit_get_generated_login_css_public_url(): string
    {
        $root = brandkit_get_glpi_root_doc();
        $url = ($root === '' ? '' : $root) . '/plugins/brandkit/generated/brandkit-login.css';
        $path = brandkit_get_generated_login_css_path();
        $version = $path && is_file($path) ? (string) filemtime($path) : PLUGIN_BRANDKIT_VERSION;

        return $url . '?bk=' . rawurlencode($version);
    }
}

if (!function_exists('brandkit_get_wallpaper_storage_dir')) {
    function brandkit_get_wallpaper_storage_dir(): ?string
    {
        $picsDir = brandkit_get_pics_dir();
        if ($picsDir) {
            $dir = $picsDir . DIRECTORY_SEPARATOR . 'brandkit-wallpapers';
            if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                return $dir;
            }
        }

        $base = brandkit_get_plugin_storage_dir();
        if ($base) {
            $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wallpaper';
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }
}

if (!function_exists('brandkit_get_wallpaper_path')) {
    function brandkit_get_wallpaper_path(string $filename): ?string
    {
        $dir = brandkit_get_wallpaper_storage_dir();
        if ($dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        $base = brandkit_get_plugin_storage_dir();
        if ($base) {
            $legacyDir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wallpaper';
            $legacyPath = $legacyDir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($legacyPath)) {
                $picsDir = brandkit_get_pics_dir();
                if ($picsDir) {
                    $publicDir = $picsDir . DIRECTORY_SEPARATOR . 'brandkit-wallpapers';
                    if (is_dir($publicDir) || @mkdir($publicDir, 0755, true)) {
                        $publicPath = $publicDir . DIRECTORY_SEPARATOR . $filename;
                        if (!is_file($publicPath)) {
                            @copy($legacyPath, $publicPath);
                        }
                        if (is_file($publicPath)) {
                            return $publicPath;
                        }
                    }
                }

                return $legacyPath;
            }
        }

        return null;
    }
}

if (!function_exists('brandkit_resolve_wallpaper_filename')) {
    function brandkit_resolve_wallpaper_filename(?string $filename): string
    {
        $candidates = [];
        if ($filename) {
            $candidates[] = $filename;
            $base = preg_replace('/\.[^.]+$/', '', $filename);
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                $candidates[] = $base . '.' . $ext;
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $path = brandkit_get_wallpaper_path($candidate);
            if ($path && is_file($path)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('brandkit_get_wallpaper_presets')) {
    function brandkit_get_wallpaper_presets(): array
    {
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR . 'wallpapers';
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $presets = [];

        if (!is_dir($baseDir)) {
            return $presets;
        }

        foreach (scandir($baseDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $baseDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }
            $presets[] = $entry;
        }

        sort($presets, SORT_NATURAL | SORT_FLAG_CASE);
        return $presets;
    }
}

if (!function_exists('brandkit_get_wallpaper_preset_default')) {
    function brandkit_get_wallpaper_preset_default(array $presets): string
    {
        $preferred = 'top-view-of-office.png';
        if (in_array($preferred, $presets, true)) {
            return $preferred;
        }

        return $presets[0] ?? '';
    }
}


if (!function_exists('brandkit_can_process_images')) {
    function brandkit_can_process_images(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled');
    }
}

if (!function_exists('brandkit_convert_image_to_png')) {
    function brandkit_convert_image_to_png(string $source, string $destination): bool
    {
        if (!brandkit_can_process_images() || !function_exists('imagecreatefromstring') || !is_file($source)) {
            return false;
        }

        $data = @file_get_contents($source);
        if ($data === false) {
            return false;
        }

        $image = @imagecreatefromstring($data);
        if (!$image) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $saved = @imagepng($image, $destination, 6);
        imagedestroy($image);

        return $saved;
    }
}

if (!function_exists('brandkit_guess_mime_from_filename')) {
    function brandkit_guess_mime_from_filename(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'webp':
                return 'image/webp';
            default:
                return 'image/jpeg';
        }
    }
}

if (!function_exists('brandkit_guess_mime_from_url')) {
    function brandkit_guess_mime_from_url(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return brandkit_guess_mime_from_filename(is_string($path) ? $path : '');
    }
}

if (!function_exists('brandkit_resize_image')) {
    function brandkit_resize_image(string $source, string $destination, int $maxWidth = 1920, int $maxHeight = 1080, int $quality = 80): bool
    {
        if (!brandkit_can_process_images() || !is_file($source)) {
            return false;
        }

        $info = @getimagesize($source);
        if (!$info || empty($info['mime'])) {
            return false;
        }

        $mime = $info['mime'];
        $createMap = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        $saveMap = [
            'image/jpeg' => 'imagejpeg',
            'image/png' => 'imagepng',
            'image/webp' => 'imagewebp',
        ];
        if (!isset($createMap[$mime], $saveMap[$mime])) {
            return false;
        }
        if (!function_exists($createMap[$mime]) || !function_exists($saveMap[$mime])) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = (int) round($width * $ratio);
        $targetHeight = (int) round($height * $ratio);

        $srcImage = @$createMap[$mime]($source);
        if (!$srcImage) {
            return false;
        }

        $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$destImage) {
            imagedestroy($srcImage);
            return false;
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            imagefilledrectangle($destImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $resampled = imagecopyresampled(
            $destImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        $saved = false;
        if ($resampled) {
            if ($mime === 'image/jpeg') {
                $saved = $saveMap[$mime]($destImage, $destination, max(40, min(95, $quality)));
            } elseif ($mime === 'image/png') {
                $compression = 6;
                $saved = $saveMap[$mime]($destImage, $destination, $compression);
            } else {
                $saved = $saveMap[$mime]($destImage, $destination, max(40, min(95, $quality)));
            }
        }

        imagedestroy($srcImage);
        imagedestroy($destImage);

        return $saved;
    }
}

if (!function_exists('brandkit_resize_image_to_webp')) {
    function brandkit_resize_image_to_webp(string $source, string $destination, int $maxWidth = 1920, int $maxHeight = 1080, int $quality = 80): bool
    {
        if (!brandkit_can_process_images() || !function_exists('imagewebp') || !is_file($source)) {
            return false;
        }

        $info = @getimagesize($source);
        if (!$info || empty($info['mime'])) {
            return false;
        }

        $mime = $info['mime'];
        $createMap = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        if (!isset($createMap[$mime]) || !function_exists($createMap[$mime])) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = (int) round($width * $ratio);
        $targetHeight = (int) round($height * $ratio);

        $srcImage = @$createMap[$mime]($source);
        if (!$srcImage) {
            return false;
        }

        $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$destImage) {
            imagedestroy($srcImage);
            return false;
        }

        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
        imagefilledrectangle($destImage, 0, 0, $targetWidth, $targetHeight, $transparent);

        $resampled = imagecopyresampled(
            $destImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        $saved = false;
        if ($resampled) {
            $saved = imagewebp($destImage, $destination, max(40, min(95, $quality)));
        }

        imagedestroy($srcImage);
        imagedestroy($destImage);

        return $saved;
    }
}

if (!function_exists('brandkit_get_wallpaper_preset_assets')) {
    function brandkit_get_wallpaper_preset_assets(string $presetFile): array
    {
        $presetDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR . 'wallpapers';
        $presetPath = $presetDir . DIRECTORY_SEPARATOR . $presetFile;
        if (!is_file($presetPath)) {
            return ['url' => '', 'webp_url' => '', 'mime' => 'image/jpeg'];
        }

        $picsDir = brandkit_get_pics_dir();
        $publicDir = $picsDir ? $picsDir . DIRECTORY_SEPARATOR . 'brandkit-wallpapers' : null;
        $publicPath = $publicDir ? $publicDir . DIRECTORY_SEPARATOR . $presetFile : null;
        $presetMtime = (int) filemtime($presetPath);

        if ($publicDir && (is_dir($publicDir) || @mkdir($publicDir, 0755, true))) {
            $needsCopy = !is_file($publicPath);
            if (!$needsCopy && $publicPath) {
                $publicMtime = (int) filemtime($publicPath);
                $needsCopy = $publicMtime < $presetMtime;
            }
            if ($needsCopy && $publicPath) {
                if (!brandkit_resize_image($presetPath, $publicPath, 1920, 1080, 80)) {
                    @copy($presetPath, $publicPath);
                }
            }
            if ($publicPath && is_file($publicPath)) {
                $publicMtime = (int) filemtime($publicPath);
                $url = brandkit_get_pics_web_base() . '/brandkit-wallpapers/' . rawurlencode($presetFile) . '?v=' . $publicMtime;
                return [
                    'url' => $url,
                    'webp_url' => '',
                    'mime' => brandkit_guess_mime_from_filename($presetFile),
                ];
            }
        }

        $pluginWebBase = rtrim(Plugin::getWebDir('brandkit', true), '/');
        return [
            'url' => $pluginWebBase . '/pics/wallpapers/' . rawurlencode($presetFile) . '?v=' . $presetMtime,
            'webp_url' => '',
            'mime' => brandkit_guess_mime_from_filename($presetFile),
        ];
    }
}

if (!function_exists('brandkit_get_wallpaper_preset_url')) {
    function brandkit_get_wallpaper_preset_url(string $presetFile): string
    {
        $assets = brandkit_get_wallpaper_preset_assets($presetFile);
        return $assets['url'] ?? '';
    }
}

function brandkit_get_login_defaults(): array
{
    $defaults = [
        'login_layout' => 'split-left',
        'login_panel_transparent' => '1',
        'login_panel_hover_effect' => '0',
        'login_panel_opacity' => '65',
        'login_panel_color' => '#0f172a',
        'login_panel_blur' => '6',
        'login_panel_width' => '520',
        'login_panel_height' => '560',
        'login_panel_scrollbar' => 'auto',
        'login_text_color' => '#ffffff',
        'login_logo_position' => 'outside',
        'login_logo_offset' => '0',
        'login_split_opacity' => '40',
        'login_split_color' => '#ffffff',
        'login_split_blur' => '3',
        'login_split_width' => '41',
        'login_button_style' => 'primary',
        'login_button_shape' => 'rounded',
        'login_button_color_mode' => 'preset',
        'login_button_color' => '#0ea5e9',
        'login_button_custom_style' => 'default',
        'login_button_hover_effect' => 'press',
        'login_button_hover_fill_mode' => 'button',
        'login_button_hover_fill_color' => '#0ea5e9',
        'login_button_hover_text_color' => '#ffffff',
        'login_button_text_size' => '20',
        'login_field_style' => 'solid',
        'login_field_bg' => '#ffffff',
        'login_field_border' => '#ffffff',
        'login_field_border_width' => '2',
        'login_field_text' => '#000000',
        'login_field_text_size' => '20',
        'login_field_height' => '50',
        'login_field_width' => '520',
        'login_text_size' => '20',
        'login_field_focus' => '#0ea5e9',
        'login_accent_color' => '#000000',
        'login_icon_user' => 'user',
        'login_icon_password' => 'lock',
        'login_icon_color' => '#000000',
        'login_icon_position' => 'left',
        'login_field_radius' => '10',
        'login_wallpaper_size' => 'cover',
        'login_wallpaper_position' => 'center',
        'login_wallpaper_attachment' => 'fixed',
        'login_wallpaper_overlay_color' => '#000000',
        'login_wallpaper_overlay_opacity' => '20',
        'login_hide_login_text' => '0',
        'login_hide_copyright' => '0',
        'login_wallpaper_source' => 'preset',
        'login_wallpaper_preset' => 'top-view-of-office.png',
        'login_wallpaper_url' => '',
        'login_wallpaper' => '',
        'login_logo_width' => '200',
        'login_logo_height' => '110',
        'login_logo_scale' => '290',
        'login_apply_error_page' => '1',
    ];

    if (brandkit_is_glpi11()) {
        $defaults['login_field_width'] = '280';
    } else {
        $defaults['login_field_width'] = '280';
        $defaults['login_logo_width'] = '200';
        $defaults['login_field_border_width'] = '0';
        $defaults['login_text_size'] = '14';
        $defaults['login_title_size'] = '20';
        $defaults['login_field_text_size'] = '15';
        $defaults['login_field_height'] = '40';
        $defaults['login_field_spacing'] = '20';
        $defaults['login_button_text_size'] = '15';
    }

    return $defaults;
}

function brandkit_get_login_button_styles(): array
{
    return [
        'primary',
        'secondary',
        'success',
        'danger',
        'warning',
        'info',
        'dark',
        'light',
        'outline-primary',
        'outline-secondary',
        'outline-success',
        'outline-danger',
        'outline-warning',
        'outline-info',
        'outline-dark',
    ];
}

function brandkit_get_login_button_shapes(): array
{
    return [
        'square',
        'rounded',
        'pill',
        'cut',
        'soft',
    ];
}

function brandkit_get_login_settings(): array
{
    $defaults = brandkit_get_login_defaults();
    $config = Config::getConfigurationValues('plugin:brandkit', array_keys($defaults));

    $settings = array_merge(
        $defaults,
        array_filter($config, static fn($value) => $value !== null && $value !== '')
    );

    if (empty($config['login_wallpaper_source']) && !empty($settings['login_wallpaper'])) {
        $settings['login_wallpaper_source'] = 'custom';
    }

    if (empty($config['login_wallpaper_preset'])) {
        $settings['login_wallpaper_preset'] = $defaults['login_wallpaper_preset'] ?? 'top-view-of-office.png';
    }

    $settings['login_wallpaper_url'] = brandkit_normalize_image_url($settings['login_wallpaper_url'] ?? '');
    if (!empty($settings['login_wallpaper_url'])) {
        $settings['login_wallpaper_source'] = 'url';
    }

    $resolvedWallpaper = brandkit_resolve_wallpaper_filename($settings['login_wallpaper'] ?? '');
    if ($resolvedWallpaper !== '') {
        $settings['login_wallpaper'] = $resolvedWallpaper;
        if (empty($settings['login_wallpaper_source'])) {
            $settings['login_wallpaper_source'] = 'custom';
        }
    }

    if (!empty($settings['login_wallpaper']) && ($settings['login_wallpaper_source'] ?? '') === 'custom') {
        $wallpaperPath = brandkit_get_wallpaper_path($settings['login_wallpaper']);
        if ($wallpaperPath && is_file($wallpaperPath)) {
            $settings['login_wallpaper_source'] = 'custom';
        }
    }

    return $settings;
}

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

function brandkit_svg_data_uri(string $svg): string
{
    $encoded = rawurlencode($svg);
    $encoded = str_replace(['%0A', '%0D'], '', $encoded);
    return "url(\"data:image/svg+xml,{$encoded}\")";
}

function brandkit_normalize_hex_color($value, string $fallback): string
{
    if (!is_string($value)) {
        return $fallback;
    }

    $value = trim($value);
    if (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
        return $fallback;
    }

    if (strlen($value) === 4) {
        $value = sprintf(
            '#%s%s%s',
            $value[1] . $value[1],
            $value[2] . $value[2],
            $value[3] . $value[3]
        );
    }

    return strtolower($value);
}

function brandkit_hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function brandkit_get_contrast_color(string $hex): string
{
    [$r, $g, $b] = brandkit_hex_to_rgb($hex);
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

    return $luma > 0.6 ? '#0f172a' : '#ffffff';
}

function brandkit_build_login_css(array $settings): string
{
    $isGlpi11 = brandkit_is_glpi11();

    $layout = $settings['login_layout'];
    if (!in_array($layout, ['split-left', 'split-right', 'classic'], true)) {
        $layout = 'split-left';
    }

    $panelTransparent = $settings['login_panel_transparent'] === '1';
    $panelHoverEffect = ($settings['login_panel_hover_effect'] ?? '1') === '1';
    $panelOpacity = brandkit_clamp_int($settings['login_panel_opacity'], 0, 100, 65);
    $panelBlur = brandkit_clamp_int($settings['login_panel_blur'], 0, 24, 6);
    $panelWidth = brandkit_clamp_int($settings['login_panel_width'] ?? null, 280, 720, 520);
    $panelHeight = brandkit_clamp_int($settings['login_panel_height'] ?? null, 360, 780, 560);
    $panelScale = $isGlpi11 ? min(1, $panelHeight / 780) : 1;
    $panelScrollbar = 'auto';
    $panelColorHex = brandkit_normalize_hex_color($settings['login_panel_color'], '#0f172a');
    [$panelR, $panelG, $panelB] = brandkit_hex_to_rgb($panelColorHex);
    $panelRgba = sprintf('rgba(%d, %d, %d, %.2f)', $panelR, $panelG, $panelB, $panelOpacity / 100);
    $textColor = brandkit_normalize_hex_color($settings['login_text_color'] ?? '#ffffff', '#ffffff');

    $splitOpacity = brandkit_clamp_int($settings['login_split_opacity'], 0, 100, 55);
    $splitBlur = brandkit_clamp_int($settings['login_split_blur'], 0, 24, 10);
    $splitColorHex = brandkit_normalize_hex_color($settings['login_split_color'], '#0f172a');
    [$splitR, $splitG, $splitB] = brandkit_hex_to_rgb($splitColorHex);
    $splitRgba = sprintf('rgba(%d, %d, %d, %.2f)', $splitR, $splitG, $splitB, $splitOpacity / 100);
    $splitWidth = brandkit_clamp_int($settings['login_split_width'], 0, 100, 50);
    if ($layout === 'classic') {
        $splitWidth = 0;
    }

    $loginLogoWidth = brandkit_clamp_int($settings['login_logo_width'] ?? null, 80, 480, 200);
    $loginLogoHeight = brandkit_clamp_int($settings['login_logo_height'] ?? null, 40, 320, 110);
    $loginLogoScale = brandkit_clamp_int($settings['login_logo_scale'] ?? null, 80, 480, 200);
    $logoScaleFactor = $loginLogoWidth > 0 ? ($loginLogoScale / $loginLogoWidth) : 1;
    $loginLogoWidth = max(1, (int) round($loginLogoWidth * $logoScaleFactor));
    $loginLogoHeight = max(1, (int) round($loginLogoHeight * $logoScaleFactor));
    $loginLogoOffset = brandkit_clamp_int($settings['login_logo_offset'] ?? null, 0, 240, 0);
    $logoPosition = $settings['login_logo_position'] ?? 'outside';
    if (!in_array($logoPosition, ['outside', 'inside'], true)) {
        $logoPosition = 'outside';
    }

    $buttonColorMode = $settings['login_button_color_mode'] ?? 'preset';
    if (!in_array($buttonColorMode, ['preset', 'custom'], true)) {
        $buttonColorMode = 'preset';
    }
    $buttonStyle = $settings['login_button_style'] ?? 'primary';
    if (!in_array($buttonStyle, brandkit_get_login_button_styles(), true)) {
        $buttonStyle = 'primary';
    }
    $buttonPalette = [
        'primary' => ['color' => '#0d6efd', 'outline' => false],
        'secondary' => ['color' => '#6c757d', 'outline' => false],
        'success' => ['color' => '#198754', 'outline' => false],
        'danger' => ['color' => '#dc3545', 'outline' => false],
        'warning' => ['color' => '#ffc107', 'outline' => false],
        'info' => ['color' => '#0dcaf0', 'outline' => false],
        'dark' => ['color' => '#212529', 'outline' => false],
        'light' => ['color' => '#f8f9fa', 'outline' => false],
        'outline-primary' => ['color' => '#0d6efd', 'outline' => true],
        'outline-secondary' => ['color' => '#6c757d', 'outline' => true],
        'outline-success' => ['color' => '#198754', 'outline' => true],
        'outline-danger' => ['color' => '#dc3545', 'outline' => true],
        'outline-warning' => ['color' => '#ffc107', 'outline' => true],
        'outline-info' => ['color' => '#0dcaf0', 'outline' => true],
        'outline-dark' => ['color' => '#212529', 'outline' => true],
    ];
    $buttonOutline = false;
    if ($buttonColorMode === 'custom') {
        $buttonColorHex = brandkit_normalize_hex_color($settings['login_button_color'] ?? '#0ea5e9', '#0ea5e9');
        $customStyle = $settings['login_button_custom_style'] ?? 'default';
        $buttonOutline = $customStyle === 'outline';
    } else {
        $buttonColorHex = brandkit_normalize_hex_color($settings['login_accent_color'] ?? '#0ea5e9', '#0ea5e9');
        $customStyle = $settings['login_button_custom_style'] ?? 'default';
        $buttonOutline = $customStyle === 'outline';
    }
    $buttonTextColor = $buttonOutline ? $buttonColorHex : brandkit_get_contrast_color($buttonColorHex);
    $buttonHoverFillMode = $settings['login_button_hover_fill_mode'] ?? 'button';
    if (!in_array($buttonHoverFillMode, ['button', 'custom'], true)) {
        $buttonHoverFillMode = 'button';
    }
    $buttonHoverFillColor = brandkit_normalize_hex_color(
        $settings['login_button_hover_fill_color'] ?? $buttonColorHex,
        $buttonColorHex
    );
    $buttonHoverFill = $buttonHoverFillMode === 'custom' ? $buttonHoverFillColor : $buttonColorHex;
    $buttonHoverTextColor = brandkit_normalize_hex_color(
        $settings['login_button_hover_text_color'] ?? '',
        brandkit_get_contrast_color($buttonHoverFill)
    );
    $buttonTextSizeDefault = $isGlpi11 ? 14 : 15;
    $buttonTextSize = brandkit_clamp_int($settings['login_button_text_size'] ?? null, 10, 28, $buttonTextSizeDefault);
    $buttonBackground = $buttonOutline ? 'transparent' : $buttonColorHex;
    $buttonBorder = $buttonColorHex;
    $buttonShape = $settings['login_button_shape'] ?? 'rounded';
    if (!in_array($buttonShape, brandkit_get_login_button_shapes(), true)) {
        $buttonShape = 'rounded';
    }
    $shapeRadiusMap = [
        'square' => '4px',
        'rounded' => '10px',
        'pill' => '999px',
        'cut' => '6px 18px 6px 18px',
        'soft' => '14px',
    ];
    $shapeClipMap = [
        'cut' => 'polygon(0 0, 92% 0, 100% 35%, 100% 100%, 8% 100%, 0 65%)',
    ];
    $buttonRadius = $shapeRadiusMap[$buttonShape] ?? '10px';
    $buttonClip = $shapeClipMap[$buttonShape] ?? 'none';
    $buttonHoverEffect = $settings['login_button_hover_effect'] ?? 'press';
    if (!in_array($buttonHoverEffect, ['press', 'fill'], true)) {
        $buttonHoverEffect = 'press';
    }

    $fieldStyle = $settings['login_field_style'] ?? 'glass';
    $allowedFieldStyles = ['solid', 'glass', 'soft', 'outline', 'underline', 'neon'];
    if (!in_array($fieldStyle, $allowedFieldStyles, true)) {
        $fieldStyle = 'glass';
    }
    $fieldBg = brandkit_normalize_hex_color($settings['login_field_bg'] ?? '#ffffff', '#ffffff');
    $fieldBorder = brandkit_normalize_hex_color($settings['login_field_border'] ?? '#e2e8f0', '#e2e8f0');
    $fieldText = brandkit_normalize_hex_color($settings['login_field_text'] ?? '#000000', '#000000');
    $fieldTextSizeDefault = $isGlpi11 ? 14 : 15;
    $fieldHeightDefault = $isGlpi11 ? 44 : 40;
    $fieldTextSize = brandkit_clamp_int($settings['login_field_text_size'] ?? null, 10, 36, $fieldTextSizeDefault);
    $fieldHeight = brandkit_clamp_int($settings['login_field_height'] ?? null, 32, 72, $fieldHeightDefault);
    $fieldWidthDefault = 280;
    $fieldWidth = brandkit_clamp_int($settings['login_field_width'] ?? null, 260, 720, $fieldWidthDefault);
    $fieldSpacing = null;
    $textSizeDefault = $isGlpi11 ? 15 : 14;
    $textSize = brandkit_clamp_int($settings['login_text_size'] ?? null, 10, 30, $textSizeDefault);
    $titleSize = null;
    if (!$isGlpi11) {
        $fieldSpacing = brandkit_clamp_int($settings['login_field_spacing'] ?? null, 8, 48, 20);
        $titleSize = brandkit_clamp_int($settings['login_title_size'] ?? null, 16, 48, 20);
    }
    $fieldFocus = brandkit_normalize_hex_color($settings['login_field_focus'] ?? '#0ea5e9', '#0ea5e9');
    $fieldBorderWidthDefault = $isGlpi11 ? 1 : 0;
    $fieldBorderWidth = brandkit_clamp_int($settings['login_field_border_width'] ?? null, 0, 6, $fieldBorderWidthDefault);
    $accentColor = brandkit_normalize_hex_color($settings['login_accent_color'] ?? '#0ea5e9', '#0ea5e9');
    $focusBorderColor = $accentColor;
    $focusGlowColor = $fieldStyle === 'neon' ? $fieldFocus : $accentColor;
    $iconColor = brandkit_normalize_hex_color($settings['login_icon_color'] ?? '#0ea5e9', '#0ea5e9');
    $iconPosition = $settings['login_icon_position'] ?? 'right';
    if (!in_array($iconPosition, ['left', 'right'], true)) {
        $iconPosition = 'right';
    }
    $fieldRadius = brandkit_clamp_int($settings['login_field_radius'] ?? null, 0, 32, 12);
    [$fieldTextR, $fieldTextG, $fieldTextB] = brandkit_hex_to_rgb($fieldText);
    $fieldPlaceholder = sprintf('rgba(%d, %d, %d, 0.6)', $fieldTextR, $fieldTextG, $fieldTextB);
    switch ($fieldStyle) {
        case 'soft':
            $fieldShadow = '0 10px 24px rgba(15, 23, 42, 0.12)';
            break;
        case 'glass':
            $fieldShadow = '0 12px 24px rgba(15, 23, 42, 0.18)';
            break;
        case 'neon':
            $fieldShadow = '0 8px 20px rgba(15, 23, 42, 0.16)';
            break;
        default:
            $fieldShadow = 'none';
            break;
    }
    $fieldAutofillBg = 'var(--brandkit-login-field-bg)';

    $iconDefs = [
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c2-4 6-6 8-6s6 2 8 6"/></svg>',
        'id' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4"/><circle cx="10" cy="10" r="2.5"/></svg>',
        'mail' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'lock' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
        'key' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="14.5" r="3.5"/><path d="M11 14h10l-2 2 2 2-2 2"/></svg>',
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6z"/><path d="M9.5 12.5 11 14l3.5-3.5"/></svg>',
    ];
    $iconUser = $settings['login_icon_user'] ?? 'none';
    $iconPassword = $settings['login_icon_password'] ?? 'none';
    $userSvg = $iconDefs[$iconUser] ?? null;
    $passwordSvg = $iconDefs[$iconPassword] ?? null;
    $iconUserUri = $userSvg ? brandkit_svg_data_uri(sprintf($userSvg, $iconColor)) : 'none';
    $iconPasswordUri = $passwordSvg ? brandkit_svg_data_uri(sprintf($passwordSvg, $iconColor)) : 'none';
    $iconUserPadding = $userSvg ? '2.5rem' : '0.75rem';
    $iconPasswordPadding = $passwordSvg ? '2.5rem' : '0.75rem';
    if ($iconPosition === 'left') {
        $iconUserPaddingLeft = $iconUserPadding;
        $iconUserPaddingRight = '0.75rem';
        $iconPasswordPaddingLeft = $iconPasswordPadding;
        $iconPasswordPaddingRight = '0.75rem';
        $iconPositionValue = 'left 0.9rem center';
    } else {
        $iconUserPaddingLeft = '0.75rem';
        $iconUserPaddingRight = $iconUserPadding;
        $iconPasswordPaddingLeft = '0.75rem';
        $iconPasswordPaddingRight = $iconPasswordPadding;
        $iconPositionValue = 'right 0.9rem center';
    }

    $wallpaperSize = $settings['login_wallpaper_size'] ?? 'cover';
    $allowedWallpaperSizes = ['cover', 'contain', 'stretch', 'original'];
    if (!in_array($wallpaperSize, $allowedWallpaperSizes, true)) {
        $wallpaperSize = 'cover';
    }
    switch ($wallpaperSize) {
        case 'contain':
            $wallpaperSizeValue = 'contain';
            break;
        case 'stretch':
            $wallpaperSizeValue = '100% 100%';
            break;
        case 'original':
            $wallpaperSizeValue = 'auto';
            break;
        default:
            $wallpaperSizeValue = 'cover';
            break;
    }

    $wallpaperPosition = $settings['login_wallpaper_position'] ?? 'center';
    $allowedWallpaperPositions = ['center', 'top', 'bottom', 'left', 'right'];
    if (!in_array($wallpaperPosition, $allowedWallpaperPositions, true)) {
        $wallpaperPosition = 'center';
    }

    $wallpaperAttachment = $settings['login_wallpaper_attachment'] ?? 'fixed';
    $allowedWallpaperAttachments = ['fixed', 'scroll'];
    if (!in_array($wallpaperAttachment, $allowedWallpaperAttachments, true)) {
        $wallpaperAttachment = 'fixed';
    }

    $wallpaperOverlayOpacity = brandkit_clamp_int($settings['login_wallpaper_overlay_opacity'] ?? null, 0, 100, 35);
    $wallpaperOverlayColorHex = brandkit_normalize_hex_color($settings['login_wallpaper_overlay_color'] ?? '#000000', '#000000');
    [$overlayR, $overlayG, $overlayB] = brandkit_hex_to_rgb($wallpaperOverlayColorHex);
    $wallpaperOverlayRgba = sprintf('rgba(%d, %d, %d, %.2f)', $overlayR, $overlayG, $overlayB, $wallpaperOverlayOpacity / 100);
    $wallpaperSource = $settings['login_wallpaper_source'] ?? '';
    if ($wallpaperSource === '') {
        $wallpaperSource = !empty($settings['login_wallpaper']) ? 'custom' : 'preset';
    }
    $wallpaperUrlSetting = brandkit_normalize_image_url($settings['login_wallpaper_url'] ?? '');
    if ($wallpaperUrlSetting !== '') {
        $wallpaperSource = 'url';
    }
    if ($wallpaperSource === 'url' && $wallpaperUrlSetting === '') {
        $wallpaperSource = !empty($settings['login_wallpaper']) ? 'custom' : 'preset';
    }
    if (!in_array($wallpaperSource, ['preset', 'custom', 'url'], true)) {
        $wallpaperSource = !empty($settings['login_wallpaper']) ? 'custom' : 'preset';
    }
    if ($wallpaperSource === 'custom' && !empty($settings['login_wallpaper']) && brandkit_get_wallpaper_path($settings['login_wallpaper'])) {
        $wallpaperSource = 'custom';
    }

    $wallpaperPresets = brandkit_get_wallpaper_presets();
    $wallpaperPresetDefault = brandkit_get_wallpaper_preset_default($wallpaperPresets);
    $wallpaperPreset = $settings['login_wallpaper_preset'] ?? $wallpaperPresetDefault;
    if (!in_array($wallpaperPreset, $wallpaperPresets, true)) {
        $wallpaperPreset = $wallpaperPresetDefault;
    }

    $wallpaperUrl = '';
    $wallpaperWebpUrl = '';
    $wallpaperMime = 'image/jpeg';
    if ($wallpaperSource === 'url' && $wallpaperUrlSetting !== '') {
        $wallpaperUrl = $wallpaperUrlSetting;
        $wallpaperMime = brandkit_guess_mime_from_url($wallpaperUrlSetting);
    }
    if ($wallpaperSource === 'preset' && $wallpaperPreset) {
        $presetAssets = brandkit_get_wallpaper_preset_assets($wallpaperPreset);
        $wallpaperUrl = $presetAssets['url'] ?? '';
        $wallpaperWebpUrl = $presetAssets['webp_url'] ?? '';
        $wallpaperMime = $presetAssets['mime'] ?? 'image/jpeg';
    }
    if ($wallpaperSource === 'custom' && !empty($settings['login_wallpaper'])) {
        $wallpaperPath = brandkit_get_wallpaper_path($settings['login_wallpaper']);
        if ($wallpaperPath && is_file($wallpaperPath)) {
            $wallpaperVersion = (int) filemtime($wallpaperPath);
            $wallpaperUrl = brandkit_get_wallpaper_image_url($settings['login_wallpaper'], $wallpaperVersion);
            $wallpaperMime = brandkit_guess_mime_from_filename($settings['login_wallpaper']);
            $wallpaperWebpPath = preg_replace('/\.[^.]+$/', '.webp', $wallpaperPath);
            if ($wallpaperWebpPath && is_file($wallpaperWebpPath)) {
                $wallpaperWebpUrl = brandkit_get_wallpaper_image_url(basename($wallpaperWebpPath), (int) filemtime($wallpaperWebpPath));
            }
        }
    }
    if ($wallpaperUrl === '') {
        $resolvedWallpaper = brandkit_resolve_wallpaper_filename($settings['login_wallpaper'] ?? '');
        if ($resolvedWallpaper !== '') {
            $wallpaperPath = brandkit_get_wallpaper_path($resolvedWallpaper);
            if ($wallpaperPath && is_file($wallpaperPath)) {
                $wallpaperVersion = (int) filemtime($wallpaperPath);
                $wallpaperUrl = brandkit_get_wallpaper_image_url($resolvedWallpaper, $wallpaperVersion);
                $wallpaperMime = brandkit_guess_mime_from_filename($resolvedWallpaper);
                $wallpaperWebpPath = preg_replace('/\.[^.]+$/', '.webp', $wallpaperPath);
                if ($wallpaperWebpPath && is_file($wallpaperWebpPath)) {
                    $wallpaperWebpUrl = brandkit_get_wallpaper_image_url(basename($wallpaperWebpPath), (int) filemtime($wallpaperWebpPath));
                }
            }
        }
    }

    $hideLoginText = ($settings['login_hide_login_text'] ?? '0') === '1';
    $hideCopyright = ($settings['login_hide_copyright'] ?? '0') === '1';

    $layoutVars = [
        'split-left' => [
            'split_width' => "{$splitWidth}vw",
            'split_left' => '0',
            'split_right' => 'auto',
            'container_max' => 'calc(var(--brandkit-login-split-width) - 1.5vw)',
            'container_left' => 'calc(var(--brandkit-login-split-width) / 2)',
            'container_right' => 'auto',
            'container_translate' => '-50%',
            'text_align' => 'left',
        ],
        'split-right' => [
            'split_width' => "{$splitWidth}vw",
            'split_left' => 'auto',
            'split_right' => '0',
            'container_max' => 'calc(var(--brandkit-login-split-width) - 1.5vw)',
            'container_left' => 'auto',
            'container_right' => 'calc(var(--brandkit-login-split-width) / 2)',
            'container_translate' => '50%',
            'text_align' => 'right',
        ],
        'classic' => [
            'split_width' => '0',
            'split_left' => 'auto',
            'split_right' => 'auto',
            'container_max' => '28rem',
            'container_left' => 'auto',
            'container_right' => 'auto',
            'container_translate' => '0',
            'text_align' => 'center',
        ],
    ];

    $vars = $layoutVars[$layout];

    $loginLogoFiles = [
        'logo-GLPI-250-black.png' => '--glpi-logo-dark-login',
        'logo-GLPI-250-white.png' => '--glpi-logo-light-login',
    ];
    $publicLogoDir = brandkit_get_pics_logos_dir();
    $configuredLogoUrls = brandkit_get_configured_logo_urls();

    $css = ":root {\n";
    $css .= "  --brandkit-login-panel-color: {$panelRgba};\n";
    $css .= "  --brandkit-login-panel-blur: {$panelBlur}px;\n";
    $css .= "  --brandkit-login-panel-width: {$panelWidth}px;\n";
    $css .= "  --brandkit-login-panel-height: {$panelHeight}px;\n";
    $css .= "  --brandkit-login-panel-scale: " . number_format($panelScale, 3, '.', '') . ";\n";
    $css .= "  --brandkit-login-panel-overflow: {$panelScrollbar};\n";
    $css .= "  --brandkit-login-text-color: {$textColor};\n";
    $css .= "  --brandkit-login-split-color: {$splitRgba};\n";
    $css .= "  --brandkit-login-split-blur: {$splitBlur}px;\n";
    $css .= "  --brandkit-login-split-width: {$vars['split_width']};\n";
    $css .= "  --brandkit-login-split-left: {$vars['split_left']};\n";
    $css .= "  --brandkit-login-split-right: {$vars['split_right']};\n";
    $css .= "  --brandkit-login-container-max: {$vars['container_max']};\n";
    if ($panelTransparent) {
        $css .= "  --brandkit-login-container-max-effective: var(--brandkit-login-container-max);\n";
    } else {
        $css .= "  --brandkit-login-container-max-effective: max(var(--brandkit-login-container-max), var(--brandkit-login-panel-width));\n";
    }
    $css .= "  --brandkit-login-container-left: {$vars['container_left']};\n";
    $css .= "  --brandkit-login-container-right: {$vars['container_right']};\n";
    $css .= "  --brandkit-login-container-translate: {$vars['container_translate']};\n";
    $css .= "  --brandkit-login-text-align: {$vars['text_align']};\n";
    $css .= "  --brandkit-login-logo-width: {$loginLogoWidth}px;\n";
    $css .= "  --brandkit-login-logo-height: {$loginLogoHeight}px;\n";
    $css .= "  --brandkit-login-logo-offset: {$loginLogoOffset}px;\n";
    $css .= "  --brandkit-login-button-color: {$buttonColorHex};\n";
    $css .= "  --brandkit-login-button-bg: {$buttonBackground};\n";
    $css .= "  --brandkit-login-button-border: {$buttonBorder};\n";
    $css .= "  --brandkit-login-button-text: {$buttonTextColor};\n";
    $css .= "  --brandkit-login-button-hover-fill: {$buttonHoverFill};\n";
    $css .= "  --brandkit-login-button-hover-text: {$buttonHoverTextColor};\n";
    $css .= "  --brandkit-login-button-text-size: {$buttonTextSize}px;\n";
    $css .= "  --brandkit-login-button-radius: {$buttonRadius};\n";
    $css .= "  --brandkit-login-button-clip: {$buttonClip};\n";
    $css .= "  --brandkit-login-field-bg: {$fieldBg};\n";
    $css .= "  --brandkit-login-field-bg-autofill: {$fieldAutofillBg};\n";
    $css .= "  --brandkit-login-field-border: {$fieldBorder};\n";
    $css .= "  --brandkit-login-field-border-width: {$fieldBorderWidth}px;\n";
    $css .= "  --brandkit-login-field-text: {$fieldText};\n";
    $css .= "  --brandkit-login-field-text-autofill: var(--tblr-body-color);\n";
    $css .= "  --brandkit-login-field-text-size: {$fieldTextSize}px;\n";
    $css .= "  --brandkit-login-field-height: {$fieldHeight}px;\n";
    $css .= "  --brandkit-login-field-width: {$fieldWidth}px;\n";
    if (!$isGlpi11 && $fieldSpacing !== null) {
        $css .= "  --brandkit-login-field-spacing: {$fieldSpacing}px;\n";
    }
    $css .= "  --brandkit-login-text-size: {$textSize}px;\n";
    if (!$isGlpi11 && $titleSize !== null) {
        $css .= "  --brandkit-login-title-size: {$titleSize}px;\n";
    }
    $css .= "  --brandkit-login-field-focus: {$fieldFocus};\n";
    $css .= "  --brandkit-login-field-focus-border: {$focusBorderColor};\n";
    $css .= "  --brandkit-login-field-focus-glow: {$focusGlowColor};\n";
    $css .= "  --brandkit-login-accent-color: {$accentColor};\n";
    $css .= "  --brandkit-login-icon-user: {$iconUserUri};\n";
    $css .= "  --brandkit-login-icon-password: {$iconPasswordUri};\n";
    $css .= "  --brandkit-login-icon-user-padding-left: {$iconUserPaddingLeft};\n";
    $css .= "  --brandkit-login-icon-user-padding-right: {$iconUserPaddingRight};\n";
    $css .= "  --brandkit-login-icon-password-padding-left: {$iconPasswordPaddingLeft};\n";
    $css .= "  --brandkit-login-icon-password-padding-right: {$iconPasswordPaddingRight};\n";
    $css .= "  --brandkit-login-icon-position: {$iconPositionValue};\n";
    $css .= "  --brandkit-login-field-placeholder: {$fieldPlaceholder};\n";
    $css .= "  --brandkit-login-field-radius: {$fieldRadius}px;\n";
    $css .= "  --brandkit-login-field-shadow: {$fieldShadow};\n";
    foreach ($loginLogoFiles as $file => $variable) {
        if (!empty($configuredLogoUrls[$file])) {
            $css .= "  {$variable}: " . brandkit_css_url($configuredLogoUrls[$file]) . " !important;\n";
            continue;
        }
        $path = $publicLogoDir ? $publicLogoDir . '/' . $file : null;
        if ($path && is_file($path)) {
            $picsBase = brandkit_get_pics_web_base();
            if ($picsBase === '') {
                $picsBase = '/pics';
            }
            $picsBase = rtrim($picsBase, '/');
            $css .= "  {$variable}: " . brandkit_css_url("{$picsBase}/logos/{$file}") . " !important;\n";
        }
    }
    $css .= "  --brandkit-login-logo-image: var(--glpi-logo-dark-login, var(--logo));\n";
    if (!empty($wallpaperUrl)) {
        $wallpaperCssUrl = brandkit_css_url($wallpaperUrl);
        $wallpaperWebpCssUrl = $wallpaperWebpUrl !== '' ? brandkit_css_url($wallpaperWebpUrl) : '';
        $css .= "  --brandkit-login-error-wallpaper: {$wallpaperCssUrl};\n";
        $css .= "  --brandkit-login-error-overlay: {$wallpaperOverlayRgba};\n";
    }
    $css .= "}\n";
    $css .= ":root[data-glpi-theme-dark=\"1\"] {\n";
    $css .= "  --brandkit-login-logo-image: var(--glpi-logo-light-login, var(--logo));\n";
    $css .= "}\n";

    $css .= "html, body {\n";
    $css .= "  height: 100%;\n";
    $css .= "  margin: 0;\n";
    $css .= "  padding: 0;\n";
    $css .= "}\n";
    $css .= ".page-anonymous,\n";
    $css .= "body.welcome-anonymous,\n";
    $css .= "body.brandkit-error-page {\n";
    $css .= "  position: relative;\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  width: 100%;\n";
    if (!empty($wallpaperUrl)) {
        $wallpaperCssUrl = $wallpaperCssUrl ?? brandkit_css_url($wallpaperUrl);
        $wallpaperWebpCssUrl = $wallpaperWebpCssUrl ?? ($wallpaperWebpUrl !== '' ? brandkit_css_url($wallpaperWebpUrl) : '');
        $css .= "  background-image: linear-gradient({$wallpaperOverlayRgba}, {$wallpaperOverlayRgba}), {$wallpaperCssUrl} !important;\n";
        if (!empty($wallpaperWebpUrl)) {
            $css .= "  background-image: linear-gradient({$wallpaperOverlayRgba}, {$wallpaperOverlayRgba}), image-set({$wallpaperWebpCssUrl} type(\"image/webp\"), {$wallpaperCssUrl} type(\"{$wallpaperMime}\")) !important;\n";
        }
        $css .= "  background-color: {$wallpaperOverlayRgba} !important;\n";
        $css .= "  background-size: {$wallpaperSizeValue} !important;\n";
        $css .= "  background-position: {$wallpaperPosition} !important;\n";
        $css .= "  background-repeat: no-repeat !important;\n";
        $css .= "  background-attachment: {$wallpaperAttachment} !important;\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous .flex-fill,\n";
    $css .= "body.welcome-anonymous .flex-fill {\n";
        $css .= "  position: relative;\n";
        $css .= "  z-index: 1;\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  margin-top: 0 !important;\n";
    $css .= "  padding-top: 0 !important;\n";
    if ($isGlpi11) {
        $css .= "  padding-bottom: 2rem !important;\n";
    } else {
        $css .= "  padding-bottom: 0.75rem !important;\n";
    }
    $css .= "}\n";
    $css .= "body.welcome-anonymous {\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  justify-content: center;\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  overflow-x: hidden;\n";
    $css .= "  overflow-y: auto;\n";
    $css .= "  font-family: system-ui, -apple-system, sans-serif;\n";
    $css .= "  transition: background-color 0.5s ease;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page {\n";
    $css .= "  font-family: system-ui, -apple-system, sans-serif;\n";
    $css .= "  transition: background-color 0.5s ease;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .glpi-logo,\n";
    $css .= "body.welcome-anonymous .glpi-logo {\n";
    $css .= "  background: var(--brandkit-login-logo-image) no-repeat center;\n";
    $css .= "  background-size: contain;\n";
    if ($isGlpi11) {
        $css .= "  width: calc(var(--brandkit-login-logo-width) * var(--brandkit-login-panel-scale));\n";
        $css .= "  height: calc(var(--brandkit-login-logo-height) * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  width: var(--brandkit-login-logo-width);\n";
        $css .= "  height: var(--brandkit-login-logo-height);\n";
    }
    $css .= "  display: block;\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "  content: none !important;\n";
    $css .= "}\n";

    if ($panelTransparent) {
        $css .= ".page-anonymous .main-content-card,\n";
        $css .= "body.welcome-anonymous .main-content-card {\n";
        $css .= "  background: transparent !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "  border-color: transparent !important;\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .page-anonymous .card {\n";
        $css .= "  background: transparent !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "  border-color: transparent !important;\n";
        $css .= "}\n";
    } else {
        $css .= ".page-anonymous .main-content-card,\n";
        $css .= "body.welcome-anonymous .main-content-card {\n";
        $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
        $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  width: min(100%, var(--brandkit-login-panel-width));\n";
        $css .= "  height: min(100vh, var(--brandkit-login-panel-height));\n";
        $css .= "  max-height: 100vh;\n";
        $css .= "  overflow-y: var(--brandkit-login-panel-overflow);\n";
        $css .= "  overflow-x: hidden;\n";
        $css .= "  margin-left: auto;\n";
        $css .= "  margin-right: auto;\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "  display: flex;\n";
        $css .= "  flex-direction: column;\n";
        $css .= "}\n";
    $css .= "body.welcome-anonymous .page-anonymous .card {\n";
        $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
        $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  border-radius: 1.5rem !important;\n";
        $css .= "  border: 1px solid rgba(255, 255, 255, 0.08) !important;\n";
        $css .= "  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 255, 255, 0.05) !important;\n";
    $css .= "}\n";
    }

    if (!$isGlpi11) {
        $css .= ".page-anonymous .card.card-md,\n";
        $css .= "body.welcome-anonymous .card.card-md {\n";
        $css .= "  width: min(100%, var(--brandkit-login-panel-width));\n";
        $css .= "  height: min(100vh, var(--brandkit-login-panel-height));\n";
        $css .= "  max-height: 100vh;\n";
        $css .= "  overflow-y: var(--brandkit-login-panel-overflow);\n";
        $css .= "  overflow-x: hidden;\n";
        $css .= "  box-sizing: border-box;\n";
        $css .= "  margin-left: auto;\n";
        $css .= "  margin-right: auto;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card,\n";
        $css .= "body.welcome-anonymous .main-content-card,\n";
        $css .= ".page-anonymous .card.card-md,\n";
        $css .= "body.welcome-anonymous .card.card-md {\n";
        $css .= "  height: auto;\n";
        $css .= "  min-height: 0;\n";
        $css .= "  max-height: none;\n";
        $css .= "}\n";
    }

    $css .= ".page-anonymous .main-content-card,\n";
    $css .= "body.welcome-anonymous .main-content-card {\n";
    if ($isGlpi11) {
        $css .= "  font-size: calc(var(--brandkit-login-text-size) * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  font-size: var(--brandkit-login-text-size);\n";
    }
    $css .= "}\n";
    $css .= "body.welcome-anonymous .page-anonymous .card {\n";
    if ($isGlpi11) {
        $css .= "  padding: calc(2rem * var(--brandkit-login-panel-scale)) calc(2.5rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  padding: 2rem 2.5rem;\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .card-body,\n";
    $css .= "body.welcome-anonymous .main-content-card .card-body {\n";
    if ($isGlpi11) {
        $css .= "  padding: calc(1.5rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  padding: 1.5rem 1.5rem 1rem;\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .mb-3,\n";
    $css .= "body.welcome-anonymous .main-content-card .mb-3 {\n";
    if ($isGlpi11) {
        $css .= "  margin-bottom: calc(1rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  margin-bottom: var(--brandkit-login-field-spacing);\n";
    }
    $css .= "}\n";
    if (!$isGlpi11) {
        $css .= ".page-anonymous .main-content-card .mb-4,\n";
        $css .= "body.welcome-anonymous .main-content-card .mb-4 {\n";
        $css .= "  margin-bottom: calc(var(--brandkit-login-field-spacing) * 1.25);\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .mb-3,\n";
        $css .= ".page-anonymous .main-content-card .mb-4,\n";
        $css .= "body.welcome-anonymous .main-content-card .mb-3,\n";
        $css .= "body.welcome-anonymous .main-content-card .mb-4 {\n";
        $css .= "  width: 100%;\n";
        $css .= "}\n";
    }
    $css .= ".page-anonymous .main-content-card .form-label,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-label {\n";
    if ($isGlpi11) {
        $css .= "  font-size: calc(var(--brandkit-login-text-size) * 0.95 * var(--brandkit-login-panel-scale));\n";
        $css .= "  margin-bottom: calc(0.4rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  font-size: calc(var(--brandkit-login-text-size) * 0.95);\n";
        $css .= "  margin-bottom: 0.4rem;\n";
    }
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .card-title,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .card-title,\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] p.text-muted,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] p.text-muted {\n";
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "  text-align: left;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-field.row,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-field.row {\n";
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "  flex-direction: column;\n";
    $css .= "  align-items: stretch;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-field.row .col-form-label,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-field.row .col-form-label {\n";
    $css .= "  width: 100%;\n";
    $css .= "  max-width: 100%;\n";
    $css .= "  text-align: left !important;\n";
    $css .= "  justify-content: flex-start;\n";
    $css .= "  align-self: flex-start;\n";
    $css .= "  padding-left: 0;\n";
    $css .= "  padding-right: 0;\n";
    $css .= "  margin-bottom: calc(0.4rem * var(--brandkit-login-panel-scale));\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-field.row .field-container,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-field.row .field-container {\n";
    $css .= "  width: 100%;\n";
    $css .= "  max-width: 100%;\n";
    $css .= "  padding-left: 0;\n";
    $css .= "  padding-right: 0;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-footer,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-footer {\n";
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-footer .btn,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-footer .btn {\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous form[action*=\"lostpassword\"] .form-footer .btn .ti,\n";
    $css .= "body.welcome-anonymous form[action*=\"lostpassword\"] .form-footer .btn .ti {\n";
    $css .= "  color: currentColor;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .text-center.text-muted.mt-3,\n";
    $css .= "body.welcome-anonymous .text-center.text-muted.mt-3 {\n";
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    if (!$isGlpi11) {
        $css .= "  margin-top: 0.5rem !important;\n";
    }
    $css .= "}\n";
    if ($isGlpi11) {
        $css .= ".page-anonymous .main-content-card .d-flex:has(.forgot_password),\n";
        $css .= "body.welcome-anonymous .main-content-card .d-flex:has(.forgot_password) {\n";
        $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
        $css .= "  max-width: var(--brandkit-login-field-width);\n";
        $css .= "  margin-left: auto;\n";
        $css .= "  margin-right: auto;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: space-between;\n";
        $css .= "  gap: 0.5rem;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .d-flex:has(.forgot_password) .form-label,\n";
        $css .= "body.welcome-anonymous .main-content-card .d-flex:has(.forgot_password) .form-label {\n";
        $css .= "  display: flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: flex-start;\n";
        $css .= "  gap: 0.4rem;\n";
        $css .= "  width: auto;\n";
        $css .= "  max-width: none;\n";
        $css .= "  margin-left: 0;\n";
        $css .= "  margin-right: 0;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .form-label + .forgot_password,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-label + .forgot_password {\n";
        $css .= "  margin-left: 0 !important;\n";
        $css .= "  margin-right: 0 !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .form-label + .forgot_password a,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-label + .forgot_password a {\n";
        $css .= "  white-space: nowrap;\n";
        $css .= "}\n";
    }
    $css .= ".page-anonymous .main-content-card .card-header,\n";
    $css .= "body.welcome-anonymous .main-content-card .card-header {\n";
    $css .= "  border-bottom: none !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card hr,\n";
    $css .= "body.welcome-anonymous .main-content-card hr,\n";
    $css .= ".page-anonymous .main-content-card .hr-text,\n";
    $css .= "body.welcome-anonymous .main-content-card .hr-text {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .form-control,\n";
    $css .= ".page-anonymous .main-content-card .form-select,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
    if ($isGlpi11) {
        $css .= "  padding: calc(0.6rem * var(--brandkit-login-panel-scale)) calc(0.8rem * var(--brandkit-login-panel-scale));\n";
        $css .= "  font-size: calc(var(--brandkit-login-field-text-size) * var(--brandkit-login-panel-scale));\n";
        $css .= "  height: calc(var(--brandkit-login-field-height) * var(--brandkit-login-panel-scale));\n";
        $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
        $css .= "  max-width: var(--brandkit-login-field-width);\n";
        $css .= "  margin-left: auto;\n";
        $css .= "  margin-right: auto;\n";
        $css .= "  box-sizing: border-box;\n";
    } else {
        $css .= "  padding: 0.6rem 0.8rem;\n";
        $css .= "  font-size: var(--brandkit-login-field-text-size);\n";
        $css .= "  height: var(--brandkit-login-field-height);\n";
        $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
        $css .= "  max-width: var(--brandkit-login-field-width);\n";
        $css .= "  margin-left: auto;\n";
        $css .= "  margin-right: auto;\n";
        $css .= "  box-sizing: border-box;\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"],\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"],\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 {\n";
    $css .= "  width: min(100%, var(--brandkit-login-field-width)) !important;\n";
    $css .= "  max-width: var(--brandkit-login-field-width) !important;\n";
    $css .= "  margin-left: auto !important;\n";
    $css .= "  margin-right: auto !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 {\n";
    $css .= "  display: block;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"],\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] {\n";
    $css .= "  text-align: left !important;\n";
    $css .= "  text-align-last: left !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .form-check,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-check {\n";
    if ($isGlpi11) {
        $css .= "  margin-bottom: calc(0.75rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  margin-bottom: calc(var(--brandkit-login-field-spacing) * 0.75);\n";
    }
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "}\n";
    if ($isGlpi11) {
        $css .= ".page-anonymous .form-footer a[href*=\"lostpassword\"],\n";
        $css .= "body.welcome-anonymous .form-footer a[href*=\"lostpassword\"] {\n";
        $css .= "  white-space: nowrap;\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  gap: 0.25rem;\n";
        $css .= "}\n";
    }
    if (!$isGlpi11) {
        $css .= ".page-anonymous .main-content-card .form-footer,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-footer {\n";
        $css .= "  width: 100%;\n";
        $css .= "  margin-bottom: 0;\n";
        $css .= "}\n";
    }
    $css .= "body.welcome-anonymous button[type=\"submit\"],\n";
    $css .= "body.welcome-anonymous .form-footer .btn {\n";
    if ($isGlpi11) {
        $css .= "  padding: calc(0.9rem * var(--brandkit-login-panel-scale)) calc(1.6rem * var(--brandkit-login-panel-scale));\n";
        $css .= "  font-size: calc(var(--brandkit-login-button-text-size) * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  padding: 0.9rem 1.6rem;\n";
        $css .= "  font-size: var(--brandkit-login-button-text-size);\n";
    }
    $css .= "  width: min(100%, var(--brandkit-login-field-width));\n";
    $css .= "  max-width: var(--brandkit-login-field-width);\n";
    $css .= "  margin-left: auto;\n";
    $css .= "  margin-right: auto;\n";
    $css .= "  display: block;\n";
    $css .= "  transition: transform 0.15s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease;\n";
    $css .= "}\n";
    if ($buttonHoverEffect === 'press') {
        $css .= "body.welcome-anonymous button[type=\"submit\"]:hover,\n";
        $css .= "body.welcome-anonymous .form-footer .btn:hover,\n";
        $css .= "body.welcome-anonymous .btn.btn-primary:not(.brandkit-login-button):hover {\n";
        $css .= "  transform: translateY(1px) scale(0.98);\n";
        $css .= "  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous button[type=\"submit\"]:active,\n";
        $css .= "body.welcome-anonymous .form-footer .btn:active,\n";
        $css .= "body.welcome-anonymous .btn.btn-primary:not(.brandkit-login-button):active {\n";
        $css .= "  transform: translateY(2px) scale(0.97);\n";
        $css .= "  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);\n";
        $css .= "}\n";
    } elseif ($buttonHoverEffect === 'fill') {
        $css .= "body.welcome-anonymous button[type=\"submit\"]:hover,\n";
        $css .= "body.welcome-anonymous .form-footer .btn:hover,\n";
        $css .= "body.welcome-anonymous .btn.btn-primary:not(.brandkit-login-button):hover {\n";
        $css .= "  background: var(--brandkit-login-button-hover-fill) !important;\n";
        $css .= "  color: var(--brandkit-login-button-hover-text) !important;\n";
        $css .= "  border-color: var(--brandkit-login-button-hover-fill) !important;\n";
        $css .= "  box-shadow: inset 0 0 0 999px var(--brandkit-login-button-hover-fill), 0 4px 15px rgba(0, 0, 0, 0.3) !important;\n";
        $css .= "}\n";
    }

    $css .= ".page-anonymous::before,\n";
    $css .= "body.welcome-anonymous::before {\n";
    $css .= "  content: '';\n";
    $css .= "  position: fixed;\n";
    $css .= "  top: 0;\n";
    $css .= "  bottom: 0;\n";
    $css .= "  left: var(--brandkit-login-split-left);\n";
    $css .= "  right: var(--brandkit-login-split-right);\n";
    $css .= "  width: var(--brandkit-login-split-width);\n";
    $css .= "  background: var(--brandkit-login-split-color);\n";
    $css .= "  backdrop-filter: blur(var(--brandkit-login-split-blur));\n";
    $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-split-blur));\n";
    $css .= "  z-index: 0;\n";
    $css .= "  pointer-events: none;\n";
    $css .= "}\n";

    $css .= ".page-anonymous .container-tight,\n";
    $css .= "body.welcome-anonymous .container-tight {\n";
    $css .= "  max-width: var(--brandkit-login-container-max-effective) !important;\n";
    $css .= "  width: min(100%, var(--brandkit-login-container-max-effective));\n";
    $css .= "  margin-left: var(--brandkit-login-container-left);\n";
    $css .= "  margin-right: var(--brandkit-login-container-right);\n";
    $css .= "  transform: translateX(var(--brandkit-login-container-translate));\n";
    $css .= "}\n";
    if (!$isGlpi11) {
        $css .= ".page-anonymous .card-body .row.justify-content-center,\n";
        $css .= "body.welcome-anonymous .card-body .row.justify-content-center {\n";
        $css .= "  width: 100%;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .card-body .row.justify-content-center > .col-md-5,\n";
        $css .= "body.welcome-anonymous .card-body .row.justify-content-center > .col-md-5 {\n";
        $css .= "  flex: 0 0 100%;\n";
        $css .= "  max-width: 100%;\n";
        $css .= "}\n";
    }
    $css .= "body.welcome-anonymous .container-tight {\n";
    $css .= "  width: 100%;\n";
    $css .= "  perspective: 1000px;\n";
    $css .= "  position: relative;\n";
    $css .= "}\n";
    $css .= "@media screen and (min-width: 768px) {\n";
    $css .= "  body.welcome-anonymous .container-tight {\n";
    $css .= "    min-width: 650px;\n";
    $css .= "    max-width: 700px;\n";
    $css .= "  }\n";
    $css .= "}\n";
    $css .= "@media screen and (max-width: 767px) {\n";
    $css .= "  body.welcome-anonymous .container-tight {\n";
    $css .= "    max-width: 450px;\n";
    $css .= "    min-width: unset;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous,\n";
    $css .= "  body.welcome-anonymous,\n";
    $css .= "  body.brandkit-error-page {\n";
    $css .= "    background-size: cover !important;\n";
    $css .= "    background-position: center !important;\n";
    $css .= "    background-attachment: scroll !important;\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous .page-anonymous .card-body,\n";
    $css .= "  body.welcome-anonymous .main-content-card .card-body,\n";
    $css .= "  .page-anonymous .main-content-card .card-body {\n";
    if ($isGlpi11) {
        $css .= "    padding: calc(1.1rem * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "    padding: 1rem 1rem 0.75rem;\n";
    }
    $css .= "  }\n";
    $css .= "}\n";
    $css .= "@media screen and (max-height: 760px) {\n";
    $css .= "  body.welcome-anonymous {\n";
    $css .= "    align-items: flex-start;\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous .flex-fill {\n";
    $css .= "    padding-top: 2rem !important;\n";
    $css .= "  }\n";
    $css .= "}\n";

    $css .= ".page-anonymous .container-tight .text-center,\n";
    $css .= "body.welcome-anonymous .container-tight .text-center {\n";
    $css .= "  text-align: var(--brandkit-login-text-align) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous.brandkit-logo-inside .container-tight > .text-center,\n";
    $css .= "body.welcome-anonymous.brandkit-logo-inside .container-tight > .text-center {\n";
    $css .= "  display: block !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous.brandkit-logo-inside .container-tight > .text-center .glpi-logo,\n";
    $css .= "body.welcome-anonymous.brandkit-logo-inside .container-tight > .text-center .glpi-logo {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card,\n";
    $css .= ".page-anonymous .main-content-card a,\n";
    $css .= ".page-anonymous .main-content-card .text-muted,\n";
    $css .= "body.welcome-anonymous .main-content-card,\n";
    $css .= "body.welcome-anonymous .main-content-card a,\n";
    $css .= "body.welcome-anonymous .main-content-card .text-muted {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card input,\n";
    $css .= ".page-anonymous .main-content-card select,\n";
    $css .= ".page-anonymous .main-content-card textarea,\n";
    $css .= "body.welcome-anonymous .main-content-card input,\n";
    $css .= "body.welcome-anonymous .main-content-card select,\n";
    $css .= "body.welcome-anonymous .main-content-card textarea {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "  caret-color: var(--brandkit-login-field-text);\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .page-anonymous,\n";
    $css .= "body.welcome-anonymous .page-anonymous * {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
    $css .= ".page-anonymous .main-content-card select,\n";
    $css .= ".page-anonymous .main-content-card textarea,\n";
    $css .= ".page-anonymous .main-content-card .form-control,\n";
    $css .= ".page-anonymous .main-content-card .form-select,\n";
    $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
    $css .= "body.welcome-anonymous .main-content-card select,\n";
    $css .= "body.welcome-anonymous .main-content-card textarea,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "  border: var(--brandkit-login-field-border-width) solid var(--brandkit-login-field-border) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-field-radius) !important;\n";
    $css .= "  box-shadow: var(--brandkit-login-field-shadow) !important;\n";
    $css .= "  --brandkit-login-field-shadow-current: var(--brandkit-login-field-shadow);\n";
    $css .= "}\n";
    $css .= ".page-anonymous #login_name,\n";
    $css .= "body.welcome-anonymous #login_name {\n";
    $css .= "  background-image: var(--brandkit-login-icon-user) !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
    $css .= "  background-size: 18px 18px !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous #login_password,\n";
    $css .= "body.welcome-anonymous #login_password {\n";
    $css .= "  background-image: var(--brandkit-login-icon-password) !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
    $css .= "  background-size: 18px 18px !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card input::placeholder,\n";
    $css .= ".page-anonymous .main-content-card textarea::placeholder,\n";
    $css .= "body.welcome-anonymous .main-content-card input::placeholder,\n";
    $css .= "body.welcome-anonymous .main-content-card textarea::placeholder {\n";
    $css .= "  color: var(--brandkit-login-field-placeholder) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .form-control:focus,\n";
    $css .= ".page-anonymous .main-content-card .form-select:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-control:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card .form-select:focus {\n";
    $css .= "  border-color: var(--brandkit-login-field-focus-border) !important;\n";
    $css .= "  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brandkit-login-field-focus-glow) 35%, transparent) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .select2-container--default .select2-selection--single,\n";
    $css .= "body.welcome-anonymous .main-content-card .select2-container--default .select2-selection--single {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
    $css .= "  border: var(--brandkit-login-field-border-width) solid var(--brandkit-login-field-border) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-field-radius) !important;\n";
    $css .= "  box-shadow: var(--brandkit-login-field-shadow) !important;\n";
    if ($isGlpi11) {
        $css .= "  height: calc(var(--brandkit-login-field-height) * var(--brandkit-login-panel-scale)) !important;\n";
    } else {
        $css .= "  height: var(--brandkit-login-field-height) !important;\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .select2-selection__rendered,\n";
    $css .= "body.welcome-anonymous .main-content-card .select2-selection__rendered {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    if ($isGlpi11) {
        $css .= "  line-height: calc(var(--brandkit-login-field-height) * var(--brandkit-login-panel-scale) - 2px);\n";
    } else {
        $css .= "  line-height: calc(var(--brandkit-login-field-height) - 2px);\n";
    }
    $css .= "}\n";
    $css .= ".page-anonymous .select2-dropdown,\n";
    $css .= "body.welcome-anonymous .select2-dropdown {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-field-border) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .select2-results__option,\n";
    $css .= "body.welcome-anonymous .select2-results__option {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .select2-container--default .select2-selection--single:focus,\n";
    $css .= "body.welcome-anonymous .select2-container--default .select2-selection--single:focus {\n";
    $css .= "  border-color: var(--brandkit-login-field-focus-border) !important;\n";
    $css .= "  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brandkit-login-field-focus-glow) 35%, transparent) !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .select2-container--default .select2-selection--single .select2-selection__rendered {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .select2-container--default .select2-results__option,\n";
    $css .= "body.welcome-anonymous .select2-container--default .select2-results__group {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .select2-container--open .select2-dropdown {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered,\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered span,\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2 .select2-selection__placeholder,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered span,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 .select2-selection__placeholder {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "  text-align: left !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 .select2-selection__rendered {\n";
    $css .= "  line-height: normal !important;\n";
    $css .= "  padding-left: calc(0.8rem * var(--brandkit-login-panel-scale)) !important;\n";
    $css .= "  padding-right: calc(2rem * var(--brandkit-login-panel-scale)) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select[name=\"auth\"] + .select2 .select2-selection--single,\n";
    $css .= "body.welcome-anonymous select[name=\"auth\"] + .select2 .select2-selection--single {\n";
    $css .= "  display: flex !important;\n";
    $css .= "  align-items: center !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select,\n";
    $css .= "body.welcome-anonymous select,\n";
    $css .= ".page-anonymous .form-select,\n";
    $css .= "body.welcome-anonymous .form-select,\n";
    $css .= ".page-anonymous .select2-selection__rendered,\n";
    $css .= "body.welcome-anonymous .select2-selection__rendered,\n";
    $css .= ".page-anonymous .select2-search__field,\n";
    $css .= "body.welcome-anonymous .select2-search__field {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous select option,\n";
    $css .= "body.welcome-anonymous select option {\n";
    $css .= "  color: var(--brandkit-login-field-text) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .form-check-input:checked,\n";
    $css .= "body.welcome-anonymous .form-check-input:checked {\n";
    $css .= "  background-color: var(--brandkit-login-accent-color) !important;\n";
    $css .= "  border-color: var(--brandkit-login-accent-color) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .form-check-input:focus,\n";
    $css .= "body.welcome-anonymous .form-check-input:focus {\n";
    $css .= "  box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--brandkit-login-accent-color) 35%, transparent) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .select2-results__option--highlighted,\n";
    $css .= "body.welcome-anonymous .select2-results__option--highlighted {\n";
    $css .= "  background-color: var(--brandkit-login-accent-color) !important;\n";
    $css .= "  color: #ffffff !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .select2-results__option[aria-selected=\"true\"],\n";
    $css .= "body.welcome-anonymous .select2-results__option[aria-selected=\"true\"] {\n";
    $css .= "  background-color: color-mix(in srgb, var(--brandkit-login-accent-color) 75%, transparent) !important;\n";
    $css .= "  color: #ffffff !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous input::selection,\n";
    $css .= ".page-anonymous textarea::selection,\n";
    $css .= ".page-anonymous .select2-search__field::selection,\n";
    $css .= "body.welcome-anonymous input::selection,\n";
    $css .= "body.welcome-anonymous textarea::selection,\n";
    $css .= "body.welcome-anonymous .select2-search__field::selection {\n";
    $css .= "  background: var(--brandkit-login-accent-color) !important;\n";
    $css .= "  color: #ffffff !important;\n";
    $css .= "}\n";

    if ($fieldStyle === 'solid') {
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= ".page-anonymous .main-content-card textarea,\n";
        $css .= ".page-anonymous .main-content-card .form-control,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= "body.welcome-anonymous .main-content-card textarea,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control {\n";
        $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_name,\n";
        $css .= ".page-anonymous #login_password,\n";
        $css .= "body.welcome-anonymous #login_name,\n";
        $css .= "body.welcome-anonymous #login_password {\n";
        $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_name,\n";
        $css .= "body.welcome-anonymous #login_name {\n";
        $css .= "  background-image: linear-gradient(var(--brandkit-login-field-bg), var(--brandkit-login-field-bg)), var(--brandkit-login-icon-user) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: 0 0, var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 100% 100%, 18px 18px !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_password,\n";
        $css .= "body.welcome-anonymous #login_password {\n";
        $css .= "  background-image: linear-gradient(var(--brandkit-login-field-bg), var(--brandkit-login-field-bg)), var(--brandkit-login-icon-password) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: 0 0, var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 100% 100%, 18px 18px !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]):focus,\n";
        $css .= ".page-anonymous .main-content-card input[type=\"password\"]:focus,\n";
        $css .= ".page-anonymous .main-content-card textarea:focus,\n";
        $css .= ".page-anonymous .main-content-card .form-control:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]):focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input[type=\"password\"]:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card textarea:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control:focus {\n";
        $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:active,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input:autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-internal-autofill-selected,\n";
        $css .= ".page-anonymous .main-content-card input:-moz-autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-moz-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill:focus,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_name:autofill,\n";
        $css .= ".page-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill:focus,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_password:autofill,\n";
        $css .= ".page-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:active,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill:focus {\n";
        $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  transition: background-color 99999s ease-in-out 0s !important;\n";
        $css .= "  -webkit-background-clip: padding-box !important;\n";
        $css .= "  background-clip: padding-box !important;\n";
        $css .= "  filter: none !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:active,\n";
        $css .= ".page-anonymous #login_name:autofill,\n";
        $css .= ".page-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:active,\n";
        $css .= "body.welcome-anonymous #login_name:autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill:focus {\n";
        $css .= "  background: var(--brandkit-login-icon-user), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
        $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:active,\n";
        $css .= ".page-anonymous #login_password:autofill,\n";
        $css .= ".page-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:active,\n";
        $css .= "body.welcome-anonymous #login_password:autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill:focus {\n";
        $css .= "  background: var(--brandkit-login-icon-password), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
        $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_name:autofill,\n";
        $css .= ".page-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill:focus {\n";
        $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
        $css .= "  background-image: var(--brandkit-login-icon-user) !important;\n";
        $css .= "  background-repeat: no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 18px 18px !important;\n";
        $css .= "  padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
        $css .= "  padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_name,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
    $css .= "  background-image: var(--brandkit-login-icon-user) !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
    $css .= "  background-size: 18px 18px !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_password,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg) !important;\n";
    $css .= "  background-image: var(--brandkit-login-icon-password) !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
    $css .= "  background-size: 18px 18px !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-moz-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-user), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  filter: none !important;\n";
    $css .= "  appearance: none !important;\n";
    $css .= "  -moz-appearance: none !important;\n";
    $css .= "  -webkit-appearance: none !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-moz-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-password), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  filter: none !important;\n";
    $css .= "  appearance: none !important;\n";
    $css .= "  -moz-appearance: none !important;\n";
    $css .= "  -webkit-appearance: none !important;\n";
    $css .= "}\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_password:autofill,\n";
        $css .= ".page-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill:focus {\n";
        $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
        $css .= "  background-image: var(--brandkit-login-icon-password) !important;\n";
        $css .= "  background-repeat: no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 18px 18px !important;\n";
        $css .= "  padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
        $css .= "  padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_name:autofill,\n";
        $css .= ".page-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill,\n";
        $css .= ".page-anonymous #login_name:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_name:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_name:autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_name:-moz-autofill:focus {\n";
        $css .= "  background-image: var(--brandkit-login-icon-user) !important;\n";
        $css .= "  background-repeat: no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 18px 18px !important;\n";
        $css .= "  padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
        $css .= "  padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous #login_password:autofill,\n";
        $css .= ".page-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill,\n";
        $css .= ".page-anonymous #login_password:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous #login_password:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous #login_password:autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill,\n";
        $css .= "body.welcome-anonymous #login_password:-moz-autofill:focus {\n";
        $css .= "  background-image: var(--brandkit-login-icon-password) !important;\n";
        $css .= "  background-repeat: no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position) !important;\n";
        $css .= "  background-size: 18px 18px !important;\n";
        $css .= "  padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
        $css .= "  padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
        $css .= "}\n";
    } elseif ($fieldStyle === 'outline') {
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= ".page-anonymous .main-content-card select,\n";
        $css .= ".page-anonymous .main-content-card textarea,\n";
        $css .= ".page-anonymous .main-content-card .form-control,\n";
        $css .= ".page-anonymous .main-content-card .form-select,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= "body.welcome-anonymous .main-content-card select,\n";
        $css .= "body.welcome-anonymous .main-content-card textarea,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "  border-width: var(--brandkit-login-field-border-width) !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .select2-container--default .select2-selection--single,\n";
        $css .= "body.welcome-anonymous .main-content-card .select2-container--default .select2-selection--single {\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "  border-width: var(--brandkit-login-field-border-width) !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "}\n";
    } elseif ($fieldStyle === 'underline') {
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= ".page-anonymous .main-content-card select,\n";
        $css .= ".page-anonymous .main-content-card textarea,\n";
        $css .= ".page-anonymous .main-content-card .form-control,\n";
        $css .= ".page-anonymous .main-content-card .form-select,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= "body.welcome-anonymous .main-content-card select,\n";
        $css .= "body.welcome-anonymous .main-content-card textarea,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "  border-radius: 0 !important;\n";
        $css .= "  border-width: 0 0 var(--brandkit-login-field-border-width) 0 !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .select2-container--default .select2-selection--single,\n";
        $css .= "body.welcome-anonymous .main-content-card .select2-container--default .select2-selection--single {\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "  border-radius: 0 !important;\n";
        $css .= "  border-width: 0 0 var(--brandkit-login-field-border-width) 0 !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "}\n";
    } elseif ($fieldStyle === 'glass') {
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= ".page-anonymous .main-content-card select,\n";
        $css .= ".page-anonymous .main-content-card textarea,\n";
        $css .= ".page-anonymous .main-content-card .form-control,\n";
        $css .= ".page-anonymous .main-content-card .form-select,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= "body.welcome-anonymous .main-content-card select,\n";
        $css .= "body.welcome-anonymous .main-content-card textarea,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
        $css .= "  background-color: color-mix(in srgb, var(--brandkit-login-field-bg) 82%, transparent) !important;\n";
        $css .= "  backdrop-filter: blur(8px);\n";
        $css .= "  -webkit-backdrop-filter: blur(8px);\n";
        $css .= "}\n";
    } elseif ($fieldStyle === 'neon') {
        $css .= ".page-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= ".page-anonymous .main-content-card select,\n";
        $css .= ".page-anonymous .main-content-card textarea,\n";
        $css .= ".page-anonymous .main-content-card .form-control,\n";
        $css .= ".page-anonymous .main-content-card .form-select,\n";
        $css .= "body.welcome-anonymous .main-content-card input:not([type=\"checkbox\"]):not([type=\"radio\"]),\n";
        $css .= "body.welcome-anonymous .main-content-card select,\n";
        $css .= "body.welcome-anonymous .main-content-card textarea,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-control,\n";
        $css .= "body.welcome-anonymous .main-content-card .form-select {\n";
        $css .= "  --brandkit-login-field-shadow-current: 0 0 0 1px var(--brandkit-login-field-border), 0 12px 26px color-mix(in srgb, var(--brandkit-login-field-focus) 35%, transparent);\n";
        $css .= "  box-shadow: var(--brandkit-login-field-shadow-current) !important;\n";
        $css .= "}\n";
    }
    $css .= ".page-anonymous .main-content-card input:-webkit-autofill,\n";
    $css .= ".page-anonymous .main-content-card input:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .main-content-card input:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .main-content-card input:-webkit-autofill:active,\n";
    $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
    $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .main-content-card input:autofill,\n";
    $css .= ".page-anonymous .main-content-card input:autofill:hover,\n";
    $css .= ".page-anonymous .main-content-card input:autofill:focus,\n";
    $css .= ".page-anonymous .main-content-card input:autofill:active,\n";
    $css .= ".page-anonymous .main-content-card input:-internal-autofill-selected,\n";
    $css .= ".page-anonymous .main-content-card input:-moz-autofill,\n";
    $css .= ".page-anonymous .main-content-card input:-moz-autofill:focus,\n";
    $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill,\n";
    $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card input:autofill,\n";
    $css .= "body.welcome-anonymous .main-content-card input:autofill:hover,\n";
    $css .= "body.welcome-anonymous .main-content-card input:autofill:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card input:autofill:active,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-internal-autofill-selected,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill:focus {\n";
    $css .= "  --brandkit-login-field-shadow-current: none;\n";
    $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
    $css .= "  border: var(--brandkit-login-field-border-width) solid var(--brandkit-login-field-border) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-field-radius) !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current) !important;\n";
    $css .= "  box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current) !important;\n";
    $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "  filter: none !important;\n";
    $css .= "  appearance: none !important;\n";
    $css .= "  -moz-appearance: none !important;\n";
    $css .= "  -webkit-appearance: none !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-webkit-autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-moz-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_name:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_name:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-user), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-webkit-autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:hover,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:focus,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:autofill:active,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-moz-autofill,\n";
    $css .= ".page-anonymous .card input.form-control#login_password:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:hover,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:focus,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:autofill:active,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-moz-autofill,\n";
    $css .= "body.welcome-anonymous .card input.form-control#login_password:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-password), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
    $css .= "  padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .page-anonymous .card {\n";
    $css .= "  padding: 2rem 2.5rem;\n";
    $css .= "  margin-top: 5px;\n";
    if ($panelHoverEffect) {
        $css .= "  transition: transform 0.3s ease, box-shadow 0.3s ease;\n";
    } else {
        $css .= "  transition: none;\n";
    }
    $css .= "}\n";
    if ($panelHoverEffect) {
        $css .= "body.welcome-anonymous .page-anonymous .card:hover {\n";
        $css .= "  transform: translateY(-5px);\n";
        $css .= "  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 8px 20px rgba(0, 0, 0, 0.4), 0 0 25px rgba(255, 255, 255, 0.075);\n";
        $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
        $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "}\n";
    }
    $css .= "@media (max-width: 850px) {\n";
    $css .= "  .page-anonymous .main-content-card,\n";
    $css .= "  body.welcome-anonymous .main-content-card {\n";
    $css .= "    width: 90vw !important;\n";
    $css .= "    height: 90vh !important;\n";
    $css .= "    max-height: none !important;\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous {\n";
    $css .= "    align-items: flex-start;\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous .flex-fill {\n";
    $css .= "    padding-top: 2rem !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .container-tight,\n";
    $css .= "  body.welcome-anonymous .container-tight {\n";
    $css .= "    max-width: 90vw !important;\n";
    $css .= "    width: 90vw !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .main-content-card,\n";
    $css .= "  body.welcome-anonymous .main-content-card {\n";
    $css .= "    padding: 1.25rem 1.5rem !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .main-content-card::before,\n";
    $css .= "  body.welcome-anonymous .main-content-card::before,\n";
    $css .= "  .page-anonymous .glpi-logo,\n";
    $css .= "  body.welcome-anonymous .glpi-logo {\n";
    $css .= "    width: min(var(--brandkit-login-logo-width), 70vw) !important;\n";
    $css .= "    height: min(var(--brandkit-login-logo-height), 120px) !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .login-title,\n";
    $css .= "  body.welcome-anonymous .login-title {\n";
    $css .= "    font-size: 1.1rem !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous,\n";
    $css .= "  body.welcome-anonymous {\n";
    $css .= "    --brandkit-login-split-width: 100% !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .container-tight,\n";
    $css .= "  body.welcome-anonymous .container-tight {\n";
    $css .= "    margin-left: auto !important;\n";
    $css .= "    margin-right: auto !important;\n";
    $css .= "    transform: translateX(0) !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .container-tight .text-center,\n";
    $css .= "  body.welcome-anonymous .container-tight .text-center {\n";
    $css .= "    text-align: center !important;\n";
    $css .= "  }\n";
    $css .= "}\n";
    $css .= "@media (max-width: 640px) {\n";
    $css .= "  .page-anonymous,\n";
    $css .= "  body.welcome-anonymous,\n";
    $css .= "  body.brandkit-error-page {\n";
    $css .= "    background-size: cover !important;\n";
    $css .= "    background-repeat: no-repeat !important;\n";
    $css .= "    background-position: center !important;\n";
    $css .= "    background-attachment: scroll !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .main-content-card,\n";
    $css .= "  body.welcome-anonymous .main-content-card {\n";
    $css .= "    height: auto !important;\n";
    $css .= "    max-height: none !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .main-content-card .card-body,\n";
    $css .= "  body.welcome-anonymous .main-content-card .card-body {\n";
    $css .= "    padding-bottom: calc(1rem * var(--brandkit-login-panel-scale));\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous .page-anonymous .card {\n";
    $css .= "    padding-bottom: calc(1.25rem * var(--brandkit-login-panel-scale));\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous .form-footer,\n";
    $css .= "  body.welcome-anonymous .form-footer {\n";
    $css .= "    margin-bottom: 0 !important;\n";
    $css .= "  }\n";
    $css .= "}\n";
    $css .= "@-moz-document url-prefix() {\n";
    $css .= "  body.welcome-anonymous .main-content-card {\n";
    $css .= "    padding-top: 12px;\n";
    $css .= "    padding-bottom: 12px;\n";
    $css .= "  }\n";
    $css .= "  body.welcome-anonymous .page-anonymous .card {\n";
    $css .= "    margin-top: 12px;\n";
    $css .= "  }\n";
    if ($panelHoverEffect) {
        $css .= "  body.welcome-anonymous .page-anonymous .card {\n";
        $css .= "    position: relative;\n";
        $css .= "    top: 0;\n";
        $css .= "    transition: top 0.3s ease, box-shadow 0.3s ease;\n";
        $css .= "  }\n";
        $css .= "  body.welcome-anonymous .page-anonymous .card:hover {\n";
        $css .= "    top: -5px;\n";
        $css .= "    transform: none;\n";
        $css .= "    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 8px 20px rgba(0, 0, 0, 0.4), 0 0 25px rgba(255, 255, 255, 0.075);\n";
        $css .= "    background: var(--brandkit-login-panel-color) !important;\n";
        $css .= "    backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "    -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
        $css .= "  }\n";
    }
    $css .= "  .page-anonymous .main-content-card input:-moz-autofill,\n";
    $css .= "  .page-anonymous .main-content-card input:-moz-autofill:focus,\n";
    $css .= "  .page-anonymous .main-content-card input.form-control:-moz-autofill,\n";
    $css .= "  .page-anonymous .main-content-card input.form-control:-moz-autofill:focus,\n";
    $css .= "  body.welcome-anonymous .main-content-card input:-moz-autofill,\n";
    $css .= "  body.welcome-anonymous .main-content-card input:-moz-autofill:focus,\n";
    $css .= "  body.welcome-anonymous .main-content-card input.form-control:-moz-autofill,\n";
    $css .= "  body.welcome-anonymous .main-content-card input.form-control:-moz-autofill:focus {\n";
    $css .= "    box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "    background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
    $css .= "    color: var(--brandkit-login-field-text) !important;\n";
    $css .= "    caret-color: var(--brandkit-login-field-text) !important;\n";
    $css .= "    background-clip: padding-box !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous #login_name:-moz-autofill,\n";
    $css .= "  .page-anonymous #login_name:-moz-autofill:focus,\n";
    $css .= "  body.welcome-anonymous #login_name:-moz-autofill,\n";
    $css .= "  body.welcome-anonymous #login_name:-moz-autofill:focus {\n";
    $css .= "    background: var(--brandkit-login-icon-user), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "    background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "    background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "    background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "    padding-left: var(--brandkit-login-icon-user-padding-left) !important;\n";
    $css .= "    padding-right: var(--brandkit-login-icon-user-padding-right) !important;\n";
    $css .= "  }\n";
    $css .= "  .page-anonymous #login_password:-moz-autofill,\n";
    $css .= "  .page-anonymous #login_password:-moz-autofill:focus,\n";
    $css .= "  body.welcome-anonymous #login_password:-moz-autofill,\n";
    $css .= "  body.welcome-anonymous #login_password:-moz-autofill:focus {\n";
    $css .= "    background: var(--brandkit-login-icon-password), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
    $css .= "    background-repeat: no-repeat, no-repeat !important;\n";
    $css .= "    background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
    $css .= "    background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "    padding-left: var(--brandkit-login-icon-password-padding-left) !important;\n";
    $css .= "    padding-right: var(--brandkit-login-icon-password-padding-right) !important;\n";
    $css .= "  }\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .card-header {\n";
    $css .= "  margin: 0;\n";
    $css .= "  padding: 0 !important;\n";
    $css .= "  min-height: 0 !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .card-header.mb-4 {\n";
    if ($isGlpi11) {
        $css .= "  margin-bottom: calc(0.2rem * var(--brandkit-login-panel-scale)) !important;\n";
    } else {
        $css .= "  margin-bottom: calc(var(--brandkit-login-field-spacing) * 0.75) !important;\n";
    }
    $css .= "}\n";
    $css .= "body.welcome-anonymous .card-header h2,\n";
    $css .= "body.welcome-anonymous .card-header .mx-auto {\n";
    if (!$isGlpi11) {
        $css .= "  font-size: var(--brandkit-login-title-size) !important;\n";
        $css .= "  line-height: 1.2 !important;\n";
        $css .= "  text-align: center !important;\n";
    }
    $css .= "  margin-bottom: 0 !important;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .card-header:empty {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";

    if ($isGlpi11) {
        $css .= "body.welcome-anonymous .alert,\n";
        $css .= "body.welcome-anonymous .alert-danger,\n";
        $css .= "body.welcome-anonymous .alert-warning,\n";
        $css .= "body.welcome-anonymous .alert-info {\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "  border: 1px solid rgba(220, 53, 69, 0.3) !important;\n";
        $css .= "  border-radius: 0.75rem !important;\n";
        $css .= "  color: var(--brandkit-login-text-color) !important;\n";
        $css .= "  backdrop-filter: blur(5px);\n";
        $css .= "  -webkit-backdrop-filter: blur(5px);\n";
        $css .= "  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);\n";
        $css .= "  margin-bottom: 1rem;\n";
        $css .= "  padding: 1rem 1.25rem;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert-danger {\n";
        $css .= "  border-color: rgba(220, 53, 69, 0.4) !important;\n";
        $css .= "  background-color: rgba(220, 53, 69, 0.1) !important;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert-warning {\n";
        $css .= "  border-color: rgba(245, 158, 11, 0.45) !important;\n";
        $css .= "  background-color: rgba(245, 158, 11, 0.12) !important;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert *,\n";
        $css .= "body.welcome-anonymous .alert-danger *,\n";
        $css .= "body.welcome-anonymous .alert p,\n";
        $css .= "body.welcome-anonymous .alert span,\n";
        $css .= "body.welcome-anonymous .alert div {\n";
        $css .= "  color: var(--brandkit-login-text-color) !important;\n";
        $css .= "  background-color: transparent !important;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert .btn,\n";
        $css .= "body.welcome-anonymous .alert-danger .btn {\n";
        $css .= "  background: rgba(255, 255, 255, 0.1) !important;\n";
        $css .= "  border: 1px solid rgba(255, 255, 255, 0.2) !important;\n";
        $css .= "  color: var(--brandkit-login-text-color) !important;\n";
        $css .= "  border-radius: 0.5rem;\n";
        $css .= "  transition: all 0.3s ease;\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert .btn.btn-primary {\n";
        $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
        $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
        $css .= "  color: var(--brandkit-login-button-text) !important;\n";
        $css .= "  font-size: calc(var(--brandkit-login-button-text-size) * var(--brandkit-login-panel-scale));\n";
        $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
        $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
        $css .= "}\n";
        $css .= "body.welcome-anonymous .alert .btn:hover,\n";
        $css .= "body.welcome-anonymous .alert-danger .btn:hover {\n";
        $css .= "  background: rgba(255, 255, 255, 0.2) !important;\n";
        $css .= "  border-color: rgba(255, 255, 255, 0.3) !important;\n";
        $css .= "  transform: translateY(-1px);\n";
        $css .= "}\n";
    } else {
        $css .= ".page-anonymous .main-content-card .alert,\n";
        $css .= ".page-anonymous .main-content-card .alert-warning,\n";
        $css .= ".page-anonymous .main-content-card .alert-danger,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert-warning,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert-danger {\n";
        $css .= "  background: transparent !important;\n";
        $css .= "  border: none !important;\n";
        $css .= "  border-radius: 0 !important;\n";
        $css .= "  box-shadow: none !important;\n";
        $css .= "  padding: 0 !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .alert .btn,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert .btn {\n";
        $css .= "  display: inline-flex;\n";
        $css .= "  align-items: center;\n";
        $css .= "  justify-content: center;\n";
        $css .= "  gap: 0.35rem;\n";
        $css .= "  margin-top: calc(var(--brandkit-login-field-spacing) * 0.5);\n";
        $css .= "  width: 321px;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .alert-title,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert-title {\n";
        $css .= "  margin-bottom: calc(var(--brandkit-login-field-spacing) * 0.5) !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .alert-title + div,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert-title + div {\n";
        $css .= "  margin-bottom: calc(var(--brandkit-login-field-spacing) * 0.75) !important;\n";
        $css .= "  display: block;\n";
        $css .= "  background: transparent !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card .alert .btn.btn-primary,\n";
        $css .= "body.welcome-anonymous .main-content-card .alert .btn.btn-primary {\n";
        $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
        $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
        $css .= "  color: var(--brandkit-login-button-text) !important;\n";
        $css .= "  font-size: var(--brandkit-login-button-text-size);\n";
        $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
        $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
        $css .= "  font-weight: 600;\n";
        $css .= "  letter-spacing: 0.03em;\n";
        $css .= "  padding: 0.9rem 1.75rem;\n";
        $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
        $css .= "  text-decoration: none;\n";
        $css .= "  width: 321px !important;\n";
        $css .= "  min-width: 321px !important;\n";
        $css .= "  max-width: 321px !important;\n";
        $css .= "}\n";
    }
    $css .= "body.welcome-anonymous .btn.btn-primary:not(.brandkit-login-button) {\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    if ($isGlpi11) {
        $css .= "  font-size: calc(var(--brandkit-login-button-text-size) * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  font-size: var(--brandkit-login-button-text-size);\n";
    }
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "  padding: 0.9rem 1.75rem;\n";
    $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
    $css .= "  width: 100%;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous button[type=\"submit\"][name=\"submit\"],\n";
    $css .= "body.welcome-anonymous .form-footer .btn {\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    if ($isGlpi11) {
        $css .= "  font-size: calc(var(--brandkit-login-button-text-size) * var(--brandkit-login-panel-scale));\n";
    } else {
        $css .= "  font-size: var(--brandkit-login-button-text-size);\n";
    }
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "  padding: 0.9rem 1.75rem;\n";
    $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
    $css .= "  width: 100%;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous,\n";
    $css .= "body.welcome-anonymous #page,\n";
    $css .= "body.welcome-anonymous main {\n";
    $css .= "  background-color: var(--brandkit-login-error-overlay, {$wallpaperOverlayRgba}) !important;\n";
    $css .= "  background-image: linear-gradient(var(--brandkit-login-error-overlay, {$wallpaperOverlayRgba}), var(--brandkit-login-error-overlay, {$wallpaperOverlayRgba})), var(--brandkit-login-error-wallpaper, none) !important;\n";
    $css .= "  background-size: {$wallpaperSizeValue} !important;\n";
    $css .= "  background-position: {$wallpaperPosition} !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-attachment: {$wallpaperAttachment} !important;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page .navbar {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page .page,\n";
    $css .= "body.brandkit-error-page .page-wrapper,\n";
    $css .= "body.brandkit-error-page .page-body {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  justify-content: center;\n";
    $css .= "  padding: 2rem 1.5rem;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page > .card {\n";
    $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
    $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  border-radius: 1.5rem !important;\n";
    $css .= "  border: 1px solid rgba(255, 255, 255, 0.08) !important;\n";
    $css .= "  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 255, 255, 0.05) !important;\n";
    $css .= "  width: min(100%, 700px);\n";
    $css .= "  padding: 2rem 2.5rem;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page .alert,\n";
    $css .= "body.brandkit-error-page #page .alert-danger,\n";
    $css .= "body.brandkit-error-page #page .alert-warning,\n";
    $css .= "body.brandkit-error-page #page .alert-info {\n";
    $css .= "  background-color: transparent !important;\n";
    $css .= "  border: 1px solid rgba(220, 53, 69, 0.3) !important;\n";
    $css .= "  border-radius: 0.75rem !important;\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "  backdrop-filter: blur(5px);\n";
    $css .= "  -webkit-backdrop-filter: blur(5px);\n";
    $css .= "  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);\n";
    $css .= "  padding: 1rem 1.25rem;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page .alert-warning {\n";
    $css .= "  border-color: rgba(245, 158, 11, 0.45) !important;\n";
    $css .= "  background-color: rgba(245, 158, 11, 0.12) !important;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page .alert * {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page #page .alert a,\n";
    $css .= "body.brandkit-error-page #page .btn.btn-primary {\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) .page,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) .page-wrapper,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) .page-body {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  background: transparent !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) .navbar {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  justify-content: center;\n";
    $css .= "  padding: 2rem 1.5rem;\n";
    $css .= "  background-image: var(--brandkit-login-error-wallpaper, none) !important;\n";
    $css .= "  background-color: var(--brandkit-login-error-overlay, transparent) !important;\n";
    $css .= "  background-size: cover !important;\n";
    $css .= "  background-position: center !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-attachment: fixed !important;\n";
    $css .= "  background-blend-mode: darken !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page.legacy {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  justify-content: center;\n";
    $css .= "  padding: 2rem 1.5rem;\n";
    $css .= "  background-image: var(--brandkit-login-error-wallpaper, none) !important;\n";
    $css .= "  background-color: var(--brandkit-login-error-overlay, transparent) !important;\n";
    $css .= "  background-size: cover !important;\n";
    $css .= "  background-position: center !important;\n";
    $css .= "  background-repeat: no-repeat !important;\n";
    $css .= "  background-attachment: fixed !important;\n";
    $css .= "  background-blend-mode: darken !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page > .card,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .card,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page.legacy > .card {\n";
    $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
    $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  border-radius: 1.5rem !important;\n";
    $css .= "  border: 1px solid rgba(255, 255, 255, 0.08) !important;\n";
    $css .= "  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 255, 255, 0.05) !important;\n";
    $css .= "  width: min(100%, 700px);\n";
    $css .= "  padding: 2rem 2.5rem;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert-danger,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert-warning,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert-info {\n";
    $css .= "  background-color: transparent !important;\n";
    $css .= "  border: 1px solid rgba(220, 53, 69, 0.3) !important;\n";
    $css .= "  border-radius: 0.75rem !important;\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "  backdrop-filter: blur(5px);\n";
    $css .= "  -webkit-backdrop-filter: blur(5px);\n";
    $css .= "  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);\n";
    $css .= "  padding: 1rem 1.25rem;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert-warning {\n";
    $css .= "  border-color: rgba(245, 158, 11, 0.45) !important;\n";
    $css .= "  background-color: rgba(245, 158, 11, 0.12) !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert * {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .alert a,\n";
    $css .= "body.horizontal-layout:not(.central):not(.helpdesk) #page .btn.btn-primary {\n";
    $css .= "  display: inline-flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  gap: 0.35rem;\n";
    $css .= "  margin-top: 0.75rem;\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "  padding: 0.9rem 1.75rem;\n";
    $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
    $css .= "  text-decoration: none;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) {\n";
    $css .= "  background-image: var(--brandkit-login-error-wallpaper, none);\n";
    $css .= "  background-color: var(--brandkit-login-error-overlay, transparent);\n";
    $css .= "  background-size: cover;\n";
    $css .= "  background-position: center;\n";
    $css .= "  background-repeat: no-repeat;\n";
    $css .= "  background-attachment: fixed;\n";
    $css .= "  background-blend-mode: darken;\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) .navbar {\n";
    $css .= "  display: none !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) .page,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) .page-wrapper,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) .page-body {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page {\n";
    $css .= "  min-height: 100vh;\n";
    $css .= "  display: flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  justify-content: center;\n";
    $css .= "  padding: 2rem 1.5rem;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page > .card {\n";
    $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
    $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  border-radius: 1.5rem !important;\n";
    $css .= "  border: 1px solid rgba(255, 255, 255, 0.08) !important;\n";
    $css .= "  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 255, 255, 0.05) !important;\n";
    $css .= "  width: min(100%, 700px);\n";
    $css .= "  padding: 2rem 2.5rem;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert-danger,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert-warning,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert-info {\n";
    $css .= "  background-color: transparent !important;\n";
    $css .= "  border: 1px solid rgba(220, 53, 69, 0.3) !important;\n";
    $css .= "  border-radius: 0.75rem !important;\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "  backdrop-filter: blur(5px);\n";
    $css .= "  -webkit-backdrop-filter: blur(5px);\n";
    $css .= "  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);\n";
    $css .= "  padding: 1rem 1.25rem;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert-warning {\n";
    $css .= "  border-color: rgba(245, 158, 11, 0.45) !important;\n";
    $css .= "  background-color: rgba(245, 158, 11, 0.12) !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert * {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .alert a,\n";
    $css .= "body.horizontal-layout:has(#page.legacy > .card) #page .btn.btn-primary {\n";
    $css .= "  display: inline-flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  gap: 0.35rem;\n";
    $css .= "  margin-top: 0.75rem;\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "  padding: 0.9rem 1.75rem;\n";
    $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
    $css .= "  text-decoration: none;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card {\n";
    $css .= "  position: relative;\n";
    $css .= "  background: var(--brandkit-login-panel-color) !important;\n";
    $css .= "  backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  -webkit-backdrop-filter: blur(var(--brandkit-login-panel-blur)) !important;\n";
    $css .= "  border-radius: 1.5rem !important;\n";
    $css .= "  border: 1px solid rgba(255, 255, 255, 0.08) !important;\n";
    $css .= "  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 255, 255, 0.05) !important;\n";
    $css .= "  width: min(100%, 700px);\n";
    $css .= "  margin: 8vh auto 0 auto;\n";
    $css .= "  padding: 2rem 2.5rem;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card::before {\n";
    $css .= "  content: '';\n";
    $css .= "  position: fixed;\n";
    $css .= "  inset: 0;\n";
    $css .= "  background-image: var(--brandkit-login-error-wallpaper, none);\n";
    $css .= "  background-color: var(--brandkit-login-error-overlay, transparent);\n";
    $css .= "  background-size: cover;\n";
    $css .= "  background-position: center;\n";
    $css .= "  background-repeat: no-repeat;\n";
    $css .= "  background-attachment: fixed;\n";
    $css .= "  background-blend-mode: darken;\n";
    $css .= "  z-index: -1;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card .alert,\n";
    $css .= "#page.legacy > .card .alert-danger,\n";
    $css .= "#page.legacy > .card .alert-warning,\n";
    $css .= "#page.legacy > .card .alert-info {\n";
    $css .= "  background-color: transparent !important;\n";
    $css .= "  border: 1px solid rgba(220, 53, 69, 0.3) !important;\n";
    $css .= "  border-radius: 0.75rem !important;\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "  backdrop-filter: blur(5px);\n";
    $css .= "  -webkit-backdrop-filter: blur(5px);\n";
    $css .= "  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);\n";
    $css .= "  padding: 1rem 1.25rem;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card .alert-warning {\n";
    $css .= "  border-color: rgba(245, 158, 11, 0.45) !important;\n";
    $css .= "  background-color: rgba(245, 158, 11, 0.12) !important;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card .alert * {\n";
    $css .= "  color: var(--brandkit-login-text-color) !important;\n";
    $css .= "}\n";
    $css .= "#page.legacy > .card .alert a,\n";
    $css .= "#page.legacy > .card .btn.btn-primary {\n";
    $css .= "  display: inline-flex;\n";
    $css .= "  align-items: center;\n";
    $css .= "  gap: 0.35rem;\n";
    $css .= "  margin-top: 0.75rem;\n";
    $css .= "  background: var(--brandkit-login-button-bg) !important;\n";
    $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
    $css .= "  color: var(--brandkit-login-button-text) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-button-radius) !important;\n";
    $css .= "  clip-path: var(--brandkit-login-button-clip);\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  letter-spacing: 0.03em;\n";
    $css .= "  padding: 0.9rem 1.75rem;\n";
    $css .= "  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);\n";
    $css .= "  text-decoration: none;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .alert .btn-close,\n";
    $css .= "body.welcome-anonymous .alert .close {\n";
    $css .= "  filter: brightness(0) invert(1);\n";
    $css .= "  opacity: 0.8;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .main-content-card .row.justify-content-center > .col-md-5,\n";
    $css .= "body.welcome-anonymous .main-content-card .row.justify-content-center > .col-md-5 {\n";
    $css .= "  flex: 0 0 100%;\n";
    $css .= "  max-width: 100%;\n";
    $css .= "}\n";
    $css .= "body.welcome-anonymous .page-anonymous .card-body,\n";
    $css .= "body.welcome-anonymous .main-content-card .card-body {\n";
    $css .= "  background: transparent !important;\n";
    $css .= "  box-sizing: border-box;\n";
    $css .= "  flex: 1;\n";
    $css .= "  overflow-y: auto;\n";
    $css .= "}\n";
    $css .= ".page-anonymous.brandkit-logo-inside .main-content-card .glpi-logo,\n";
    $css .= "body.welcome-anonymous.brandkit-logo-inside .main-content-card .glpi-logo {\n";
    $css .= "  margin: 0 auto 1rem auto;\n";
    $css .= "}\n";
    if ($logoPosition === 'inside') {
        $css .= ".page-anonymous .container-tight > .text-center,\n";
        $css .= "body.welcome-anonymous .container-tight > .text-center {\n";
        $css .= "  display: block !important;\n";
        $css .= "  text-align: center;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .container-tight > .text-center .glpi-logo,\n";
        $css .= "body.welcome-anonymous .container-tight > .text-center .glpi-logo {\n";
        $css .= "  display: none !important;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .main-content-card::before,\n";
        $css .= "body.welcome-anonymous .main-content-card::before {\n";
        $css .= "  content: '';\n";
        $css .= "  display: block;\n";
        $css .= "  background: var(--glpi-logo-dark-login) no-repeat center;\n";
        $css .= "  background-size: contain;\n";
        $css .= "  width: calc(var(--brandkit-login-logo-width) * var(--brandkit-login-panel-scale));\n";
        $css .= "  max-width: none;\n";
        $css .= "  height: calc(var(--brandkit-login-logo-height) * var(--brandkit-login-panel-scale));\n";
        $css .= "  max-height: none;\n";
        $css .= "  flex: 0 0 auto;\n";
        $css .= "  margin: 0 auto calc(0.25rem * var(--brandkit-login-panel-scale)) auto;\n";
        $css .= "  align-self: center;\n";
        $css .= "}\n";
        $css .= ":root[data-glpi-theme-dark=\"1\"] .page-anonymous .main-content-card::before,\n";
        $css .= ":root[data-glpi-theme-dark=\"1\"] body.welcome-anonymous .main-content-card::before {\n";
        $css .= "  background: var(--glpi-logo-light-login) no-repeat center;\n";
        $css .= "  background-size: contain;\n";
        $css .= "}\n";
    } else {
        $css .= ".page-anonymous .flex-fill,\n";
        $css .= "body.welcome-anonymous .flex-fill {\n";
        $css .= "  padding-top: var(--brandkit-login-logo-offset) !important;\n";
        $css .= "}\n";
        if (!$isGlpi11) {
            $css .= ".page-anonymous > .text-center,\n";
            $css .= "body.welcome-anonymous > .text-center {\n";
            $css .= "  margin-top: var(--brandkit-login-logo-offset) !important;\n";
            $css .= "  width: min(100%, var(--brandkit-login-panel-width));\n";
            $css .= "  margin-left: auto;\n";
            $css .= "  margin-right: auto;\n";
            $css .= "}\n";
        }
    }

    if ($hideLoginText) {
        $css .= ".page-anonymous .card-header,\n";
        $css .= ".page-anonymous .login-title,\n";
        $css .= ".page-anonymous .page-title,\n";
        $css .= "body.welcome-anonymous .card-header,\n";
        $css .= "body.welcome-anonymous .login-title,\n";
        $css .= "body.welcome-anonymous .page-title {\n";
        $css .= "  display: none !important;\n";
        $css .= "}\n";
    }

    $css .= ".page-anonymous .brandkit-login-button,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button {\n";
    $css .= "  font-weight: 600;\n";
    $css .= "  transition: transform 0.2s ease, box-shadow 0.2s ease;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .brandkit-login-button.brandkit-button--square,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-button--square {\n";
    $css .= "  border-radius: 4px;\n";
    $css .= "  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.18);\n";
    $css .= "}\n";
    $css .= ".page-anonymous .brandkit-login-button.brandkit-button--rounded,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-button--rounded {\n";
    $css .= "  border-radius: 10px;\n";
    $css .= "  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18);\n";
    $css .= "}\n";
    $css .= ".page-anonymous .brandkit-login-button.brandkit-button--pill,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-button--pill {\n";
    $css .= "  border-radius: 999px;\n";
    $css .= "  padding-left: 1.6rem;\n";
    $css .= "  padding-right: 1.6rem;\n";
    $css .= "  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.2);\n";
    $css .= "}\n";
    $css .= ".page-anonymous .brandkit-login-button.brandkit-button--cut,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-button--cut {\n";
    $css .= "  border-radius: 6px 18px 6px 18px;\n";
    $css .= "  letter-spacing: 0.04em;\n";
    $css .= "  text-transform: uppercase;\n";
    $css .= "  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2);\n";
    $css .= "  clip-path: polygon(0 0, 92% 0, 100% 35%, 100% 100%, 8% 100%, 0 65%);\n";
    $css .= "}\n";
    $css .= ".page-anonymous .brandkit-login-button.brandkit-button--soft,\n";
    $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-button--soft {\n";
    $css .= "  border-radius: 14px;\n";
    $css .= "  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.35);\n";
    $css .= "}\n";

    if ($buttonColorMode === 'custom') {
        $css .= ".page-anonymous .brandkit-login-button.brandkit-color--custom,\n";
        $css .= "body.welcome-anonymous .brandkit-login-button.brandkit-color--custom {\n";
        $css .= "  background-color: var(--brandkit-login-button-bg) !important;\n";
        $css .= "  border-color: var(--brandkit-login-button-border) !important;\n";
        $css .= "  color: var(--brandkit-login-button-text) !important;\n";
        $css .= "}\n";
    }

    $css .= "body.brandkit-error-page,\n";
    $css .= "body.brandkit-error-page #page,\n";
    $css .= "body.brandkit-error-page main,\n";
    $css .= "body.brandkit-error-page .page-anonymous {\n";
    $css .= "  background-color: #000000 !important;\n";
    $css .= "  background-image: none !important;\n";
    $css .= "}\n";
    $css .= "body.brandkit-error-page .card,\n";
    $css .= "body.brandkit-error-page .card-body {\n";
    $css .= "  background: transparent !important;\n";
    $css .= "}\n";
    if ($hideCopyright) {
        $css .= ".page-anonymous .copyright,\n";
        $css .= "body.welcome-anonymous .copyright {\n";
        $css .= "  display: none !important;\n";
        $css .= "}\n";
    } else {
        $css .= ".page-anonymous .copyright,\n";
        $css .= "body.welcome-anonymous .copyright {\n";
        $css .= "  display: block !important;\n";
        $css .= "  visibility: visible !important;\n";
        $css .= "  opacity: 1 !important;\n";
        $css .= "  position: relative;\n";
        $css .= "  z-index: 2;\n";
        $css .= "  width: min(100%, var(--brandkit-login-container-max-effective));\n";
        $css .= "  margin: 1rem auto 2rem auto;\n";
        $css .= "  text-align: center;\n";
        $css .= "  padding: 0 1rem;\n";
        $css .= "  color: color-mix(in srgb, var(--brandkit-login-text-color) 75%, transparent) !important;\n";
        $css .= "  font-size: 0.75rem;\n";
        $css .= "  letter-spacing: 0.02em;\n";
        $css .= "  text-decoration: none;\n";
        $css .= "}\n";
        $css .= ".page-anonymous .copyright:hover,\n";
        $css .= "body.welcome-anonymous .copyright:hover {\n";
        $css .= "  color: var(--brandkit-login-text-color) !important;\n";
        $css .= "  text-decoration: underline;\n";
        $css .= "}\n";
    }
    if ($fieldStyle !== 'solid') {
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input:-webkit-autofill:active,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input:autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-internal-autofill-selected,\n";
        $css .= ".page-anonymous .main-content-card input:-moz-autofill,\n";
        $css .= ".page-anonymous .main-content-card input:-moz-autofill:focus,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill,\n";
        $css .= ".page-anonymous .main-content-card input.form-control:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-webkit-autofill:active,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:hover,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-webkit-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input:autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-internal-autofill-selected,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input:-moz-autofill:focus,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill,\n";
        $css .= "body.welcome-anonymous .main-content-card input.form-control:-moz-autofill:focus {\n";
        $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  -webkit-background-clip: padding-box !important;\n";
        $css .= "  background-clip: padding-box !important;\n";
        $css .= "  filter: none !important;\n";
        $css .= "}\n";
    }
    $css .= ".page-anonymous #login_name:-webkit-autofill,\n";
    $css .= ".page-anonymous #login_name:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous #login_name:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous #login_name:-webkit-autofill:active,\n";
    $css .= ".page-anonymous #login_name:autofill,\n";
    $css .= ".page-anonymous #login_name:-internal-autofill-selected,\n";
    $css .= ".page-anonymous #login_name:-moz-autofill,\n";
    $css .= ".page-anonymous #login_name:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous #login_name:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous #login_name:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous #login_name:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous #login_name:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous #login_name:autofill,\n";
    $css .= "body.welcome-anonymous #login_name:-internal-autofill-selected,\n";
    $css .= "body.welcome-anonymous #login_name:-moz-autofill,\n";
    $css .= "body.welcome-anonymous #login_name:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-user), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
        $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  -webkit-background-clip: padding-box !important;\n";
        $css .= "  background-clip: padding-box !important;\n";
        $css .= "}\n";
    $css .= ".page-anonymous #login_password:-webkit-autofill,\n";
    $css .= ".page-anonymous #login_password:-webkit-autofill:hover,\n";
    $css .= ".page-anonymous #login_password:-webkit-autofill:focus,\n";
    $css .= ".page-anonymous #login_password:-webkit-autofill:active,\n";
    $css .= ".page-anonymous #login_password:autofill,\n";
    $css .= ".page-anonymous #login_password:-internal-autofill-selected,\n";
    $css .= ".page-anonymous #login_password:-moz-autofill,\n";
    $css .= ".page-anonymous #login_password:-moz-autofill:focus,\n";
    $css .= "body.welcome-anonymous #login_password:-webkit-autofill,\n";
    $css .= "body.welcome-anonymous #login_password:-webkit-autofill:hover,\n";
    $css .= "body.welcome-anonymous #login_password:-webkit-autofill:focus,\n";
    $css .= "body.welcome-anonymous #login_password:-webkit-autofill:active,\n";
    $css .= "body.welcome-anonymous #login_password:autofill,\n";
    $css .= "body.welcome-anonymous #login_password:-internal-autofill-selected,\n";
    $css .= "body.welcome-anonymous #login_password:-moz-autofill,\n";
    $css .= "body.welcome-anonymous #login_password:-moz-autofill:focus {\n";
    $css .= "  background: var(--brandkit-login-icon-password), linear-gradient(var(--brandkit-login-field-bg-autofill), var(--brandkit-login-field-bg-autofill)) !important;\n";
        $css .= "  background-repeat: no-repeat, no-repeat !important;\n";
        $css .= "  background-position: var(--brandkit-login-icon-position), 0 0 !important;\n";
        $css .= "  background-size: 18px 18px, 100% 100% !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  box-shadow: 0 0 0px 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
        $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
        $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "}\n";
    $css .= ".page-anonymous .form-control:autofill,\n";
    $css .= ".page-anonymous .form-control:autofill:hover,\n";
    $css .= ".page-anonymous .form-control:autofill:focus,\n";
    $css .= ".page-anonymous .form-control:autofill:active,\n";
    $css .= "body.welcome-anonymous .form-control:autofill,\n";
    $css .= "body.welcome-anonymous .form-control:autofill:hover,\n";
    $css .= "body.welcome-anonymous .form-control:autofill:focus,\n";
    $css .= "body.welcome-anonymous .form-control:autofill:active {\n";
    $css .= "  background-color: var(--brandkit-login-field-bg-autofill) !important;\n";
    $css .= "  border: var(--brandkit-login-field-border-width) solid var(--brandkit-login-field-border) !important;\n";
    $css .= "  border-radius: var(--brandkit-login-field-radius) !important;\n";
    $css .= "  -webkit-box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  box-shadow: 0 0 0 1000px var(--brandkit-login-field-bg-autofill) inset, var(--brandkit-login-field-shadow-current, var(--brandkit-login-field-shadow)) !important;\n";
    $css .= "  -webkit-text-fill-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  caret-color: var(--brandkit-login-field-text-autofill, var(--tblr-body-color)) !important;\n";
    $css .= "  -webkit-background-clip: padding-box !important;\n";
    $css .= "  background-clip: padding-box !important;\n";
    $css .= "  filter: none !important;\n";
    $css .= "  appearance: none !important;\n";
    $css .= "  -moz-appearance: none !important;\n";
    $css .= "  -webkit-appearance: none !important;\n";
    $css .= "}\n";

    return $css;
}

function brandkit_write_login_css(?string $pluginDocDir, ?array $settings = null): bool
{
    $settings = $settings ?? brandkit_get_login_settings();

    $css = brandkit_build_login_css($settings);

    $docWritten = false;

    if ($pluginDocDir) {
        $normalizedDir = rtrim($pluginDocDir, DIRECTORY_SEPARATOR);
        $cssTargetDir = (basename($normalizedDir) === 'brandkit')
            ? $normalizedDir
            : $normalizedDir . DIRECTORY_SEPARATOR . 'brandkit';
        if (is_dir($cssTargetDir) || @mkdir($cssTargetDir, 0755, true)) {
            $cssPath = $cssTargetDir . '/brandkit-login.css';
            if (@file_put_contents($cssPath, $css) !== false) {
                $docWritten = true;
            }
            $scssPath = $cssTargetDir . '/brandkit-login.scss';
            @file_put_contents($scssPath, $css);
        }
    }

    return $docWritten;
}

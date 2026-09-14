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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Http::displayErrorAndDie(__('Method Not Allowed', 'brandkit'), 405);
}

function brandkit_get_wallpaper_upload_error_message(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return __('Wallpaper upload failed. The file is larger than the allowed size.', 'brandkit');
        case UPLOAD_ERR_PARTIAL:
            return __('Wallpaper upload failed because the file was only partially uploaded.', 'brandkit');
        case UPLOAD_ERR_NO_FILE:
            return __('Please choose an image file to upload.', 'brandkit');
        case UPLOAD_ERR_NO_TMP_DIR:
            return __('Wallpaper upload failed because the temporary upload folder is missing on the server.', 'brandkit');
        case UPLOAD_ERR_CANT_WRITE:
            return __('Wallpaper upload failed because the server could not write the temporary file.', 'brandkit');
        case UPLOAD_ERR_EXTENSION:
            return __('Wallpaper upload was blocked by a PHP extension on the server.', 'brandkit');
        default:
            return sprintf(__('Wallpaper upload failed due to an unexpected error (code %d).', 'brandkit'), $errorCode);
    }
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

$action = $_POST['brandkit_action'] ?? 'upload_logo';
$refreshToken = (string) round(microtime(true) * 1000);
$logoRedirectUrl = Plugin::getWebDir('brandkit') . '/front/index.php?step=1&refresh=' . rawurlencode($refreshToken);
$wallpaperRedirectUrl = Plugin::getWebDir('brandkit') . '/front/index.php?step=3&refresh=' . rawurlencode($refreshToken);
$redirectUrl = in_array($action, ['upload_logo', 'clear_logo'], true) ? $logoRedirectUrl : $wallpaperRedirectUrl;

if (empty($_POST) && empty($_FILES)) {
    Session::addMessageAfterRedirect(__('Upload failed. The file may be too large for the server limits.', 'brandkit'), false, ERROR);
    Html::redirect($redirectUrl);
    exit;
}

// CSRF validation disabled to match osfree upload behavior.

$logoFiles = [
    'logo-GLPI-100-white.png' => __('Expanded menu logo (light)', 'brandkit'),
    'logo-G-100-white.png' => __('Collapsed menu logo (light)', 'brandkit'),
    'logo-GLPI-100-black.png' => __('Expanded menu logo (dark)', 'brandkit'),
    'logo-G-100-black.png' => __('Collapsed menu logo (dark)', 'brandkit'),
    'logo-GLPI-250-black.png' => __('Login logo (light)', 'brandkit'),
    'logo-GLPI-250-white.png' => __('Login logo (dark)', 'brandkit'),
    'favicon.ico' => __('Favicon (browser tab)', 'brandkit'),
];

$allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];

$pluginDocDir = brandkit_get_plugin_storage_dir();
$logosDir = $pluginDocDir ? $pluginDocDir . '/logos' : null;
if (!$logosDir) {
    Session::addMessageAfterRedirect(__('Plugin storage directory is not available.', 'brandkit'), false, ERROR);
    Html::redirect($redirectUrl);
    exit;
}

if (!is_dir($logosDir) && !@mkdir($logosDir, 0755, true)) {
    Session::addMessageAfterRedirect(__('Unable to create plugin logo directory.', 'brandkit'), false, ERROR);
    Html::redirect($redirectUrl);
    exit;
}

$wallpaperBaseName = 'wallpaperLogin';
$picsDir = brandkit_get_pics_dir();
$wallpaperDir = $picsDir ? $picsDir . '/brandkit-wallpapers' : null;
$wallpaperPath = $wallpaperDir ? $wallpaperDir . '/' . $wallpaperBaseName . '.png' : null;

if ($action === 'clear_wallpaper') {
    brandkit_delete_uploaded_wallpaper_variants($wallpaperBaseName);
    Config::setConfigurationValues('plugin:brandkit', [
        'login_wallpaper' => '',
        'login_wallpaper_source' => 'custom',
        'login_wallpaper_url' => '',
    ]);
    brandkit_write_login_css($pluginDocDir);
    Session::addMessageAfterRedirect(__('Login wallpaper cleared.', 'brandkit'), false, INFO);
    Html::redirect($wallpaperRedirectUrl);
    exit;
}

if ($action === 'upload_wallpaper') {
    $wallpaperFile = $_FILES['brandkit_wallpaper'] ?? ($_FILES['login_wallpaper'] ?? null);
    if (!$wallpaperFile || ($wallpaperFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = (int) ($wallpaperFile['error'] ?? UPLOAD_ERR_NO_FILE);
        Session::addMessageAfterRedirect(brandkit_get_wallpaper_upload_error_message($error), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    $extension = strtolower(pathinfo($wallpaperFile['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        Session::addMessageAfterRedirect(__('Allowed formats: PNG, JPG, JPEG, WEBP.', 'brandkit'), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    $tmpName = $wallpaperFile['tmp_name'];
    if (!is_uploaded_file($tmpName)) {
        Session::addMessageAfterRedirect(__('The upload could not be validated.', 'brandkit'), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    if (!$wallpaperPath) {
        Session::addMessageAfterRedirect(__('Wallpaper upload failed. Public pics path is unavailable.', 'brandkit'), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    if ($wallpaperDir && !is_dir($wallpaperDir) && !@mkdir($wallpaperDir, 0755, true)) {
        Session::addMessageAfterRedirect(__('Unable to create wallpaper directory.', 'brandkit'), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    if (is_file($wallpaperPath)) {
        @unlink($wallpaperPath);
    }

    $storedFilename = $wallpaperBaseName . '.png';
    $destination = $wallpaperPath;
    if ($extension === 'png') {
        if (!@move_uploaded_file($tmpName, $destination)) {
            Session::addMessageAfterRedirect(__('Unable to save the uploaded wallpaper. Check file permissions.', 'brandkit'), false, ERROR);
            Html::redirect($wallpaperRedirectUrl);
            exit;
        }
    } else {
        if (brandkit_convert_image_to_png($tmpName, $destination)) {
            $storedFilename = $wallpaperBaseName . '.png';
        } elseif ($extension === 'webp') {
            $storedFilename = $wallpaperBaseName . '.webp';
            $destination = $wallpaperDir ? $wallpaperDir . '/' . $storedFilename : null;
            if (!$destination || !@move_uploaded_file($tmpName, $destination)) {
                Session::addMessageAfterRedirect(__('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'brandkit'), false, ERROR);
                Html::redirect($wallpaperRedirectUrl);
                exit;
            }
        } else {
            Session::addMessageAfterRedirect(__('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'brandkit'), false, ERROR);
            Html::redirect($wallpaperRedirectUrl);
            exit;
        }
    }

    if (!Document::isImage($destination)) {
        @unlink($destination);
        Session::addMessageAfterRedirect(__('The uploaded file is not a valid image.', 'brandkit'), false, ERROR);
        Html::redirect($wallpaperRedirectUrl);
        exit;
    }

    Config::setConfigurationValues('plugin:brandkit', [
        'login_wallpaper' => $storedFilename,
        'login_wallpaper_source' => 'custom',
        'login_wallpaper_url' => '',
    ]);
    brandkit_write_login_css($pluginDocDir);
    Session::addMessageAfterRedirect(__('Login wallpaper uploaded successfully.', 'brandkit'), false, INFO);
    Html::redirect($wallpaperRedirectUrl);
    exit;
}

$targetFile = $_POST['logo_target'] ?? '';
if (!isset($logoFiles[$targetFile])) {
    Session::addMessageAfterRedirect(__('Invalid logo target selected.', 'brandkit'), false, ERROR);
    Html::redirect($logoRedirectUrl);
    exit;
}

$targetUrlKey = brandkit_get_logo_url_config_key($targetFile);
if (!$targetUrlKey) {
    Session::addMessageAfterRedirect(__('Invalid logo URL target selected.', 'brandkit'), false, ERROR);
    Html::redirect($logoRedirectUrl);
    exit;
}

if ($action === 'save_logo_url') {
    $rawUrl = $_POST['logo_url'] ?? '';
    $logoUrl = brandkit_normalize_image_url($rawUrl);
    if (trim((string) $rawUrl) !== '' && $logoUrl === '') {
        Session::addMessageAfterRedirect(__('Image URL must use HTTPS or a same-origin path starting with /.', 'brandkit'), false, ERROR);
        Html::redirect($logoRedirectUrl);
        exit;
    }

    Config::setConfigurationValues('plugin:brandkit', [
        $targetUrlKey => $logoUrl,
    ]);
    brandkit_write_login_css($pluginDocDir);
    Session::addMessageAfterRedirect(__('Image URL saved.', 'brandkit'), false, INFO);
    Html::redirect($logoRedirectUrl);
    exit;
}

if ($action === 'clear_logo_url') {
    Config::setConfigurationValues('plugin:brandkit', [
        $targetUrlKey => '',
    ]);
    brandkit_write_login_css($pluginDocDir);
    Session::addMessageAfterRedirect(__('Image URL cleared.', 'brandkit'), false, INFO);
    Html::redirect($logoRedirectUrl);
    exit;
}

if ($action === 'clear_logo') {
    $destination = $logosDir . '/' . $targetFile;
    if (is_file($destination)) {
        @unlink($destination);
    }
    $restoreErrors = brandkit_restore_original_logo_assets($targetFile, $logosDir);
    foreach ($restoreErrors as $error) {
        switch ($error) {
            case 'favicon_restore_failed':
                Session::addMessageAfterRedirect(__('Unable to restore the original favicon. Check file permissions.', 'brandkit'), false, WARNING);
                break;
            case 'login_restore_failed':
                Session::addMessageAfterRedirect(__('Unable to restore the original login logo. Check file permissions.', 'brandkit'), false, WARNING);
                break;
            case 'login_original_missing':
                Session::addMessageAfterRedirect(__('Original login logo not found in plugin pics.', 'brandkit'), false, WARNING);
                break;
            case 'header_restore_failed':
                Session::addMessageAfterRedirect(__('Unable to restore the original header logo. Check file permissions.', 'brandkit'), false, WARNING);
                break;
            case 'header_original_missing':
                Session::addMessageAfterRedirect(__('Original header logo not found in plugin pics.', 'brandkit'), false, WARNING);
                break;
        }
    }
    Session::addMessageAfterRedirect(__('Logo cleared. GLPI will use the default asset.', 'brandkit'), false, INFO);
    Html::redirect($logoRedirectUrl);
    exit;
}

if (!isset($_FILES['brandkit_logo']) || $_FILES['brandkit_logo']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['brandkit_logo']['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        Session::addMessageAfterRedirect(__('Upload failed. The file is larger than the allowed size.', 'brandkit'), false, ERROR);
    } elseif ($error === UPLOAD_ERR_NO_FILE) {
        Session::addMessageAfterRedirect(__('Please choose an image file to upload.', 'brandkit'), false, ERROR);
    } else {
        Session::addMessageAfterRedirect(__('Upload failed due to an unexpected error.', 'brandkit'), false, ERROR);
    }
    Html::redirect($logoRedirectUrl);
    exit;
}

$extension = strtolower(pathinfo($_FILES['brandkit_logo']['name'], PATHINFO_EXTENSION));
if ($targetFile === 'favicon.ico') {
    if ($extension !== 'ico') {
        Session::addMessageAfterRedirect(__('Favicon must be an ICO file.', 'brandkit'), false, ERROR);
        Html::redirect($logoRedirectUrl);
        exit;
    }
    $allowedFaviconMimes = [
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/ico',
        'image/icon',
    ];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = finfo_file($finfo, $_FILES['brandkit_logo']['tmp_name']);
            finfo_close($finfo);
            if ($detectedMime && !in_array($detectedMime, $allowedFaviconMimes, true)) {
                Session::addMessageAfterRedirect(__('Favicon must be an ICO file.', 'brandkit'), false, ERROR);
                Html::redirect($logoRedirectUrl);
                exit;
            }
        }
    }
} else {
    if (!in_array($extension, $allowedExtensions, true)) {
        Session::addMessageAfterRedirect(__('Allowed formats: PNG, JPG, JPEG, WEBP.', 'brandkit'), false, ERROR);
        Html::redirect($logoRedirectUrl);
        exit;
    }
}

$tmpName = $_FILES['brandkit_logo']['tmp_name'];
if (!is_uploaded_file($tmpName)) {
    Session::addMessageAfterRedirect(__('The upload could not be validated.', 'brandkit'), false, ERROR);
    Html::redirect($logoRedirectUrl);
    exit;
}

$destination = $logosDir . '/' . $targetFile;
if ($targetFile === 'favicon.ico') {
    if (!@move_uploaded_file($tmpName, $destination)) {
        Session::addMessageAfterRedirect(__('Unable to save the uploaded logo. Check file permissions.', 'brandkit'), false, ERROR);
        Html::redirect($logoRedirectUrl);
        exit;
    }
} else {
    if ($extension === 'png') {
        if (!@move_uploaded_file($tmpName, $destination)) {
            Session::addMessageAfterRedirect(__('Unable to save the uploaded logo. Check file permissions.', 'brandkit'), false, ERROR);
            Html::redirect($logoRedirectUrl);
            exit;
        }
    } else {
        if (!brandkit_convert_image_to_png($tmpName, $destination)) {
            Session::addMessageAfterRedirect(__('Unable to process the uploaded logo. Ensure the file is a valid image.', 'brandkit'), false, ERROR);
            Html::redirect($logoRedirectUrl);
            exit;
        }
    }
}

if ($targetFile !== 'favicon.ico') {
    if (!Document::isImage($destination)) {
        @unlink($destination);
        Session::addMessageAfterRedirect(__('The uploaded file is not a valid image.', 'brandkit'), false, ERROR);
        Html::redirect($logoRedirectUrl);
        exit;
    }
}

// Replace the native GLPI login logo files when login logos are uploaded.
$nativeLoginLogo = brandkit_get_native_login_logo_path($targetFile);
if ($nativeLoginLogo) {
    if (!@copy($destination, $nativeLoginLogo)) {
        Session::addMessageAfterRedirect(__('Uploaded, but failed to replace the native login logo. Check file permissions.', 'brandkit'), false, WARNING);
    }
}

$nativeHeaderLogo = brandkit_get_native_header_logo_path($targetFile);
if ($nativeHeaderLogo) {
    if (!@copy($destination, $nativeHeaderLogo)) {
        Session::addMessageAfterRedirect(__('Uploaded, but failed to replace the native header logo. Check file permissions.', 'brandkit'), false, WARNING);
    }
}

if ($targetFile === 'favicon.ico') {
    $nativeFavicon = brandkit_get_native_favicon_path();
    $backupFavicon = $logosDir . '/favicon.ico.bak';
    if ($nativeFavicon) {
        if (is_file($nativeFavicon) && !is_file($backupFavicon)) {
            @copy($nativeFavicon, $backupFavicon);
        }
        if (!@copy($destination, $nativeFavicon)) {
            Session::addMessageAfterRedirect(__('Uploaded, but failed to replace the GLPI favicon. Check file permissions.', 'brandkit'), false, WARNING);
        }
    }
}

Session::addMessageAfterRedirect(__('Logo uploaded successfully.', 'brandkit'), false, INFO);
if ($targetUrlKey) {
    Config::setConfigurationValues('plugin:brandkit', [
        $targetUrlKey => '',
    ]);
}
brandkit_write_login_css($pluginDocDir);
Html::redirect($logoRedirectUrl);

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
 * Serves preview images for Brandkit logos.
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

if (!Session::getLoginUserID() || !Session::haveRight('config', UPDATE)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

$allowedFiles = [
    'logo-GLPI-100-white.png',
    'logo-G-100-white.png',
    'logo-GLPI-100-black.png',
    'logo-G-100-black.png',
    'logo-GLPI-250-black.png',
    'logo-GLPI-250-white.png',
    'favicon.ico',
];

$name = $_GET['name'] ?? '';
if (!is_string($name) || $name === '' || !in_array($name, $allowedFiles, true)) {
    Http::displayErrorAndDie(__('File not found', 'brandkit'), 404);
}

$pluginDocDir = brandkit_get_plugin_storage_dir_existing();
if (!$pluginDocDir) {
    $pluginDocDir = brandkit_get_plugin_storage_dir();
}
$logosDir = $pluginDocDir ? $pluginDocDir . DIRECTORY_SEPARATOR . 'logos' : null;
if (!$logosDir || !is_dir($logosDir)) {
    if (defined('GLPI_VAR_DIR')) {
        $fallback = rtrim(GLPI_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '_plugins' . DIRECTORY_SEPARATOR . 'brandkit' . DIRECTORY_SEPARATOR . 'logos';
        if (is_dir($fallback)) {
            $logosDir = $fallback;
        }
    }
}
if (!$logosDir || !is_dir($logosDir)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'File not found';
    exit;
}

$path = $logosDir . DIRECTORY_SEPARATOR . $name;
$realLogosDir = realpath($logosDir);
$realPath = realpath($path);
if (!$realLogosDir || !$realPath || strpos($realPath, $realLogosDir) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'File not found';
    exit;
}

$mime = null;
if (function_exists('mime_content_type')) {
    $mime = @mime_content_type($realPath);
}
if (!$mime || $mime === 'application/octet-stream') {
    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        $mime = 'image/png';
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    } elseif ($ext === 'ico') {
        $mime = 'image/x-icon';
    } else {
        $mime = 'application/octet-stream';
    }
}

header('Content-Type: ' . $mime);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
readfile($realPath);
exit;

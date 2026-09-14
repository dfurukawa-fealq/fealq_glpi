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

$name = $_GET['name'] ?? '';
$name = basename((string) $name);
if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
    Http::displayErrorAndDie(__('File not found', 'brandkit'), 404);
}

$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$allowed = ['png', 'jpg', 'jpeg', 'webp'];
if (!in_array($extension, $allowed, true)) {
    Http::displayErrorAndDie(__('File not found', 'brandkit'), 404);
}

$storageDir = brandkit_get_plugin_storage_dir();
$wallpaperDir = $storageDir ? $storageDir . DIRECTORY_SEPARATOR . 'wallpaper' : null;
$path = $wallpaperDir ? $wallpaperDir . DIRECTORY_SEPARATOR . $name : null;
if (!$path || !is_file($path)) {
    Http::displayErrorAndDie(__('File not found', 'brandkit'), 404);
}

$mime = brandkit_guess_mime_from_filename($name);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');

readfile($path);

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

document.addEventListener('DOMContentLoaded', function () {
  var loginCard = document.querySelector('.page-anonymous .card.card-md');
  if (loginCard && !loginCard.classList.contains('main-content-card')) {
    loginCard.classList.add('main-content-card');
  }

  var logoNodes = Array.prototype.slice.call(document.querySelectorAll('.page-anonymous .glpi-logo'));
  if (logoNodes.length > 1) {
    logoNodes.slice(1).forEach(function (node) {
      var wrapper = node.closest('[data-brandkit-login-logo="1"]');
      if (wrapper) {
        wrapper.remove();
      } else {
        node.remove();
      }
    });
  }

  var logoSource =
    getComputedStyle(document.documentElement).getPropertyValue('--glpi-logo-dark-login').trim() ||
    getComputedStyle(document.documentElement).getPropertyValue('--glpi-logo-light-login').trim();
  var existingLogo = logoNodes[0] || document.querySelector('.page-anonymous .glpi-logo');

  if (existingLogo && logoSource) {
    var computedBg = getComputedStyle(existingLogo).backgroundImage;
    if (!computedBg || computedBg === 'none') {
      existingLogo.style.background = logoSource + ' no-repeat center';
      existingLogo.style.backgroundSize = 'contain';
    }
  }

  if (loginCard && !existingLogo && logoSource) {
    var logoWrapper = document.createElement('div');
    logoWrapper.className = 'text-center';
    logoWrapper.setAttribute('data-brandkit-login-logo', '1');
    var logo = document.createElement('span');
    logo.className = 'glpi-logo';
    logo.style.background = logoSource + ' no-repeat center';
    logo.style.backgroundSize = 'contain';
    logoWrapper.appendChild(logo);
    loginCard.parentNode.insertBefore(logoWrapper, loginCard);
  }
});

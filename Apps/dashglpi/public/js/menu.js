/**
 * DashGLPI — comportamento global do menu carregado pelo plugin.
 */
(function () {
    function getScreenMode() {
        return window.matchMedia && window.matchMedia('(max-width: 768px)').matches
            ? 'mobile'
            : 'web';
    }

    function exposeScreenMode() {
        var mode = getScreenMode();
        window.DASHGLPI_SCREEN_MODE = mode;
        if (document.documentElement) {
            document.documentElement.dataset.screenMode = mode;
        }
        return mode;
    }

    function mobileCentralRedirect(mode) {
        if (mode !== 'mobile' || !/\/front\/central\.php$/.test(window.location.pathname)) {
            return;
        }

        var dashboardLink = document.querySelector('a[href*="/plugins/dashglpi/front/dashboard.php"]');
        var dashboardUrl = dashboardLink
            ? dashboardLink.href
            : window.location.pathname.replace(/\/front\/central\.php$/, '/plugins/dashglpi/front/dashboard.php');

        if (dashboardUrl && dashboardUrl !== window.location.href) {
            window.location.replace(dashboardUrl);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var mode = exposeScreenMode();
        var links = document.querySelectorAll('a[href*="/plugins/dashglpi/"]');
        for (var i = 0; i < links.length; i++) {
            links[i].setAttribute('target', '_blank');
        }
        mobileCentralRedirect(mode);
    });

    window.addEventListener('resize', exposeScreenMode);
}());

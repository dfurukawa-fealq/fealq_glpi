const CACHE_NAME = 'dashglpi-static-v4-attendance';
const STATIC_PATHS = [
    'manifest.webmanifest',
    'pwa-icons/icon-192.svg',
    'pwa-icons/icon-512.svg',
    'css/style.css',
    'js/menu.js',
    'js/script.js',
    'js/script.admin.js',
    'js/script.charts.js',
    'js/script.kanban.js',
    'js/script.self-service.js',
    'js/script.ticket-attendance.js', // PLAN-20260905-001
    'js/script.sla-monitor.js',
    'js/script.ticket-create.js',
    'vendor/css/bootstrap.min.css',
    'vendor/css/fontawesome.min.css',
    'vendor/js/chart.umd.min.js'
];

function scopedUrl(path) {
    return new URL(path, self.registration.scope).toString();
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => (
        cache.addAll(STATIC_PATHS.map(scopedUrl))
    )));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.includes('/ajax/')) {
        return;
    }

    const isStatic = /\.(?:css|js|svg|woff2?|ttf|webmanifest)$/.test(url.pathname);
    if (!isStatic) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
            return response;
        }))
    );
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Fealq - GLPI', body: event.data ? event.data.text() : 'Novo chamado' };
    }

    const title = data.title || 'Fealq - GLPI';
    const options = {
        body: data.body || 'Novo chamado disponível para atendimento.',
        icon: data.icon || scopedUrl('pwa-icons/icon-192.svg'),
        badge: data.badge || scopedUrl('pwa-icons/icon-192.svg'),
        tag: data.tag || 'dashglpi-notification',
        renotify: true,
        data: { url: data.url || scopedUrl('front/dashboard.php#tickets') }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : scopedUrl('front/dashboard.php#tickets');
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            return clients.openWindow(targetUrl);
        })
    );
});

/**
 * Minimal service worker.
 *
 * Its only job is to make the app installable and to keep the shell
 * (CSS, icons) available instantly. Score data is deliberately NOT
 * cached: two phones sharing one scoreboard must never disagree about
 * the current score, so every page load goes to the network and simply
 * fails honestly if there's no signal.
 */
const SHELL = 'shell-v3';
const SHELL_FILES = [
    '/css/app.css?v=3',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL)
            .then((cache) => cache.addAll(SHELL_FILES))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== SHELL).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Static shell: cache first, it never changes without a version bump.
    const isShell = /\.(css|js|png|svg|webmanifest)$/.test(url.pathname)
        || url.pathname.startsWith('/icons/');

    if (isShell) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(SHELL).then((cache) => cache.put(request, copy));
                return response;
            }))
        );
        return;
    }

    // Everything else - the scoreboard itself - is network only.
});

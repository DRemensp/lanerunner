/* Lane Runner service worker: network-first pages with an offline fallback,
   cache-first for immutable build assets, models, and audio. */
// v4: navigations are now cached as they load, so /game itself boots from
// cache when offline (offline.html only remains for never-visited pages).
// v5: /images (menu stage art) joins the cache-first static media.
// v6: purge cached audio — the five non-Uppbeat tracks lost their license
// and must disappear from client caches too.
// v7: new app/PWA icons (night-city runner art).
// v8: endless preview recrop (same filename, cache-first would pin the old one).
// v9: new SVG-based app icon + another endless recrop.
const CACHE_NAME = 'lanerunner-v9';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll([OFFLINE_URL])),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))),
        ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // Network-first, but keep the last good copy: offline players boot the
        // game from cache (the in-game offline run cooldown handles fairness —
        // see Game.vue). The embedded page data is stale offline (auth/CSRF),
        // which the game already tolerates via the guest-friendly API paths.
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(async () => (await caches.match(request)) || caches.match(OFFLINE_URL)),
        );
        return;
    }

    // Hashed build assets and static game media never change: cache-first.
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/models/') ||
        url.pathname.startsWith('/audio/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/icons/')
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});

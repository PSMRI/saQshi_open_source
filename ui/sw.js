/* SaQshi UI shell cache. Assessment and authentication API responses are never cached here. */
const CACHE_NAME = "saqshi-ui-shell-20260825-network-first-1";
const APP_SHELL = [
    "/ui/dashboard.html",
    "/ui/login.html",
    "/ui/assets/css/sq-ui.css?v=20260824-page-header-soft-1",
    "/ui/assets/js/sq-ui.js?v=20260702-13",
    "/ui/assets/js/app.js?v=20260825-theme-persistence-1",
    "/ui/assets/images/logo.png"
];

self.addEventListener("install", event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener("activate", event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key.startsWith("saqshi-ui-shell-") && key !== CACHE_NAME)
                .map(key => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener("fetch", event => {
    const request = event.request;
    const url = new URL(request.url);

    // Never cache API, authentication, or non-GET requests.
    if (request.method !== "GET" || url.origin !== self.location.origin || url.pathname.startsWith("/api/")) {
        return;
    }

    // Online users must receive the latest UI. The cache is only an offline fallback.
    event.respondWith(
        fetch(request).then(response => {
            if (response.ok && url.pathname.startsWith("/ui/")) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
            }
            return response;
        }).catch(() =>
            caches.match(request).then(cached => cached || caches.match("/ui/dashboard.html"))
        )
    );
});

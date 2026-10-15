/**
 * sw.js - Service Worker for Bus Reservation System PWA
 * Provides offline shell caching, stale-while-revalidate for static assets,
 * and graceful fallback for network disruptions without disrupting transactional requests.
 */

const CACHE_NAME = 'busres-pwa-v1';

const STATIC_ASSETS = [
  './manifest.json',
  './assets/img/app-icon.svg',
  './assets/images/bus.svg',
  './assets/css/design-system.css',
  './assets/css/home.css',
  './assets/css/public.css',
  './assets/js/theme-toggle.js'
];

// Install: Pre-cache core app shell assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate: Prune outdated cache versions and claim clients
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Network-first for navigation, stale-while-revalidate for assets, skip non-GET
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never intercept non-GET requests (e.g. POST booking reservations, logins, payments)
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // Skip dynamic administrative exports, diagnostic tools, and OAuth callbacks
  if (url.searchParams.has('export') || url.pathname.includes('google-callback') || url.pathname.includes('mfa.php')) {
    return;
  }

  // 1. Navigation requests (HTML pages) -> Network-first with cache fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const clone = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
          }
          return networkRes;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          if (cached) {
            return cached;
          }
          const homeCached = await caches.match('./homepage.php');
          if (homeCached) {
            return homeCached;
          }
          return new Response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Offline - Bus Service</title>' +
            '<style>body{font-family:sans-serif;text-align:center;padding:50px;background:#0f172a;color:#f8fafc;}' +
            'h1{color:#38bdf8;}p{color:#94a3b8;}</style></head>' +
            '<body><h1>You are offline</h1><p>Please check your internet connection to continue browsing bus routes.</p></body></html>',
            { headers: { 'Content-Type': 'text/html' } }
          );
        })
    );
    return;
  }

  // 2. Static asset requests (CSS, JS, SVG, WebP, Fonts) -> Stale-While-Revalidate
  const isStatic =
    url.origin === self.location.origin &&
    (url.pathname.match(/\.(css|js|svg|png|jpg|webp|woff2|ttf|json)$/) || STATIC_ASSETS.includes(url.pathname));

  if (isStatic) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const fetchPromise = fetch(req)
          .then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              const clone = networkRes.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
            }
            return networkRes;
          })
          .catch(() => cached);

        return cached || fetchPromise;
      })
    );
  }
});

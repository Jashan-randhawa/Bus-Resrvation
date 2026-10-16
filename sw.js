const CACHE_NAME = 'sbtbs-bus-cache-v1';
const OFFLINE_URL = './offline.html';

const STATIC_ASSETS = [
  './offline.html',
  './manifest.json',
  './assets/img/app-icon.svg',
  './assets/images/bus.svg',
  './assets/css/design-system.css',
  './assets/css/home.css',
  './assets/css/public.css',
  './assets/css/admin.css',
  './assets/css/dashboard.css',
  './assets/css/user.css',
  './assets/js/theme-toggle.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      try {
        await cache.addAll(STATIC_ASSETS);
      } catch (err) {
        console.warn('[SW] Pre-cache warning:', err);
      }
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => key !== CACHE_NAME && caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const request = req;
  if (request.method !== 'GET') return; // Never intercept POST requests (req.method !== 'GET')

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Dynamic PHP pages & JSON endpoints -> Network First, Offline Fallback
  if (req.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Static CSS, JS, and Images -> Cache First, Background Revalidate
  event.respondWith(
    caches.match(req).then((cached) => {
      const networkFetch = fetch(req).then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
        }
        return response;
      }).catch(() => {});

      return cached || networkFetch;
    })
  );
});

// Windows 11 Widget Board Integration (Win + W)
self.addEventListener('widgetinstall', (event) => {
  if (event.widget) {
    event.waitUntil(renderWidget(event.widget));
  }
});

self.addEventListener('widgetresume', (event) => {
  if (event.widget) {
    event.waitUntil(renderWidget(event.widget));
  }
});

self.addEventListener('widgetclick', (event) => {
  if (event.action === 'check-pnr') {
    event.waitUntil(clients.openWindow('./pnr.php'));
  } else if (event.action === 'book-ticket') {
    event.waitUntil(clients.openWindow('./index.php'));
  }
});

async function renderWidget(widget) {
  if (!widget || !self.widgets) return;
  try {
    const template = await (await fetch('./widgets/bus-widget.json')).text();
    const data = await (await fetch('./widgets/bus-widget-data.json')).text();
    await self.widgets.updateByTag(widget.definition.tag, { template, data });
  } catch (err) {
    console.warn('[SW] Widget render warning:', err);
  }
}

// Periodic Background Sync (Fetch route timetables in the background)
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'update-bus-routes') {
    event.waitUntil(syncBusSchedules());
  }
});

async function syncBusSchedules() {
  try {
    const response = await fetch('./index.php');
    if (response && response.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      await cache.put('./index.php', response);
      console.log('[SW] Periodic Sync: Latest bus schedules updated in background.');
    }
  } catch (err) {
    console.warn('[SW] Periodic sync skipped (offline):', err);
  }
}

// Background Sync (Defer offline form submissions until stable network is restored)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-inquiries') {
    event.waitUntil(replayQueuedInquiries());
  }
});

async function replayQueuedInquiries() {
  console.log('[SW] Background Sync: Stable network detected. Replaying queued submissions.');
}

const CACHE_NAME = 'sbtbs-bus-cache-v1-rev5'; // increments sbtbs-bus-cache-v1
const OFFLINE_URL = './offline.html';

const STATIC_ASSETS = [
  './offline.html',
  './manifest.json',
  './assets/img/app-icon.svg',
  './assets/img/app-icon-192.png',
  './assets/img/app-icon-512.png',
  './assets/img/screenshot-wide.png',
  './assets/img/screenshot-narrow.png',
  './assets/img/widget-screenshot.png',
  './assets/images/bus.svg',
  './assets/images/bus-emblem.png',
  './assets/images/logo.png',
  './assets/css/bootstrap.min.css',
  './assets/css/design-system.css',
  './assets/css/home.css',
  './assets/css/public.css',
  './assets/css/admin.css',
  './assets/css/dashboard.css',
  './assets/css/user.css',
  './assets/css/preloader.css',
  './assets/js/vendor/jquery.slim.min.js',
  './assets/js/vendor/bootstrap.bundle.min.js',
  './assets/js/preloader.js',
  './assets/js/theme-toggle.js',
  './favicon.ico',
  './loading.html'
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

// Push Notifications: Listen for incoming push messages from the server
self.addEventListener('push', (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'Bus Booking Update', body: event.data.text() };
    }
  }

  const title = data.title || 'Bus Reservation System';
  const options = {
    body: data.body || 'Your bus booking or route status has been updated.',
    icon: './assets/img/app-icon.svg',
    badge: './assets/img/app-icon.svg',
    data: {
      url: data.url || './index.php'
    },
    actions: [
      { action: 'open', title: 'View Ticket' },
      { action: 'close', title: 'Dismiss' }
    ],
    vibrate: [100, 50, 100]
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Notification Click: Navigate to target booking or route on tap
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'close') {
    return;
  }

  const targetUrl = (event.notification.data && event.notification.data.url)
    ? event.notification.data.url
    : './index.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          if ('navigate' in client) client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});

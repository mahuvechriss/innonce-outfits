const CACHE_NAME = 'innonce-cache-v2';
const STATIC_CACHE = 'innonce-static-v2';
const IMAGE_CACHE = 'innonce-images-v2';

const BASE_URL = '/innonce-outfits';

const PRECACHE_URLS = [
  BASE_URL + '/offline.php',
  BASE_URL + '/manifest.json',
  BASE_URL + '/assets/css/style.css',
  BASE_URL + '/assets/js/app.js',
  BASE_URL + '/assets/images/logo.png',
  BASE_URL + '/assets/images/offline-placeholder.svg',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE_URLS))
      .then(() => caches.open(STATIC_CACHE))
      .then(() => self.skipWaiting())
      .catch(() => {})
  );
});

self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME, STATIC_CACHE, IMAGE_CACHE];
  event.waitUntil(
    caches.keys().then(cacheNames =>
      Promise.all(
        cacheNames.map(cacheName => {
          if (!cacheWhitelist.includes(cacheName))
            return caches.delete(cacheName);
        })
      )
    ).then(() => self.clients.claim())
  );
});

function isNavigationRequest(request) {
  return request.mode === 'navigate' ||
    (request.method === 'GET' &&
     request.headers.get('Accept') &&
     request.headers.get('Accept').includes('text/html'));
}

function isStaticAsset(url) {
  const extensions = ['.css', '.js', '.json', '.woff', '.woff2', '.ttf', '.eot'];
  return extensions.some(ext => url.pathname.endsWith(ext));
}

function isImage(url) {
  const extensions = ['.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.ico'];
  return extensions.some(ext => url.pathname.endsWith(ext));
}

function isSameOrigin(url) {
  return url.origin === self.location.origin;
}

function isCacheableCDN(url) {
  const cdnPatterns = [
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'placehold.co'
  ];
  return cdnPatterns.some(pattern => url.hostname.includes(pattern));
}

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;

  // === NAVIGATION (HTML) — Network only, never cache ===
  if (isNavigationRequest(request)) {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(BASE_URL + '/offline.php')
          .then(fallback => fallback || new Response('Offline', { status: 503 }))
      )
    );
    return;
  }

  // === STATIC ASSETS (CSS, JS, fonts) — Cache-first ===
  if (isStaticAsset(url) && (isSameOrigin(url) || isCacheableCDN(url))) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) {
          event.waitUntil(
            fetch(request).then(response => {
              if (response && response.status === 200) {
                const clone = response.clone();
                caches.open(STATIC_CACHE).then(cache => cache.put(request, clone));
              }
            }).catch(() => {})
          );
          return cached;
        }
        return fetch(request).then(response => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(STATIC_CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        }).catch(() => new Response('', { status: 408, statusText: 'Offline' }));
      })
    );
    return;
  }

  // === IMAGES — Cache-first ===
  if (isImage(url) && isSameOrigin(url)) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(IMAGE_CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        }).catch(() => caches.match(BASE_URL + '/assets/images/logo.png'));
      })
    );
    return;
  }

  // === CDN RESOURCES — Cache-first ===
  if (isCacheableCDN(url)) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(STATIC_CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        }).catch(() => caches.match(BASE_URL + '/offline.php'));
      })
    );
    return;
  }
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

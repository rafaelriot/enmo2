const CACHE_NAME = 'enmo2-cache-v5';
const STATIC_ASSETS = [
  './manifest.json',
  './tailwind-config.js',
  './app.js',
  './offline.html',
  './images/icon-192.png',
  './images/icon-512.png',
  './images/branding.png'
];

// Instalar Service Worker y guardar solo assets estáticos en caché
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activar y limpiar cachés antiguas, tomar control inmediato
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[SW] Eliminando caché antigua:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => {
      // Tomar control de todas las pestañas abiertas inmediatamente
      return self.clients.claim();
    }).then(() => {
      // Notificar a todas las pestañas que hay una nueva versión
      return self.clients.matchAll({ type: 'window' }).then((clients) => {
        clients.forEach((client) => {
          client.postMessage({ type: 'SW_UPDATED', version: CACHE_NAME });
        });
      });
    })
  );
});

// Interceptar solicitudes con estrategias diferenciadas
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // 1. NUNCA cachear peticiones a la API
  if (url.pathname.includes('/api/')) {
    return;
  }

  // 2. Para páginas HTML: Network-First (siempre intentar red primero)
  if (event.request.mode === 'navigate' || 
      event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then((networkResponse) => {
          // Guardar la respuesta fresca en caché para uso offline
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
          return networkResponse;
        })
        .catch(() => {
          // Si falla la red (offline), intentar servir desde caché
          return caches.match(event.request).then((cachedResponse) => {
            return cachedResponse || caches.match('./offline.html');
          });
        })
    );
    return;
  }

  // 3. Para assets estáticos (JS, CSS, imágenes, fuentes): Stale-While-Revalidate
  //    Sirve desde caché inmediatamente pero actualiza en segundo plano
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const fetchPromise = fetch(event.request).then((networkResponse) => {
        // Actualizar la caché en segundo plano con la versión fresca
        if (networkResponse.ok) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return networkResponse;
      }).catch(() => {
        // Red no disponible, no pasa nada, ya servimos desde caché
      });

      // Devolver la versión en caché inmediatamente (o esperar la red si no hay caché)
      return cachedResponse || fetchPromise;
    })
  );
});

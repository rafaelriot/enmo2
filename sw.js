const CACHE_NAME = 'enmo2-cache-v2';
const ASSETS_TO_CACHE = [
  './',
  './inicio_de_sesion.html',
  './inicio_cliente.html',
  './dashboard_repartidor.html',
  './dashboard_principal.html',
  './pedido_en_curso.html',
  './pedidos_disponibles.html',
  './perfil_del_repartidor.html',
  './gestion_de_pedidos.html',
  './historial_pedidos.html',
  './manifest.json',
  './tailwind-config.js',
  './app.js',
  './offline.html',
  './images/icon-192.png',
  './images/icon-512.png',
  './images/branding.png'
];

// Instalar Service Worker y guardar recursos en caché
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activar y limpiar cachés antiguas
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar solicitudes y responder desde caché si está disponible, o hacer petición de red
self.addEventListener('fetch', (event) => {
  // Ignorar peticiones a la API para evitar almacenar en caché respuestas de bases de datos
  if (event.request.url.includes('/api/')) {
    return;
  }
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request).catch(() => {
        // Si falla la red (offline) y se solicita una página HTML, servir offline.html
        if (event.request.headers.get('accept').includes('text/html')) {
          return caches.match('./offline.html');
        }
      });
    })
  );
});

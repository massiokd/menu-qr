const CACHE_NAME = 'fennemenu-v1';
const ASSETS_TO_CACHE = [
  'admin.php',
  'stats.php',
  'settings.php',
  'subscription.php',
  '../assets/css/admin.css',
  'https://cdn.tailwindcss.com',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// 1. Installation : on met les fichiers en cache
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// 2. Activation : on nettoie les anciens caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// 3. Interception des requêtes
self.addEventListener('fetch', (event) => {
  // On ne met pas en cache les requêtes POST (modifications de prix, AJAX)
  if (event.request.method !== 'GET') return;
  
  // On ne met pas en cache les requêtes vers Supabase ou les actions PHP
  if (event.request.url.includes('/actions/') || event.request.url.includes('supabase')) return;

  event.respondWith(
    caches.match(event.request).then((response) => {
      // Si c'est dans le cache, on renvoie le cache, sinon on va sur internet
      return response || fetch(event.request);
    })
  );
});
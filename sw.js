/* Q-TRACK inspector PWA service worker: caches the app shell + pages for offline use.
   Data saves are queued in localStorage by app.js and synced when back online. */
const C = 'qtrack-v1';
const SHELL = ['assets/css/app.css', 'assets/js/app.js', 'assets/img/mark-t.png', 'assets/img/icon-192.png'];
self.addEventListener('install', e => { e.waitUntil(caches.open(C).then(c => c.addAll(SHELL.map(u => new URL(u, self.registration.scope).href))).catch(() => {})); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== C).map(x => caches.delete(x))))); self.clients.claim(); });
self.addEventListener('fetch', e => {
  const r = e.request; if (r.method !== 'GET') return;
  const u = new URL(r.url); if (u.origin !== location.origin) return;
  const isAsset = /\/assets\//.test(u.pathname) || /\/file$/.test(u.pathname);
  if (isAsset) { e.respondWith(caches.match(r, {ignoreSearch: false}).then(m => m || fetch(r).then(res => { const cp = res.clone(); caches.open(C).then(c => c.put(r, cp)); return res; }))); return; }
  // pages: network first, fall back to cache (so an opened job/inspection still works offline)
  e.respondWith(fetch(r).then(res => { if (res.ok && /\/app/.test(u.pathname)) { const cp = res.clone(); caches.open(C).then(c => c.put(r, cp)); } return res; })
    .catch(() => caches.match(r).then(m => m || new Response('<h3 style="font-family:sans-serif;padding:20px">Offline — open this page once while online to use it offline.</h3>', {headers: {'Content-Type': 'text/html'}}))));
});

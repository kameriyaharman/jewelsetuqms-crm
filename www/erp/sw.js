/* Q-TRACK service worker: app shell cache + network-first cache for inspector API reads.
   Writes (inspection saves/photos) are queued in localStorage by the app and replayed when online. */
const C = 'qtrack-erp-v3';
self.addEventListener('install', (e) => { self.skipWaiting(); e.waitUntil(caches.open(C).then((c) => c.addAll([new URL('./', self.registration.scope).href]).catch(() => {}))); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then((k) => Promise.all(k.filter((x) => x !== C).map((x) => caches.delete(x))))); self.clients.claim(); });
self.addEventListener('fetch', (e) => {
  const r = e.request; if (r.method !== 'GET') return;
  const u = new URL(r.url); if (u.origin !== location.origin) return;
  const p = u.pathname;
  // hashed build assets & images: cache first
  if (/\/assets\/|\/img\//.test(p) || /\/api\/file$/.test(p)) {
    e.respondWith(caches.match(r).then((m) => m || fetch(r).then((res) => { if (res.ok) { const cp = res.clone(); caches.open(C).then((c) => c.put(r, cp)); } return res; })));
    return;
  }
  // inspector data reads: network first, fall back to last copy when offline
  if (/\/api\/(app\/|me$|options$|notifications$|profile$)/.test(p)) {
    e.respondWith(fetch(r).then((res) => { if (res.ok) { const cp = res.clone(); caches.open(C).then((c) => c.put(r, cp)); } return res; }).catch(() => caches.match(r).then((m) => m || new Response(JSON.stringify({ error: 'Offline — open this screen once while online to use it offline.' }), { status: 503, headers: { 'Content-Type': 'application/json' } }))));
    return;
  }
  if (/\/api\//.test(p)) return;
  // SPA navigations: network first, fall back to cached index.html
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).then((res) => { const cp = res.clone(); caches.open(C).then((c) => c.put(new URL('./', self.registration.scope).href, cp)); return res; })
      .catch(() => caches.match(new URL('./', self.registration.scope).href)));
  }
});

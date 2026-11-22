/* The Q-TRACK ERP moved from / to /erp/. This replaces the old root service worker: it removes itself
   and its caches so the public website is always loaded fresh. The ERP registers its own worker at /erp/sw.js. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k.startsWith('qtrack-v')).map((k) => caches.delete(k)));
    await self.registration.unregister();
    const cs = await self.clients.matchAll({ type: 'window' });
    cs.forEach((c) => c.navigate(c.url));
  })());
});

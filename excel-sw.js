const CACHE_NAME = 'excel-exports-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('message', async event => {
    if (event.data?.type !== 'START_EXPORT') return;

    const { url, filename } = event.data;
    await broadcast({ type: 'EXPORT_STARTED', filename });

    try {
        const res = await fetch(url);
        if (!res.ok) {
            const text = await res.text().catch(() => '');
            throw new Error(text.substring(0, 200) || `HTTP ${res.status}`);
        }

        const cache = await caches.open(CACHE_NAME);
        await cache.put('pending-export', res);

        await broadcast({ type: 'EXPORT_DONE', filename });
    } catch (err) {
        await broadcast({ type: 'EXPORT_ERROR', error: err.message });
    }
});

async function broadcast(msg) {
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(c => c.postMessage(msg));
}

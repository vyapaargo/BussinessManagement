// Minimal service worker solely to enable native browser "Add to Home Screen" shortcut prompt
// No offline caching, no push notifications, no background sync.

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

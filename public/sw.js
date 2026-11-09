/*
 * GIYA offline worker.
 *
 * Many churches in the south and north of Cebu have weak mobile data. This
 * keeps the pages a pilgrim has opened (their itineraries, church pages,
 * Home, the map) so they still open without signal, and shows an offline
 * page for anything else.
 *
 * - Pages: network first; the copy is kept for next time. Only GET pages a
 *   pilgrim would want offline are kept, never admin, chat or forms.
 * - Styles, scripts, fonts, images: served from the copy, refreshed quietly.
 * - Map tiles: only tiles the pilgrim has already looked at, up to 600
 *   (OpenStreetMap allows caching viewed tiles, not bulk downloading).
 * - Signing out or in clears the saved pages, so the next person on a shared
 *   phone never sees someone else's itineraries.
 */
const VERSION = 'giya-v1';
const SHELL = VERSION + '-shell';
const PAGES = 'giya-pages';
const TILES = 'giya-tiles';
const MAX_TILES = 600;
const MAX_PAGES = 60;

const PRECACHE = [
  '/offline',
  '/assets/css/giya.css',
  '/assets/css/giya-icons.css',
  '/assets/js/giya.js',
  '/images/logo/giya-logo.svg',
  '/images/brand/icon-192.png',
];

// Pages worth having offline.
const KEEP = [/^\/home$/, /^\/map$/, /^\/plan$/, /^\/plan\/my-itineraries$/, /^\/plan\/\d+$/, /^\/plan\/visita-iglesia$/,
              /^\/churches\/\d+$/, /^\/profile$/, /^\/privacy$/, /^\/terms$/];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(SHELL).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.endsWith('-shell') && k !== SHELL).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'giya:clear-pages') caches.delete(PAGES);
});

async function trim(cacheName, max) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
}

async function page(request) {
  const url = new URL(request.url);
  try {
    const response = await fetch(request);
    if (response.ok && KEEP.some((re) => re.test(url.pathname)) && !response.redirected) {
      const cache = await caches.open(PAGES);
      await cache.put(url.pathname, response.clone());
      trim(PAGES, MAX_PAGES);
    }
    return response;
  } catch (e) {
    const saved = await caches.match(url.pathname, { cacheName: PAGES });
    return saved || (await caches.match('/offline')) || Response.error();
  }
}

async function asset(request) {
  const cache = await caches.open(SHELL);
  const hit = await cache.match(request, { ignoreSearch: true });
  const refresh = fetch(request).then((response) => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  }).catch(() => hit);
  return hit || refresh;
}

async function tile(request) {
  const cache = await caches.open(TILES);
  const hit = await cache.match(request);
  if (hit) return hit;
  try {
    const response = await fetch(request);
    if (response.ok || response.type === 'opaque') {
      await cache.put(request, response.clone());
      trim(TILES, MAX_TILES);
    }
    return response;
  } catch (e) {
    return (await caches.match('/tiles/blank.png')) || Response.error();
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  if (url.hostname === 'tile.openstreetmap.org' || (url.origin === location.origin && url.pathname.startsWith('/tiles/'))) {
    event.respondWith(tile(request));
    return;
  }

  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/chatbot') || url.pathname.startsWith('/livewire')) return;

  if (request.mode === 'navigate') {
    // Signing in or out: forget the previous person's saved pages.
    if (url.pathname === '/login' || url.pathname === '/register') caches.delete(PAGES);
    event.respondWith(page(request));
    return;
  }

  if (/^\/(assets|images|build)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest') {
    event.respondWith(asset(request));
  }
});

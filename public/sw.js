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
// Change this whenever the caching rules change: the old saved copies are dropped.
const VERSION = 'giya-v7';
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
  '/images/logo/giya-logo.svg?v=2',
  '/images/brand/icon-192.png?v=2',
];

// Pages worth having offline.
const KEEP = [/^\/home$/, /^\/map$/, /^\/plan$/, /^\/plan\/my-itineraries$/, /^\/plan\/\d+$/, /^\/plan\/visita-iglesia$/,
              /^\/churches\/\d+$/, /^\/profile$/, /^\/privacy$/, /^\/terms$/];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(SHELL).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

/*
 * Navigation preload: the browser asks for the page itself, the same way it
 * would with no worker at all, while the worker is still starting. A request
 * the worker made by hand failed more often on a slow server (Render's free
 * plan waking up) and on weak data, and showed "GIYA is not answering" for a
 * page that loaded fine a second later.
 */
self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.resolve(self.registration.navigationPreload && self.registration.navigationPreload.enable()).catch(() => {})
      .then(() => caches.keys())
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

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/*
 * One failed request is not "no signal". A connection the phone had kept
 * open goes stale, the server restarts for a deploy, the browser wakes from
 * the background: each fails once and works a moment later. The browser
 * retries those by itself for an ordinary page load, but not for a request
 * made from here, so this used to show "You are offline" to people with
 * full signal. Now it tries twice more before believing it.
 *
 * Not retried: when the phone itself says there is no connection (nothing
 * to wait for), and one-time links (Google's sign-in return, signed email
 * links), which must never be sent twice.
 */
async function fetchPatiently(request, url) {
  try {
    return await fetch(request);
  } catch (first) {
    const oneTime = url.pathname.startsWith('/auth/') || url.searchParams.has('code') || url.searchParams.has('signature');
    if (self.navigator.onLine === false || oneTime) throw first;

    let last = first;
    for (const ms of [500, 1500, 3000]) {
      await wait(ms);
      try {
        return await fetch(request);
      } catch (again) {
        last = again;
      }
    }
    throw last;
  }
}

async function page(event) {
  const request = event.request;
  const url = new URL(request.url);
  try {
    let response;
    try {
      response = await event.preloadResponse;
    } catch (e) {
      response = undefined; // the browser's own try failed: try again from here
    }
    if (!response) response = await fetchPatiently(request, url);
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

/*
 * The stylesheet and scripts are linked as giya.css?v=<when it last changed>,
 * so each deploy asks for a new address. The copy used to be looked up
 * ignoring that ?v=, which handed back the first version ever saved after
 * every deploy: style fixes went live and nobody saw them. Now the exact
 * address must match. A different version is fetched fresh, and the old
 * one is used only when there is no signal.
 */
async function asset(request) {
  const cache = await caches.open(SHELL);
  const exact = await cache.match(request);

  const refresh = fetch(request).then((response) => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  });

  if (exact) {
    refresh.catch(() => {});
    return exact;
  }

  try {
    return await refresh;
  } catch (e) {
    // Offline and never saved at this version: any saved version beats nothing.
    return (await cache.match(request, { ignoreSearch: true })) || Response.error();
  }
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
    event.respondWith(page(event));
    return;
  }

  if (/^\/(assets|images|build)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest') {
    event.respondWith(asset(request));
  }
});

/* ---------------------------------------------------------------- push
   A notification for the phone's notification bar (see App\Support\WebPush).
   The server encrypts it for this browser; the browser decrypts it before
   this runs, so event.data is the plain JSON {title, body, url, tag}. */
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data && event.data.text() }; }

  const title = data.title || 'GIYA';
  event.waitUntil(self.registration.showNotification(title, {
    body: data.body || '',
    icon: '/images/brand/icon-192.png',
    badge: '/images/brand/icon-192.png',
    tag: data.tag || undefined,
    renotify: !!data.tag,
    data: { url: data.url || '/home' },
  }));
});

// Tapping it opens the page it is about: an open GIYA window if there is one.
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL((event.notification.data && event.notification.data.url) || '/home', self.location.origin).href;

  event.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const win of wins) {
      if (new URL(win.url).origin === self.location.origin && 'focus' in win) {
        try { await win.navigate(target); } catch (e) {}
        return win.focus();
      }
    }
    return self.clients.openWindow(target);
  })());
});

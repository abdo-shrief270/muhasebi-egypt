/*
 * Muhasebi service worker: keeps the app shell on the device so the POS opens with no internet.
 *
 * - navigations: network first, falling back to the cached index.html (it's an SPA);
 * - /_nuxt/* (hashed file names, never change): cache first; /_nuxt/builds/* (the build manifest): network first;
 * - the API (/api, /up, websockets) and other sites: never touched, never cached.
 *
 * Updates: the first worker takes over at once (offline from the first visit); a newer one waits
 * until the page says so (`SKIP_WAITING`, the «فيه نسخة جديدة — حدّث» toast of plugins/pwa.client.ts)
 * or every window of the old version is closed, so a page never runs against another build's cache.
 *
 * The build (nuxt.config.ts, `nitro:build:public-assets`) writes the version (a hash of the build's
 * files) and the list of /_nuxt files into the copy in .output/public, so every deploy installs a
 * new worker that precaches the new files and drops the old cache.
 */
const VERSION = 'dev'
const PRECACHE = []

const PREFIX = 'muhasebi-shell-'
const CACHE = PREFIX + VERSION
const SHELL = '/index.html'
// The installed app's manifest and icons, so its window and home-screen icon also work offline.
const APP_FILES = ['/site.webmanifest', '/favicon.svg?v=1', '/icons/icon-192.png?v=1']

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE)
    await cache.add(new Request(SHELL, { cache: 'reload' }))
    // Best effort: a file that fails now is cached the first time it's used.
    await Promise.all([...APP_FILES, ...PRECACHE].map(url => cache.add(new Request(url, { cache: 'reload' })).catch(() => undefined)))
  })())
})

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys()
    await Promise.all(keys.filter(key => key.startsWith(PREFIX) && key !== CACHE).map(key => caches.delete(key)))
    await self.clients.claim()
  })())
})

function isApi(path) {
  return path.startsWith('/api/') || path === '/up' || path.startsWith('/broadcasting/') || path.startsWith('/app/')
}

async function networkFirst(request, cacheKey) {
  const cache = await caches.open(CACHE)
  try {
    const response = await fetch(request)
    const isPage = !cacheKey || (response.headers.get('content-type') ?? '').includes('text/html')
    if (response.ok && isPage) {
      await cache.put(cacheKey ?? request, response.clone())
    }
    return response
  }
  catch (error) {
    const cached = await cache.match(cacheKey ?? request)
    if (cached) {
      return cached
    }
    throw error
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(CACHE)
  const cached = await cache.match(request)
  if (cached) {
    return cached
  }
  const response = await fetch(request)
  if (response.ok) {
    await cache.put(request, response.clone())
  }
  return response
}

self.addEventListener('fetch', (event) => {
  const request = event.request
  if (request.method !== 'GET') {
    return
  }
  const url = new URL(request.url)
  if (url.origin !== self.location.origin || isApi(url.pathname)) {
    return
  }

  if (request.mode === 'navigate') {
    // Every page of the SPA is index.html: keep the latest one for offline starts.
    event.respondWith(networkFirst(request, SHELL))
  }
  else if (url.pathname.startsWith('/_nuxt/builds/')) {
    event.respondWith(networkFirst(request))
  }
  else if (url.pathname.startsWith('/_nuxt/')) {
    event.respondWith(cacheFirst(request))
  }
  else {
    event.respondWith(networkFirst(request))
  }
})

// Push notifications (owner alerts, partner shops…): the API sends {title, body, url, tag, urgent}.
self.addEventListener('push', (event) => {
  let data = {}
  try {
    data = event.data ? event.data.json() : {}
  }
  catch {
    data = { title: event.data ? event.data.text() : 'محاسبي' }
  }
  event.waitUntil(self.registration.showNotification(data.title || 'محاسبي', {
    body: data.body || '',
    icon: '/icons/icon-192.png?v=1',
    badge: '/icons/icon-192.png?v=1',
    tag: data.tag || undefined,
    lang: 'ar',
    dir: 'rtl',
    requireInteraction: !!data.urgent,
    data: { url: data.url || '/' },
  }))
})

// Tapping a notification: focus an open window on that page, or open one.
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = new URL(event.notification.data?.url || '/', self.location.origin).href
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
        await client.focus()
        return client.navigate ? client.navigate(url) : undefined
      }
    }
    return self.clients.openWindow(url)
  })())
})

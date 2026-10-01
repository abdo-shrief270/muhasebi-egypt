/**
 * Offline mode: connectivity tracking, sending the sales queued offline (on reconnect, window
 * focus and every 30 seconds). The service worker that keeps the app shell on the device (so the POS
 * opens with no internet) is registered by plugins/pwa.client.ts.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  setupConnectivity(config.public.apiBase)
  setupOutbox({ api: useApi(), store: useSessionStore() })

  const { online, browserOnline } = useConnectivity()
  const outbox = useOutbox()
  const syncSoon = (force = false) => {
    if (browserOnline.value) {
      outbox.sync(force).catch(() => undefined)
    }
  }

  watch(online, (isOnline) => {
    if (isOnline) {
      syncSoon(true)
    }
  })
  window.addEventListener('focus', () => syncSoon())
  setInterval(() => syncSoon(), 30_000)
  nuxtApp.hook('app:mounted', () => syncSoon())
})

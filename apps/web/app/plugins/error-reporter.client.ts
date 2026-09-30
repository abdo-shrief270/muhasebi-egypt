/**
 * Sends uncaught errors (window errors, unhandled promise rejections, Vue render/handler errors)
 * to the API (`POST /client-errors`) so the platform team sees what breaks in the shops.
 *
 * - Only while signed in; nothing personal is sent (the API scrubs again): the message, where it
 *   happened in our code, the page path (no query string), the build.
 * - Deduplicated: the same error is sent at most once per 10 minutes, with how many times it happened.
 * - Rate-limited: at most one batch per 5 s, 10 errors per batch, 30 errors per page load.
 * - Noise is dropped: browser extensions, ResizeObserver warnings, failed API calls (offline / the API's own errors).
 */
interface Report { kind: 'error' | 'rejection', message: string, source?: string, stack?: string, page: string, count: number }

const RESEND_AFTER_MS = 10 * 60 * 1000
const FLUSH_DELAY_MS = 5000
const MAX_PER_BATCH = 10
const MAX_PER_PAGE_LOAD = 30

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const auth = useAuthToken()
  const route = useRoute()

  const pending = new Map<string, Report>()
  const lastSent = new Map<string, number>()
  let sentTotal = 0
  let timer: ReturnType<typeof setTimeout> | undefined
  let sending = false

  function ignored(message: string, source: string | undefined, error: unknown): boolean {
    if (/ResizeObserver loop|^Script error\.?$/i.test(message)) {
      return true
    }
    if (source && /^(chrome|moz|safari)(-web)?-extension:/.test(source)) {
      return true
    }
    // API calls: no answer = offline (handled by the app); an answered error (validation, permission…)
    // is the API's, already logged there.
    if ((error as { name?: string } | null)?.name === 'FetchError') {
      return true
    }
    return /AbortError|The user aborted a request/i.test(message)
  }

  function capture(kind: Report['kind'], error: unknown, fallbackMessage?: string, source?: string) {
    try {
      if (!auth.token.value || sentTotal >= MAX_PER_PAGE_LOAD) {
        return
      }
      const err = error instanceof Error ? error : null
      const message = (err ? `${err.name}: ${err.message}` : (fallbackMessage ?? String(error))).slice(0, 1000)
      const stack = err?.stack?.slice(0, 4000)
      const where = source ?? stack?.split('\n').find(line => /https?:\/\//.test(line))?.match(/(https?:\/\/[^\s)]+)/)?.[1]
      if (!message || ignored(message, where, error)) {
        return
      }
      const key = `${kind}|${message}`
      const queued = pending.get(key)
      if (queued) {
        queued.count++
        return
      }
      pending.set(key, { kind, message, source: where?.slice(0, 500), stack, page: route.path, count: 1 })
      clearTimeout(timer)
      timer = setTimeout(flush, FLUSH_DELAY_MS)
    }
    catch {
      // Never let the reporter itself throw.
    }
  }

  async function flush() {
    if (sending || !pending.size || !auth.token.value || !navigator.onLine) {
      return
    }
    const now = Date.now()
    const batch: Report[] = []
    for (const [key, report] of pending) {
      if (batch.length >= MAX_PER_BATCH || sentTotal + batch.length >= MAX_PER_PAGE_LOAD) {
        break
      }
      pending.delete(key)
      // Sent a little while ago: counted, not sent again yet.
      if (now - (lastSent.get(key) ?? 0) < RESEND_AFTER_MS) {
        continue
      }
      lastSent.set(key, now)
      batch.push(report)
    }
    if (!batch.length) {
      return
    }
    sending = true
    sentTotal += batch.length
    try {
      await $fetch('/client-errors', {
        baseURL: config.public.apiBase,
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: `Bearer ${auth.token.value}` },
        body: { app_version: appVersion(), errors: batch },
      })
    }
    catch {
      // Lost: better than retrying in a loop.
    }
    finally {
      sending = false
      if (pending.size) {
        timer = setTimeout(flush, FLUSH_DELAY_MS)
      }
    }
  }

  window.addEventListener('error', (event) => {
    // Resource load failures (an <img>) come here too, without an error object: skip them.
    if (!event.error && !event.message) {
      return
    }
    capture('error', event.error, event.message, event.filename ? `${event.filename}:${event.lineno}:${event.colno}` : undefined)
  })
  window.addEventListener('unhandledrejection', (event) => {
    capture('rejection', event.reason, typeof event.reason === 'string' ? event.reason : undefined)
  })
  window.addEventListener('online', () => {
    flush()
  })
  // Errors inside components are caught by Vue before they reach window.
  nuxtApp.hook('vue:error', error => capture('error', error))
})

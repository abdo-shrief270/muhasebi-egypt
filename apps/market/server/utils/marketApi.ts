import type { H3Event } from 'h3'

/**
 * The API's public marketplace endpoints, called from this server only (the browser asks
 * /api/market/* here). The visitor's IP goes along, so the API's rate limit is per visitor.
 */
export function marketApi<T>(event: H3Event, path: string, query?: Record<string, unknown>): Promise<T> {
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  return $fetch<T>(`${config.apiInternal}/public/market${path}`, {
    query,
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
  })
}

/** Another public API endpoint (a shop's store: ordering options, coupon, order tracking). */
export function publicApi<T>(event: H3Event, path: string, init: { method?: 'GET' | 'POST', query?: Record<string, unknown>, body?: unknown } = {}): Promise<T> {
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  return $fetch<T>(`${config.apiInternal}/public${path}`, {
    method: init.method ?? 'GET',
    query: init.query,
    body: init.body as Record<string, unknown> | undefined,
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
  })
}

/** A shop store's slug, as the API allows them. */
export function validStoreSlug(slug: string): boolean {
  return /^[a-z0-9-]{3,40}$/.test(slug)
}

/** API errors keep their meaning: 404 = no such offer / shop, 503 = search is down. */
export function rethrow(error: unknown): never {
  const status = (error as { statusCode?: number, status?: number }).statusCode ?? (error as { status?: number }).status ?? 502
  const kept = [404, 422, 503].includes(status) ? status : 502
  throw createError({ statusCode: kept, statusMessage: kept === 404 ? 'Not found' : 'Market unavailable' })
}

/** Only the query keys an endpoint takes, without empty ones. */
export function pick(event: H3Event, allowed: string[]): Record<string, unknown> {
  return Object.fromEntries(Object.entries(getQuery(event)).filter(([k, v]) => allowed.includes(k.replace(/\[\]$/, '')) && v !== '' && v !== undefined))
}

/** A cache key from the exact query (points rounded to ~100 m, so nearby visitors share it). */
export function queryKey(prefix: string, query: Record<string, unknown>): string {
  const q = { ...query }
  for (const k of ['lat', 'lng']) {
    if (q[k] !== undefined) {
      q[k] = Number(q[k]).toFixed(3)
    }
  }
  return `${prefix}:${new URLSearchParams(q as Record<string, string>).toString()}`
}

/** A visitor's POST to the API, never cached, the API's status and message kept (422 / 409 …). */
export async function forwardPost(event: H3Event, path: string, body: Record<string, unknown>): Promise<unknown> {
  setResponseHeader(event, 'Cache-Control', 'no-store')
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  const res = await $fetch.raw(`${config.apiInternal}/public${path}`, {
    method: 'POST',
    body,
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
    ignoreResponseError: true,
  })
  setResponseStatus(event, res.status)
  return res._data
}

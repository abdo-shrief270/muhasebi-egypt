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

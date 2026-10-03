import type { H3Event } from 'h3'

/**
 * The API's public store endpoints, called from this server only (the browser talks to /_store/*
 * here). The customer's IP goes along, so the API's rate limit is per customer, not per server.
 */
export function storeApi<T>(event: H3Event, path: string, query?: Record<string, unknown>): Promise<T> {
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  return $fetch<T>(`${config.apiInternal}/public/stores${path}`, {
    query,
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
  })
}

/** API errors keep their status (404 = no such store / product). */
export function rethrow(error: unknown): never {
  const status = (error as { statusCode?: number, status?: number }).statusCode ?? (error as { status?: number }).status ?? 502
  throw createError({ statusCode: status === 404 ? 404 : 502, statusMessage: status === 404 ? 'Not found' : 'Store unavailable' })
}

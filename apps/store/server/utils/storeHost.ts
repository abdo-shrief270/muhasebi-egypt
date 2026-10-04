import type { H3Event } from 'h3'

/** The request's host name, lowercase, without the port. */
export function requestHostname(event: H3Event): string {
  return getRequestHost(event, { xForwardedHost: true }).toLowerCase().replace(/:\d+$/, '').replace(/\.$/, '')
}

/** Not a shop's own domain (see isPlatformHostname). */
export function isPlatformHost(event: H3Event, host: string): boolean {
  const config = useRuntimeConfig(event)
  return isPlatformHostname(host, String(config.public.storeHost ?? ''), String(config.public.storeUrl ?? ''))
}

/** Which store a host is ({slug, domain}: domain = the shop's own verified one), from the API, cached a minute. */
export const lookupStoreHost = defineCachedFunction(async (event: H3Event, host: string): Promise<{ slug: string, domain: string | null } | null> => {
  const config = useRuntimeConfig(event)
  try {
    const res = await $fetch<{ data: { slug: string, domain: string | null } }>(`${config.apiInternal}/public/stores-host`, {
      query: { domain: host },
      headers: { Accept: 'application/json' },
      timeout: 5000,
    })
    return res.data
  }
  catch {
    return null
  }
}, { name: 'store-host', maxAge: 60, getKey: (_event: H3Event, host: string) => host })

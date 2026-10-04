import type { StoreHome } from '~/types'

/**
 * The store this host is: its subdomain (elnour.muhasebi.com), or the shop's own domain (the
 * server resolved it — server/middleware/0.store-host.ts — and the page carries it to the browser).
 */
export function useHostSlug(): string | null {
  const config = useRuntimeConfig()
  return slugFromHost(useRequestURL().hostname, String(config.public.storeHost ?? '')) ?? useCustomDomainSlug().value
}

/**
 * The store's pages sit at the root here (its subdomain or its own domain) — from the host name
 * alone, so the router can know it before the page's state arrives.
 */
export function useStoreAtRoot(): boolean {
  const config = useRuntimeConfig()
  const host = useRequestURL().hostname
  const storeHost = String(config.public.storeHost ?? '')
  return slugFromHost(host, storeHost) !== null || !isPlatformHostname(host, storeHost, String(config.public.storeUrl ?? ''))
}

function useCustomDomainSlug() {
  return useState<string | null>('custom-domain-slug', () => (import.meta.server ? (useRequestEvent()?.context.customSlug as string | undefined) ?? null : null))
}

export function useStoreSlug(): string {
  return useHostSlug() ?? String(useRoute().params.slug ?? '').toLowerCase()
}

/**
 * Where the store's pages are: `path('/p/1')` for links (/p/1 on a subdomain or the shop's own domain, /elnour/p/1 on the
 * shared domain) and `url(...)` / `media(...)` for absolute addresses (canonical, sitemap, shares).
 */
export function useStorePlace() {
  const config = useRuntimeConfig()
  const slug = useStoreSlug()
  const onSubdomain = useHostSlug() !== null
  const storeHost = String(config.public.storeHost ?? '')
  const ownDomain = useCustomDomainSlug().value !== null
  const atRoot = ownDomain || !!storeHost
  const origin = ownDomain ? `https://${useRequestURL().hostname}` : storeHost ? `https://${slug}.${storeHost}` : String(config.public.storeUrl)
  const path = (p = '') => (atRoot ? (p || '/') : `/${slug}${p}`)
  const url = (p = '') => `${origin}${atRoot ? p : `/${slug}${p}`}`
  const media = (relative: string) => `${origin}${relative}`
  return { slug, onSubdomain, path, url, media }
}

export async function useStoreHome() {
  const slug = useStoreSlug()
  const { data, error } = await useFetch<{ data: StoreHome }>(`/api/stores/${slug}`, { key: `home:${slug}` })
  if (error.value || !data.value) {
    throw createError({ statusCode: error.value?.statusCode === 404 ? 404 : 502, statusMessage: 'store', fatal: true })
  }
  return computed(() => data.value!.data)
}

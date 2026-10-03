import type { StoreHome } from '~/types'

/** The store this request is for: its subdomain (elnour.muhasebi.com), else the path (/elnour/…). */
export function useHostSlug(): string | null {
  const config = useRuntimeConfig()
  return slugFromHost(useRequestURL().hostname, String(config.public.storeHost ?? ''))
}

export function useStoreSlug(): string {
  return useHostSlug() ?? String(useRoute().params.slug ?? '').toLowerCase()
}

/**
 * Where the store's pages are: `path('/p/1')` for links (/p/1 on a subdomain, /elnour/p/1 on the
 * shared domain) and `url(...)` / `media(...)` for absolute addresses (canonical, sitemap, shares).
 */
export function useStorePlace() {
  const config = useRuntimeConfig()
  const slug = useStoreSlug()
  const onSubdomain = useHostSlug() !== null
  const storeHost = String(config.public.storeHost ?? '')
  const origin = storeHost ? `https://${slug}.${storeHost}` : String(config.public.storeUrl)
  const path = (p = '') => (storeHost ? (p || '/') : `/${slug}${p}`)
  const url = (p = '') => `${origin}${storeHost ? p : `/${slug}${p}`}`
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

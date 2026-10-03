import type { StoreHome } from '~/types'

/** The current store (from the route's slug), shared by every page of it. */
export function useStoreSlug(): string {
  return String(useRoute().params.slug ?? '').toLowerCase()
}

export async function useStoreHome() {
  const slug = useStoreSlug()
  const { data, error } = await useFetch<{ data: StoreHome }>(`/api/stores/${slug}`, { key: `home:${slug}` })
  if (error.value || !data.value) {
    throw createError({ statusCode: error.value?.statusCode === 404 ? 404 : 502, statusMessage: 'store', fatal: true })
  }
  return computed(() => data.value!.data)
}

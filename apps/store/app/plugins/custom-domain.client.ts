// On a shop's own domain, pages rendered only in the browser (the cart) have no server state with
// the store's slug: ask this server which store the host is, before the app starts.
export default defineNuxtPlugin(async () => {
  const state = useState<string | null>('custom-domain-slug', () => null)
  const config = useRuntimeConfig()
  const host = window.location.hostname
  const storeHost = String(config.public.storeHost ?? '')
  if (state.value || slugFromHost(host, storeHost) !== null || isPlatformHostname(host, storeHost, String(config.public.storeUrl ?? ''))) {
    return
  }
  state.value = (await $fetch<{ slug: string }>('/api/host').catch(() => null))?.slug ?? null
})

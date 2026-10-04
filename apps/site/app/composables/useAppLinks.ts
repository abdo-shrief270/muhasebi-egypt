/** Links into the app itself (sign-up / sign-in live on the app's domain). */
export function useAppLinks() {
  const base = useRuntimeConfig().public.appUrl.replace(/\/$/, '')
  // The campaign the visit came from rides along to sign-up (the app stores it on the new shop).
  // Server-rendered pages get the plain link; the browser adds the campaign once mounted.
  const campaign = ref<Record<string, string>>({})
  if (import.meta.client) {
    onMounted(() => {
      campaign.value = campaignParams()
    })
  }
  const register = computed(() => {
    const q = new URLSearchParams(campaign.value).toString()
    return `${base}/register${q ? `?${q}` : ''}`
  })
  return reactive({
    register,
    login: `${base}/login`,
    privacy: `${base}/privacy`,
  })
}

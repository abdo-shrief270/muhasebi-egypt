/** Measures the pages that set `definePageMeta({ marketing: true })` (sign-up, sign-in); see utils/marketingTags.ts. */
export default defineNuxtPlugin(() => {
  const router = useRouter()
  let last = ''
  const onRoute = (to: { fullPath: string, meta: Record<string, unknown> }) => {
    if (!to.meta.marketing) {
      last = ''
      pauseMarketing()
      return
    }
    if (to.fullPath === last) return
    last = to.fullPath
    trackMarketingPage(to.fullPath)
  }
  router.afterEach(to => onRoute(to))
  router.isReady().then(() => onRoute(router.currentRoute.value))
})

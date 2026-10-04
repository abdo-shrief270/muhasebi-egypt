/**
 * GA4 and the Meta Pixel, each only when its ID was set at build time, loaded after the page is
 * idle so they never slow the first paint. Page views follow client-side navigation.
 */
export default defineNuxtPlugin(() => {
  const { gaId, metaPixelId } = useRuntimeConfig().public
  const router = useRouter()
  const w = window as unknown as Record<string, unknown> & { dataLayer?: unknown[], gtag?: (...a: unknown[]) => void, fbq?: ((...a: unknown[]) => void) & { queue?: unknown[], loaded?: boolean, version?: string, callMethod?: (...a: unknown[]) => void, push?: unknown } }

  campaignParams()   // remember where this visit came from before the URL changes

  const load = (src: string) => {
    const s = document.createElement('script')
    s.async = true
    s.src = src
    document.head.appendChild(s)
  }

  const start = () => {
    if (gaId) {
      w.dataLayer = w.dataLayer || []
      // gtag.js reads the `arguments` objects themselves from dataLayer.
      w.gtag = function () {
        // eslint-disable-next-line prefer-rest-params
        w.dataLayer!.push(arguments)
      }
      w.gtag('js', new Date())
      w.gtag('config', gaId, { anonymize_ip: true })
      load(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`)
    }
    if (metaPixelId) {
      const fbq = function (...args: unknown[]) {
        fbq.callMethod ? fbq.callMethod(...args) : fbq.queue!.push(args)
      } as NonNullable<typeof w.fbq>
      fbq.queue = []
      fbq.loaded = true
      fbq.version = '2.0'
      w.fbq = fbq
      w._fbq = fbq
      load('https://connect.facebook.net/en_US/fbevents.js')
      fbq('init', metaPixelId)
      fbq('track', 'PageView')
    }
  }

  if (!gaId && !metaPixelId) return

  // Every link into the app's sign-up / sign-in counts, wherever it is on the page.
  const { appUrl } = useRuntimeConfig().public
  const { track } = useTracking()
  document.addEventListener('click', (e) => {
    const a = (e.target as HTMLElement | null)?.closest('a')
    if (!a?.href.startsWith(appUrl)) return
    const place = router.currentRoute.value.path
    if (a.href.includes('/register')) track('sign_up_click', { place })
    else if (a.href.includes('/login')) track('login_click', { place })
  }, { capture: true })
  const idle = (window as unknown as { requestIdleCallback?: (cb: () => void) => void }).requestIdleCallback
  idle ? idle(start) : setTimeout(start, 1500)

  router.afterEach((to, from) => {
    if (to.fullPath === from.fullPath) return
    w.gtag?.('event', 'page_view', { page_path: to.fullPath })
    w.fbq?.('track', 'PageView')
  })
})

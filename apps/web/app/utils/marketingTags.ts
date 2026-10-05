/**
 * The website's measurement (GA4 + Meta Pixel), on the app's sign-up and sign-in pages only — the
 * pages people reach from the website and the ads. Nothing inside the app is measured: leaving those
 * pages switches GA off (its official `ga-disable-<id>` flag) and the pixel only ever sends what is
 * called here (no push-state page views, no automatic events). The one thing reported after that is
 * a finished sign-up (`reportSignup`: Meta CompleteRegistration, GA sign_up). IDs come from
 * NUXT_PUBLIC_GA_ID / NUXT_PUBLIC_META_PIXEL_ID (the same as the website's); empty = nothing loads.
 */
type Fbq = ((...args: unknown[]) => void) & { queue: unknown[], loaded: boolean, version: string, disablePushState?: boolean, callMethod?: (...args: unknown[]) => void }
type Win = Window & Record<string, unknown> & { dataLayer?: unknown[], gtag?: (...args: unknown[]) => void, fbq?: Fbq, _fbq?: Fbq }

function ids() {
  const config = useRuntimeConfig().public
  // Numeric-looking values arrive as numbers from the runtime config.
  return { gaId: String(config.gaId || ''), pixelId: String(config.metaPixelId || '') }
}

function loadScript(src: string) {
  const s = document.createElement('script')
  s.async = true
  s.src = src
  document.head.appendChild(s)
}

function ensureTags(): Win {
  const w = window as unknown as Win
  const { gaId, pixelId } = ids()
  if (gaId && !w.gtag) {
    w.dataLayer = w.dataLayer || []
    w.gtag = function () {
      // gtag.js reads the `arguments` objects themselves from dataLayer.
      // eslint-disable-next-line prefer-rest-params
      w.dataLayer!.push(arguments)
    }
    w.gtag('js', new Date())
    w.gtag('config', gaId, { anonymize_ip: true, send_page_view: false })
    loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`)
  }
  if (pixelId && !w.fbq) {
    const fbq = function (...args: unknown[]) {
      fbq.callMethod ? fbq.callMethod(...args) : fbq.queue.push(args)
    } as Fbq
    fbq.queue = []
    fbq.loaded = true
    fbq.version = '2.0'
    fbq.disablePushState = true   // no PageView for every screen of the app
    w.fbq = fbq
    w._fbq = fbq
    loadScript('https://connect.facebook.net/en_US/fbevents.js')
    fbq('set', 'autoConfig', false, pixelId)   // no automatic click / form events
    fbq('init', pixelId)
  }
  return w
}

/** A view of a measured page (sign-up / sign-in). */
export function trackMarketingPage(path: string): void {
  const { gaId, pixelId } = ids()
  if (!gaId && !pixelId) return
  const w = ensureTags()
  if (gaId) {
    w[`ga-disable-${gaId}`] = false
    w.gtag?.('event', 'page_view', { page_path: path, page_location: window.location.href, page_title: document.title })
  }
  if (pixelId) w.fbq?.('track', 'PageView')
}

/** Inside the app: GA stays off (history-based page views included). */
export function pauseMarketing(): void {
  const { gaId } = ids()
  if (gaId) (window as unknown as Win)[`ga-disable-${gaId}`] = true
}

/** A shop finished signing up: lets the ads aim at people who really register. */
export function reportSignup(): void {
  const { gaId, pixelId } = ids()
  if (!gaId && !pixelId) return
  const w = ensureTags()
  w.gtag?.('event', 'sign_up', { method: 'phone' })
  w.fbq?.('track', 'CompleteRegistration')
}

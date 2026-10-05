/**
 * Tells the website's Meta Pixel that a shop finished signing up (CompleteRegistration), so ads can
 * aim at people who really register, not only at clicks on the sign-up button. This is the only
 * thing the app reports: the pixel loads here and nowhere else, with its automatic page views on
 * route changes and its automatic button events switched off. No-op without NUXT_PUBLIC_META_PIXEL_ID.
 */
export function reportSignup(pixelId: string): void {
  if (!pixelId || import.meta.server) return
  type Fbq = ((...args: unknown[]) => void) & { queue: unknown[], loaded: boolean, version: string, disablePushState?: boolean, callMethod?: (...args: unknown[]) => void }
  const w = window as unknown as { fbq?: Fbq, _fbq?: Fbq }
  if (!w.fbq) {
    const fbq = function (...args: unknown[]) {
      fbq.callMethod ? fbq.callMethod(...args) : fbq.queue.push(args)
    } as Fbq
    fbq.queue = []
    fbq.loaded = true
    fbq.version = '2.0'
    fbq.disablePushState = true   // no PageView for every screen of the app
    w.fbq = fbq
    w._fbq = fbq
    const s = document.createElement('script')
    s.async = true
    s.src = 'https://connect.facebook.net/en_US/fbevents.js'
    document.head.appendChild(s)
    fbq('set', 'autoConfig', false, pixelId)   // no automatic click / form events
    fbq('init', pixelId)
  }
  w.fbq('track', 'CompleteRegistration')
}

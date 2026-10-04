/**
 * Measurement for the marketing: GA4 events and Meta Pixel standard events, only when their IDs are
 * configured (plugins/tracking.client.ts loads them). The campaign a visitor came from (utm_*, fbclid,
 * ref) is kept for the visit and carried to the app's sign-up link, which stores it on the new shop.
 */
const CAMPAIGN_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref'] as const

export function useTracking() {
  function track(event: 'sign_up_click' | 'whatsapp_click' | 'pricing_view' | 'login_click', params: Record<string, string> = {}) {
    if (import.meta.server) return
    const w = window as unknown as { gtag?: (...a: unknown[]) => void, fbq?: (...a: unknown[]) => void }
    w.gtag?.('event', event, params)
    // Meta standard events: a sign-up click is a Lead, a WhatsApp click a Contact.
    if (event === 'sign_up_click') w.fbq?.('track', 'Lead', params)
    else if (event === 'whatsapp_click') w.fbq?.('track', 'Contact', params)
    else if (event === 'pricing_view') w.fbq?.('track', 'ViewContent', { content_name: 'pricing' })
  }
  return { track }
}

/** The campaign this visit came from (first landing page wins), as query params for the app. */
export function campaignParams(): Record<string, string> {
  if (import.meta.server) return {}
  try {
    const saved = JSON.parse(sessionStorage.getItem('muhasebi:campaign') ?? 'null') as Record<string, string> | null
    if (saved) return saved
    const q = new URLSearchParams(window.location.search)
    const found: Record<string, string> = {}
    for (const k of CAMPAIGN_KEYS) {
      const v = q.get(k)
      if (v) found[k] = v.slice(0, 80)
    }
    if (!found.utm_source && q.get('fbclid')) found.utm_source = 'facebook'
    if (!found.utm_source && document.referrer && !document.referrer.startsWith(window.location.origin)) {
      found.utm_source = new URL(document.referrer).hostname.replace(/^www\./, '').slice(0, 80)
      found.utm_medium ??= 'referral'
    }
    sessionStorage.setItem('muhasebi:campaign', JSON.stringify(found))
    return found
  }
  catch {
    return {}
  }
}

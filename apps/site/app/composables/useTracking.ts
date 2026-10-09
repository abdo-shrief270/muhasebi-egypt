/**
 * Measurement for the marketing: GA4 events and Meta Pixel standard events, only when their IDs are
 * configured (plugins/tracking.client.ts loads them). The campaign a visitor came from (utm_*, fbclid,
 * ref) is kept for the visit and carried to the app's sign-up link, which stores it on the new shop.
 */
const CAMPAIGN_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'aff'] as const

/** A partner's link (?aff=CODE) counts for 60 days on this device, even across visits. */
const AFF_KEY = 'muhasebi:aff'
const AFF_DAYS = 60

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
    if (saved) return withAffiliate(saved)
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
    if (found.aff) {
      found.aff = found.aff.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 20)
      localStorage.setItem(AFF_KEY, JSON.stringify({ code: found.aff, at: Date.now() }))
      countVisit(found.aff)
    }
    sessionStorage.setItem('muhasebi:campaign', JSON.stringify(found))
    return withAffiliate(found)
  }
  catch {
    return {}
  }
}

/** The partner code of a recent visit, added when this visit's link didn't carry one. */
function withAffiliate(params: Record<string, string>): Record<string, string> {
  if (params.aff) return params
  try {
    const saved = JSON.parse(localStorage.getItem(AFF_KEY) ?? 'null') as { code: string, at: number } | null
    if (saved?.code && Date.now() - saved.at < AFF_DAYS * 24 * 3600 * 1000) return { ...params, aff: saved.code }
  }
  catch {
    // private mode: only this visit's link counts
  }
  return params
}

/** Tells the API a partner's link brought a visit (it counts one per visitor every few hours). */
function countVisit(code: string) {
  if (!/^[A-Z0-9]{4,20}$/.test(code)) return
  const { apiBase } = useRuntimeConfig().public
  $fetch(`${apiBase}/public/affiliates/${code}/click`, { method: 'POST' }).catch(() => {})
}

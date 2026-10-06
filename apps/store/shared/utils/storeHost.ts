/**
 * Stores live at {slug}.<storeHost> (e.g. elnour.muhasebi.com) when a store host is set, else at
 * <storeUrl>/{slug}. Mirrors the API's OnlineStore\Support\Slugs (pattern and reserved names).
 */
const PATTERN = /^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/
const RESERVED = new Set([
  'assets', 'cart', 'checkout', 'login', 'new', 'order', 'orders', 'robots', 'search', 'sitemap', 'static', '_nuxt',
  'account', 'admin', 'api', 'app', 'beta', 'billing', 'blog', 'cdn', 'dashboard', 'dev', 'docs', 'ftp', 'help',
  'm', 'mail', 'muhasebi', 'my', 'ns1', 'ns2', 'owner', 'pay', 'shop', 'shops', 'smtp', 'staging', 'status',
  'store', 'stores', 'souq', 'souk', 'market', 'support', 'test', 'www',
])

export function validSlug(slug: string): boolean {
  return PATTERN.test(slug) && !slug.includes('--') && !RESERVED.has(slug)
}

/** "elnour.muhasebi.com" → "elnour" (with storeHost "muhasebi.com"), else null. */
export function slugFromHost(hostname: string, storeHost: string): string | null {
  const base = storeHost.trim().toLowerCase()
  const host = hostname.trim().toLowerCase().replace(/\.$/, '').replace(/:\d+$/, '')
  if (!base || !host.endsWith(`.${base}`)) {
    return null
  }
  const label = host.slice(0, -(base.length + 1))
  return validSlug(label) ? label : null
}

/**
 * Names that are never a shop's own domain: the shared store address, the store host itself, this
 * server's own names (localhost, an IP, a container name without a dot). Any other name is a shop's
 * own domain (the server checks it with the API before serving it).
 */
export function isPlatformHostname(hostname: string, storeHost: string, storeUrl: string): boolean {
  const host = hostname.trim().toLowerCase().replace(/\.$/, '').replace(/:\d+$/, '')
  const base = storeHost.trim().toLowerCase()
  let shared = ''
  try {
    shared = new URL(storeUrl).hostname.toLowerCase()
  }
  catch {
    // No shared address.
  }
  return !host.includes('.') || /^[\d.]+$/.test(host) || host.includes(':') || host === shared || (base !== '' && (host === base || host.endsWith(`.${base}`)))
}

// With stores on subdomains, an old address on the shared domain (store.muhasebi.com/elnour/p/1)
// moves for good to the store's own (elnour.muhasebi.com/p/1): one address per page for search engines.
export default defineEventHandler((event) => {
  const storeHost = String(useRuntimeConfig(event).public.storeHost ?? '')
  if (!storeHost || hostSlug(event) !== null) {
    return
  }
  const url = getRequestURL(event, { xForwardedHost: true })
  const match = url.pathname.match(/^\/([a-z0-9-]{3,40})(\/.*)?$/)
  if (!match || !validSlug(match[1]!) || url.pathname.startsWith('/api/') || url.pathname.startsWith('/_nuxt/')) {
    return
  }
  const rest = match[2] && match[2] !== '/' ? match[2] : '/'
  return sendRedirect(event, `https://${match[1]}.${storeHost}${rest}${url.search}`, 301)
})

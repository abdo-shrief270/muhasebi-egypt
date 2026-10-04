// A shop's own domain (www.elnour-mobile.com) serves its store at the root, like its subdomain; and
// once it has one, the subdomain sends visitors there for good (one address per page).
export default defineEventHandler(async (event) => {
  const path = event.path
  if (path.startsWith('/_nuxt/') || path.startsWith('/api/') || path.startsWith('/__nuxt')) {
    return
  }
  const host = requestHostname(event)
  const storeHost = String(useRuntimeConfig(event).public.storeHost ?? '')
  if (slugFromHost(host, storeHost) !== null) {
    const info = await lookupStoreHost(event, host)
    if (info?.domain) {
      return sendRedirect(event, `https://${info.domain}${path}`, 301)
    }
    return
  }
  if (isPlatformHost(event, host)) {
    return
  }
  const info = await lookupStoreHost(event, host)
  if (!info) {
    throw createError({ statusCode: 404, statusMessage: 'No store here' })
  }
  event.context.customSlug = info.slug
})

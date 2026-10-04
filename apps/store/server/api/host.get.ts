// Which store this host is (a shop's own domain), for pages rendered only in the browser.
export default defineEventHandler(async (event) => {
  const host = requestHostname(event)
  const info = isPlatformHost(event, host) ? null : await lookupStoreHost(event, host)
  if (!info) {
    throw createError({ statusCode: 404, statusMessage: 'No store here' })
  }
  setResponseHeader(event, 'Cache-Control', 'private, max-age=60')
  return { slug: info.slug }
})

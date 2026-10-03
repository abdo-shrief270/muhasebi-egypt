// The product feed of the store on this subdomain (elnour.muhasebi.com/feed.xml), for Meta / Google.
export default defineCachedEventHandler(async (event) => {
  const slug = hostSlug(event)
  if (!slug) {
    throw createError({ statusCode: 404 })
  }
  return storeFeed(event, slug, `https://${slug}.${useRuntimeConfig(event).public.storeHost}`)
}, { maxAge: 900, swr: true, getKey: event => `feed-host:${hostSlug(event)}`, varies: ['host', 'x-forwarded-host'] })

// The product feed of the store on this subdomain (elnour.muhasebi.com/feed.xml) or its own domain, for Meta / Google.
export default defineCachedEventHandler(async (event) => {
  const slug = hostSlug(event)
  if (!slug) {
    throw createError({ statusCode: 404 })
  }
  return storeFeed(event, slug, hostOrigin(event, slug))
}, { maxAge: 900, swr: true, getKey: event => `feed-host:${hostSlug(event)}`, varies: ['host', 'x-forwarded-host'] })

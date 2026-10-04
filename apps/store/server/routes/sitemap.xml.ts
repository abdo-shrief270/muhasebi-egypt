// The sitemap of the store on this subdomain (elnour.muhasebi.com/sitemap.xml) or its own domain.
export default defineCachedEventHandler(async (event) => {
  const slug = hostSlug(event)
  if (!slug) {
    throw createError({ statusCode: 404 })
  }
  return storeSitemap(event, slug, hostOrigin(event, slug))
}, { maxAge: 600, swr: true, getKey: event => `sitemap-host:${hostSlug(event)}`, varies: ['host', 'x-forwarded-host'] })

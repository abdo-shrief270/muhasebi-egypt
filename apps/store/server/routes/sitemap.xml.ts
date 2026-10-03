// The sitemap of the store on this subdomain (elnour.muhasebi.com/sitemap.xml).
export default defineCachedEventHandler(async (event) => {
  const slug = hostSlug(event)
  if (!slug) {
    throw createError({ statusCode: 404 })
  }
  return storeSitemap(event, slug, `https://${slug}.${useRuntimeConfig(event).public.storeHost}`)
}, { maxAge: 600, swr: true, getKey: event => `sitemap-host:${hostSlug(event)}`, varies: ['host', 'x-forwarded-host'] })

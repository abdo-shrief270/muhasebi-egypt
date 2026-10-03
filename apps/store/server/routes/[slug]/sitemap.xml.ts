// The sitemap of a store at <storeUrl>/{slug} (stores without their own subdomain).
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  return storeSitemap(event, slug, `${useRuntimeConfig(event).public.storeUrl}/${slug}`)
}, { maxAge: 600, swr: true, getKey: event => `sitemap:${getRouterParam(event, 'slug')}`, varies: ['host', 'x-forwarded-host'] })

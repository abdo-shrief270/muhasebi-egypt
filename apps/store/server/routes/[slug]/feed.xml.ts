// The product feed of a store at <storeUrl>/{slug} (stores without their own subdomain).
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  return storeFeed(event, slug, `${useRuntimeConfig(event).public.storeUrl}/${slug}`)
}, { maxAge: 900, swr: true, getKey: event => `feed:${getRouterParam(event, 'slug')}`, varies: ['host', 'x-forwarded-host'] })

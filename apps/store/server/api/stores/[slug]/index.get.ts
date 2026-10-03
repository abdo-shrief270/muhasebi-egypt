// The store's home: shop, categories, phone models, latest products. Cached 30 s per store.
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  return await storeApi(event, `/${encodeURIComponent(slug)}`).catch(rethrow)
}, { maxAge: 30, swr: true, getKey: event => `home:${getRouterParam(event, 'slug')}` })

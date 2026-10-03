// One product, cached 30 s.
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  const id = getRouterParam(event, 'id') ?? ''
  if (!/^[0-9a-f-]{36}$/i.test(id)) {
    throw createError({ statusCode: 404 })
  }
  return await storeApi(event, `/${encodeURIComponent(slug)}/products/${id}`).catch(rethrow)
}, { maxAge: 30, swr: true, getKey: event => `product:${getRouterParam(event, 'slug')}:${getRouterParam(event, 'id')}` })

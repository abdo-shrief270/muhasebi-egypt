// A shop's page data, a minute.
export default defineCachedEventHandler(async (event) => {
  const slug = (getRouterParam(event, 'slug') ?? '').toLowerCase()
  if (!/^[a-z0-9]{3,16}$/.test(slug)) {
    throw createError({ statusCode: 404 })
  }
  return await marketApi(event, `/shops/${slug}`, pick(event, ['lat', 'lng'])).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => queryKey(`shop:${getRouterParam(event, 'slug')}`, pick(event, ['lat', 'lng'])) })

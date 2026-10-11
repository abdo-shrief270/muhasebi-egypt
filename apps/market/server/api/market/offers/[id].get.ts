// One offer and the same thing in other shops, 30 s.
export default defineCachedEventHandler(async (event) => {
  const id = getRouterParam(event, 'id') ?? ''
  if (!/^[A-Za-z0-9:_-]{1,120}$/.test(id)) {
    throw createError({ statusCode: 404 })
  }
  return await marketApi(event, `/offers/${encodeURIComponent(id)}`, pick(event, ['lat', 'lng'])).catch(rethrow)
}, { maxAge: 30, swr: true, getKey: event => queryKey(`offer:${getRouterParam(event, 'id')}`, pick(event, ['lat', 'lng'])) })

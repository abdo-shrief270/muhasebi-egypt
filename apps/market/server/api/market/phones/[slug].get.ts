// One phone: its prices in every shop, new and used, 60 s.
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!/^[a-z0-9-]{1,140}$/.test(slug)) {
    throw createError({ statusCode: 404 })
  }
  return await marketApi(event, `/phones/${slug}`, pick(event, ['lat', 'lng'])).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => queryKey(`phone:${getRouterParam(event, 'slug')}`, pick(event, ['lat', 'lng'])) })

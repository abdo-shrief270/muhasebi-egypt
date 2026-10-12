// An order's tracking page: never cached (it moves as the shop works on it).
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  const token = getRouterParam(event, 'token') ?? ''
  if (!validStoreSlug(slug) || !/^[A-Za-z0-9]{32}$/.test(token)) {
    throw createError({ statusCode: 404 })
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  return await publicApi(event, `/stores/${slug}/orders/${token}`).catch(rethrow)
})

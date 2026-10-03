// An order's tracking page data. Never cached: the shop moves it along.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  const token = getRouterParam(event, 'token') ?? ''
  if (!/^[A-Za-z0-9]{32}$/.test(token)) {
    throw createError({ statusCode: 404 })
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  return await storeApi(event, `/${encodeURIComponent(slug)}/orders/${token}`).catch(rethrow)
})

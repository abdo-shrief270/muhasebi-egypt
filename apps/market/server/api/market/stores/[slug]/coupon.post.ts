// «عندك كود خصم؟» at one shop. Never cached; refusals keep the API's message.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validStoreSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const body = await readBody<{ code?: string, subtotal?: number }>(event)
  setResponseHeader(event, 'Cache-Control', 'no-store')
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  const res = await $fetch.raw(`${config.apiInternal}/public/stores/${slug}/coupon`, {
    method: 'POST',
    body: { code: String(body?.code ?? ''), subtotal: Number(body?.subtotal ?? 0) },
    headers: { Accept: 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 8000,
    ignoreResponseError: true,
  })
  setResponseStatus(event, res.status)
  return res._data
})

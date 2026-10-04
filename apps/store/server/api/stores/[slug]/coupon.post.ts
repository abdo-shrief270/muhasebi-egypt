// «عندك كود خصم؟»: the code and the cart's goods total to the API, with the customer's IP for its
// rate limit (guessing codes). Never cached.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const body = await readBody<{ code?: unknown, subtotal?: unknown }>(event)
  const code = typeof body?.code === 'string' ? body.code.slice(0, 32) : ''
  const subtotal = Number(body?.subtotal)
  if (!code || !Number.isInteger(subtotal) || subtotal < 0) {
    throw createError({ statusCode: 422, statusMessage: 'Bad coupon' })
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  const res = await $fetch.raw(`${config.apiInternal}/public/stores/${slug}/coupon`, {
    method: 'POST',
    body: { code, subtotal },
    headers: { 'Accept': 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 10_000,
    ignoreResponseError: true,
  })
  setResponseStatus(event, res.status)
  return res._data
})

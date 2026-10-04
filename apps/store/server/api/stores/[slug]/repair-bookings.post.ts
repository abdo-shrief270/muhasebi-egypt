// «احجز صيانة»: the form to the API as JSON, with the customer's IP for its rate limit. Never cached.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const body = await readBody<Record<string, unknown>>(event)
  if (!body || typeof body !== 'object' || JSON.stringify(body).length > 8_000) {
    throw createError({ statusCode: 413, statusMessage: 'Too large' })
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  const res = await $fetch.raw(`${config.apiInternal}/public/stores/${slug}/repair-bookings`, {
    method: 'POST',
    body,
    headers: { 'Accept': 'application/json', ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 15_000,
    ignoreResponseError: true,
  })
  setResponseStatus(event, res.status)
  return res._data
})

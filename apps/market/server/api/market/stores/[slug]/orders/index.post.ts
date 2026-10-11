// An order from the market's cart at one shop: the browser's form (multipart, with the transfer
// photo when there is one, and the offers' branch) goes to the shop's store as is, with the
// customer's IP for its rate limit. Never cached; refusals keep the API's message.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validStoreSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const body = await readRawBody(event, false)
  const contentType = getRequestHeader(event, 'content-type')
  if (!body || !contentType || body.length > 10 * 1024 * 1024) {
    throw createError({ statusCode: 413, statusMessage: 'Too large' })
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  const config = useRuntimeConfig(event)
  const ip = getRequestIP(event, { xForwardedFor: true })
  const res = await $fetch.raw(`${config.apiInternal}/public/stores/${slug}/orders`, {
    method: 'POST',
    body,
    headers: { 'Accept': 'application/json', 'Content-Type': contentType, ...(ip ? { 'X-Forwarded-For': ip } : {}) },
    timeout: 20_000,
    ignoreResponseError: true,
  })
  setResponseStatus(event, res.status)
  return res._data
})

// Product and used-device photos live on the API. In production Caddy serves this path straight from the API;
// this route covers running the market on its own (local preview).
export default defineEventHandler((event) => {
  const path = getRouterParam(event, 'path') ?? ''
  if (!/^(products|stores|used)\/[0-9a-z/-]+\.webp$/.test(path)) {
    throw createError({ statusCode: 404 })
  }
  return proxyRequest(event, `${useRuntimeConfig(event).apiInternal}/public/media/${path}`)
})

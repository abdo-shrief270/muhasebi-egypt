// Product photos and store logos live on the API. In production Caddy serves this path straight
// from the API; this route covers running the store on its own (local preview).
export default defineEventHandler((event) => {
  const path = getRouterParam(event, 'path') ?? ''
  if (!/^(products|stores)\/[0-9a-z/-]+\.webp$/.test(path)) {
    throw createError({ statusCode: 404 })
  }
  return proxyRequest(event, `${useRuntimeConfig(event).apiInternal}/public/media/${path}`)
})

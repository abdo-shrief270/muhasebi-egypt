// The customer rates the shop after a delivered order.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  const token = getRouterParam(event, 'token') ?? ''
  if (!validStoreSlug(slug) || !/^[A-Za-z0-9]{32}$/.test(token)) {
    throw createError({ statusCode: 404 })
  }
  const body = await readBody<{ rating?: number, comment?: string }>(event)
  return forwardPost(event, `/stores/${slug}/orders/${token}/review`, { rating: Number(body?.rating ?? 0), comment: body?.comment ? String(body.comment) : null })
})

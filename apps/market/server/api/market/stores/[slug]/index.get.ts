// How a shop takes orders (pickup / delivery zones, payment, hours, minimum): from its store, 30 s.
export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validStoreSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const res = await publicApi<{ data: { store: Record<string, unknown>, zones: unknown[] } }>(event, `/stores/${slug}`).catch(rethrow)
  // Only what the checkout needs.
  const s = res.data.store
  return { data: { store: { slug: s.slug, name: s.name, mode: s.mode, whatsapp: s.whatsapp, ordering: s.ordering }, zones: res.data.zones ?? [] } }
}, { maxAge: 30, swr: true, getKey: event => `store:${getRouterParam(event, 'slug')}` })

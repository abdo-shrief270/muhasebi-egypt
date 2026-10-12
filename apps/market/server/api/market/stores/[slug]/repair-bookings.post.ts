// «احجز صيانة» at one shop, from the market.
export default defineEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  if (!validStoreSlug(slug)) {
    throw createError({ statusCode: 404 })
  }
  const b = await readBody<Record<string, unknown>>(event)
  const pickText = (k: string) => (b?.[k] === undefined || b?.[k] === null ? null : String(b[k]))
  return forwardPost(event, `/stores/${slug}/repair-bookings`, {
    id: pickText('id'), name: pickText('name'), phone: pickText('phone'), device: pickText('device'), problem: pickText('problem'),
    preferred_on: pickText('preferred_on'), consent: !!b?.consent, website: pickText('website'), source: 'market',
  })
})

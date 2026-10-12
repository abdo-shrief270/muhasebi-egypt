// «في حاجة غلط في العرض ده»: a visitor's report, for the platform admins.
export default defineEventHandler(async (event) => {
  const b = await readBody<Record<string, unknown>>(event)
  const text = (k: string) => (b?.[k] === undefined || b?.[k] === null ? null : String(b[k]))
  return forwardPost(event, '/market/reports', { offer_id: text('offer_id'), reason: text('reason'), note: text('note'), contact: text('contact'), website: text('website') })
})

// The home page, the shops page, phone prices, every phone on sale and every listed shop (cached 10 minutes).
export default defineCachedEventHandler(async (event) => {
  const base = String(useRuntimeConfig(event).public.marketUrl).replace(/\/$/, '')
  const shops = await marketApi<{ data: { slug: string }[] }>(event, '/shops', { limit: 100 }).then(r => r.data).catch(() => [])
  const phones = await marketApi<{ data: { phones: { slug: string, prices: { new: unknown, used: unknown } }[] } }>(event, '/phones').then(r => r.data.phones).catch(() => [])
  const urls = [
    '/', '/shops', '/prices', '/used',
    ...phones.filter(p => p.prices.new || p.prices.used).map(p => `/phones/${p.slug}`),
    ...[...new Set(shops.map(s => s.slug))].map(s => `/s/${s}`),
  ]
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map(u => `  <url><loc>${base}${u}</loc></url>`).join('\n')}\n</urlset>\n`
}, { maxAge: 600, swr: true, getKey: () => 'sitemap' })

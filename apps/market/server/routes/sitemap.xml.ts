// The home page, the shops page and every listed shop (cached 10 minutes).
export default defineCachedEventHandler(async (event) => {
  const base = String(useRuntimeConfig(event).public.marketUrl).replace(/\/$/, '')
  const shops = await marketApi<{ data: { slug: string }[] }>(event, '/shops', { limit: 100 }).then(r => r.data).catch(() => [])
  const urls = ['/', '/shops', ...[...new Set(shops.map(s => s.slug))].map(s => `/s/${s}`)]
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map(u => `  <url><loc>${base}${u}</loc></url>`).join('\n')}\n</urlset>\n`
}, { maxAge: 600, swr: true, getKey: () => 'sitemap' })

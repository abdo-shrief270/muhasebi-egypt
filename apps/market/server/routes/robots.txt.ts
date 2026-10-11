export default defineEventHandler((event) => {
  const base = String(useRuntimeConfig(event).public.marketUrl).replace(/\/$/, '')
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  return `User-agent: *\nAllow: /\nDisallow: /api/\n\nSitemap: ${base}/sitemap.xml\n`
})

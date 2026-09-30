import { docs } from '../../app/data/docs'

// Prerendered at build time (nitro.prerender.routes).
export default defineEventHandler((event) => {
  const site = useRuntimeConfig().public.siteUrl.replace(/\/$/, '')
  const paths = ['/', '/pricing', '/docs', ...docs.map(d => `/docs/${d.slug}`)]
  setHeader(event, 'content-type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${paths.map(p => `  <url><loc>${site}${p}</loc></url>`).join('\n')}
</urlset>
`
})

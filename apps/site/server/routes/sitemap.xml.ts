import { docs } from '../../app/data/docs'

// Prerendered at build time (nitro.prerender.routes): every page with the build date as lastmod
// and its screenshots as image entries (Google Images).
export default defineEventHandler((event) => {
  const site = useRuntimeConfig().public.siteUrl.replace(/\/$/, '')
  const lastmod = new Date().toISOString().slice(0, 10)
  const pages: { path: string, priority: string, images: string[] }[] = [
    { path: '/', priority: '1.0', images: ['/screens/dashboard.webp', '/screens/pos.webp', '/screens/repairs.webp', '/screens/inventory.webp'] },
    { path: '/pricing', priority: '0.9', images: [] },
    { path: '/docs', priority: '0.8', images: [] },
    ...docs.map(d => ({
      path: `/docs/${d.slug}`,
      priority: '0.7',
      images: d.blocks.flatMap(b => (b.t === 'img' ? [b.src] : [])),
    })),
  ]
  const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
  setHeader(event, 'content-type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
${pages.map(p => `  <url>
    <loc>${esc(site + p.path)}</loc>
    <lastmod>${lastmod}</lastmod>
    <priority>${p.priority}</priority>${p.images.map(src => `
    <image:image><image:loc>${esc(site + src)}</image:loc></image:image>`).join('')}
  </url>`).join('\n')}
</urlset>
`
})

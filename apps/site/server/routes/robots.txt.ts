// Prerendered at build time (nitro.prerender.routes).
export default defineEventHandler((event) => {
  const site = useRuntimeConfig().public.siteUrl.replace(/\/$/, '')
  setHeader(event, 'content-type', 'text/plain; charset=utf-8')
  return [
    'User-agent: *',
    'Allow: /',
    'Disallow: /api/',
    '',
    `Sitemap: ${site}/sitemap.xml`,
    '',
    `# A plain-text summary for AI assistants: ${site}/llms.txt (full guide: ${site}/llms-full.txt)`,
    '',
  ].join('\n')
})

export default defineEventHandler((event) => {
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  const slug = hostSlug(event)
  const sitemap = slug ? `\nSitemap: ${hostOrigin(event, slug)}/sitemap.xml\n` : '\n'
  return `User-agent: *\nAllow: /\nDisallow: /cart\nDisallow: /*/cart\nDisallow: /api/\n${sitemap}`
})

import type { H3Event } from 'h3'

/** A store's sitemap: its pages and every product on it, under `base` (the store's address). */
export async function storeSitemap(event: H3Event, slug: string, base: string): Promise<string> {
  const [home, index] = await Promise.all([
    storeApi<{ data: { categories: { id: number }[], device_brands: { models: { id: number }[] }[], store: { updated_at: string } } }>(event, `/${encodeURIComponent(slug)}`),
    storeApi<{ data: { id: string, updated_at: string }[] }>(event, `/${encodeURIComponent(slug)}/index`),
  ]).catch(rethrow)
  const url = (loc: string, lastmod?: string) => `<url><loc>${loc}</loc>${lastmod ? `<lastmod>${lastmod}</lastmod>` : ''}</url>`
  const urls = [
    url(base || '/', home.data.store.updated_at),
    url(`${base}/about`),
    ...home.data.categories.map(c => url(`${base}/c/${c.id}`)),
    ...home.data.device_brands.flatMap(b => b.models.map(m => url(`${base}/m/${m.id}`))),
    ...index.data.map(p => url(`${base}/p/${p.id}`, p.updated_at)),
  ]
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${urls.join('')}</urlset>`
}

/** The store this request's host stands for (elnour.muhasebi.com → elnour), or null. */
export function hostSlug(event: H3Event): string | null {
  return slugFromHost(getRequestHost(event, { xForwardedHost: true }), String(useRuntimeConfig(event).public.storeHost ?? ''))
}

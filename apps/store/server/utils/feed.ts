import type { H3Event } from 'h3'

interface FeedRow {
  id: string
  product_id: string
  title: string
  description: string | null
  brand: string | null
  category: { id: number, name: string }
  price: number
  image: Record<string, string> | null
  images: Record<string, string>[]
  availability: 'in' | 'out'
}

const esc = (text: string) => text.replace(/[<>&'"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '\'': '&apos;', '"': '&quot;' })[c]!)

/**
 * A store's product feed (RSS 2.0 + the g: namespace): the format both Meta Commerce Manager and
 * Google Merchant Center read on a schedule. One item per variant, grouped by product.
 */
export async function storeFeed(event: H3Event, slug: string, base: string): Promise<string> {
  const [home, feed] = await Promise.all([
    storeApi<{ data: { store: { name: string, tagline: string | null } } }>(event, `/${encodeURIComponent(slug)}`),
    storeApi<{ data: FeedRow[] }>(event, `/${encodeURIComponent(slug)}/feed`),
  ]).catch(rethrow)
  const origin = new URL(base).origin
  const image = (urls: Record<string, string>) => `${origin}${urls['1600'] ?? urls['800'] ?? Object.values(urls)[0] ?? ''}`
  const items = feed.data.filter(r => r.image).map((r) => {
    const tag = (name: string, value: string | null | undefined) => (value ? `<g:${name}>${esc(value)}</g:${name}>` : '')
    return [
      '<item>',
      tag('id', r.id),
      tag('item_group_id', r.product_id),
      tag('title', r.title.slice(0, 150)),
      tag('description', (r.description || r.title).slice(0, 5000)),
      tag('link', `${base}/p/${r.product_id}`),
      tag('image_link', image(r.image!)),
      ...r.images.map(i => tag('additional_image_link', image(i))),
      tag('availability', r.availability === 'in' ? 'in stock' : 'out of stock'),
      tag('price', `${(r.price / 100).toFixed(2)} EGP`),
      tag('brand', r.brand ?? home.data.store.name),
      tag('condition', 'new'),
      tag('product_type', r.category.name),
      '</item>',
    ].join('')
  })
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>`
    + `<title>${esc(home.data.store.name)}</title><link>${esc(base)}</link><description>${esc(home.data.store.tagline ?? home.data.store.name)}</description>`
    + `${items.join('')}</channel></rss>`
}

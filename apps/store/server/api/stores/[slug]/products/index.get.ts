// A product list (category, model, search…), cached 30 s per exact query.
const ALLOWED = ['q', 'category', 'brand', 'model', 'quality', 'min', 'max', 'sort', 'page', 'per_page']

export default defineCachedEventHandler(async (event) => {
  const slug = getRouterParam(event, 'slug') ?? ''
  const query = Object.fromEntries(Object.entries(getQuery(event)).filter(([k, v]) => ALLOWED.includes(k) && v !== '' && v !== undefined))
  return await storeApi(event, `/${encodeURIComponent(slug)}/products`, query).catch(rethrow)
}, { maxAge: 30, swr: true, getKey: event => `list:${getRouterParam(event, 'slug')}:${new URLSearchParams(getQuery(event) as Record<string, string>).toString()}` })

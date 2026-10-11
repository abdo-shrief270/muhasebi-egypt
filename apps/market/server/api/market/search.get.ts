// Offers across every listed shop, cached 30 s per exact query.
const ALLOWED = ['q', 'category', 'condition', 'governorate', 'brand', 'shop', 'min', 'max', 'lat', 'lng', 'sort', 'page', 'per_page']

export default defineCachedEventHandler(async (event) => {
  return await marketApi(event, '/search', pick(event, ALLOWED)).catch(rethrow)
}, { maxAge: 30, swr: true, getKey: event => queryKey('search', pick(event, ALLOWED)) })

// Shops near the visitor that take repair bookings, 60 s.
export default defineCachedEventHandler(async (event) => {
  return await marketApi(event, '/repair-shops', pick(event, ['lat', 'lng', 'governorate'])).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => queryKey('repair-shops', pick(event, ['lat', 'lng', 'governorate'])) })

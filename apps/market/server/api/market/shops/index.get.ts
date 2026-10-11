// Shops around a point / in a governorate, a minute.
const ALLOWED = ['governorate', 'lat', 'lng', 'limit']

export default defineCachedEventHandler(async (event) => {
  return await marketApi(event, '/shops', pick(event, ALLOWED)).catch(rethrow)
}, { maxAge: 60, swr: true, getKey: event => queryKey('shops', pick(event, ALLOWED)) })

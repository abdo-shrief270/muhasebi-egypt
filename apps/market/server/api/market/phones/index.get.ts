// Every catalog phone with its prices across the shops («أسعار الموبايلات»), 5 minutes.
export default defineCachedEventHandler(async (event) => {
  return await marketApi(event, '/phones', pick(event, ['brand'])).catch(rethrow)
}, { maxAge: 300, swr: true, getKey: event => queryKey('phones', pick(event, ['brand'])) })

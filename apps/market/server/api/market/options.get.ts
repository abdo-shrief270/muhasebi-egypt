// The filters' lists (categories, conditions, governorates, sorts): an hour.
export default defineCachedEventHandler(async (event) => {
  return await marketApi(event, '/options').catch(rethrow)
}, { maxAge: 3600, swr: true, getKey: () => 'options' })

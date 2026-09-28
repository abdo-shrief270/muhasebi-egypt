/**
 * Every page needs a session unless it's marked `guest`. Pages that belong to a module
 * (definePageMeta({ module: 'repairs' })) are only reachable when the module is usable.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const store = useSessionStore()

  if (to.meta.guest) {
    return store.isLoggedIn ? navigateTo('/') : undefined
  }

  if (!store.isLoggedIn) {
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }

  if (!store.session) {
    await store.load()
  }

  if (to.meta.module && !store.hasModule(to.meta.module)) {
    return navigateTo({ path: '/settings/modules', query: { need: to.meta.module } })
  }
})

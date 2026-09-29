/**
 * Every page needs a session unless it's marked `guest`. Pages that belong to a module
 * (definePageMeta({ module: 'repairs' })) are only reachable when the module is usable.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const store = useSessionStore()

  // Customer-facing pages (a receipt behind a QR code) open for anyone, logged in or not.
  if (to.meta.public) {
    return
  }

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
    return navigateTo(store.isOwner ? { path: '/settings/modules', query: { need: to.meta.module } } : '/')
  }

  if ((to.meta.ownerOnly && !store.isOwner) || (to.meta.permission && !store.can(to.meta.permission))) {
    return navigateTo('/')
  }
})

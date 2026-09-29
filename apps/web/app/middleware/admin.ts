/** Admin pages (definePageMeta({ public: true, layout: 'admin', middleware: 'admin' })) need the admin's own sign-in. */
export default defineNuxtRouteMiddleware((to) => {
  const { token } = useAdminToken()
  if (!token.value && to.path !== '/admin/login') {
    return navigateTo('/admin/login')
  }
})

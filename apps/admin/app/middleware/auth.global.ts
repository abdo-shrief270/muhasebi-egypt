/** Every page but /login needs the admin's sign-in. */
export default defineNuxtRouteMiddleware((to) => {
  const { token } = useAdminToken()
  if (!token.value && to.path !== '/login') {
    return navigateTo('/login')
  }
  if (token.value && to.path === '/login') {
    return navigateTo('/')
  }
})

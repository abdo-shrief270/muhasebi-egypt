/**
 * The platform admin's API client: a separate sign-in and token from any shop session.
 * A 401 sends the admin back to /admin/login.
 */
export function useAdminToken() {
  const cookie = useCookie<string | null>('muhasebi_admin_token', { sameSite: 'strict', maxAge: 60 * 60 * 12 })
  const token = useState<string | null>('admin-token', () => cookie.value ?? null)

  function set(value: string | null) {
    token.value = value
    cookie.value = value
  }

  return { token: readonly(token), set }
}

export function useAdminApi() {
  const config = useRuntimeConfig()
  const auth = useAdminToken()

  return $fetch.create({
    baseURL: `${config.public.apiBase}/admin`,
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      if (auth.token.value) {
        options.headers.set('Authorization', `Bearer ${auth.token.value}`)
      }
    },
    async onResponseError({ response }) {
      if (response.status === 401 || response.status === 403) {
        auth.set(null)
        await navigateTo('/admin/login')
      }
    },
  })
}

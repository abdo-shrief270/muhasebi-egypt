/**
 * Partners (برنامج الشركاء) have their own account, apart from any shop: their token lives in its
 * own cookie and goes only to /affiliates/*. A 401 sends them to the partners' sign-in.
 */
export function usePartnerToken() {
  const cookie = useCookie<string | null>('muhasebi_partner_token', { sameSite: 'lax', maxAge: 60 * 60 * 24 * 30 })
  const token = useState<string | null>('partner-token', () => cookie.value ?? null)

  function set(value: string | null) {
    token.value = value
    cookie.value = value
  }

  return { token: readonly(token), set }
}

export function usePartnerApi() {
  const config = useRuntimeConfig()
  const auth = usePartnerToken()

  return $fetch.create({
    baseURL: config.public.apiBase,
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      if (auth.token.value) {
        options.headers.set('Authorization', `Bearer ${auth.token.value}`)
      }
    },
    async onResponseError({ response }) {
      if (response.status === 401) {
        auth.set(null)
        await navigateTo('/partners/login')
      }
    },
  })
}

/** A partner code from the link a shop came from (?aff=CODE), kept 60 days on this device. */
export function rememberedAffiliate(fromQuery: unknown): string {
  const key = 'muhasebi:aff'
  const clean = (v: unknown) => (typeof v === 'string' ? v.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 20) : '')
  const code = clean(fromQuery)
  try {
    if (code) {
      localStorage.setItem(key, JSON.stringify({ code, at: Date.now() }))
      return code
    }
    const saved = JSON.parse(localStorage.getItem(key) ?? 'null') as { code: string, at: number } | null
    if (saved && Date.now() - saved.at < 60 * 24 * 3600 * 1000) return clean(saved.code)
  }
  catch {
    // private mode: only the link itself counts
  }
  return code
}

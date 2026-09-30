import type { ApiError } from '~/types/api'

export const TOKEN_COOKIE = 'muhasebi_token'

/**
 * The API token, shared app-wide (useState) and persisted in a cookie.
 * Separate useCookie() refs don't see each other's writes, so everything goes through here.
 */
export function useAuthToken() {
  const cookie = useCookie<string | null>(TOKEN_COOKIE, { sameSite: 'lax', maxAge: 60 * 60 * 24 * 30 })
  const token = useState<string | null>('auth-token', () => cookie.value ?? null)

  function set(value: string | null) {
    token.value = value
    cookie.value = value
  }

  return { token: readonly(token), set }
}

/** The branch the user works in; sent to the API as X-Branch-Id. */
export function useBranchId() {
  const cookie = useCookie<string | null>('muhasebi_branch', { sameSite: 'lax', maxAge: 60 * 60 * 24 * 365 })
  const branchId = useState<string | null>('branch-id', () => cookie.value ?? null)

  function set(value: string | null) {
    branchId.value = value
    cookie.value = value
  }

  return { branchId: readonly(branchId), set }
}

/**
 * Typed $fetch bound to the Laravel API with the bearer token.
 * A 401 logs out; a 403 `module_not_enabled` sends the user to the modules page.
 */
export function useApi() {
  const config = useRuntimeConfig()
  const auth = useAuthToken()
  const branch = useBranchId()
  const nuxtApp = useNuxtApp()

  return $fetch.create({
    baseURL: config.public.apiBase,
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      if (auth.token.value) {
        options.headers.set('Authorization', `Bearer ${auth.token.value}`)
      }
      // A queued offline sale is sent with the branch it was made in.
      if (branch.branchId.value && !options.headers.has('X-Branch-Id')) {
        options.headers.set('X-Branch-Id', branch.branchId.value)
      }
    },
    // Any answer means the API is reachable; no answer at all means we're offline (useConnectivity).
    onResponse() {
      markReachable(true)
    },
    onRequestError({ error }) {
      if (error?.name !== 'AbortError') {
        markReachable(false)
      }
    },
    async onResponseError({ response }) {
      const body = response._data as ApiError | undefined

      if (response.status === 401) {
        auth.set(null)
        await navigateTo('/login')
      }
      else if (response.status === 403 && body?.code === 'branch_forbidden') {
        branch.set(null)
      }
      else if (response.status === 402) {
        // Subscription restricted / suspended: say why, and refresh the banner.
        nuxtApp.runWithContext(() => {
          useToast().add({ color: 'warning', title: body?.message ?? 'الاشتراك مش مفعّل', actions: useSessionStore().isOwner ? [{ label: 'الاشتراك', onClick: () => { navigateTo('/settings/billing') } }] : [] })
          useSubscription().refresh()
        })
      }
      else if (response.status === 403 && body?.code === 'module_not_enabled') {
        await navigateTo({ path: '/settings/modules', query: { need: body.module } })
      }
    },
  })
}

export function apiErrorMessage(error: unknown): string {
  const data = (error as { data?: ApiError })?.data

  if (data?.errors) {
    return Object.values(data.errors).flat()[0] ?? data.message
  }

  return data?.message ?? 'حصلت مشكلة، حاول تاني.'
}

/** No answer from the server at all (offline, DNS, timeout) — as opposed to an error response. */
export function isNetworkError(error: unknown): boolean {
  const e = error as { name?: string, response?: unknown } | null
  return !!e && e.name === 'FetchError' && !e.response
}

/** The HTTP status of an error response, or null when there was no answer. */
export function apiErrorStatus(error: unknown): number | null {
  return (error as { response?: { status?: number } })?.response?.status ?? null
}

/** The API's machine-readable error code (`{message, code}`), if any. */
export function apiErrorCode(error: unknown): string | null {
  return (error as { data?: ApiError })?.data?.code ?? null
}

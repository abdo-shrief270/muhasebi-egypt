import { defineStore } from 'pinia'
import type { LoginResponse, MenuEntry, Session } from '~/types/api'

export const useSessionStore = defineStore('session', () => {
  const api = useApi()
  const auth = useAuthToken()
  const branch = useBranchId()
  const session = ref<Session | null>(null)

  const isLoggedIn = computed(() => !!auth.token.value)
  const menu = computed<MenuEntry[]>(() => session.value?.menu ?? [])

  function hasModule(key: string): boolean {
    return session.value?.enabled_modules.includes(key) ?? false
  }

  function can(permission: string): boolean {
    return session.value?.permissions.includes(permission) ?? false
  }

  const isOwner = computed(() => session.value?.user.is_owner ?? false)
  const currentBranch = computed(() => session.value?.branches.find(b => b.id === session.value?.current_branch_id) ?? null)

  async function switchBranch(id: string): Promise<void> {
    branch.set(id)
    await load()
  }

  async function load(): Promise<Session | null> {
    if (!auth.token.value) {
      return null
    }
    const { data } = await api<{ data: Session }>('/auth/me')
    session.value = data
    if (data.current_branch_id !== branch.branchId.value) {
      branch.set(data.current_branch_id)
    }
    return data
  }

  /** Signs in, or returns the challenge when the account has two-factor sign-in (then call completeTwoFactor). */
  async function login(phone: string, password: string): Promise<{ challenge: string } | null> {
    const res = await api<LoginResponse>('/auth/login', {
      method: 'POST',
      body: { phone, password, device_name: currentDeviceName() },
    })
    if ('challenge' in res) {
      return { challenge: res.challenge }
    }
    auth.set(res.token)
    await load()
    return null
  }

  /** Second step: the code from the authenticator app, or a recovery code. */
  async function completeTwoFactor(challenge: string, code: string): Promise<{ recoveryCodesLeft: number | null }> {
    const res = await api<{ token: string, recovery_codes_left: number | null }>('/auth/two-factor', {
      method: 'POST',
      body: { challenge, code },
    })
    auth.set(res.token)
    await load()
    return { recoveryCodesLeft: res.recovery_codes_left }
  }

  async function register(payload: Record<string, unknown>): Promise<void> {
    const res = await api<{ token: string }>('/auth/register', { method: 'POST', body: { device_name: currentDeviceName(), ...payload } })
    auth.set(res.token)
    await load()
  }

  async function logout(): Promise<void> {
    try {
      await api('/auth/logout', { method: 'POST' })
    }
    finally {
      auth.set(null)
      branch.set(null)
      session.value = null
      await navigateTo('/login')
    }
  }

  return { session, isLoggedIn, isOwner, menu, currentBranch, hasModule, can, switchBranch, load, login, completeTwoFactor, register, logout }
})

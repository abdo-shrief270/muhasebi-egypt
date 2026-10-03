import type { PasskeyGetOptions } from '~/utils/webauthn'

/**
 * «قفل التطبيق» (per device): when on, the app opens locked and locks again after
 * BACKGROUND_MINUTES out of sight; the fingerprint / face (this device's passkey) or the PIN opens
 * it. `confirm()` asks the same thing before a sensitive answer (a big approval: step_up_required)
 * and resolves true once the API counts this device as verified. Too many wrong tries sign it out.
 * Offline (the cashier is never blocked by the internet) the app still opens: the PIN against a
 * PBKDF2 hash kept from the last online unlock, or this device's passkey checked locally. A
 * `confirm()` always needs the API.
 */
const BACKGROUND_MINUTES = 5

interface DevicePrefs {
  enabled: boolean
  /** This device's passkey (credential id), when one was made here. */
  passkey: string | null
}

export function useAppLock() {
  const store = useSessionStore()
  const api = useApi()
  const locked = useState('app-lock-locked', () => false)
  /** 'lock' = the app is locked; 'confirm' = asking before a sensitive answer (can be cancelled). */
  const mode = useState<'lock' | 'confirm'>('app-lock-mode', () => 'lock')
  const confirming = useState<((ok: boolean) => void) | null>('app-lock-confirming', () => null)
  const prefs = useState<DevicePrefs>('app-lock-prefs', () => ({ enabled: false, passkey: null }))

  function key(): string | null {
    return store.session ? `muhasebi:app-lock:${store.session.user.id}` : null
  }

  function loadPrefs(): DevicePrefs {
    const k = key()
    try {
      const saved = k ? JSON.parse(localStorage.getItem(k) ?? 'null') : null
      prefs.value = { enabled: saved?.enabled === true, passkey: typeof saved?.passkey === 'string' ? saved.passkey : null }
    }
    catch {
      prefs.value = { enabled: false, passkey: null }
    }
    return prefs.value
  }

  function savePrefs(next: Partial<DevicePrefs>) {
    prefs.value = { ...prefs.value, ...next }
    const k = key()
    try {
      if (k) {
        localStorage.setItem(k, JSON.stringify(prefs.value))
      }
    }
    catch {
      // Private mode / storage blocked: the setting lasts this session only.
    }
  }

  function lock() {
    if (prefs.value.enabled) {
      mode.value = 'lock'
      locked.value = true
    }
  }

  /** Before a sensitive answer: true once verified, false if the person backs out. */
  function confirm(): Promise<boolean> {
    confirming.value?.(false)
    mode.value = 'confirm'
    locked.value = true
    return new Promise((resolve) => {
      confirming.value = resolve
    })
  }

  function done(ok: boolean) {
    locked.value = false
    confirming.value?.(ok)
    confirming.value = null
  }

  async function unlockWith(body: Record<string, unknown>): Promise<void> {
    try {
      await api('/account/unlock', { method: 'POST', body })
      done(true)
    }
    catch (e) {
      if (apiErrorCode(e) === 'unlock_locked_out') {
        done(false)
        await store.logout()
        await navigateTo('/login')
      }
      throw e
    }
  }

  /** No answer from the API at all (offline), as opposed to an answer saying no. */
  const offline = (e: unknown) => apiErrorStatus(e) === null

  async function unlockWithPin(pin: string) {
    try {
      await unlockWith({ pin })
      await rememberPin(pin)
    }
    catch (e) {
      if (offline(e) && mode.value === 'lock' && await pinMatchesLocally(pin)) {
        done(true)
        return
      }
      throw e
    }
  }

  /** Fingerprint / face. False when the person closed the prompt. */
  async function unlockWithPasskey(): Promise<boolean> {
    let options: { data: PasskeyGetOptions }
    try {
      options = await api<{ data: PasskeyGetOptions }>('/account/unlock/options', { method: 'POST', body: {} })
    }
    catch (e) {
      const own = prefs.value.passkey
      if (!offline(e) || mode.value !== 'lock' || !own) {
        throw e
      }
      // Offline: this device's own key, checked here (the fingerprint itself is the proof).
      const local = await getPasskey({
        challenge: toBase64Url(crypto.getRandomValues(new Uint8Array(32)).buffer),
        rpId: window.location.hostname,
        allowCredentials: [{ type: 'public-key', id: own }],
        userVerification: 'required',
        timeout: 60000,
      })
      if (local?.id === own) {
        done(true)
        return true
      }
      return false
    }
    // Prefer this device's own key, so the phone doesn't offer keys from other devices.
    const own = prefs.value.passkey
    const allow = own && options.data.allowCredentials.some(c => c.id === own)
      ? options.data.allowCredentials.filter(c => c.id === own)
      : options.data.allowCredentials
    const assertion = await getPasskey({ ...options.data, allowCredentials: allow })
    if (!assertion) {
      return false
    }
    await unlockWith({ passkey: assertion })
    return true
  }

  function pinKey(): string | null {
    return store.session ? `muhasebi:app-lock-pin:${store.session.user.id}` : null
  }

  async function pinHash(pin: string, salt: Uint8Array): Promise<string> {
    const material = await crypto.subtle.importKey('raw', new TextEncoder().encode(pin), 'PBKDF2', false, ['deriveBits'])
    const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: salt as BufferSource, iterations: 200_000 }, material, 256)
    return toBase64Url(bits)
  }

  /** After an online unlock: keep a slow hash of the PIN so the app opens offline too. */
  async function rememberPin(pin: string) {
    const k = pinKey()
    if (!k || !crypto.subtle) {
      return
    }
    const salt = crypto.getRandomValues(new Uint8Array(16))
    try {
      localStorage.setItem(k, JSON.stringify({ salt: toBase64Url(salt.buffer), hash: await pinHash(pin, salt) }))
    }
    catch {
      // Storage blocked: offline unlock just won't be available.
    }
  }

  async function pinMatchesLocally(pin: string): Promise<boolean> {
    const k = pinKey()
    try {
      const saved = k ? JSON.parse(localStorage.getItem(k) ?? 'null') : null
      return !!saved && crypto.subtle !== undefined
        && await pinHash(pin, new Uint8Array(fromBase64Url(saved.salt))) === saved.hash
    }
    catch {
      return false
    }
  }

  /** Starts watching (once, from the layout): lock on open and after time in the background. */
  function watchDevice() {
    if (import.meta.server) {
      return () => {}
    }
    loadPrefs()
    lock()
    let hiddenAt: number | null = null
    const onVisibility = () => {
      if (document.visibilityState === 'hidden') {
        hiddenAt = Date.now()
      }
      else if (hiddenAt !== null && Date.now() - hiddenAt > BACKGROUND_MINUTES * 60_000) {
        lock()
      }
    }
    document.addEventListener('visibilitychange', onVisibility)
    return () => document.removeEventListener('visibilitychange', onVisibility)
  }

  return {
    locked: readonly(locked),
    mode: readonly(mode),
    prefs: readonly(prefs),
    loadPrefs,
    savePrefs,
    confirm,
    cancel: () => done(false),
    unlockWithPin,
    unlockWithPasskey,
    watchDevice,
  }
}

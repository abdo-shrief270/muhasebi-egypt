/**
 * «نزّل التطبيق»: installing محاسبي as an app (public/site.webmanifest).
 *
 * Chrome / Edge / Samsung Internet fire `beforeinstallprompt` (kept early by an inline script in
 * nuxt.config.ts as `window.__installPrompt`); we show our own button and call its `prompt()`.
 * iPhone / iPad (every browser there is Safari underneath) and desktop Safari / Firefox have no
 * prompt: we explain the steps instead (`InstallAppModal`, `/settings/app`). Nothing shows once the
 * app runs installed (display-mode standalone / window-controls-overlay, or iOS `navigator.standalone`).
 */
export type InstallPlatform = 'ios' | 'android' | 'desktop' | 'mac-safari'

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

declare global {
  interface Window {
    __installPrompt?: BeforeInstallPromptEvent | null
  }
  interface Navigator {
    standalone?: boolean
  }
}

const DISMISS_KEY = 'muhasebi:install-card-dismissed'
const STANDALONE_QUERY = '(display-mode: standalone), (display-mode: window-controls-overlay), (display-mode: fullscreen), (display-mode: minimal-ui)'

let deferred: BeforeInstallPromptEvent | null = null

export function runningInstalled(): boolean {
  return navigator.standalone === true || window.matchMedia(STANDALONE_QUERY).matches
}

export function installPlatform(ua = navigator.userAgent, maxTouchPoints = navigator.maxTouchPoints ?? 0): InstallPlatform {
  // iPadOS reports a Mac user agent; touch gives it away.
  if (/iPhone|iPad|iPod/i.test(ua) || (/Macintosh/i.test(ua) && maxTouchPoints > 1)) {
    return 'ios'
  }
  if (/Android/i.test(ua)) {
    return 'android'
  }
  if (/Macintosh/i.test(ua) && /Safari/i.test(ua) && !/Chrome|Chromium|Edg|OPR|Firefox/i.test(ua)) {
    return 'mac-safari'
  }
  return 'desktop'
}

function readDismissed(): boolean {
  try {
    return localStorage.getItem(DISMISS_KEY) === '1'
  }
  catch {
    return false
  }
}

export function useInstallApp() {
  const state = useState('install-app', () => ({
    installed: false,
    canPrompt: false,
    dismissed: false,
    helpOpen: false,
    platform: 'desktop' as InstallPlatform,
  }))

  /** Not installed here: the user menu / Ctrl+K entry shows (prompt, or the steps). */
  const installable = computed(() => !state.value.installed)
  /** The home-page card: only where we can really help (a prompt, or iPhone steps), until dismissed. */
  const showCard = computed(() => !state.value.installed && !state.value.dismissed && (state.value.canPrompt || state.value.platform === 'ios'))

  async function install(): Promise<'accepted' | 'dismissed' | 'help'> {
    if (!deferred) {
      state.value.helpOpen = true
      return 'help'
    }
    const event = deferred
    deferred = null
    window.__installPrompt = null
    state.value.canPrompt = false
    await event.prompt()
    const { outcome } = await event.userChoice
    return outcome
  }

  function dismiss() {
    state.value.dismissed = true
    try {
      localStorage.setItem(DISMISS_KEY, '1')
    }
    catch {
      // private mode: hidden for this visit only
    }
  }

  return { state, installable, showCard, install, dismiss, showHelp: () => (state.value.helpOpen = true) }
}

/** Once, from plugins/pwa.client.ts. */
export function setupInstallApp(onInstalled: () => void) {
  const { state } = useInstallApp()
  state.value.installed = runningInstalled()
  state.value.platform = installPlatform()
  state.value.dismissed = readDismissed()

  const take = (event: BeforeInstallPromptEvent) => {
    deferred = event
    state.value.canPrompt = true
  }
  if (window.__installPrompt) {
    take(window.__installPrompt)
  }
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    take(event as BeforeInstallPromptEvent)
  })
  window.addEventListener('appinstalled', () => {
    deferred = null
    state.value.canPrompt = false
    state.value.installed = true
    onInstalled()
  })
  window.matchMedia(STANDALONE_QUERY).addEventListener('change', () => {
    state.value.installed = runningInstalled()
  })
}

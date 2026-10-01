/**
 * Push notifications on this device (Web Push through the service worker). The API pushes owner
 * alerts, partner-shop activity… to every device a user turned on here.
 * iPhone / iPad: only once the app is installed on the home screen (iOS 16.4+).
 */
export type PushState = 'unsupported' | 'needs_install' | 'blocked' | 'off' | 'on'

function urlBase64ToBytes(base64: string): Uint8Array<ArrayBuffer> {
  const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(padded)
  const bytes = new Uint8Array(new ArrayBuffer(raw.length))
  for (let i = 0; i < raw.length; i++) {
    bytes[i] = raw.charCodeAt(i)
  }
  return bytes
}

function deviceName(): string {
  const ua = navigator.userAgent
  const os = /iPhone|iPad/.test(ua) ? 'iPhone/iPad' : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'Windows' : /Mac/.test(ua) ? 'Mac' : 'جهاز'
  const browser = /Edg\//.test(ua) ? 'Edge' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : ''
  return [os, browser].filter(Boolean).join(' · ')
}

export function usePush() {
  const api = useApi()
  const state = useState<PushState>('push-state', () => 'off')

  async function registration(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) {
      return null
    }
    // The worker is only registered in production builds; don't wait forever without one.
    return await Promise.race([
      navigator.serviceWorker.ready,
      new Promise<null>(resolve => setTimeout(() => resolve(null), 4000)),
    ])
  }

  async function refresh(): Promise<PushState> {
    if (import.meta.server) {
      return state.value
    }
    if (installPlatform() === 'ios' && !runningInstalled()) {
      state.value = 'needs_install'
    }
    else if (!('PushManager' in window) || !('Notification' in window) || !('serviceWorker' in navigator)) {
      state.value = 'unsupported'
    }
    else if (Notification.permission === 'denied') {
      state.value = 'blocked'
    }
    else {
      const reg = await registration()
      state.value = reg && await reg.pushManager.getSubscription() ? 'on' : 'off'
    }
    return state.value
  }

  /** Asks the browser, subscribes, and tells the API this device wants the user's notifications. */
  async function enable(): Promise<void> {
    const { data: key } = await api<{ data: { configured: boolean, public_key: string | null } }>('/push/key')
    if (!key.configured || !key.public_key) {
      throw new Error('الإشعارات لسه مش متظبطة على السيرفر.')
    }
    if (await Notification.requestPermission() !== 'granted') {
      await refresh()
      throw new Error('المتصفح ما سمحش بالإشعارات. اسمح بيها من إعدادات الموقع وجرّب تاني.')
    }
    const reg = await registration()
    if (!reg) {
      throw new Error('الإشعارات بتشتغل من التطبيق المنزّل أو من الموقع نفسه، مش من نسخة التجربة.')
    }
    // The browser registers with its push service (Google / Apple / Mozilla); don't spin forever if it can't.
    const subscription = await reg.pushManager.getSubscription()
      ?? await Promise.race([
        reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToBytes(key.public_key) }),
        new Promise<never>((_, reject) => setTimeout(() => reject(new Error('المتصفح مقدرش يوصل لخدمة الإشعارات. اتأكد من النت وجرّب تاني.')), 20000)),
      ])
    const json = subscription.toJSON()
    await api('/push/subscriptions', { method: 'POST', body: { endpoint: json.endpoint, keys: json.keys, device: deviceName() } })
    state.value = 'on'
  }

  async function disable(): Promise<void> {
    const reg = await registration()
    const subscription = await reg?.pushManager.getSubscription()
    if (subscription) {
      await api('/push/subscriptions', { method: 'DELETE', body: { endpoint: subscription.endpoint } }).catch(() => {})
      await subscription.unsubscribe()
    }
    state.value = 'off'
  }

  async function test(): Promise<number> {
    return (await api<{ data: { devices: number } }>('/push/test', { method: 'POST' })).data.devices
  }

  return { state, refresh, enable, disable, test }
}

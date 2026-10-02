import type Echo from 'laravel-echo'

/**
 * Live updates over WebSockets (Laravel Echo → Reverb), when the API offers them (/auth/me
 * `realtime`). One connection per signed-in session, opened on first use. Screens still poll
 * when `connected` is false — the WebSocket only makes them faster.
 */
type EchoClient = Echo<'reverb'>

let echo: EchoClient | null = null
let echoToken: string | null = null
// The connection being opened: callers arriving meanwhile share it (one socket, not one each).
let opening: Promise<EchoClient | null> | null = null

export function useRealtime() {
  const store = useSessionStore()
  const auth = useAuthToken()
  const config = useRuntimeConfig()
  const connected = useState('realtime-connected', () => false)

  function client(): Promise<EchoClient | null> {
    const rt = store.session?.realtime
    if (import.meta.server || !rt || !auth.token.value) {
      return Promise.resolve(null)
    }
    if (echo && echoToken === auth.token.value) {
      return Promise.resolve(echo)
    }
    opening ??= open(rt, auth.token.value).finally(() => {
      opening = null
    })
    return opening
  }

  async function open(rt: NonNullable<typeof store.session>['realtime'] & object, token: string): Promise<EchoClient> {
    disconnect()
    const [{ default: EchoClass }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')])
    ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher
    const tls = rt.scheme === 'https'
    echo = new EchoClass({
      broadcaster: 'reverb',
      key: rt.key,
      wsHost: rt.host || window.location.hostname,
      wsPort: rt.port,
      wssPort: rt.port,
      forceTLS: tls,
      enabledTransports: tls ? ['wss'] : ['ws'],
      authEndpoint: `${String(config.public.apiBase).replace(/\/v1\/?$/, '')}/broadcasting/auth`,
      auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
    })
    echoToken = token
    const pusher = (echo.connector as unknown as { pusher: { connection: { bind: (e: string, cb: (s: { current: string }) => void) => void, state: string } } }).pusher
    pusher.connection.bind('state_change', (s) => {
      connected.value = s.current === 'connected'
    })
    return echo
  }

  /** Listens on a private channel; returns a function that stops listening. */
  async function listen<T>(channel: string, event: string, callback: (payload: T) => void): Promise<() => void> {
    const e = await client()
    if (!e) {
      return () => {}
    }
    const ch = e.private(channel)
    ch.listen(`.${event}`, callback)
    return () => ch.stopListening(`.${event}`, callback)
  }

  function disconnect() {
    echo?.disconnect()
    echo = null
    echoToken = null
    connected.value = false
  }

  return { connected: readonly(connected), listen, disconnect }
}

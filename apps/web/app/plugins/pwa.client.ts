/**
 * The installable app (public/site.webmanifest, public/sw.js):
 * - registers the service worker (production builds only) and, when a newer build's worker is
 *   waiting, shows «فيه نسخة جديدة — حدّث»; the click activates it and reloads this window;
 * - `beforeinstallprompt` / `appinstalled` for «نزّل التطبيق» (useInstallApp);
 * - the theme-color meta (window title bar / Android status bar) follows the theme picked in the app;
 * - the installed app opens where it was left (start_url `/?source=pwa` → the last page), and a
 *   manifest shortcut / a second launch in an open window (launch_handler focus-existing) navigates it;
 * - installed, links to other sites (wa.me, maps…) open in the browser, not inside the app window.
 */
const LAST_ROUTE_KEY = 'muhasebi:last-route'
const UPDATE_CHECK_MS = 60 * 60 * 1000

interface LaunchParams {
  targetURL?: string
}

declare global {
  interface Window {
    launchQueue?: { setConsumer: (consumer: (params: LaunchParams) => void) => void }
  }
}

function storage(): Storage | null {
  try {
    return window.localStorage
  }
  catch {
    return null
  }
}

/** `/pos?source=shortcut&x=1` → `/pos?x=1`: `source` only says how the app was opened. */
function withoutSource(path: string, query: Record<string, unknown>) {
  const rest = Object.fromEntries(Object.entries(query).filter(([key]) => key !== 'source'))
  return { path, query: rest as Record<string, string>, replace: true }
}

export default defineNuxtPlugin(() => {
  const router = useRouter()
  const toast = useToast()
  const colorMode = useColorMode()
  const install = useInstallApp()

  setupInstallApp(() => {
    toast.add({ color: 'success', icon: 'i-lucide-circle-check', title: 'اتنزّل التطبيق', description: 'هتلاقي «محاسبي» على الشاشة الرئيسية أو في قايمة البرامج.' })
  })

  // --- Where the user left off -------------------------------------------------------------
  let firstNavigation = true
  router.beforeEach((to) => {
    if (!firstNavigation) {
      return
    }
    firstNavigation = false
    if (to.query.source === undefined) {
      return
    }
    const last = storage()?.getItem(LAST_ROUTE_KEY)
    if (to.path === '/' && to.query.source === 'pwa' && last && last !== '/' && last.startsWith('/')) {
      return { path: last, replace: true }
    }
    return withoutSource(to.path, to.query)
  })
  router.afterEach((to) => {
    if (!to.meta.public && !to.meta.guest && to.path !== '/login') {
      storage()?.setItem(LAST_ROUTE_KEY, to.fullPath)
    }
  })

  window.launchQueue?.setConsumer((params) => {
    if (!params.targetURL) {
      return
    }
    const url = new URL(params.targetURL)
    if (url.origin !== location.origin || (url.pathname === '/' && url.searchParams.get('source') === 'pwa')) {
      return // a plain launch: stay where the window is
    }
    router.push(withoutSource(url.pathname, Object.fromEntries(url.searchParams))).catch(() => undefined)
  })

  // --- Links to other sites leave the app window -------------------------------------------
  document.addEventListener('click', (event) => {
    if (!install.state.value.installed || event.defaultPrevented) {
      return
    }
    const link = (event.target as Element | null)?.closest?.('a[href]') as HTMLAnchorElement | null
    if (!link || link.target) {
      return
    }
    const url = new URL(link.href, location.href)
    if (/^https?:$/.test(url.protocol) && url.origin !== location.origin) {
      link.target = '_blank'
      link.rel = 'noopener noreferrer'
    }
  }, true)

  // --- Title bar / status bar colour = the top bar ------------------------------------------
  const syncThemeColor = () => nextTick(() => {
    const metas = document.querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]')
    const current = getComputedStyle(document.documentElement).getPropertyValue('--ui-bg').trim()
    metas.forEach((meta) => {
      meta.dataset.original ??= meta.content
      meta.content = colorMode.preference === 'system' || !current ? meta.dataset.original : current
    })
  })
  watch(() => [colorMode.preference, colorMode.value], syncThemeColor, { immediate: true })

  // --- Service worker: offline shell + updates ----------------------------------------------
  if (import.meta.dev || !('serviceWorker' in navigator)) {
    return
  }
  const sw = navigator.serviceWorker
  const hadController = Boolean(sw.controller)
  let reloading = false

  const offerUpdate = (worker: ServiceWorker | null) => {
    if (!worker || !sw.controller) {
      return // the first install: nothing to update from
    }
    toast.add({
      id: 'app-update',
      color: 'primary',
      icon: 'i-lucide-refresh-cw',
      title: 'فيه نسخة جديدة — حدّث',
      description: 'دوس «حدّث» عشان تشتغل بآخر نسخة من محاسبي.',
      duration: Number.POSITIVE_INFINITY,
      actions: [{
        label: 'حدّث',
        color: 'primary',
        onClick: () => {
          reloading = true
          if (worker.state === 'activated') {
            location.reload()
          }
          else {
            worker.postMessage({ type: 'SKIP_WAITING' })
          }
        },
      }],
    })
  }

  sw.addEventListener('controllerchange', () => {
    if (reloading) {
      location.reload()
    }
    else if (hadController) {
      // Another window took the update: this one still runs the old build.
      offerUpdate(sw.controller)
    }
  })

  sw.register('/sw.js', { scope: '/' }).then((registration) => {
    offerUpdate(registration.waiting)
    registration.addEventListener('updatefound', () => {
      const worker = registration.installing
      worker?.addEventListener('statechange', () => {
        if (worker.state === 'installed') {
          offerUpdate(worker)
        }
      })
    })

    // An installed app (a POS left open all day) never navigates: look for a new build now and then.
    let lastCheck = Date.now()
    const check = () => {
      if (navigator.onLine && Date.now() - lastCheck > 10 * 60 * 1000) {
        lastCheck = Date.now()
        registration.update().catch(() => undefined)
      }
    }
    setInterval(() => registration.update().catch(() => undefined), UPDATE_CHECK_MS)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        check()
      }
    })
  }).catch(() => undefined)
})

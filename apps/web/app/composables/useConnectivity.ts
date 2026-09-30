/**
 * Are we online? The browser's own flag (navigator.onLine) AND the API answering: a request that
 * gets no answer at all (useApi's onRequestError) marks the API unreachable; any answer marks it
 * reachable again. While unreachable, the API's /up is probed every 20 seconds.
 */
const browserOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
const reachable = ref(true)
const online = computed(() => browserOnline.value && reachable.value)

let probeTimer: ReturnType<typeof setInterval> | undefined
let upUrl: string | null = null

export function markReachable(value: boolean) {
  reachable.value = value
  if (value) {
    stopProbe()
  }
  else {
    startProbe()
  }
}

function stopProbe() {
  clearInterval(probeTimer)
  probeTimer = undefined
}

function startProbe() {
  if (probeTimer || !upUrl) {
    return
  }
  probeTimer = setInterval(probe, 20_000)
}

/** A cheap "is the server there?": any answer (even opaque) counts. */
export async function probe(): Promise<boolean> {
  if (!upUrl || !browserOnline.value) {
    return false
  }
  try {
    await fetch(upUrl, { mode: 'no-cors', cache: 'no-store', signal: AbortSignal.timeout(8000) })
    markReachable(true)
    return true
  }
  catch {
    return false
  }
}

/** Called once from the offline plugin. */
export function setupConnectivity(apiBase: string) {
  try {
    upUrl = new URL('/up', new URL(apiBase, window.location.href)).toString()
  }
  catch {
    upUrl = null
  }
  window.addEventListener('online', () => {
    browserOnline.value = true
    probe()
  })
  window.addEventListener('offline', () => {
    browserOnline.value = false
  })
}

export function useConnectivity() {
  return { online, browserOnline: readonly(browserOnline), reachable: readonly(reachable), probe }
}

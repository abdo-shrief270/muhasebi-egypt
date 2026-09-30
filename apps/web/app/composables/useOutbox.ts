import type { Sale } from '~/types/api'

/**
 * Sales made while the internet was down ("فواتير مستنية"), kept in IndexedDB on this device and
 * sent to POST /sales in order once the API answers again. The sale id is the cart id, so a sale
 * that did reach the server before the connection dropped is still saved once.
 *
 * - success → removed;
 * - 4xx (the server refused it: shift closed, serial already sold, price changed…) → kept as
 *   failed with the server's message, to retry or discard from the panel;
 * - 5xx / no answer → retried later with a growing delay, keeping the order.
 *
 * A sale is only ever sent by the user who made it (their token = their shift).
 */
export interface OutboxEntry {
  /** = the sale id */
  id: string
  tenant_id: string
  branch_id: string
  user_id: string
  /** when it was sold (the device clock), also sent as sold_at */
  created_at: string
  /** the exact POST /sales body, with offline: true and sold_at */
  body: Record<string, unknown>
  /** what the receipt showed, for reprinting */
  receipt: Sale
  /** units that left the shelf, to keep the local stock right until it syncs */
  lines: { variant_id: string, qty: number, serials: string[] }[]
  status: 'pending' | 'failed'
  error: string | null
  error_code: string | null
  attempts: number
  next_try_at: number
}

interface OutboxContext {
  api: ReturnType<typeof useApi>
  store: ReturnType<typeof useSessionStore>
}

const entries = ref<OutboxEntry[]>([])
const syncing = ref(false)
const panelOpen = ref(false)
let ctx: OutboxContext | null = null
/** Fired after a queued sale reached the server (the POS refreshes its items). */
const syncedListeners = new Set<(entry: OutboxEntry) => void>()

const MAX_DELAY = 5 * 60_000

function byTime(a: OutboxEntry, b: OutboxEntry) {
  return a.created_at.localeCompare(b.created_at)
}

async function reload() {
  try {
    entries.value = (await idbAll<OutboxEntry>('outbox')).sort(byTime)
  }
  catch {
    // No IndexedDB (private mode): nothing can have been queued either.
  }
}

async function save(entry: OutboxEntry) {
  await idbPut('outbox', toRaw(entry))
  await reload()
}

/** Called once from the offline plugin, with the app's API client and session store. */
export function setupOutbox(context: OutboxContext) {
  ctx = context
  return reload()
}

/**
 * Sends what's queued for the signed-in user. `force` ignores the back-off (the connection just
 * came back). Only one tab at a time (Web Locks, when the browser has them).
 */
async function sync(force = false): Promise<void> {
  if (syncing.value || !ctx?.store.session) {
    return
  }
  const run = () => drain(force)
  if (typeof navigator !== 'undefined' && navigator.locks) {
    await navigator.locks.request('muhasebi-outbox', { ifAvailable: true }, lock => (lock ? run() : undefined))
  }
  else {
    await run()
  }
}

async function drain(force: boolean) {
  const session = ctx?.store.session
  if (!ctx || !session) {
    return
  }
  syncing.value = true
  try {
    await reload()
    const queue = entries.value.filter(e => e.status === 'pending' && e.tenant_id === session.tenant.id && e.user_id === session.user.id)
    for (const entry of queue) {
      if (!force && entry.next_try_at > Date.now()) {
        break
      }
      try {
        await ctx.api('/sales', { method: 'POST', body: entry.body, headers: { 'X-Branch-Id': entry.branch_id }, timeout: 20_000 })
        await idbDelete('outbox', entry.id)
        syncedListeners.forEach(fn => fn(entry))
      }
      catch (e) {
        const status = apiErrorStatus(e)
        if (status === 401) {
          return
        }
        if (status !== null && status >= 400 && status < 500 && status !== 408 && status !== 429) {
          await idbPut('outbox', { ...toRaw(entry), status: 'failed', error: apiErrorMessage(e), error_code: apiErrorCode(e), attempts: entry.attempts + 1 })
          continue
        }
        // Server error or no answer: try again later, and keep the order.
        const attempts = entry.attempts + 1
        await idbPut('outbox', { ...toRaw(entry), attempts, next_try_at: Date.now() + Math.min(15_000 * 2 ** (attempts - 1), MAX_DELAY) })
        break
      }
    }
  }
  finally {
    syncing.value = false
    await reload()
  }
}

async function enqueue(entry: Omit<OutboxEntry, 'status' | 'error' | 'error_code' | 'attempts' | 'next_try_at'>) {
  // A plain copy: IndexedDB can't store Vue's reactive proxies (the cart's serial arrays).
  const plain = JSON.parse(JSON.stringify(entry)) as typeof entry
  await save({ ...plain, status: 'pending', error: null, error_code: null, attempts: 0, next_try_at: 0 })
}

async function retry(id: string) {
  const entry = entries.value.find(e => e.id === id)
  if (entry) {
    await save({ ...toRaw(entry), status: 'pending', error: null, error_code: null, next_try_at: 0 })
    await sync(true)
  }
}

async function discard(id: string) {
  await idbDelete('outbox', id)
  await reload()
}

function onSynced(fn: (entry: OutboxEntry) => void) {
  syncedListeners.add(fn)
  return () => syncedListeners.delete(fn)
}

export function useOutbox() {
  const store = useSessionStore()

  /** This user's queued sales in this shop (any branch). */
  const mine = computed(() => entries.value.filter(e => e.tenant_id === store.session?.tenant.id && e.user_id === store.session?.user.id))
  const pending = computed(() => mine.value.filter(e => e.status === 'pending'))
  const failed = computed(() => mine.value.filter(e => e.status === 'failed'))

  /** Queued sales of this user in a branch: a shift there can't be closed before they're in. */
  const forBranch = (branchId: string | null | undefined) => mine.value.filter(e => e.branch_id === branchId)

  /** Units sold offline in a branch and not on the server yet: variant id → qty, and their serials. */
  function soldHere(branchId: string | null | undefined) {
    const qty = new Map<string, number>()
    const serials = new Set<string>()
    for (const entry of forBranch(branchId)) {
      for (const line of entry.lines) {
        qty.set(line.variant_id, (qty.get(line.variant_id) ?? 0) + line.qty)
        line.serials.forEach(s => serials.add(s))
      }
    }
    return { qty, serials }
  }

  return { entries: mine, pending, failed, syncing: readonly(syncing), panelOpen, forBranch, soldHere, enqueue, sync, retry, discard, reload, onSynced }
}

import type { PosCatalogItem, PosItem } from '~/types/api'

interface CatalogRecord {
  /** `${tenant}:${branch}` */
  scope: string
  items: PosCatalogItem[]
  synced_at: string
}

/** Refreshed on POS open and every 10 minutes while it's open (when online). */
export const CATALOG_REFRESH_MS = 10 * 60_000

const items = shallowRef<PosCatalogItem[]>([])
const haystacks = new WeakMap<PosCatalogItem, string>()
const syncedAt = ref<string | null>(null)
const loadedScope = ref<string | null>(null)
const refreshing = ref(false)

function index(list: PosCatalogItem[]) {
  for (const item of list) {
    haystacks.set(item, normalizeSearch([item.display_name, item.barcode, item.sku, item.category.name, item.quality_label].filter(Boolean).join(' ')))
  }
  items.value = list
}

/**
 * The branch's whole sellable catalog kept on this device (IndexedDB, GET /pos/catalog), so the
 * POS can show, search and scan items with no internet. Local stock = the server's qty at the
 * last refresh minus what was sold offline since and isn't on the server yet.
 */
export function usePosCatalog() {
  const store = useSessionStore()
  const api = useApi()
  const outbox = useOutbox()
  const scope = computed(() => store.session?.current_branch_id ? `${store.session.tenant.id}:${store.session.current_branch_id}` : null)

  async function load(): Promise<void> {
    const key = scope.value
    if (!key || loadedScope.value === key) {
      return
    }
    loadedScope.value = key
    try {
      const record = await idbGet<CatalogRecord>('catalog', key)
      if (loadedScope.value === key) {
        index(record?.items ?? [])
        syncedAt.value = record?.synced_at ?? null
      }
    }
    catch {
      index([])
      syncedAt.value = null
    }
  }

  /** Downloads every page (500 each) and replaces the stored copy. Fails soft. */
  async function refresh(): Promise<boolean> {
    const key = scope.value
    const branchId = store.session?.current_branch_id
    if (!key || !branchId || refreshing.value) {
      return false
    }
    refreshing.value = true
    try {
      const all: PosCatalogItem[] = []
      let page = 1
      let last = 1
      do {
        const res = await api<{ data: PosCatalogItem[], meta: { last_page: number, generated_at: string } }>('/pos/catalog', {
          query: { page },
          headers: { 'X-Branch-Id': branchId },
          timeout: 30_000,
        })
        all.push(...res.data)
        last = res.meta.last_page
        page++
      } while (page <= last)
      const record: CatalogRecord = { scope: key, items: all, synced_at: new Date().toISOString() }
      await idbPut('catalog', record).catch(() => undefined)
      if (scope.value === key) {
        loadedScope.value = key
        index(all)
        syncedAt.value = record.synced_at
      }
      return true
    }
    catch {
      return false
    }
    finally {
      refreshing.value = false
    }
  }

  const sold = computed(() => outbox.soldHere(store.session?.current_branch_id))

  /** The item as the grid shows it: stock net of offline sales, serials net of those sold offline. */
  function local(item: PosCatalogItem, exact = false): PosItem & { serials: string[] | null } {
    const soldQty = sold.value.qty.get(item.id) ?? 0
    return {
      ...item,
      qty: item.qty - soldQty,
      serials: item.serials ? item.serials.filter(s => !sold.value.serials.has(s)) : null,
      exact_barcode: exact,
    }
  }

  /** Like /pos/items: every word must match (name, barcode, SKU, category), exact barcode first. */
  function search(q: string, categoryId: number | null, limit = 60): PosItem[] {
    const tokens = searchTokens(q)
    const code = q.trim().toLowerCase()
    const found: PosItem[] = []
    let exact: PosItem | null = null
    for (const item of items.value) {
      if (categoryId && item.category.id !== categoryId) {
        continue
      }
      if (code && item.barcode?.toLowerCase() === code) {
        exact = local(item, true)
        continue
      }
      const hay = haystacks.get(item) ?? ''
      if (tokens.every(t => hay.includes(t)) && found.length < limit) {
        found.push(local(item))
      }
    }
    return exact ? [exact, ...found] : found
  }

  function byId(id: string): PosItem | null {
    const item = items.value.find(i => i.id === id)
    return item ? local(item) : null
  }

  /** An IMEI / serial in stock here (as of the last refresh, less what was sold offline). */
  function bySerial(raw: string): { item: PosItem, serial: string } | null {
    const serial = normalizeSerial(raw)
    if (sold.value.serials.has(serial)) {
      return null
    }
    const item = items.value.find(i => i.serials?.includes(serial))
    return item ? { item: local(item), serial } : null
  }

  /** Categories that have items, for the chips when the API can't be asked. */
  const categories = computed(() => {
    const seen = new Map<number, string>()
    items.value.forEach(i => seen.set(i.category.id, i.category.name))
    return [...seen].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'ar'))
  })

  return { items, syncedAt: readonly(syncedAt), refreshing: readonly(refreshing), categories, load, refresh, search, byId, bySerial }
}

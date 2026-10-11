/**
 * The market's cart, kept in this browser (localStorage), across shops: the cart page splits it
 * by shop and branch, and each part becomes one order at that shop (or a WhatsApp message when the
 * shop doesn't take orders in the app). Prices are re-read when the cart opens.
 */
export interface CartLine {
  /** the market offer: "{variant}:{branch}" */
  offerId: string
  variantId: string
  branchId: string
  shop: { slug: string, name: string }
  title: string
  image: string | null
  price: number
  qty: number
}

const KEY = 'souq:cart'

export function useCart() {
  const lines = useState<CartLine[]>('cart', () => [])
  const loaded = useState('cart-loaded', () => false)

  function load() {
    if (import.meta.server || loaded.value) {
      return
    }
    loaded.value = true
    try {
      const saved = JSON.parse(localStorage.getItem(KEY) ?? '[]')
      lines.value = Array.isArray(saved) ? saved.filter(l => l && typeof l.offerId === 'string' && l.qty > 0 && l.shop?.slug) : []
    }
    catch {
      lines.value = []
    }
  }

  function save() {
    try {
      localStorage.setItem(KEY, JSON.stringify(lines.value))
    }
    catch {
      // Storage blocked (private mode): the cart lasts this visit.
    }
  }

  function add(line: Omit<CartLine, 'qty' | 'variantId' | 'branchId'>, qty = 1) {
    load()
    const [variantId = '', branchId = ''] = line.offerId.split(':')
    const found = lines.value.find(l => l.offerId === line.offerId)
    if (found) {
      found.qty = Math.min(50, found.qty + qty)
      found.price = line.price
    }
    else {
      lines.value.push({ ...line, variantId, branchId, qty })
    }
    save()
  }

  function setQty(offerId: string, qty: number) {
    lines.value = lines.value
      .map(l => (l.offerId === offerId ? { ...l, qty: Math.max(0, Math.min(50, qty)) } : l))
      .filter(l => l.qty > 0)
    save()
  }

  function setPrice(offerId: string, price: number) {
    const line = lines.value.find(l => l.offerId === offerId)
    if (line) {
      line.price = price
      save()
    }
  }

  /** The lines of one shop's branch leave the cart once ordered. */
  function removeGroup(key: string) {
    lines.value = lines.value.filter(l => groupKey(l) !== key)
    save()
  }

  const count = computed(() => lines.value.reduce((n, l) => n + l.qty, 0))

  /** One part per shop branch: each is one order. */
  const groups = computed(() => {
    const map = new Map<string, { key: string, shop: CartLine['shop'], branchId: string, lines: CartLine[], total: number }>()
    for (const l of lines.value) {
      const key = groupKey(l)
      const g = map.get(key) ?? { key, shop: l.shop, branchId: l.branchId, lines: [], total: 0 }
      g.lines.push(l)
      g.total += l.price * l.qty
      map.set(key, g)
    }
    return [...map.values()]
  })

  return { lines, load, add, setQty, setPrice, removeGroup, count, groups }
}

export function groupKey(l: Pick<CartLine, 'shop' | 'branchId'>): string {
  return `${l.shop.slug}.${l.branchId}`
}

/**
 * The cart, kept in this browser per store (localStorage). In «واتساب» mode it becomes a ready
 * WhatsApp message to the shop; prices are re-read from the store when the cart opens.
 */
export interface CartLine {
  variantId: string
  productId: string
  name: string
  variant: string | null
  price: number
  qty: number
  image: string | null
}

export function useCart(slug: string) {
  const key = `muhasebi-store-cart:${slug}`
  const lines = useState<CartLine[]>(`cart:${slug}`, () => [])
  const loaded = useState(`cart-loaded:${slug}`, () => false)

  function load() {
    if (import.meta.server || loaded.value) {
      return
    }
    loaded.value = true
    try {
      const saved = JSON.parse(localStorage.getItem(key) ?? '[]')
      lines.value = Array.isArray(saved) ? saved.filter(l => l && typeof l.variantId === 'string' && l.qty > 0) : []
    }
    catch {
      lines.value = []
    }
  }

  function save() {
    try {
      localStorage.setItem(key, JSON.stringify(lines.value))
    }
    catch {
      // Storage blocked (private mode): the cart lasts this visit.
    }
  }

  function add(line: Omit<CartLine, 'qty'>, qty = 1) {
    load()
    const found = lines.value.find(l => l.variantId === line.variantId)
    if (found) {
      found.qty = Math.min(99, found.qty + qty)
    }
    else {
      lines.value.push({ ...line, qty })
    }
    save()
  }

  function setQty(variantId: string, qty: number) {
    lines.value = lines.value
      .map(l => (l.variantId === variantId ? { ...l, qty: Math.max(0, Math.min(99, qty)) } : l))
      .filter(l => l.qty > 0)
    save()
  }

  function clear() {
    lines.value = []
    save()
  }

  const count = computed(() => lines.value.reduce((s, l) => s + l.qty, 0))
  const total = computed(() => lines.value.reduce((s, l) => s + l.price * l.qty, 0))

  onMounted(load)

  return { lines, count, total, add, setQty, clear, load }
}

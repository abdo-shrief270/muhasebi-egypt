import type { PosCustomer, PosItem } from '~/types/api'

export type PriceLevel = 'retail' | 'wholesale' | 'technician'

export interface CartLine {
  variant_id: string
  name: string
  barcode: string | null
  prices: { retail: number, wholesale: number | null, technician: number | null }
  stock: number
  qty: number
  /** piasters off the whole line */
  discount: number
  /** phones and other products that track IMEI / serials: one per unit, qty follows */
  track_serial?: boolean
  serials?: string[]
}

export interface Cart {
  id: string
  lines: CartLine[]
  discount: number
  price_level: PriceLevel
  /** A customer with an account (for credit); otherwise just a name / phone for the receipt. */
  customer?: PosCustomer | null
  customer_name: string
  customer_phone: string
  held_at?: string
}

const emptyCart = (): Cart => ({ id: crypto.randomUUID(), lines: [], discount: 0, price_level: 'retail', customer: null, customer_name: '', customer_phone: '' })

function read<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key)
    return raw ? JSON.parse(raw) as T : fallback
  }
  catch {
    return fallback
  }
}

function write(key: string, value: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  }
  catch {
    // Private mode / full storage: the cart just won't survive a reload.
  }
}

/**
 * The cashier's cart for this device and branch, kept in the browser so a reload (or a power cut)
 * doesn't lose it, plus held invoices ("تعليق"). The cart id is the sale id sent to the API, so a
 * checkout retried after a timeout is saved once.
 */
export function usePosCart(branchId: Ref<string | null | undefined>) {
  const key = computed(() => `muhasebi_pos_${branchId.value ?? 'none'}`)
  const cart = ref<Cart>(emptyCart())
  const held = ref<Cart[]>([])

  watch(key, (k) => {
    cart.value = read(`${k}_cart`, emptyCart())
    held.value = read(`${k}_held`, [])
  }, { immediate: true })

  watch(cart, value => write(`${key.value}_cart`, value), { deep: true })
  watch(held, value => write(`${key.value}_held`, value), { deep: true })

  function unitPrice(line: CartLine): number {
    const level = cart.value.price_level
    return (level === 'retail' ? line.prices.retail : line.prices[level]) ?? line.prices.retail
  }

  const lineTotal = (line: CartLine) => Math.max(0, unitPrice(line) * line.qty - line.discount)
  const subtotal = computed(() => cart.value.lines.reduce((sum, l) => sum + lineTotal(l), 0))
  const total = computed(() => Math.max(0, subtotal.value - cart.value.discount))
  const count = computed(() => cart.value.lines.reduce((sum, l) => sum + l.qty, 0))
  /** Lines still waiting for their IMEI / serial. */
  const missingSerials = computed(() => cart.value.lines.filter(l => l.track_serial && !l.qty))

  /** A product that tracks serials is added with the scanned serial (or none yet, to be scanned on the line). */
  function add(item: PosItem, qty = 1, serial?: string) {
    const existing = cart.value.lines.find(l => l.variant_id === item.id)
    if (existing) {
      if (existing.track_serial) {
        if (serial && !existing.serials?.includes(serial)) {
          existing.serials = [...(existing.serials ?? []), serial]
        }
        existing.qty = existing.serials?.length ?? 0
      }
      else {
        existing.qty += qty
      }
      existing.stock = item.qty
      return
    }
    if (item.track_serial) {
      const serials = serial ? [serial] : []
      cart.value.lines.unshift({
        variant_id: item.id,
        name: item.display_name,
        barcode: item.barcode,
        prices: { retail: item.price_retail, wholesale: item.price_wholesale, technician: item.price_technician },
        stock: item.qty,
        qty: serials.length,
        discount: 0,
        track_serial: true,
        serials,
      })
      return
    }
    cart.value.lines.unshift({
      variant_id: item.id,
      name: item.display_name,
      barcode: item.barcode,
      prices: { retail: item.price_retail, wholesale: item.price_wholesale, technician: item.price_technician },
      stock: item.qty,
      qty,
      discount: 0,
    })
  }

  function setQty(line: CartLine, qty: number) {
    if (qty <= 0) {
      remove(line)
      return
    }
    line.qty = qty
    line.discount = Math.min(line.discount, unitPrice(line) * qty)
  }

  function remove(line: CartLine) {
    cart.value.lines = cart.value.lines.filter(l => l !== line)
  }

  function clear() {
    cart.value = emptyCart()
  }

  function hold() {
    if (!cart.value.lines.length) {
      return
    }
    held.value.unshift({ ...cart.value, held_at: new Date().toISOString() })
    clear()
  }

  function resume(heldCart: Cart) {
    if (cart.value.lines.length) {
      held.value.unshift({ ...cart.value, held_at: new Date().toISOString() })
    }
    held.value = held.value.filter(c => c.id !== heldCart.id)
    cart.value = { ...heldCart, held_at: undefined }
  }

  function dropHeld(heldCart: Cart) {
    held.value = held.value.filter(c => c.id !== heldCart.id)
  }

  return { cart, held, subtotal, total, count, missingSerials, unitPrice, lineTotal, add, setQty, remove, clear, hold, resume, dropHeld }
}

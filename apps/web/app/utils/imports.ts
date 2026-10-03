import type { ImportShipmentStatus } from '~/types/api'

export const IMPORT_CONTACT_TYPES: { value: string, label: string }[] = [
  { value: 'supplier', label: 'مصنع / تاجر' },
  { value: 'agent', label: 'وسيط' },
  { value: 'shipping', label: 'شركة شحن' },
  { value: 'customs', label: 'مخلّص جمركي' },
]

export const IMPORT_COST_KINDS: { value: string, label: string }[] = [
  { value: 'shipping', label: 'شحن' },
  { value: 'customs', label: 'جمارك' },
  { value: 'clearance', label: 'تخليص' },
  { value: 'inland', label: 'نقل داخلي' },
  { value: 'agent', label: 'عمولة وسيط' },
  { value: 'other', label: 'مصاريف تانية' },
]

export const IMPORT_PAYMENT_METHODS: { value: string, label: string }[] = [
  { value: 'bank', label: 'تحويل بنكي' },
  { value: 'exchange', label: 'شركة تحويل' },
  { value: 'agent', label: 'عن طريق وسيط' },
  { value: 'cash', label: 'كاش' },
  { value: 'wallet', label: 'محفظة' },
]

export const IMPORT_ATTACHMENT_KINDS: { value: string, label: string }[] = [
  { value: 'invoice', label: 'فاتورة' },
  { value: 'packing', label: 'قايمة التعبئة' },
  { value: 'bill_of_lading', label: 'بوليصة الشحن' },
  { value: 'photo', label: 'صور البضاعة' },
  { value: 'other', label: 'مرفق' },
]

export const IMPORT_STATUS_COLORS: Record<ImportShipmentStatus, 'neutral' | 'info' | 'warning' | 'primary' | 'success'> = {
  ordered: 'neutral',
  shipped: 'info',
  customs: 'warning',
  arrived: 'primary',
  received: 'success',
  cancelled: 'neutral',
}

/** The steps on the way, in order (receiving has its own button). */
export const IMPORT_STEPS: { value: ImportShipmentStatus, label: string }[] = [
  { value: 'ordered', label: 'تم الطلب' },
  { value: 'shipped', label: 'اتشحنت' },
  { value: 'customs', label: 'في الجمارك' },
  { value: 'arrived', label: 'وصلت' },
]

/** A contact's balance in words: what the shop owes them, or what they owe back. */
export function importBalanceLabel(balance: number): string {
  if (balance > 0) {
    return `عليك ${formatMoney(balance)}`
  }
  if (balance < 0) {
    return `ليك عنده ${formatMoney(-balance)}`
  }
  return 'خالص'
}

/** The costs split by weights, remainders to the largest fractions (sum exact) — like LandedCost::split(). */
export function splitCosts(total: number, weights: number[]): number[] {
  let sum = weights.reduce((s, w) => s + w, 0)
  if (sum <= 0) {
    weights = weights.map(() => 1)
    sum = weights.length
  }
  if (!sum) {
    return []
  }
  const shares = weights.map(w => Math.floor((total * w) / sum))
  const order = weights.map((w, i) => ({ i, f: (total * w) % sum })).sort((a, b) => b.f - a.f)
  let left = total - shares.reduce((s, x) => s + x, 0)
  for (const { i } of order) {
    if (left <= 0) {
      break
    }
    shares[i]! += 1
    left--
  }
  return shares
}

/** Landed unit cost per line — like LandedCost::compute(): claimed shortages don't weigh on the good units. */
export function landedCosts(lines: { qty: number, unitPrice: number, received: number }[], costs: number, allocation: 'value' | 'qty', claimed: boolean): number[] {
  const shares = splitCosts(costs, lines.map(l => (allocation === 'qty' ? l.qty : l.qty * l.unitPrice)))
  return lines.map((l, i) => {
    if (l.received <= 0) {
      return 0
    }
    const goods = l.unitPrice * (claimed ? l.received : l.qty)
    return Math.floor((goods + (shares[i] ?? 0) + Math.floor(l.received / 2)) / l.received)
  })
}

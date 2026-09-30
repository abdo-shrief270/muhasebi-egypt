/** Supplier returns (سلة المرتجعات / أذونات المرتجع): shapes and helpers shared by the screens. */

export type ReturnSourceType = 'supplier' | 'shop'

export interface ReturnSource {
  type: ReturnSourceType
  type_label: string
  id: string
  name: string
  phone?: string | null
}

export interface BinItem {
  id: string
  variant_id: string
  name: string
  qty: number
  serial: string | null
  unit_cost: number | null
  value: number | null
  source: ReturnSource | null
  source_doc: string | null
  detected_by: 'serial' | 'lot' | 'manual' | null
  reason: string
  reason_label: string
  note: string | null
  origin: 'manual' | 'sale_return' | 'repair'
  origin_label: string
  status: 'in_bin' | 'on_note' | 'settled'
  accepted_qty: number | null
  outcome: 'accepted' | 'rejected' | 'partial' | 'restocked' | 'written_off' | null
  return_id: string | null
  created_by_name: string | null
  created_at: string
}

export interface BinGroup {
  key: string
  source: ReturnSource | null
  lines: number
  units: number
  value: number | null
  items: BinItem[]
}

export interface BinData {
  groups: BinGroup[]
  units: number
  value: number | null
  open_notes: number
  reasons: { value: string, label: string }[]
}

export type NoteStatus = 'pending' | 'sent' | 'accepted' | 'partially_accepted' | 'rejected' | 'cancelled'

export interface ReturnNote {
  id: string
  reference: string
  source: ReturnSource
  status: NoteStatus
  status_label: string
  units: number
  total_cost: number | null
  accepted_value: number | null
  rejected_value: number | null
  resolution: 'credit' | 'refund' | 'replacement' | null
  refund_method: string | null
  rejected_action: 'restock' | 'write_off' | null
  notes: string | null
  settle_note: string | null
  created_by_name: string | null
  settled_by_name: string | null
  created_at: string
  sent_at: string | null
  settled_at: string | null
  items?: BinItem[]
}

export interface SourceCandidate {
  type: ReturnSourceType
  id: string
  name: string
  doc?: string | null
  date?: string | null
  unit_cost?: number | null
}

export interface SourceCandidates {
  suggested: SourceCandidate[]
  suppliers: SourceCandidate[]
  shops: SourceCandidate[]
}

/** The fixed list of reasons (same as the API's ReturnReason). */
export const RETURN_REASONS = [
  { value: 'defect', label: 'عيب صناعة' },
  { value: 'not_working', label: 'مش شغال' },
  { value: 'shipping_damage', label: 'مكسور في الشحن' },
  { value: 'wrong_item', label: 'غلط في الطلب' },
  { value: 'warranty_expired', label: 'انتهى ضمانه' },
  { value: 'other', label: 'سبب تاني' },
]

export const RESOLUTIONS = [
  { value: 'credit', label: 'رصيد على حسابه', icon: 'i-lucide-wallet' },
  { value: 'refund', label: 'رجّع فلوس', icon: 'i-lucide-banknote' },
  { value: 'replacement', label: 'بعت بديل', icon: 'i-lucide-repeat' },
] as const

export const REFUND_METHODS = [
  { value: 'cash', label: 'كاش' },
  { value: 'wallet', label: 'محفظة' },
  { value: 'instapay', label: 'InstaPay' },
  { value: 'bank_transfer', label: 'تحويل بنكي' },
]

export function noteStatusColor(status: NoteStatus): 'warning' | 'info' | 'success' | 'error' | 'neutral' {
  switch (status) {
    case 'pending': return 'warning'
    case 'sent': return 'info'
    case 'accepted': return 'success'
    case 'partially_accepted': return 'success'
    case 'rejected': return 'error'
    default: return 'neutral'
  }
}

export function detectedLabel(how: BinItem['detected_by']): string {
  switch (how) {
    case 'serial': return 'من السيريال'
    case 'lot': return 'من الدفعة'
    case 'manual': return 'اختيار يدوي'
    default: return 'مش معروف'
  }
}

/** One line per variant for the WhatsApp message: "• 3 × شاشة A54 (عيب صناعة)". */
export function noteItemLines(items: BinItem[]): string {
  const lines = new Map<string, { name: string, qty: number, reasons: Set<string> }>()
  for (const i of items) {
    const line = lines.get(i.variant_id) ?? { name: i.name, qty: 0, reasons: new Set<string>() }
    line.qty += i.qty
    line.reasons.add(i.reason_label)
    lines.set(i.variant_id, line)
  }
  return [...lines.values()].map(l => `• ${l.qty} × ${l.name} (${[...l.reasons].join('، ')})`).join('\n')
}

/** The built-in wording of the supplier_return_note template (used until templates load). */
export function returnNoteText(note: ReturnNote, shopName: string, withTotal: boolean): string {
  return [
    `أهلاً أستاذ/ة ${note.source.name} 👋`,
    `معاك ${shopName}. جهّزنا إذن مرتجع ${note.reference} (${note.units} قطعة):`,
    noteItemLines(note.items ?? []),
    withTotal && note.total_cost ? `القيمة: ${formatMoney(note.total_cost)}` : null,
    'ياريت تقولنا إمتى نبعتهم أو تعدّي تستلمهم. شكراً 🙏',
  ].filter(Boolean).join('\n')
}

/**
 * Items for a source picker: the variant's likely sources first (bought from lately), then every
 * supplier and partner shop. Values are "type:id".
 */
export function sourceOptions(c: SourceCandidates | null, first?: { label: string, value: string }) {
  const items: { label: string, value?: string, description?: string, type?: 'label' }[] = first ? [first] : []
  if (!c) {
    return items
  }
  const seen = new Set<string>()
  const add = (s: SourceCandidate, description?: string) => {
    const value = `${s.type}:${s.id}`
    if (!seen.has(value)) {
      seen.add(value)
      items.push({ label: s.name, value, description })
    }
  }
  if (c.suggested.length) {
    items.push({ type: 'label', label: 'اتشرى منهم قريب' })
    c.suggested.forEach(s => add(s, [s.doc, s.date ? formatDate(s.date) : null].filter(Boolean).join(' · ')))
  }
  if (c.suppliers.length) {
    items.push({ type: 'label', label: 'كل الموردين' })
    c.suppliers.forEach(s => add(s))
  }
  if (c.shops.length) {
    items.push({ type: 'label', label: 'محلات شريكة' })
    c.shops.forEach(s => add(s, 'محل شريك'))
  }
  return items
}

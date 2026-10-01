import type { RepairTicket, Sale } from '~/types/api'
import type { ReturnNote } from '~/utils/supplierReturns'

export interface MessageTemplate {
  key: string
  label: string
  group: string
  group_label: string
  variables: { name: string, label: string }[]
  body: string
  default_body: string
  customized: boolean
  updated_by_name: string | null
  /** The owner's switch this message belongs to (off = it isn't offered anywhere) */
  feature?: string | null
}

type Vars = Record<string, string | null | undefined>

/**
 * Fills a template: {name} → its value. A line with a variable that has no value is left out
 * (no "ميعاد التسليم: " with nothing after it); names the message doesn't know stay as typed.
 */
export function renderTemplate(body: string, vars: Vars): string {
  return body
    .split('\n')
    .filter(line => [...line.matchAll(/\{(\w+)\}/g)].every(m => !(m[1]! in vars) || !!vars[m[1]!]))
    .map(line => line.replace(/\{(\w+)\}/g, (all, name: string) => name in vars ? (vars[name] ?? '') : all))
    .join('\n')
}

export function ticketVars(ticket: RepairTicket, shopName: string, withLink = true): Vars {
  const faults = (ticket.diagnosed_faults ?? ticket.reported_faults).map(f => f.name).join('، ')
  const cost = ticket.total || ticket.estimate || 0
  return {
    customer: ticket.customer_name,
    device: ticket.device_name,
    ticket: ticket.reference,
    shop: shopName,
    link: withLink ? ticketUrl(ticket.public_token) : null,
    expected: ticket.expected_at ? formatDate(ticket.expected_at, true) : null,
    faults: faults || 'محتاج صيانة',
    cost: cost ? formatMoney(cost) : null,
    due: ticket.due > 0 ? formatMoney(ticket.due) : null,
    warranty: ticket.warranty_until ? formatDate(ticket.warranty_until) : null,
  }
}

/**
 * WhatsApp messages in the shop's own wording (/settings/templates). Each one opens WhatsApp with
 * the text and is logged (so a ready device whose customer wasn't told stands out).
 */
export function useMessages() {
  const api = useApi()
  const store = useSessionStore()
  const templates = useState<MessageTemplate[] | null>('message-templates', () => null)
  const shopName = computed(() => store.session?.tenant.name ?? '')
  const tracking = computed(() => store.hasFeature('repairs.public_tracking'))
  const receiptLink = computed(() => store.hasFeature('sales.receipt_link'))

  async function load(force = false) {
    if (templates.value && !force) {
      return templates.value
    }
    try {
      templates.value = (await api<{ data: MessageTemplate[] }>('/messages/templates')).data
    }
    catch {
      // Offline / not allowed: the built-in wording below is used.
    }
    return templates.value
  }
  load()

  /** The shop's wording, or the built-in text while templates haven't loaded. */
  function text(key: string, vars: Vars, fallback: () => string): string {
    const template = templates.value?.find(t => t.key === key)
    return template ? renderTemplate(template.body, vars) : fallback()
  }

  function open(key: string, phone: string | null, message: string, subject?: { type: 'repair_ticket' | 'sale' | 'customer' | 'supplier_return', id: string }) {
    window.open(phone ? whatsappLink(phone, message) : `https://wa.me/?text=${encodeURIComponent(message)}`, '_blank')
    if (store.can('messages.send')) {
      api('/messages/log', { method: 'POST', body: { template: key, phone, subject_type: subject?.type, subject_id: subject?.id } }).catch(() => {})
    }
  }

  function ticketText(ticket: RepairTicket): string {
    return text(`repair_${ticket.status}`, ticketVars(ticket, shopName.value, tracking.value), () => ticketMessage(ticket, shopName.value, tracking.value))
  }

  function sendTicket(ticket: RepairTicket) {
    open(`repair_${ticket.status}`, ticket.customer_phone, ticketText(ticket), { type: 'repair_ticket', id: ticket.id })
  }

  function sendSale(sale: Sale) {
    const message = text('sale_receipt', {
      customer: sale.customer_name,
      shop: shopName.value,
      invoice: sale.reference,
      total: formatMoney(sale.total),
      link: receiptLink.value ? receiptUrl(sale.public_token) : null,
    }, () => receiptWhatsappText(sale, shopName.value, receiptLink.value))
    open('sale_receipt', sale.customer_phone, message, { type: 'sale', id: sale.id })
  }

  function sendDebtReminder(customer: { id: string, name: string, phone: string | null, balance: number }) {
    const message = text('debt_reminder', { customer: customer.name, shop: shopName.value, balance: formatMoney(customer.balance) }, () => debtReminderText(customer.name, customer.balance, shopName.value))
    open('debt_reminder', customer.phone, message, { type: 'customer', id: customer.id })
  }

  /** A return note to its supplier / partner shop (the value only for users who see costs). */
  function sendReturnNote(note: ReturnNote) {
    const withTotal = store.can('products.view_cost')
    const message = text('supplier_return_note', {
      supplier: note.source.name,
      shop: shopName.value,
      note: note.reference,
      count: String(note.units),
      items: noteItemLines(note.items ?? []),
      total: withTotal && note.total_cost ? formatMoney(note.total_cost) : null,
    }, () => returnNoteText(note, shopName.value, withTotal))
    open('supplier_return_note', note.source.phone ?? null, message, { type: 'supplier_return', id: note.id })
  }

  return { templates, load, ticketText, sendTicket, sendSale, sendDebtReminder, sendReturnNote }
}

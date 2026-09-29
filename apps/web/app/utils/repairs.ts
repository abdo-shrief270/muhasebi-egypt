import type { RepairTicket, TicketStatus } from '~/types/api'

/** Badge colour per ticket status. */
export function ticketStatusColor(status: TicketStatus): 'neutral' | 'info' | 'warning' | 'primary' | 'success' | 'error' {
  return ({
    received: 'neutral',
    diagnosing: 'info',
    awaiting_approval: 'warning',
    repairing: 'info',
    awaiting_part: 'warning',
    ready: 'success',
    rejected: 'error',
    delivered: 'neutral',
  } as const)[status]
}

/** The customer's tracking page (the QR code on the intake receipt). */
export function ticketUrl(token: string): string {
  return `${window.location.origin}/t/${token}`
}

/**
 * The WhatsApp message that fits the ticket's status right now. The shop reviews it before
 * sending (wa.me opens with the text filled in).
 */
export function ticketMessage(ticket: RepairTicket, shopName: string): string {
  const hello = `أهلاً أستاذ/ة ${ticket.customer_name} 👋`
  const link = `تابع جهازك من هنا: ${ticketUrl(ticket.public_token)}`
  const faults = (ticket.diagnosed_faults ?? ticket.reported_faults).map(f => f.name).join('، ')
  const due = ticket.due > 0 ? `\nالمطلوب: ${formatMoney(ticket.due)}` : ''
  const lines: Record<TicketStatus, string[]> = {
    received: [hello, `استلمنا جهازك ${ticket.device_name} في ${shopName} (تذكرة ${ticket.reference}).`, ticket.expected_at ? `ميعاد التسليم المتوقع: ${formatDate(ticket.expected_at, true)}.` : '', link],
    diagnosing: [hello, `جهازك ${ticket.device_name} دلوقتي قيد الفحص، هنكلمك أول ما نعرف العطل.`, link],
    awaiting_approval: [hello, `فحصنا جهازك ${ticket.device_name}: ${faults || 'محتاج صيانة'}.`, `التكلفة ${formatMoney(ticket.total || ticket.estimate || 0)}. نبدأ؟ رد علينا بالموافقة.`, link],
    repairing: [hello, `بدأنا نصلّح جهازك ${ticket.device_name}.`, link],
    awaiting_part: [hello, `جهازك ${ticket.device_name} مستني قطعة غيار، هنبلّغك أول ما توصل.`, link],
    ready: [hello, `جهازك ${ticket.device_name} جاهز للاستلام من ${shopName} ✅${due}`, 'متنساش تجيب إيصال الاستلام.', link],
    rejected: [hello, `جهازك ${ticket.device_name} مش هينفع يتصلح. تقدر تستلمه من ${shopName} في أي وقت.`, link],
    delivered: [hello, `شكراً لتعاملك مع ${shopName} 🌷`, ticket.warranty_until ? `الضمان لحد ${formatDate(ticket.warranty_until)}.` : ''],
  }
  return lines[ticket.status].filter(Boolean).join('\n')
}

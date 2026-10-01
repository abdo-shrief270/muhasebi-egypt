import type { InstallmentItem, InstallmentPlan, InstallmentStatus } from '~/types/api'

/**
 * Same split as the API's Installments\Support\Schedule: equal installments rounded down to whole
 * pounds, the last takes the rest; a date every `intervalMonths` months (the 31st → the month's last day).
 */
export function installmentSchedule(total: number, count: number, firstDue: string, intervalMonths = 1): { seq: number, due_on: string, amount: number }[] {
  if (count < 1 || total <= 0 || !firstDue) {
    return []
  }
  let base = Math.floor(total / count)
  if (base >= 100) {
    base = Math.floor(base / 100) * 100
  }
  const [y, m, d] = firstDue.split('-').map(Number) as [number, number, number]
  return Array.from({ length: count }, (_, i) => {
    const month = m - 1 + i * intervalMonths
    const last = new Date(Date.UTC(y, month + 1, 0)).getUTCDate()
    const date = new Date(Date.UTC(y, month, Math.min(d, last)))
    return { seq: i + 1, due_on: date.toISOString().slice(0, 10), amount: i === count - 1 ? total - base * (count - 1) : base }
  })
}

/** Markup for a monthly rate (percent) over the plan's months, rounded to whole pounds — like Schedule::markup(). */
export function installmentMarkup(principal: number, percentPerMonth: number, months: number): number {
  return Math.round(principal * percentPerMonth * months / 100 / 100) * 100
}

/** YYYY-MM-DD in Cairo, `months` from today (the default first due date: a month from now). */
export function dateInMonths(months: number): string {
  const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Africa/Cairo' }))
  const target = new Date(today.getFullYear(), today.getMonth() + months, 1)
  const last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate()
  target.setDate(Math.min(today.getDate(), last))
  return `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}-${String(target.getDate()).padStart(2, '0')}`
}

export function installmentStatusColor(status: InstallmentStatus): 'primary' | 'success' | 'neutral' {
  return status === 'active' ? 'primary' : status === 'completed' ? 'success' : 'neutral'
}

/** "متأخر 12 يوم" / "النهارده" / "فاضل 3 أيام" for an open installment. */
export function dueLabel(item: Pick<InstallmentItem, 'due_on' | 'days_late' | 'remaining'>): string {
  if (item.remaining <= 0) {
    return 'اتدفع'
  }
  if (item.days_late > 0) {
    return `متأخر ${item.days_late} يوم`
  }
  const days = Math.round((Date.parse(item.due_on) - Date.parse(dateInMonths(0))) / 86400000)
  return days === 0 ? 'النهارده' : days === 1 ? 'بكرة' : `فاضل ${days} يوم`
}

/** Built-in wording of the installment_reminder template (used while the shop's templates load). */
export function installmentReminderText(vars: { customer: string, shop: string, amount: string, due: string, late: string | null, remaining: string, plan: string }): string {
  return [
    `أهلاً أستاذ/ة ${vars.customer} 👋`,
    `بنفكّر حضرتك إن قسط ${vars.amount} من تقسيط ${vars.plan} عند ${vars.shop} ميعاده ${vars.due}.`,
    vars.late,
    `الباقي من التقسيط: ${vars.remaining}`,
    'تقدر تدفع في المحل أو تحوّل على المحفظة / InstaPay. شكراً ليك 🙏',
  ].filter(Boolean).join('\n')
}

export type InstallmentReminderSubject = Pick<InstallmentPlan, 'id' | 'reference' | 'customer_name' | 'customer_phone' | 'remaining'>

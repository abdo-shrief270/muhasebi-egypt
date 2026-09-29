import type { CashMethod } from '~/types/api'

/** Ways money is taken at the counter (the API's cash / card / wallet / instapay). */
export const CASH_METHODS: { value: CashMethod, label: string }[] = [
  { value: 'cash', label: 'كاش' },
  { value: 'card', label: 'فيزا' },
  { value: 'wallet', label: 'محفظة' },
  { value: 'instapay', label: 'InstaPay' },
]

export function cashMethodLabel(method: string): string {
  return CASH_METHODS.find(m => m.value === method)?.label ?? (method === 'credit' ? 'آجل' : method)
}

/** A polite WhatsApp reminder of what a customer owes. */
export function debtReminderText(customerName: string, balance: number, shopName: string): string {
  return [
    `أهلاً أستاذ/ة ${customerName}،`,
    `حابين نفكّر حضرتك إن الحساب عندنا في ${shopName} عليه ${formatMoney(balance)}.`,
    'ياريت تعدّي علينا أو تحوّل على المحفظة / InstaPay في أقرب وقت. شكراً ليك 🙏',
  ].join('\n')
}

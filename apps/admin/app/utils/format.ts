/** Piasters → "1,234.50 ج" (drops .00). */
export function formatMoney(piasters: number | null | undefined): string {
  if (piasters === null || piasters === undefined) {
    return '—'
  }
  return `${(piasters / 100).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ج`
}

export function formatDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) {
    return '—'
  }
  return new Date(iso).toLocaleString('ar-EG-u-nu-latn', withTime
    ? { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }
    : { day: 'numeric', month: 'short', year: 'numeric' })
}

/** +201012345678 → 01012345678. */
export function localPhone(phone: string | null | undefined): string {
  return phone ? phone.replace(/^\+20/, '0') : ''
}

/** Pounds typed in a form → piasters for the API (empty → null). */
export function toPiasters(pounds: number | string | null | undefined): number | null {
  if (pounds === null || pounds === undefined || pounds === '') {
    return null
  }
  const value = Number(pounds)
  return Number.isFinite(value) ? Math.round(value * 100) : null
}

export function apiErrorMessage(error: unknown): string {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } })?.data
  if (data?.errors) {
    return Object.values(data.errors).flat()[0] ?? data.message ?? 'حصلت مشكلة.'
  }
  return data?.message ?? 'حصلت مشكلة، حاول تاني.'
}

/** "منذ 3 أيام" style, for last activity. */
const relative = new Intl.RelativeTimeFormat('ar-EG-u-nu-latn', { numeric: 'auto' })
export function timeAgo(iso: string | null | undefined, never = 'لسه'): string {
  if (!iso) {
    return never
  }
  const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60000)
  if (minutes > -1) {
    return 'دلوقتي'
  }
  if (minutes > -60) {
    return relative.format(minutes, 'minute')
  }
  if (minutes > -60 * 24) {
    return relative.format(Math.round(minutes / 60), 'hour')
  }
  if (minutes > -60 * 24 * 30) {
    return relative.format(Math.round(minutes / 1440), 'day')
  }
  return formatDate(iso)
}

/** Days since, or null when never. */
export function daysSince(iso: string | null | undefined): number | null {
  return iso ? Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000) : null
}

/** A beta shop nobody opened for 3+ days is probably stuck (7+ days or never: red). */
export function staleTone(iso: string | null | undefined): string {
  const days = daysSince(iso)
  return days === null || days >= 7 ? 'text-(--ui-error)' : days >= 3 ? 'text-(--ui-warning)' : ''
}

export const feedbackTypeColor = (type: string) => ({ problem: 'error', suggestion: 'info', question: 'warning' } as const)[type as 'problem'] ?? 'neutral'
export const feedbackStatusColor = (status: string) => ({ new: 'primary', seen: 'warning', done: 'success' } as const)[status as 'new'] ?? 'neutral'

export const subscriptionStatusColor = (status: string) => ({
  trialing: 'info',
  active: 'success',
  past_due: 'warning',
  restricted: 'warning',
  suspended: 'error',
} as const)[status as 'active'] ?? 'neutral'

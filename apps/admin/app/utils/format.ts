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

export const subscriptionStatusColor = (status: string) => ({
  trialing: 'info',
  active: 'success',
  past_due: 'warning',
  restricted: 'warning',
  suspended: 'error',
} as const)[status as 'active'] ?? 'neutral'

import type { ReportColumnType } from '~/types/api'

/** One report cell as text: money in pounds, Latin digits, Cairo dates. */
export function formatReportValue(value: string | number | null | undefined, type: ReportColumnType): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  // Labels such as "الإجمالي" in a totals row pass through whatever the column's type.
  if (typeof value === 'string' && !/^\d{4}-\d{2}-\d{2}/.test(value) && !/^-?\d+(\.\d+)?$/.test(value)) {
    return value
  }
  switch (type) {
    case 'money':
      return formatMoney(Number(value))
    case 'int':
      return Number(value).toLocaleString('en-US')
    case 'percent':
      return `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 1 })}%`
    case 'date':
      return new Date(`${value}T12:00:00`).toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'short', day: 'numeric', month: 'short' })
    case 'month':
      return new Date(`${value}T12:00:00`).toLocaleDateString('ar-EG-u-nu-latn', { month: 'long', year: 'numeric' })
    case 'datetime':
      return formatDate(String(value), true)
    default:
      return String(value)
  }
}

/** yyyy-mm-dd in the browser's (the shop's) own time zone. */
export function isoDay(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export interface PeriodPreset { key: string, label: string, from: string, to: string }

export function periodPresets(now = new Date()): PeriodPreset[] {
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const shift = (days: number) => new Date(today.getFullYear(), today.getMonth(), today.getDate() + days)
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
  const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1)
  const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
  return [
    { key: 'today', label: 'النهارده', from: isoDay(today), to: isoDay(today) },
    { key: 'yesterday', label: 'امبارح', from: isoDay(shift(-1)), to: isoDay(shift(-1)) },
    { key: '7d', label: 'آخر 7 أيام', from: isoDay(shift(-6)), to: isoDay(today) },
    { key: 'month', label: 'الشهر ده', from: isoDay(monthStart), to: isoDay(today) },
    { key: 'last_month', label: 'الشهر اللي فات', from: isoDay(lastMonthStart), to: isoDay(lastMonthEnd) },
    { key: 'year', label: 'السنة دي', from: isoDay(new Date(today.getFullYear(), 0, 1)), to: isoDay(today) },
  ]
}

export function saveBlob(blob: Blob, name: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}

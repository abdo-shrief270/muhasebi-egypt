import type { CategoryType, ProductVariant, QualityGrade } from '~/types/api'

export const qualityGrades: { value: QualityGrade, label: string }[] = [
  { value: 'original', label: 'أصلي' },
  { value: 'service_pack', label: 'سيرفس باك' },
  { value: 'high_copy', label: 'هاي كوبي' },
  { value: 'copy', label: 'كوبي' },
]

export const categoryTypes: { value: CategoryType, label: string }[] = [
  { value: 'accessory', label: 'إكسسوارات' },
  { value: 'part', label: 'قطع غيار' },
  { value: 'device', label: 'أجهزة' },
  { value: 'other', label: 'أخرى' },
]

/** Pounds typed in a form → piasters for the API (empty → null). */
export function toPiasters(pounds: number | string | null | undefined): number | null {
  if (pounds === null || pounds === undefined || pounds === '') {
    return null
  }
  const value = Number(pounds)
  return Number.isFinite(value) ? Math.round(value * 100) : null
}

/** Piasters from the API → pounds for a form field. */
export function toPounds(piasters: number | null | undefined): number | null {
  return piasters === null || piasters === undefined ? null : piasters / 100
}

/** "150 ج" or "150 – 250 ج" across a product's variants. */
export function retailPriceRange(variants: ProductVariant[]): string {
  const prices = variants.map(v => v.price_retail)
  if (!prices.length) {
    return '—'
  }
  const min = Math.min(...prices)
  const max = Math.max(...prices)
  return min === max ? formatMoney(min) : `${formatMoney(min).replace(' ج', '')} – ${formatMoney(max)}`
}

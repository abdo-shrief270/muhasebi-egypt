export interface PlanModule { key: string, name: string, available: boolean }
export interface Plan { key: string, name: string, description: string, monthly: number, yearly: number, featured: boolean, modules: PlanModule[] }
export interface Plans { trial_days: number, yearly_months: number, vat_percent: number, plans: Plan[], modules: (PlanModule & { monthly: number })[] }

/**
 * The live plans and prices from the API (config/billing.php), so the site never shows a stale price.
 * Fetched in the browser: the static build has no API to ask.
 */
export function usePlans() {
  const apiBase = useRuntimeConfig().public.apiBase
  return useFetch<{ data: Plans }>(`${apiBase}/public/plans`, {
    key: 'plans',
    server: false,
    transform: r => r,
  })
}

/** Piasters → "449" / "4,490" (Latin digits, no decimals when whole). */
export function pounds(piasters: number): string {
  const v = piasters / 100
  return new Intl.NumberFormat('ar-EG-u-nu-latn', { maximumFractionDigits: v % 1 ? 2 : 0 }).format(v)
}

export interface PlanModule { key: string, name: string, available: boolean }
export interface Plan { key: string, name: string, description: string, monthly: number, yearly: number, featured: boolean, modules: PlanModule[] }
export interface Plans { trial_days: number, yearly_months: number, vat_percent: number, plans: Plan[], modules: (PlanModule & { monthly: number })[] }

/**
 * The live plans and prices from the API (config/billing.php), so the site never shows a stale price.
 * At build time (prerender) they're fetched from the app's API so the prices are in the static HTML
 * that search engines read; the browser then asks again on the site's own domain for today's prices.
 */
export function usePlans() {
  const { apiBase, appUrl } = useRuntimeConfig().public
  const built = useNuxtData<{ data: Plans } | null>('plans').data
  const result = useAsyncData<{ data: Plans } | null>('plans', () => {
    if (import.meta.server) {
      // No API during a build is fine: the page then fills in the browser.
      return $fetch<{ data: Plans }>(`${appUrl.replace(/\/$/, '')}/api/v1/public/plans`, { timeout: 5000 })
        .then(r => (Array.isArray(r?.data?.plans) ? r : null))   // anything but the plans JSON = none
        .catch(() => null)
    }
    // A failed refresh keeps the prices from the build rather than blanking the page.
    const previous = built.value
    return $fetch<{ data: Plans }>(`${apiBase}/public/plans`).catch((e) => {
      if (previous) return previous
      throw e
    })
  })
  if (import.meta.client) {
    onMounted(() => result.refresh())
  }
  return result
}

/** Piasters → "449" / "4,490" (Latin digits, no decimals when whole). */
export function pounds(piasters: number): string {
  const v = piasters / 100
  return new Intl.NumberFormat('ar-EG-u-nu-latn', { maximumFractionDigits: v % 1 ? 2 : 0 }).format(v)
}

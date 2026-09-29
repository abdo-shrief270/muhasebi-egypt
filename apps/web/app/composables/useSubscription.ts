import type { SubscriptionInfo } from '~/types/api'

/** The shop's subscription status (for the banner on every screen); refreshed after paying. */
export function useSubscription() {
  const api = useApi()
  const store = useSessionStore()
  const status = useState<SubscriptionInfo | null>('subscription-status', () => null)

  async function refresh() {
    if (!store.isLoggedIn) {
      return
    }
    try {
      status.value = (await api<{ data: SubscriptionInfo }>('/billing/status')).data
    }
    catch {
      // The banner is a nicety; never block a screen on it.
    }
  }

  return { status, refresh }
}

export const subscriptionStatusColor = (status: SubscriptionInfo['status']) => ({
  trialing: 'info',
  active: 'success',
  past_due: 'warning',
  restricted: 'warning',
  suspended: 'error',
} as const)[status]

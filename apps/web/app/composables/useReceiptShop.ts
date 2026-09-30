import type { ReceiptShop } from '~/types/api'

/**
 * The shop lines printed on a receipt: the shop's name, the current branch's address and phone
 * (the shop's phone when the branch has none), and what the owner set in /settings/shop.
 */
export function useReceiptShop() {
  const store = useSessionStore()
  return computed<ReceiptShop | null>(() => {
    const session = store.session
    if (!session) {
      return null
    }
    const branch = store.currentBranch
    return {
      name: session.tenant.name,
      phone: branch?.phone || session.tenant.phone,
      address: branch?.address ?? null,
      receipt: session.tenant.receipt,
    }
  })
}

import type { ApprovalNeeded } from '~/types/api'

/**
 * «اطلب موافقة» on the cashier's side. When the API answers `approval_required`, `ask(error)`
 * opens the window (ApprovalsRequestModal, in the layout): send it to the owner's phone and wait,
 * or let a manager type their PIN. Resolves with the approval id to send back as X-Approval-Id,
 * or null if it was refused / given up.
 */
interface Pending {
  needed: ApprovalNeeded
  resolve: (id: string | null) => void
}

export function approvalNeeded(error: unknown): ApprovalNeeded | null {
  return apiErrorCode(error) === 'approval_required'
    ? ((error as { data?: { approval?: ApprovalNeeded } }).data?.approval ?? null)
    : null
}

export function useApproval() {
  const pending = useState<Pending | null>('approval-pending', () => null)

  function ask(error: unknown): Promise<string | null> {
    const needed = approvalNeeded(error)
    if (!needed) {
      return Promise.resolve(null)
    }
    pending.value?.resolve(null)
    return new Promise((resolve) => {
      pending.value = { needed, resolve }
    })
  }

  function finish(id: string | null) {
    pending.value?.resolve(id)
    pending.value = null
  }

  return { pending: readonly(pending), ask, finish }
}

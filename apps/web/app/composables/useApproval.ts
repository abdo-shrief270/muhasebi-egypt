import type { ApprovalNeeded, ApprovalRequest } from '~/types/api'

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

/**
 * The approver's side: approve / deny, asking for the fingerprint or PIN when the owner wants it
 * for big amounts (step_up_required), and pointing to «الأمان» when two-factor sign-in is required.
 * Resolves true when the request was answered (or someone else already answered it).
 */
export function useApprovalDecision() {
  const api = useApi()
  const toast = useToast()
  const lock = useAppLock()

  async function decide(a: ApprovalRequest, action: 'approve' | 'deny', reason: string | null = null): Promise<boolean> {
    for (let attempt = 0; attempt < 2; attempt++) {
      try {
        await api(`/approvals/${a.id}/${action}`, { method: 'POST', body: { reason } })
        toast.add({ color: action === 'approve' ? 'success' : 'neutral', title: action === 'approve' ? `وافقت لـ ${a.requested_by_name}` : `رفضت طلب ${a.requested_by_name}` })
        return true
      }
      catch (e) {
        const code = apiErrorCode(e)
        if (code === 'step_up_required' && attempt === 0 && await lock.confirm()) {
          continue
        }
        toast.add({
          color: code === 'approval_closed' ? 'neutral' : 'error',
          title: apiErrorMessage(e),
          actions: code === 'two_factor_required' ? [{ label: 'فعّله', onClick: () => { navigateTo('/settings/security') } }] : undefined,
        })
        return code === 'approval_closed'
      }
    }
    return false
  }

  return { decide }
}

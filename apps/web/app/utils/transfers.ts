import type { TransferStatus } from '~/types/api'

/** Badge colours for the transfers' statuses. */
export const TRANSFER_COLORS: Record<TransferStatus, 'warning' | 'info' | 'success' | 'neutral'> = {
  requested: 'warning',
  shipped: 'info',
  received: 'success',
  cancelled: 'neutral',
}

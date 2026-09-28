import type { ShopOrderStatus } from '~/types/api'

type BadgeColor = 'neutral' | 'info' | 'primary' | 'warning' | 'success' | 'error'

export const shopOrderStatusColor: Record<ShopOrderStatus, BadgeColor> = {
  placed: 'info',
  accepted: 'primary',
  preparing: 'warning',
  ready: 'success',
  delivered: 'primary',
  completed: 'neutral',
  rejected: 'error',
  cancelled: 'error',
}

export const shopOrderActionIcon: Record<ShopOrderStatus, string> = {
  placed: 'i-lucide-send',
  accepted: 'i-lucide-check',
  preparing: 'i-lucide-package-open',
  ready: 'i-lucide-package-check',
  delivered: 'i-lucide-truck',
  completed: 'i-lucide-circle-check-big',
  rejected: 'i-lucide-x',
  cancelled: 'i-lucide-ban',
}

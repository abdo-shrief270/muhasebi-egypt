export interface QuickAction {
  to: string
  label: string
  description: string
  icon: string
  permission: string
}

// The everyday "do something now" shortcuts, shown on the home page and in Ctrl+K.
// `can()` already folds in whether the shop can use the permission's module.
const ACTIONS: QuickAction[] = [
  { to: '/pos', label: 'بيع جديد', description: 'افتح الكاشير', icon: 'i-lucide-shopping-cart', permission: 'sales.sell' },
  { to: '/purchases/new', label: 'فاتورة شراء', description: 'استلم بضاعة من مورد', icon: 'i-lucide-receipt-text', permission: 'suppliers.manage' },
  { to: '/products/new', label: 'صنف جديد', description: 'ضيف صنف للكتالوج', icon: 'i-lucide-package-plus', permission: 'products.manage' },
  { to: '/inventory?count=1', label: 'جرد', description: 'اعد البضاعة اللي على الرف', icon: 'i-lucide-clipboard-check', permission: 'inventory.adjust' },
  { to: '/products/labels', label: 'ليبلات باركود', description: 'اطبع باركود أو QR', icon: 'i-lucide-tag', permission: 'products.view' },
  { to: '/products/import', label: 'استيراد إكسل', description: 'ارفع أصناف كتير مرة واحدة', icon: 'i-lucide-file-spreadsheet', permission: 'products.manage' },
]

export function useQuickActions() {
  const store = useSessionStore()
  return computed(() => ACTIONS.filter(a => store.can(a.permission)))
}

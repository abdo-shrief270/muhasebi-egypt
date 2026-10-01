export interface QuickAction {
  label: string
  description: string
  icon: string
  permission: string
  feature?: string
  /** a page to open… */
  to?: string
  /** …or something to do in place (open a window) */
  run?: () => void
  /** shown as a hint on the tile */
  kbd?: string
}

/**
 * The everyday "do something now" shortcuts, shown on the home page and in Ctrl+K.
 * `can()` already folds in whether the shop can use the permission's module.
 */
export function useQuickActions() {
  const store = useSessionStore()
  const priceCheck = usePriceCheck()

  const actions: QuickAction[] = [
    { to: '/pos', label: 'بيع جديد', description: 'افتح الكاشير', icon: 'i-lucide-shopping-cart', permission: 'sales.sell' },
    { run: () => priceCheck.show(), label: 'استعلام سعر', description: 'امسح أو دوّر: السعر والمخزون', icon: 'i-lucide-scan-barcode', permission: 'products.view', feature: 'inventory.price_check', kbd: 'F8' },
    { to: '/repairs/new', label: 'استلام جهاز', description: 'تذكرة صيانة جديدة', icon: 'i-lucide-wrench', permission: 'repairs.create' },
    { to: '/customers?owing=1', label: 'تحصيل آجل', description: 'اللي عليهم فلوس', icon: 'i-lucide-hand-coins', permission: 'customers.credit' },
    { to: '/installments', label: 'الأقساط', description: 'المتأخر واللي عليه الدور', icon: 'i-lucide-calendar-clock', permission: 'installments.collect' },
    { to: '/customers?new=1', label: 'عميل جديد', description: 'ضيف عميل بحسابه', icon: 'i-lucide-user-plus', permission: 'customers.manage' },
    { to: '/services', label: 'شحن وتحويل', description: 'فودافون كاش وشحن الرصيد', icon: 'i-lucide-arrow-left-right', permission: 'services.manage' },
    { to: '/cash?expense=1', label: 'مصروف', description: 'سجّل مصروف من الدرج', icon: 'i-lucide-receipt', permission: 'cash.expenses', feature: 'cash.expenses' },
    { to: '/cash', label: 'الوردية', description: 'الدرج وقفل اليوم', icon: 'i-lucide-wallet', permission: 'cash.shift' },
    { to: '/purchases/new', label: 'فاتورة شراء', description: 'استلم بضاعة من مورد', icon: 'i-lucide-receipt-text', permission: 'suppliers.manage' },
    { to: '/products/new', label: 'صنف جديد', description: 'ضيف صنف للكتالوج', icon: 'i-lucide-package-plus', permission: 'products.manage' },
    { to: '/inventory?count=1', label: 'جرد', description: 'اعد البضاعة اللي على الرف', icon: 'i-lucide-clipboard-check', permission: 'inventory.adjust' },
    { to: '/products/prices', label: 'تعديل الأسعار', description: 'زوّد أو خفّض أسعار كتير مرة واحدة', icon: 'i-lucide-percent', permission: 'products.manage', feature: 'catalog.bulk_prices' },
    { to: '/inventory/serials', label: 'بحث IMEI', description: 'الجهاز ده اتشرى واتباع إمتى', icon: 'i-lucide-scan-line', permission: 'inventory.view' },
    { to: '/products/labels', label: 'ليبلات باركود', description: 'اطبع باركود أو QR', icon: 'i-lucide-tag', permission: 'products.view', feature: 'catalog.labels' },
    { to: '/reports/sales?period=today', label: 'تقرير النهارده', description: 'مبيعات وأرباح اليوم', icon: 'i-lucide-chart-column', permission: 'reports.view' },
    { to: '/products/import', label: 'استيراد إكسل', description: 'ارفع أصناف كتير مرة واحدة', icon: 'i-lucide-file-spreadsheet', permission: 'products.manage', feature: 'catalog.excel_import' },
  ]

  return computed(() => actions.filter(a => store.can(a.permission) && (!a.feature || store.hasFeature(a.feature))))
}

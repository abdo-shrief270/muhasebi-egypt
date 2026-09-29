// Hand-written for now; will be generated from the API's OpenAPI spec (Scramble) into packages/api-types.

export type ModuleState = 'not_entitled' | 'trial' | 'enabled' | 'disabled' | 'read_only'

export interface MenuEntry {
  to: string
  label: string
  icon: string
  permission: string | null
  module: string
  group: MenuGroup
}

export type MenuGroup = 'sales' | 'stock' | 'services' | 'reports' | 'settings'

export interface SessionUser {
  id: string
  name: string
  phone: string
  email: string | null
  is_owner: boolean
  is_active: boolean
  role?: { id: number, name: string } | null
  branch_ids?: string[]
}

export interface Branch {
  id: string
  name: string
  phone: string | null
  address: string | null
  invoice_prefix: string
  is_main: boolean
  is_active: boolean
}

export interface Role {
  id: number
  key: string | null
  name: string
  permissions: string[]
  users_count?: number
}

export interface PermissionGroup {
  module: string
  name: string
  permissions: { key: string, label: string }[]
}

export interface AuditEntry {
  id: number
  action: string
  description: string
  user_name: string
  subject_type: string | null
  ip: string | null
  created_at: string
}

export interface Session {
  user: SessionUser
  tenant: { id: string, name: string, code: string, phone: string, shop_type: string, shop_type_label: string }
  branches: Branch[]
  current_branch_id: string | null
  enabled_modules: string[]
  permissions: string[]
  modules: { key: string, state: ModuleState, usable: boolean }[]
  menu: MenuEntry[]
}

export interface ModuleInfo {
  key: string
  name: string
  description: string
  tier: 'core' | 'optional'
  depends_on: string[]
  state: ModuleState
  state_label: string
  usable: boolean
  /** false while the module is still being built («قريباً») */
  available: boolean
  entitled: boolean
  trial_available: boolean
  trial_ends_at: string | null
  menu: Omit<MenuEntry, 'module'>[]
}

export interface ApiError {
  message: string
  code?: string
  module?: string
  errors?: Record<string, string[]>
}

export interface ShopRef {
  id: string
  name: string
  code: string
  phone: string
}

export type ShopOrderStatus = 'placed' | 'accepted' | 'preparing' | 'ready' | 'delivered' | 'completed' | 'rejected' | 'cancelled'

export interface ShopOrderItem {
  id: number
  description: string
  quantity: number
  unit_price: number | null
  device_model: string | null
  imei: string | null
  note: string | null
}

export interface ShopOrder {
  id: string
  reference: string
  type: 'goods' | 'repair'
  type_label: string
  status: ShopOrderStatus
  status_label: string
  my_party: 'buyer' | 'seller'
  counterparty: ShopRef | null
  needed_by: string | null
  notes: string | null
  total: number | null
  created_at: string
  allowed_transitions: { status: ShopOrderStatus, label: string }[]
  items_count: number | null
  items?: ShopOrderItem[]
  activities?: { to_status: ShopOrderStatus, to_status_label: string, by: 'buyer' | 'seller', note: string | null, at: string }[]
}

export interface ShopConnection {
  id: string
  status: 'pending' | 'accepted' | 'declined'
  status_label: string
  direction: 'incoming' | 'outgoing'
  shop: ShopRef | null
  created_at: string
}

export type CategoryType = 'accessory' | 'part' | 'device' | 'other'
export type QualityGrade = 'original' | 'service_pack' | 'high_copy' | 'copy'

export interface Category {
  id: number
  name: string
  type: CategoryType
  type_label: string
  products_count?: number
}

export interface DeviceModel {
  id: number
  brand_id: number
  name: string
  full_name: string
}

export interface Brand {
  id: number
  name: string
  models?: DeviceModel[]
}

/** Prices are piasters. */
export interface ProductVariant {
  id: string
  name: string | null
  quality_grade: QualityGrade | null
  quality_label: string | null
  barcode: string | null
  price_retail: number
  price_wholesale: number | null
  price_technician: number | null
  price_online: number | null
  min_stock: number
  is_active: boolean
}

export interface Product {
  id: string
  name: string
  sku: string | null
  track_serial: boolean
  is_active: boolean
  notes: string | null
  category: { id: number, name: string }
  brand: { id: number, name: string } | null
  variants: ProductVariant[]
  device_models: DeviceModel[]
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number, last_page: number, per_page: number, total: number }
}

export type StockStatus = 'ok' | 'low' | 'out'

/** A variant with its stock in the current branch. Costs are null without products.view_cost. */
export interface StockRow {
  id: string
  product_id: string
  product_name: string
  variant_name: string | null
  display_name: string
  barcode: string | null
  sku: string | null
  category: { id: number, name: string }
  price_retail: number
  min_stock: number
  is_active: boolean
  track_serial: boolean
  qty: number
  status: StockStatus
  avg_cost: number | null
  value: number | null
}

export interface StockSummary {
  variants: number
  in_stock: number
  out_of_stock: number
  low: number
  units: number
  value: number | null
}

export interface StockMovementRow {
  id: string
  type: string
  type_label: string
  qty: number
  balance_after: number
  unit_cost: number | null
  reason: string | null
  reason_label: string | null
  note: string | null
  user_name: string | null
  created_at: string
}

/** Balance > 0: the shop owes the supplier. Money in piasters. */
export interface Supplier {
  id: string
  name: string
  phone: string | null
  notes: string | null
  balance: number
  is_active: boolean
  purchases_count?: number
  last_purchase_at?: string | null
}

export interface SupplierTransaction {
  id: string
  type: 'opening' | 'purchase' | 'payment' | 'purchase_return'
  type_label: string
  amount: number
  balance_after: number
  payment_method: string | null
  payment_method_label: string | null
  ref_type: string | null
  ref_id: string | null
  note: string | null
  user_name: string | null
  created_at: string
}

export interface PurchaseItem {
  id: number
  variant_id: string
  name: string | null
  barcode: string | null
  qty: number
  unit_cost: number
  net_unit_cost: number
  line_total: number
  previous_cost: number | null
  cost_increased: boolean
  returned_qty: number
  serials: string[] | null
}

export interface Purchase {
  id: string
  number: number
  reference: string
  supplier: { id: string, name: string } | null
  supplier_invoice_no: string | null
  invoice_date: string
  subtotal: number
  discount: number
  total: number
  paid: number
  payment_method: string | null
  payment_method_label: string | null
  returned: number
  notes: string | null
  created_by_name: string | null
  created_at: string
  items_count: number | null
  items: PurchaseItem[]
  returns: { id: string, reference: string, total: number, notes: string | null, created_by_name: string | null, created_at: string }[]
}

export interface PurchasableVariant {
  id: string
  display_name: string
  barcode: string | null
  sku: string | null
  category: { id: number, name: string }
  avg_cost: number | null
  exact_barcode: boolean
  track_serial: boolean
}

export interface PosItem {
  id: string
  product_id: string
  product_name: string
  variant_name: string | null
  display_name: string
  barcode: string | null
  sku: string | null
  category: { id: number, name: string }
  price_retail: number
  price_wholesale: number | null
  price_technician: number | null
  quality_label: string | null
  min_stock: number
  is_active: boolean
  track_serial: boolean
  qty: number
  exact_barcode: boolean
}

export interface SaleLine {
  id: number
  variant_id: string
  name: string
  barcode: string | null
  qty: number
  unit_price: number
  discount: number
  line_total: number
  returned_qty: number
  serials: string[] | null
  returned_serials?: string[]
}

export interface Sale {
  id: string
  number: number
  reference: string
  status: 'completed' | 'partially_refunded' | 'refunded'
  status_label: string
  price_level: string
  price_level_label: string
  customer_id: string | null
  customer_name: string | null
  customer_phone: string | null
  subtotal: number
  discount: number
  total: number
  paid: number
  credit: number
  change: number
  refunded: number
  cost_total?: number
  profit?: number
  notes: string | null
  cashier_name: string | null
  public_token: string
  completed_at: string
  items_count?: number
  items?: SaleLine[]
  payments?: { method: string, method_label: string, amount: number, reference: string | null }[]
  returns?: { id: string, reference: string, total: number, refund_method_label: string, reason: string | null, created_by_name: string | null, created_at: string }[]
}

/** What a printed / public receipt shows. */
export interface ReceiptData {
  shop: { name: string, phone: string | null } | null
  branch?: string | null
  reference: string
  completed_at: string
  cashier_name?: string | null
  customer_name: string | null
  subtotal: number
  discount: number
  total: number
  paid: number
  change: number
  refunded: number
  items: { name: string, qty: number, unit_price: number, discount: number, line_total: number, returned_qty: number, serials?: string[] | null }[]
  payments: { method_label: string, amount: number }[]
}

export interface SalesStats {
  days: number
  today: { sales: number, revenue: number, profit: number | null, average: number, revenue_yesterday: number }
  period: { sales: number, revenue: number, profit: number | null }
  series: { date: string, sales: number, revenue: number, profit: number | null }[]
  top_items: { name: string, qty: number, revenue: number }[]
  payments: { method: string, label: string, amount: number }[]
}

export interface Customer {
  id: string
  name: string
  phone: string | null
  notes: string | null
  /** piasters; > 0 = owes the shop, < 0 = store credit */
  balance: number
  credit_limit: number | null
  is_active: boolean
  last_activity_at: string | null
  created_at: string | null
}

export interface CustomerTransaction {
  id: string
  type: 'opening' | 'sale' | 'payment' | 'sale_return'
  type_label: string
  amount: number
  balance_after: number
  payment_method: string | null
  payment_method_label: string | null
  ref_type: string | null
  ref_id: string | null
  reference: string | null
  note: string | null
  user_name: string | null
  created_at: string
}

export type CashMethod = 'cash' | 'card' | 'wallet' | 'instapay'

export interface CashMovement {
  id: string
  type: 'sale' | 'sale_refund' | 'customer_payment' | 'expense' | 'deposit' | 'withdrawal'
  type_label: string
  method: CashMethod
  method_label: string
  amount: number
  category: string | null
  category_label: string | null
  ref_type: string | null
  ref_id: string | null
  note: string | null
  user_name: string | null
  created_at: string
}

export interface CashShift {
  id: string
  number: number
  reference: string
  branch_id: string
  user_id: string
  user_name: string
  opening_cash: number
  opened_at: string
  closed_at: string | null
  closed_by_name: string | null
  is_open: boolean
  expected: Record<CashMethod, number> | null
  counted: Record<CashMethod, number> | null
  cash_difference: number | null
  note: string | null
  by_type?: { type: string, label: string, method: CashMethod, amount: number, count: number }[]
  movements?: CashMovement[]
}

export interface CashOptions {
  methods: { value: CashMethod, label: string }[]
  expense_categories: { value: string, label: string }[]
}

/** The customer attached to a POS cart (kept with the cart in the browser). */
export interface PosCustomer {
  id: string
  name: string
  phone: string | null
  balance: number
  credit_limit: number | null
}

export type ReportColumnType = 'text' | 'int' | 'money' | 'percent' | 'date' | 'month' | 'datetime'

export interface ReportDefinition {
  key: string
  title: string
  description: string
  group: 'sales' | 'stock' | 'money'
  uses_dates: boolean
  uses_branches: boolean
  options: { key: string, label: string, choices: { value: string, label: string }[] }[]
}

export interface ReportData {
  key: string
  title: string
  from: string | null
  to: string | null
  branches: string[] | null
  summary: { label: string, value: number | string | null, type: ReportColumnType, hint?: string }[]
  columns: { key: string, label: string, type: ReportColumnType }[]
  rows: Record<string, string | number | null>[]
  totals: Record<string, string | number | null> | null
  chart: { kind: 'daily' | 'bars', label: string, type: ReportColumnType, points: { label: string, value: number, date?: string }[] } | null
  notes: string[]
}

export interface PriceCheckItem {
  id: string
  product_id: string
  display_name: string
  barcode: string | null
  category: string
  quality_label: string | null
  is_active: boolean
  exact_barcode: boolean
  /** wholesale / technician are null for whoever can't sell at them */
  prices: { retail: number, wholesale: number | null, technician: number | null }
  /** average cost in this branch; null without products.view_cost */
  cost: number | null
  /** per branch the user may see; null without inventory.view */
  stock: { branch_id: string, name: string, qty: number, current: boolean }[] | null
}

export type TicketStatus = 'received' | 'diagnosing' | 'awaiting_approval' | 'repairing' | 'awaiting_part' | 'ready' | 'rejected' | 'delivered'

export interface TicketFault { id: number, category: string, name: string }

export interface RepairTicket {
  id: string
  number: number
  reference: string
  branch_id: string
  status: TicketStatus
  status_label: string
  next_statuses: { value: TicketStatus, label: string }[]
  can_deliver: boolean
  is_overdue: boolean
  customer_id: string
  customer_name: string
  customer_phone: string
  device_model_id: number | null
  device_name: string
  imei: string | null
  color: string | null
  unlock_type: 'none' | 'pin' | 'pattern' | 'password'
  /** null for whoever doesn't work on devices */
  unlock_code: string | null
  accessories: string[]
  accessories_labels: string[]
  condition: string[]
  condition_labels: string[]
  checks: Record<string, 'yes' | 'no' | 'unknown'>
  reported_faults: TicketFault[]
  reported_note: string | null
  diagnosed_faults: TicketFault[] | null
  diagnosis_note: string | null
  suggested_labor?: number
  received_by_name: string | null
  received_at: string
  expected_at: string | null
  technician_id: string | null
  technician_name: string | null
  ready_at: string | null
  /** ready tickets: when the customer was told on WhatsApp (null = not yet) */
  ready_notified_at: string | null
  delivered_at: string | null
  delivered_by_name: string | null
  estimate: number | null
  labor: number
  parts_total: number
  parts_cost: number | null
  discount: number
  total: number
  paid: number
  credit: number
  due: number
  /** the technician's; null unless you're that technician or see profits */
  commission: number | null
  commission_rule: string | null
  warranty_days: number
  warranty_until: string | null
  under_warranty: boolean
  warranty_of_id: string | null
  public_token: string
  parts?: { id: number, variant_id: string, name: string, qty: number, unit_price: number, line_total: number, added_by_name: string | null }[]
  payments?: { kind: 'deposit' | 'payment' | 'refund', method: string, amount: number, user_name: string | null, created_at: string }[]
  events?: { type: string, type_label: string, from_status_label: string | null, to_status: TicketStatus | null, to_status_label: string | null, note: string | null, user_name: string | null, created_at: string }[]
}

export interface RepairFaultCategory {
  id: number
  name: string
  types: { id: number, name: string, default_labor_price: number | null, is_active: boolean }[]
}

export interface RepairOptions {
  accessories: { value: string, label: string }[]
  condition: { value: string, label: string }[]
  checks: { value: string, label: string }[]
  statuses: { value: TicketStatus, label: string }[]
  technicians: { id: string, name: string }[]
  faults: RepairFaultCategory[]
}

export interface RepairSummary { open: number, ready: number, unnotified: number, overdue: number, abandoned: number, mine: number }

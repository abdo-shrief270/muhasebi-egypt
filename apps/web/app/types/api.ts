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
  two_factor_enabled: boolean
  /** when they last signed in on any device */
  last_login_at?: string | null
}

/** POST /auth/login: a token, or (two-factor sign-in on) a challenge for POST /auth/two-factor. */
export type LoginResponse = { token: string, user: SessionUser } | { two_factor: true, challenge: string, expires_in: number }

/** GET /onboarding: the «ابدأ من هنا» card (steps ticked from the shop's data). */
export interface OnboardingStep {
  key: string
  title: string
  description: string
  to: string
  icon: string
  done: boolean
}

export interface OnboardingState {
  visible: boolean
  done: number
  total: number
  complete: boolean
  collapsed: boolean
  dismissed: boolean
  steps: OnboardingStep[]
}

export interface TwoFactorStatus {
  enabled: boolean
  confirmed_at: string | null
  recovery_codes_left: number
}

export interface TwoFactorSetup {
  secret: string
  otpauth_url: string
}

/** A signed-in device (an API token). */
export interface DeviceSession {
  id: number
  device_name: string
  ip_address: string | null
  user_agent: string | null
  created_at: string | null
  last_used_at: string | null
  current: boolean
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
  tenant: { id: string, name: string, code: string, phone: string, shop_type: string, shop_types: string[], shop_type_label: string, receipt?: ReceiptSettings }
  branches: Branch[]
  current_branch_id: string | null
  enabled_modules: string[]
  /** feature switch key → on */
  features: Record<string, boolean>
  feature_settings?: Record<string, number | string>
  /** Where Laravel Echo connects (Reverb); null = no live updates, screens poll. */
  realtime?: { key: string, host: string, port: number, scheme: string } | null
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
  /** the seller hides prices from the buyer until the order is ready */
  prices_hidden?: boolean
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
  online_visible: boolean
  online_description: string | null
  images?: ProductImage[]
  category: { id: number, name: string }
  brand: { id: number, name: string } | null
  variants: ProductVariant[]
  device_models: DeviceModel[]
  created_at: string
}

/** «المتجر الأونلاين» settings (GET/PUT /online-store/settings). Phones are E.164. */
export interface OnlineStoreSettings {
  slug: string
  mode: 'off' | 'whatsapp' | 'orders'
  name: string
  tagline: string | null
  about: string | null
  color: string
  branch_id: string | null
  whatsapp: string | null
  phone: string | null
  address: string | null
  map_url: string | null
  hours: string | null
  policy: string | null
  facebook: string | null
  instagram: string | null
  show_out_of_stock: boolean
  show_quantity: boolean
  show_prices: boolean
  show_models: boolean
  show_latest: boolean
  show_whatsapp: boolean
  show_brand: boolean
  announcement: string | null
  /** what a category is called on the store, by category id */
  category_names: Record<string, string>
  /** HH:MM Cairo time, both null = any time */
  orders_from: string | null
  orders_until: string | null
  logo: Record<string, string> | null
  cover: Record<string, string> | null
  pickup: boolean
  delivery: boolean
  min_order: number
  free_delivery_over: number | null
  pay_cod: boolean
  pay_transfer: boolean
  transfer_instapay: string | null
  transfer_wallet: string | null
  url: string
  /** the Meta / Google product feed */
  feed_url: string
  updated_at: string
}

export interface DeliveryZone {
  id: string
  name: string
  fee: number
  is_active: boolean
}

export type OnlineOrderStatus = 'new' | 'confirmed' | 'preparing' | 'out_for_delivery' | 'ready' | 'delivered' | 'cancelled'

/** An order placed on the shop's online store (WEB-00001). */
export interface OnlineOrder {
  id: string
  number: number
  reference: string
  status: OnlineOrderStatus
  status_label: string
  next: { value: OnlineOrderStatus, label: string }[]
  customer_id: string | null
  customer_name: string
  customer_phone: string
  fulfilment: 'pickup' | 'delivery'
  zone_name: string | null
  payment: 'cod' | 'transfer'
  has_proof: boolean
  subtotal: number
  delivery_fee: number
  total: number
  items_count: number | null
  sale_id: string | null
  sale_reference: string | null
  token: string
  created_at: string
}

export interface OnlineOrderDetail extends OnlineOrder {
  branch_id: string | null
  address: string | null
  notes: string | null
  consent: boolean | null
  cancel_reason: string | null
  fee_collected: boolean
  track_url: string | null
  items: { id: string, product_id: string, variant_id: string, name: string, qty: number, unit_price: number, line_total: number }[]
  timeline: { status: OnlineOrderStatus, label: string, note: string | null, user_name: string | null, at: string }[]
}

/** A product photo: WebP at 320 / 800 / 1600 px wide (URL paths on the API's origin). */
export interface ProductImage {
  id: string
  width: number
  height: number
  urls: Record<string, string>
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
  type: 'opening' | 'purchase' | 'payment' | 'purchase_return' | 'return_note' | 'refund'
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

/** GET /pos/catalog: the branch's sellable items kept on the device for selling offline. */
export interface PosCatalogItem extends Omit<PosItem, 'exact_barcode'> {
  /** IMEIs / serials in stock here, for products that track them */
  serials: string[] | null
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
  /** made offline on this device and not on the server yet (no number / public link) */
  offline?: boolean
  items_count?: number
  items?: SaleLine[]
  payments?: { method: string, method_label: string, amount: number, reference: string | null }[]
  returns?: { id: string, reference: string, total: number, refund_method_label: string, reason: string | null, created_by_name: string | null, created_at: string }[]
}

/** What the shop prints on its receipts (/settings/shop). */
export interface ReceiptSettings {
  tax_number: string | null
  commercial_register: string | null
  footer: string
  /** printed unless switched off (older settings have no value: on) */
  show_cashier?: boolean
  show_customer?: boolean
  show_serials?: boolean
  /** thermal paper width in mm */
  paper?: '80' | '58'
}

/** The shop's lines at the top / bottom of a receipt. */
export interface ReceiptShop {
  name: string
  phone: string | null
  address?: string | null
  receipt?: ReceiptSettings
}

/** What a printed / public receipt shows. */
export interface ReceiptData {
  shop: ReceiptShop | null
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
  /** wallets / airtime profit, apart from goods: only with reports.profit and the Services module */
  services: { today: number, today_operations: number, period: number } | null
}

export type ServiceOperation = 'deposit' | 'withdraw' | 'topup'
export type ServiceTransactionType = ServiceOperation | 'opening' | 'fund' | 'cash_out'

export interface ServiceFeeRule {
  /** basis points: 150 = 1.5% */
  percent: number
  fixed: number
  min: number
  max: number | null
  round_to: number
}

export interface ServiceAccount {
  id: string
  branch_id: string
  kind: 'wallet' | 'airtime'
  kind_label: string
  provider: string
  provider_label: string
  name: string
  phone: string | null
  balance: number
  /** what the balance cost (airtime bought at a discount); null without services.settings / reports.profit */
  cost_value: number | null
  daily_limit: number | null
  today_used: number
  withdraw_fee_mode: 'cash' | 'wallet'
  is_active: boolean
  operations: ServiceOperation[]
  fees: Partial<Record<ServiceOperation, ServiceFeeRule>>
}

export interface ServiceTransaction {
  id: string
  number: number
  account_id: string
  account_name: string | null
  account_kind: 'wallet' | 'airtime' | null
  provider_label: string | null
  type: ServiceTransactionType
  type_label: string
  /** negative on a reversal */
  amount: number
  fee: number
  suggested_fee: number | null
  balance_change: number
  balance_after: number
  /** into (+) / out of (−) the drawer */
  cash: number
  profit: number | null
  fee_mode: 'cash' | 'wallet' | null
  source: 'drawer' | 'safe' | null
  source_label: string | null
  customer_name: string | null
  customer_phone: string | null
  reference: string | null
  note: string | null
  reverses_id: string | null
  reversed_number: number | null
  reversed: boolean
  user_id: string | null
  user_name: string | null
  created_at: string
}

export interface ServiceOptions {
  kinds: { value: 'wallet' | 'airtime', label: string, providers: { value: string, label: string }[], operations: { value: ServiceOperation, label: string }[] }[]
  types: { value: ServiceTransactionType, label: string }[]
  fee_modes: { value: 'cash' | 'wallet', label: string }[]
  sources: { value: 'drawer' | 'safe', label: string }[]
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
  /** Agreed to having their data kept (Law 151/2020); null = never asked */
  data_consent: boolean | null
  data_consent_at: string | null
  data_consent_by_name: string | null
  /** Personal data erased: name «عميل محذوف», no phone */
  erased_at: string | null
}

export interface CustomerPrivacySettings {
  /** Erase customers with no activity for this many years; null = off */
  retention_years: number | null
  updated_by_name: string | null
  updated_at: string | null
  min_years: number
  max_years: number
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
  type: 'sale' | 'sale_refund' | 'customer_payment' | 'repair' | 'supplier_payment' | 'used_device_purchase' | 'delivery' | 'expense' | 'deposit' | 'withdrawal'
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
  /** «قفل الوردية على العمياني»: expected / difference hidden from this viewer */
  blind?: boolean
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
  track_serial: boolean
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
  /** null once the customer's data was erased */
  customer_phone: string | null
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
  /** sent to a partner shop for repair */
  outsourced: { order_id: string, reference: string, shop: string, status: string, active: boolean, cost: number | null } | null
  /** taken in from a partner shop's repair order */
  partner: { order_id: string, reference: string } | null
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
  parts?: { id: number, variant_id: string, name: string, qty: number, unit_price: number, line_total: number, serials: string[] | null, added_by_name: string | null }[]
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

export interface SubscriptionInfo {
  status: 'trialing' | 'active' | 'past_due' | 'restricted' | 'suspended'
  status_label: string
  paid_up: boolean
  on_trial: boolean
  plan: string | null
  plan_name: string | null
  cycle: 'monthly' | 'yearly' | null
  modules: { key: string, name: string }[]
  paid_until: string
  days_left: number
  suspended_reason: string | null
  /** in a free beta period granted by the Muhasebi team */
  beta?: boolean
  beta_until?: string | null
  monthly_value: number
}

export interface BillingPlan {
  key: string
  name: string
  description: string
  monthly: number
  yearly: number
  featured: boolean
  modules: { key: string, name: string }[]
}

export interface PaymentRequestInfo {
  id: string
  tenant_id: string
  plan: string
  plan_name: string
  cycle: 'monthly' | 'yearly'
  modules: { key: string, name: string }[]
  amount: number
  method: string
  reference: string
  sender_name: string | null
  sender_phone: string | null
  has_proof: boolean
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  requested_by_name: string | null
  reviewed_by_name: string | null
  reviewed_at: string | null
  rejection_reason: string | null
  invoice_id: string | null
  created_at: string
}

export interface BillingInvoiceInfo {
  id: string
  reference: string
  plan: string
  plan_name: string
  cycle: 'monthly' | 'yearly'
  months: number
  lines: { description: string, amount: number }[]
  total: number
  vat: number
  net: number
  period_start: string
  period_end: string
  method: string
  method_label: string
  payment_reference: string | null
  issued_by_name: string | null
  note: string | null
  paid_at: string
}

export interface BillingOverview {
  subscription: SubscriptionInfo
  plans: BillingPlan[]
  extra_modules: { key: string, name: string, monthly: number }[]
  yearly_months: number
  instapay: { address: string, name: string, phone: string }
  requests: PaymentRequestInfo[]
  invoices: BillingInvoiceInfo[]
}

/** The bell: what partner shops did, as far as this user may see. */
export interface AppNotification {
  id: string
  type: string
  title: string
  body: string | null
  icon: string | null
  /** web route to open */
  to: string | null
  read: boolean
  created_at: string
}

// Used devices (المستعمل). Prices are piasters.
export type UsedDeviceGrade = 'A' | 'B' | 'C'
export type UsedDeviceStatus = 'in_stock' | 'sold' | 'gone'
export type UsedDevicePaymentMethod = 'cash' | 'wallet' | 'instapay' | 'bank'
export type CheckValue = 'yes' | 'no' | 'na'

export interface UsedDeviceSeller {
  id: string
  name: string
  phone: string | null
  national_id: string | null
  birth_date: string | null
  age?: number | null
  gender: 'male' | 'female' | null
  governorate: string | null
  erased: boolean
  id_purged: boolean
}

export interface UsedDevice {
  id: string
  reference: string
  branch_id: string
  title: string
  device_model_id: number | null
  model_name: string
  storage: string | null
  color: string | null
  imei: string
  imei2: string | null
  grade: UsedDeviceGrade
  grade_label: string
  battery_health: number | null
  notes: string | null
  asking_price: number
  /** only with products.view_cost */
  purchase_price: number | null
  payment_method: UsedDevicePaymentMethod
  payment_method_label: string
  variant_id: string
  status: UsedDeviceStatus
  status_label: string
  sale: { id: string, reference: string | null, price: number | null, sold_at: string | null } | null
  sold_at: string | null
  /** only with products.view_cost */
  profit: number | null
  days_in_stock: number
  bought_at: string
  bought_by_name: string | null
  /** only with used_devices.view_seller */
  seller: UsedDeviceSeller | null
  seller_hidden: boolean
  checklist?: { key: string, label: string, required: boolean, value: CheckValue }[]
  photos?: { id: number, kind: 'id_front' | 'id_back' | 'device' }[]
}

export interface UsedDeviceSummary {
  in_stock: number
  stock_asking: number
  stock_cost: number | null
  bought_this_month: number
  sold_this_month: number
}

export interface UsedDeviceOptions {
  grades: { value: UsedDeviceGrade, label: string }[]
  checklist: { key: string, label: string, required: boolean }[]
  payment_methods: { value: UsedDevicePaymentMethod, label: string }[]
  max_photo_kb: number
  max_device_photos: number
  can_view_seller: boolean
}

export interface ImeiCheck {
  imei: string
  valid: boolean
  in_stock: boolean
  sold_before: boolean
  events: { type: string, type_label: string, ref_type: string | null, ref_id: string | null, note: string | null, user_name: string | null, created_at: string }[]
  previous: { id: string, reference: string, title: string, bought_at: string, status: UsedDeviceStatus }[]
}

export interface NationalIdCheck {
  valid: boolean
  error?: string
  birth_date?: string
  age?: number
  gender?: 'male' | 'female'
  gender_label?: string
  governorate?: string
  known?: boolean
  devices_count?: number
  seller?: { id: string, name: string, phone: string | null } | null
}

export interface UsedDeviceSellerSummary extends UsedDeviceSeller {
  erased_at: string | null
  devices_count: number
  last_sold_at: string | null
  devices?: UsedDevice[]
}

export interface UsedDeviceSettings {
  id_retention_years: number
  updated_by_name: string | null
  updated_at: string | null
  min_years: number
  max_years: number
}

/** One dated installment of a plan (piasters). */
export interface InstallmentItem {
  id: number
  seq: number
  due_on: string
  amount: number
  paid: number
  remaining: number
  paid_at: string | null
  days_late: number
}

export type InstallmentStatus = 'active' | 'completed' | 'cancelled'

export interface InstallmentPlan {
  id: string
  reference: string
  branch_id: string
  customer_id: string
  customer_name: string
  customer_phone: string | null
  sale_id: string | null
  sale_reference: string | null
  principal: number
  markup: number
  /** basis points a month it was worked out from */
  markup_rate: number | null
  total: number
  paid: number
  remaining: number
  count: number
  interval_months: number
  first_due_on: string
  guarantor_name: string | null
  guarantor_phone: string | null
  notes: string | null
  status: InstallmentStatus
  status_label: string
  created_by_name: string | null
  created_at: string
  completed_at: string | null
  cancelled_at: string | null
  cancelled_by_name: string | null
  paid_count?: number
  next_due?: InstallmentItem | null
  late_amount?: number
  late_count?: number
  items?: InstallmentItem[]
  payments?: { id: string, amount: number, method: CashMethod | null, source: 'counter' | 'account', user_name: string | null, created_at: string }[]
}

/** An open installment on the collection list. */
export interface InstallmentDue extends InstallmentItem {
  plan_id: string
  plan_reference: string
  plan_remaining: number
  customer_id: string
  customer_name: string
  customer_phone: string | null
}

export interface InstallmentSummary {
  active: number
  outstanding: number
  late_amount: number
  late_plans: number
  due_today: number
  collected_month: number
}

/** The owner app's «النهارده» (piasters; Cairo day). */
export interface OwnerToday {
  as_of: string
  sales: { net: number, invoices: number, average: number, profit: number | null, discount: number, same_time_yesterday: number, last_week_day: number }
  by_hour: { hour: number, today: number | null, yesterday: number }[]
  returns: { count: number, amount: number }
  expenses: number
  open_shifts: { id: string, reference: string, user_name: string, branch: string | null, opened_at: string, expected_cash: number }[]
  top_items: { name: string, qty: number, amount: number }[]
  low_stock: number
  repairs: { ready: number, in_progress: number, received_today: number, delivered_today: number } | null
  installments: { late_amount: number, due_today: number } | null
}

/** One line of «اللي بيحصل». */
export interface OwnerFeedItem {
  id: string
  kind: 'sale' | 'return' | 'expense' | 'withdrawal' | 'deposit' | 'shift_opened' | 'shift_closed' | 'price'
  at: string
  title: string
  icon: string
  tone: 'neutral' | 'warning' | 'error' | 'success'
  amount: number | null
  discount: number | null
  user_name: string | null
  branch: string | null
  to: string | null
}

/** A cashier's request for the owner's / a manager's OK. */
export interface Passkey {
  id: string
  credential_id: string
  name: string
  created_at: string
  last_used_at: string | null
}

export interface ApprovalRequest {
  id: string
  kind: 'discount' | 'below_cost' | 'return' | 'withdrawal'
  kind_label: string
  summary: string
  amount: number
  branch_id: string | null
  requested_by_name: string
  status: 'pending' | 'approved' | 'denied' | 'expired'
  via: 'app' | 'pin' | null
  decided_by_name: string | null
  decided_at: string | null
  reason: string | null
  used: boolean
  expires_at: string
  created_at: string
}

/** What the API answers (409 approval_required) when an action needs an OK. */
export interface ApprovalNeeded {
  kind: ApprovalRequest['kind']
  kind_label: string
  summary: string
  amount: number
  token: string
}

export type TransferStatus = 'requested' | 'shipped' | 'received' | 'cancelled'

/** Goods moving between two of the shop's branches (TR-00001). */
export interface StockTransfer {
  id: string
  number: number
  reference: string
  status: TransferStatus
  status_label: string
  from: { id: string, name: string }
  to: { id: string, name: string }
  units_requested: number
  units_shipped: number
  units_received: number
  /** what was shipped cost (only with products.view_cost) */
  value: number | null
  can_ship: boolean
  can_receive: boolean
  notes: string | null
  requested_by_name: string | null
  created_at: string
  shipped_by_name: string | null
  shipped_at: string | null
  received_by_name: string | null
  received_at: string | null
  cancel_reason: string | null
  items?: StockTransferItem[]
}

export interface StockTransferItem {
  id: string
  variant_id: string
  name: string
  track_serial: boolean
  qty_requested: number
  qty_shipped: number
  qty_received: number
  unit_cost: number | null
  serials: string[]
  received_serials: string[]
}

/** A variant to add to a transfer, with what the sending branch has. */
export interface TransferVariant {
  id: string
  display_name: string
  barcode: string | null
  category: { id: number, name: string }
  track_serial: boolean
  qty: number
  serials: string[]
  exact_barcode: boolean
}

export type ImportContactType = 'supplier' | 'agent' | 'shipping' | 'customs'

/** Someone an importer deals with (EGP balance: > 0 = the shop owes them). */
export interface ImportContact {
  id: string
  type: ImportContactType
  type_label: string
  name: string
  country: string | null
  city: string | null
  phone: string | null
  wechat: string | null
  whatsapp: string | null
  notes: string | null
  balance: number
  is_active: boolean
}

export interface ImportStatementLine {
  id: number
  type: 'shipment' | 'cost' | 'payment' | 'claim' | 'reversal'
  type_label: string
  amount: number
  balance_after: number
  ref_type: string | null
  ref_id: string | null
  note: string | null
  user_name: string | null
  created_at: string
}

export interface ImportPayment {
  id: string
  contact_id: string
  shipment_id: string | null
  amount: number
  method: 'bank' | 'exchange' | 'agent' | 'cash' | 'wallet'
  method_label: string
  paid_on: string
  received_by: string | null
  reference: string | null
  has_proof: boolean
  note: string | null
  user_name: string | null
  reversed: boolean
}

export type ImportShipmentStatus = 'ordered' | 'shipped' | 'customs' | 'arrived' | 'received' | 'cancelled'

export interface ImportShipment {
  id: string
  number: number
  reference: string
  status: ImportShipmentStatus
  status_label: string
  contact: { id: string, name: string | null }
  branch: { id: string, name: string | null }
  ordered_on: string
  expected_on: string | null
  late: boolean
  allocation: 'value' | 'qty'
  original_amount: string | null
  goods_total: number
  costs_total: number
  total: number
  notes: string | null
  received_at: string | null
  received_by_name: string | null
  cancel_reason: string | null
  created_at: string
}

export interface ImportShipmentDetail extends ImportShipment {
  items: { id: string, variant_id: string, name: string, track_serial: boolean, qty: number, unit_price: number, line_total: number, received_qty: number, damaged_qty: number, landed_unit_cost: number, serials: string[] }[]
  costs: { id: string, kind: string, kind_label: string, contact: { id: string, name: string | null } | null, amount: number, note: string | null }[]
  payments: ImportPayment[]
  paid: number
  attachments: { id: string, kind: string, kind_label: string, name: string, mime: string, size: number, uploaded_by_name: string | null, created_at: string }[]
}

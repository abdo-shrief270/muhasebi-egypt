// Hand-written for now; will be generated from the API's OpenAPI spec (Scramble) into packages/api-types.

export type ModuleState = 'not_entitled' | 'trial' | 'enabled' | 'disabled' | 'read_only'

export interface MenuEntry {
  to: string
  label: string
  icon: string
  permission: string | null
  module: string
}

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

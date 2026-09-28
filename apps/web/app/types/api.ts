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
}

export interface Session {
  user: SessionUser
  tenant: { id: string, name: string, phone: string, shop_type: string, shop_type_label: string }
  branches: { id: string, name: string, phone: string | null, is_main: boolean }[]
  enabled_modules: string[]
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

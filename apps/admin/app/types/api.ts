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

export interface AdminShop {
  id: string
  name: string
  code: string
  phone: string
  types: string
  owner_name: string | null
  owner_phone: string | null
  users: number
  branches: number
  created_at: string
}

export type AdminPayment = PaymentRequestInfo & { shop: AdminShop | null }

export interface AdminOverview {
  counts: Record<SubscriptionInfo['status'], number>
  shops: number
  pending_payments: number
  expiring_soon: number
  mrr: number
  collected_this_month: number
  plans: BillingPlan[]
  extra_modules: { key: string, name: string, monthly: number }[]
}

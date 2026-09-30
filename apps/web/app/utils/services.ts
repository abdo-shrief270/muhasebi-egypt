import type { ServiceFeeRule, ServiceOperation } from '~/types/api'

/**
 * The suggested fee for an amount (piasters). Mirrors `FeeRule::feeFor()` in the API
 * (apps/api/app/Modules/Services/Support/FeeRule.php): change both together.
 */
export function serviceFee(rule: ServiceFeeRule | null | undefined, amount: number): number {
  if (!rule || amount <= 0) {
    return 0
  }
  let fee = Math.floor((amount * rule.percent + 5000) / 10000) + rule.fixed
  if (rule.round_to > 1 && fee % rule.round_to !== 0) {
    fee = (Math.floor(fee / rule.round_to) + 1) * rule.round_to
  }
  fee = Math.max(fee, rule.min)
  return rule.max !== null ? Math.min(fee, rule.max) : fee
}

export const SERVICE_OPERATIONS: { value: ServiceOperation, label: string, icon: string, hint: string }[] = [
  { value: 'deposit', label: 'إيداع', icon: 'i-lucide-send', hint: 'العميل يدفع كاش وإنت تحوّل له' },
  { value: 'withdraw', label: 'سحب', icon: 'i-lucide-hand-coins', hint: 'العميل يحوّل لك وياخد كاش' },
  { value: 'topup', label: 'شحن رصيد', icon: 'i-lucide-smartphone-charging', hint: 'رصيد على خط العميل' },
]

export const SERVICE_TYPE_LABELS: Record<string, string> = {
  opening: 'رصيد افتتاحي',
  deposit: 'إيداع',
  withdraw: 'سحب',
  topup: 'شحن رصيد',
  fund: 'تمويل',
  cash_out: 'تسييل',
}

/** SV-00012 */
export function serviceReference(number: number): string {
  return `SV-${String(number).padStart(5, '0')}`
}

/** A provider's brand colour class for the account chips (tokens only). */
export function providerTone(provider: string): string {
  return ({
    vodafone: 'bg-(--app-provider-vodafone)',
    etisalat: 'bg-(--app-provider-etisalat)',
    orange: 'bg-(--app-provider-orange)',
    we: 'bg-(--app-provider-we)',
    instapay: 'bg-(--app-provider-instapay)',
  } as Record<string, string>)[provider] ?? 'bg-(--ui-text-muted)'
}

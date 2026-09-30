<template>
  <UModal v-model:open="open" :title="account ? `تعديل «${account.name}»` : 'إضافة محفظة / رصيد'" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <form id="service-account-form" class="space-y-5" @submit.prevent="save">
        <div v-if="!account" class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="النوع">
          <button
            v-for="k in options?.kinds ?? []"
            :key="k.value"
            type="button"
            role="radio"
            :aria-checked="form.kind === k.value"
            class="flex items-center gap-2 rounded-(--ui-radius) border p-3 text-start"
            :class="form.kind === k.value ? 'border-primary bg-(--app-primary-soft) text-(--app-primary-strong)' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
            @click="pickKind(k.value)"
          >
            <UIcon :name="k.value === 'wallet' ? 'i-lucide-wallet' : 'i-lucide-smartphone-charging'" class="size-5" />
            <span>
              <span class="block font-bold">{{ k.label }}</span>
              <span class="block text-xs opacity-80">{{ k.value === 'wallet' ? 'فودافون كاش، InstaPay…: إيداع وسحب' : 'شحن فكة على خطوط العملاء' }}</span>
            </span>
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="الشركة" required>
            <USelect v-model="form.provider" :items="providers" class="w-full" @update:model-value="suggestName" />
          </UFormField>
          <UFormField label="الاسم" hint="اللي هيظهر في شاشة الخدمة" required>
            <UInput v-model="form.name" class="w-full" @update:model-value="nameTouched = true" />
          </UFormField>
          <UFormField :label="form.kind === 'wallet' ? 'رقم المحفظة' : 'رقم الخط'">
            <UInput v-model="form.phone" type="tel" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="الحد اليومي" hint="بالجنيه — تنبيه بس، مش منع">
            <UInput v-model="form.dailyLimit" type="number" min="0" step="any" dir="ltr" class="w-full" placeholder="من غير حد" />
          </UFormField>
          <template v-if="!account">
            <UFormField label="الرصيد الحالي" hint="بالجنيه">
              <UInput v-model="form.opening" type="number" min="0" step="any" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField v-if="form.kind === 'airtime'" label="اتدفع فيه" hint="لو اشتريته بخصم من الموزع">
              <UInput v-model="form.openingCost" type="number" min="0" step="any" dir="ltr" class="w-full" :placeholder="form.opening || 'نفس الرصيد'" />
            </UFormField>
          </template>
        </div>

        <UFormField v-if="form.kind === 'wallet'" label="عمولة السحب" description="لما العميل يسحب كاش من محفظتك">
          <URadioGroup v-model="form.withdrawFeeMode" :items="options?.fee_modes ?? []" />
        </UFormField>

        <div class="space-y-3">
          <p class="font-bold">
            العمولة المقترحة
          </p>
          <div v-for="op in kindOperations" :key="op.value" class="rounded-(--ui-radius) border border-(--ui-border) p-3">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
              <p class="font-bold">
                {{ op.label }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                مثال: على <span class="num">1,000 ج</span> = <span class="font-bold text-(--ui-text) num">{{ formatMoney(serviceFee(ruleOf(op.value), 100000)) }}</span>
                · على <span class="num">100 ج</span> = <span class="font-bold text-(--ui-text) num">{{ formatMoney(serviceFee(ruleOf(op.value), 10000)) }}</span>
              </p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
              <UFormField label="نسبة %">
                <UInput v-model="fees[op.value]!.percent" type="number" min="0" max="100" step="any" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="+ ثابت (ج)">
                <UInput v-model="fees[op.value]!.fixed" type="number" min="0" step="any" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="أقل (ج)">
                <UInput v-model="fees[op.value]!.min" type="number" min="0" step="any" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="أقصى (ج)">
                <UInput v-model="fees[op.value]!.max" type="number" min="0" step="any" dir="ltr" class="w-full" placeholder="—" />
              </UFormField>
              <UFormField label="تقريب لفوق">
                <USelect v-model="fees[op.value]!.roundTo" :items="roundings" class="w-full" />
              </UFormField>
            </div>
          </div>
        </div>

        <UCheckbox v-if="account" v-model="form.isActive" label="شغّالة" description="المحفظة المتوقفة ما بتظهرش في شاشة الخدمة" />
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="service-account-form" icon="i-lucide-check" :label="account ? 'حفظ' : 'إضافة'" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ServiceAccount, ServiceFeeRule, ServiceOperation, ServiceOptions } from '~/types/api'

const props = defineProps<{ account: ServiceAccount | null, options: ServiceOptions | null | undefined }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [ServiceAccount] }>()

const api = useApi()
const toast = useToast()

interface FeeForm { percent: string, fixed: string, min: string, max: string, roundTo: string }
const form = reactive({ kind: 'wallet' as 'wallet' | 'airtime', provider: 'vodafone', name: '', phone: '', dailyLimit: '', opening: '', openingCost: '', withdrawFeeMode: 'cash' as 'cash' | 'wallet', isActive: true })
const fees = reactive<Partial<Record<ServiceOperation, FeeForm>>>({})
const nameTouched = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

const roundings = [
  { label: 'من غير', value: '0' },
  { label: 'نص جنيه', value: '50' },
  { label: 'جنيه', value: '100' },
  { label: '5 جنيه', value: '500' },
  { label: '10 جنيه', value: '1000' },
]

const kind = computed(() => props.options?.kinds.find(k => k.value === form.kind))
const providers = computed(() => kind.value?.providers ?? [])
const kindOperations = computed(() => kind.value?.operations ?? [])

const pounds = (p: number | null | undefined) => (p === null || p === undefined ? '' : String(p / 100))
function feeForm(rule: ServiceFeeRule | undefined): FeeForm {
  return {
    percent: rule?.percent ? String(rule.percent / 100) : '',
    fixed: rule?.fixed ? pounds(rule.fixed) : '',
    min: rule?.min ? pounds(rule.min) : '',
    max: pounds(rule?.max),
    roundTo: String(rule?.round_to ?? 0),
  }
}
function resetFees() {
  for (const key of Object.keys(fees) as ServiceOperation[]) {
    delete fees[key]
  }
  for (const op of kindOperations.value) {
    fees[op.value] = feeForm(props.account?.fees[op.value])
  }
}

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }
  const a = props.account
  Object.assign(form, {
    kind: a?.kind ?? 'wallet',
    provider: a?.provider ?? 'vodafone',
    name: a?.name ?? '',
    phone: a?.phone ?? '',
    dailyLimit: pounds(a?.daily_limit),
    opening: '',
    openingCost: '',
    withdrawFeeMode: a?.withdraw_fee_mode ?? 'cash',
    isActive: a?.is_active ?? true,
  })
  nameTouched.value = !!a
  error.value = null
  resetFees()
  if (!a) {
    suggestName()
  }
})

function pickKind(value: 'wallet' | 'airtime') {
  form.kind = value
  if (!providers.value.some(p => p.value === form.provider)) {
    form.provider = providers.value[0]?.value ?? 'other'
  }
  resetFees()
  suggestName()
}
function suggestName() {
  if (!nameTouched.value) {
    form.name = providers.value.find(p => p.value === form.provider)?.label ?? ''
  }
}

/** The rule as the API stores it (percent in basis points, money in piasters). */
function ruleOf(op: ServiceOperation): ServiceFeeRule {
  const f = fees[op]
  const pct = Number(f?.percent || 0)
  return {
    percent: Number.isFinite(pct) ? Math.round(pct * 100) : 0,
    fixed: toPiasters(f?.fixed) ?? 0,
    min: toPiasters(f?.min) ?? 0,
    max: toPiasters(f?.max),
    round_to: Number(f?.roundTo || 0),
  }
}

async function save() {
  saving.value = true
  error.value = null
  const body: Record<string, unknown> = {
    provider: form.provider,
    name: form.name.trim(),
    phone: form.phone.trim() || null,
    daily_limit: toPiasters(form.dailyLimit),
    withdraw_fee_mode: form.withdrawFeeMode,
    fees: Object.fromEntries(kindOperations.value.map(op => [op.value, ruleOf(op.value)])),
  }
  if (props.account) {
    body.is_active = form.isActive
  }
  else {
    body.kind = form.kind
    body.opening_balance = toPiasters(form.opening) ?? 0
    body.opening_cost = form.kind === 'airtime' ? toPiasters(form.openingCost) : null
  }
  try {
    const res = await api<{ data: ServiceAccount }>(props.account ? `/services/accounts/${props.account.id}` : '/services/accounts', { method: props.account ? 'PATCH' : 'POST', body })
    toast.add({ color: 'success', title: props.account ? 'اتحفظت التعديلات' : `اتضافت «${res.data.name}»` })
    open.value = false
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

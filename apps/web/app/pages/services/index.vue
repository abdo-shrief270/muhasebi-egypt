<template>
  <div class="space-y-4">
    <PageHeader title="الشحن والتحويلات" :description="`محافظ وأرصدة فرع «${store.currentBranch?.name ?? ''}».`" />
    <ServicesTabs />

    <UAlert
      v-if="noShift"
      color="warning"
      variant="subtle"
      icon="i-lucide-lock"
      title="مفيش وردية مفتوحة"
      description="الإيداع والسحب والشحن فلوسهم بتدخل وتطلع من درجك، فلازم تفتح وردية الأول."
      :actions="[{ label: 'افتح وردية', to: '/cash', color: 'warning' }]"
    />

    <div v-if="status !== 'pending' && !accounts.length" class="app-card p-8 text-center">
      <UIcon name="i-lucide-wallet-cards" class="mx-auto size-10 text-(--ui-text-muted)" />
      <p class="mt-2 font-bold">
        لسه مفيش محافظ أو أرصدة في الفرع ده
      </p>
      <p class="text-sm text-(--ui-text-muted)">
        ضيف محفظة فودافون كاش أو خط رصيد برصيده الحالي، وحدد العمولة، وابدأ.
      </p>
      <UButton v-if="store.can('services.settings')" to="/services/accounts?new=1" icon="i-lucide-plus" label="إضافة محفظة / رصيد" class="mt-4" />
    </div>

    <div v-else-if="accounts.length" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
      <form class="space-y-4" @submit.prevent="save">
        <!-- Account -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="المحفظة / الخط">
          <button
            v-for="(a, i) in accounts"
            :key="a.id"
            type="button"
            role="radio"
            :aria-checked="a.id === accountId"
            class="app-card relative flex min-w-0 flex-col items-start gap-0.5 p-3 text-start transition"
            :class="a.id === accountId ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-(--ui-border-accented)'"
            @click="pickAccount(a.id)"
          >
            <span class="flex w-full items-center gap-1.5">
              <span class="size-2.5 shrink-0 rounded-full" :class="providerTone(a.provider)" aria-hidden="true" />
              <span class="truncate text-sm font-bold">{{ a.name }}</span>
              <UKbd v-if="i < 9" :value="`Alt ${i + 1}`" size="sm" class="ms-auto hidden shrink-0 lg:inline-flex" />
            </span>
            <span class="text-lg font-extrabold num">{{ formatMoney(a.balance) }}</span>
            <span class="text-xs text-(--ui-text-muted)">{{ a.name === a.provider_label ? a.kind_label : a.provider_label }}</span>
          </button>
        </div>

        <UCard v-if="account">
          <div class="space-y-4">
            <!-- Operation -->
            <div class="grid gap-2" :class="operations.length > 1 ? 'grid-cols-2' : 'grid-cols-1'" role="radiogroup" aria-label="العملية">
              <button
                v-for="op in operations"
                :key="op.value"
                type="button"
                role="radio"
                :aria-checked="op.value === operation"
                class="flex items-center gap-2 rounded-(--ui-radius) border p-3 text-start transition"
                :class="op.value === operation ? 'border-primary bg-(--app-primary-soft) text-(--app-primary-strong)' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
                @click="pickOperation(op.value)"
              >
                <UIcon :name="op.icon" class="size-5 shrink-0" />
                <span class="min-w-0">
                  <span class="block font-bold">{{ op.label }}</span>
                  <span class="block truncate text-xs opacity-80">{{ op.hint }}</span>
                </span>
                <UKbd :value="`Alt ${op.key}`" size="sm" class="ms-auto hidden shrink-0 lg:inline-flex" />
              </button>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <UFormField :label="operation === 'topup' ? 'قيمة الشحن' : 'المبلغ'" hint="بالجنيه" required>
                <UInput
                  ref="amountInput"
                  v-model="amount"
                  type="number"
                  min="0"
                  step="any"
                  inputmode="decimal"
                  dir="ltr"
                  size="xl"
                  class="w-full"
                  :ui="{ base: 'text-2xl font-extrabold num' }"
                  autofocus
                />
              </UFormField>
              <UFormField label="العمولة" :hint="canEditFee ? (feeTouched ? `المقترحة ${formatMoney(suggestedFee)}` : 'مقترحة، تقدر تغيّرها') : 'حسب إعدادات المحفظة'">
                <UInput
                  v-model="feeText"
                  type="number"
                  min="0"
                  step="any"
                  inputmode="decimal"
                  dir="ltr"
                  size="xl"
                  class="w-full"
                  :readonly="!canEditFee"
                  :tabindex="canEditFee ? undefined : -1"
                  :ui="{ base: 'text-2xl font-bold num' }"
                  @update:model-value="feeTouched = true"
                >
                  <template v-if="feeTouched" #trailing>
                    <UButton size="xs" color="neutral" variant="link" icon="i-lucide-rotate-ccw" aria-label="رجّع المقترحة" @click="resetFee" />
                  </template>
                </UInput>
              </UFormField>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <UFormField :label="operation === 'topup' ? 'رقم الخط' : 'رقم محفظة العميل'">
                <UInput v-model="phone" type="tel" inputmode="tel" dir="ltr" class="w-full" placeholder="01xxxxxxxxx" />
              </UFormField>
              <UFormField label="اسم العميل">
                <UInput v-model="customerName" class="w-full" />
              </UFormField>
              <UFormField v-if="account.kind === 'wallet'" label="مرجع التحويل" hint="رقم العملية من رسالة المحفظة" class="sm:col-span-2">
                <UInput v-model="reference" dir="ltr" class="w-full" />
              </UFormField>
            </div>
          </div>
        </UCard>

        <!-- What to take / hand over -->
        <div v-if="account" class="app-card grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
          <div class="space-y-1">
            <p class="text-sm text-(--ui-text-muted)">
              {{ preview.cash >= 0 ? 'خُد من العميل كاش' : 'ادّي العميل كاش' }}
            </p>
            <p class="text-4xl font-extrabold num" :class="preview.cash < 0 ? 'text-warning' : 'text-(--app-primary-strong)'">
              {{ formatMoney(Math.abs(preview.cash)) }}
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              <template v-if="operation === 'withdraw'">
                العميل يحوّل <span class="font-bold text-(--ui-text) num">{{ formatMoney(preview.balanceChange) }}</span> على {{ account.phone ? localPhone(account.phone) : account.name }} ·
              </template>
              رصيد «{{ account.name }}» بعدها <span class="font-bold num" :class="preview.balanceAfter < 0 ? 'text-error' : 'text-(--ui-text)'">{{ formatMoney(preview.balanceAfter) }}</span>
            </p>
            <p v-if="preview.balanceAfter < 0" class="text-sm font-bold text-error">
              الرصيد مش كفاية — موّل المحفظة الأول.
            </p>
            <p v-else-if="limitPassed" class="text-sm font-bold text-warning">
              هتعدّي الحد اليومي ({{ formatMoney(account.daily_limit) }}): اتحوّل النهارده {{ formatMoney(account.today_used) }}.
            </p>
          </div>
          <UButton type="submit" size="xl" icon="i-lucide-check" :label="`سجّل ${operationLabel}`" :loading="saving" :disabled="!canSave" class="justify-center">
            <template #trailing>
              <UKbd value="Enter" class="hidden lg:inline-flex" />
            </template>
          </UButton>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" class="sm:col-span-2" :actions="errorCode === 'shift_not_open' ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
        </div>
      </form>

      <!-- Last one + today -->
      <aside class="space-y-4">
        <UCard v-if="last">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="flex items-center gap-1.5 font-bold">
                <UIcon name="i-lucide-circle-check" class="size-5 text-success" />
                اتسجّلت {{ serviceReference(last.number) }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                {{ last.type_label }} <span class="num">{{ formatMoney(last.amount) }}</span> · {{ last.account_name }}
              </p>
            </div>
            <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-printer" label="إيصال" @click="print" />
          </div>
          <UAlert v-if="lastWarning" class="mt-3" color="warning" variant="subtle" icon="i-lucide-triangle-alert" :title="lastWarning" />
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex items-center justify-between gap-2">
              <p class="font-bold">
                عملياتك النهارده
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                <span class="num">{{ today.operations }}</span> عملية في الفرع · عمولات <span class="num">{{ formatMoney(today.fees) }}</span>
              </p>
            </div>
          </template>
          <ul class="divide-y divide-(--ui-border)">
            <li v-for="t in recent" :key="t.id" class="flex items-center gap-3 p-3 text-sm">
              <div class="min-w-0 flex-1">
                <p class="truncate font-bold">
                  {{ t.reverses_id ? `إلغاء ${t.type_label}` : t.type_label }} · {{ t.account_name }}
                </p>
                <p class="truncate text-xs text-(--ui-text-muted)">
                  <span class="num">{{ time(t.created_at) }}</span>
                  <template v-if="t.customer_phone">
                    · <span class="num">{{ localPhone(t.customer_phone) }}</span>
                  </template>
                  <UBadge v-if="t.reversed" size="sm" color="neutral" variant="subtle" class="ms-1">
                    اتلغت
                  </UBadge>
                </p>
              </div>
              <span class="shrink-0 font-bold num" :class="t.reverses_id || t.reversed ? 'text-(--ui-text-muted) line-through' : ''">{{ formatMoney(Math.abs(t.amount)) }}</span>
              <UDropdownMenu v-if="!t.reverses_id" :items="rowItems(t)">
                <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" aria-label="خيارات" />
              </UDropdownMenu>
            </li>
            <li v-if="!recent.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
              لسه ما عملتش عمليات النهارده.
            </li>
          </ul>
          <template #footer>
            <UButton to="/services/transactions" variant="link" size="sm" label="كل الحركات" trailing-icon="i-lucide-arrow-left" />
          </template>
        </UCard>
      </aside>
    </div>

    <ServicesReverseModal v-model:open="reverseOpen" :t="reversing" @reversed="onReversed" />

    <PrintSheet v-if="printing && printed" page-size="80mm auto">
      <ServicesReceipt :t="printed" :shop-name="store.session?.tenant.name ?? ''" :branch-name="store.currentBranch?.name" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { CashShift, ServiceAccount, ServiceOperation, ServiceTransaction } from '~/types/api'

definePageMeta({ permission: 'services.manage' })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const canEditFee = computed(() => store.can('services.fees'))
const branchKey = computed(() => store.session?.current_branch_id ?? '')
const todayIso = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' })

const [{ data: accountsData, status, refresh: refreshAccounts }, { data: recentData, refresh: refreshRecent }, { data: shiftData }] = await Promise.all([
  useAsyncData('services-accounts', () => api<{ data: ServiceAccount[], meta: { today: { operations: number, fees: number } } }>('/services/accounts'), { watch: [branchKey] }),
  useAsyncData('services-recent', () => api<{ data: ServiceTransaction[] }>('/services/transactions', { query: { mine: 1, from: todayIso() } }), { watch: [branchKey] }),
  useAsyncData('services-shift', async () => store.can('cash.shift') ? (await api<{ data: CashShift | null }>('/cash/current')).data : undefined, { watch: [branchKey] }),
])
const accounts = computed(() => accountsData.value?.data ?? [])
const today = computed(() => accountsData.value?.meta.today ?? { operations: 0, fees: 0 })
const recent = computed(() => (recentData.value?.data ?? []).filter(t => t.type !== 'opening').slice(0, 12))
const noShift = computed(() => shiftData.value === null)

// The picked account is remembered per branch on this device.
const ACCOUNT_KEY = computed(() => `muhasebi:services-account:${branchKey.value}`)
const accountId = ref<string | null>(null)
const account = computed(() => accounts.value.find(a => a.id === accountId.value) ?? null)
watch(accounts, (list) => {
  if (!list.some(a => a.id === accountId.value)) {
    let saved: string | null = null
    try {
      saved = localStorage.getItem(ACCOUNT_KEY.value)
    }
    catch {}
    accountId.value = list.find(a => a.id === saved)?.id ?? list[0]?.id ?? null
  }
}, { immediate: true })

const OPERATION_KEYS: Record<ServiceOperation, string> = { deposit: 'D', withdraw: 'S', topup: 'T' }
const operations = computed(() => SERVICE_OPERATIONS
  .filter(op => account.value?.operations.includes(op.value))
  .map(op => ({ ...op, key: OPERATION_KEYS[op.value] })))
const operation = ref<ServiceOperation>('deposit')
watch(account, (a) => {
  if (a && !a.operations.includes(operation.value)) {
    operation.value = a.operations[0] ?? 'deposit'
  }
}, { immediate: true })
const operationLabel = computed(() => SERVICE_OPERATIONS.find(o => o.value === operation.value)?.label ?? '')

const amount = ref('')
const feeText = ref('')
const feeTouched = ref(false)
const phone = ref('')
const customerName = ref('')
const reference = ref('')
const amountInput = ref<{ inputRef?: HTMLInputElement } | null>(null)

const amountP = computed(() => toPiasters(amount.value) ?? 0)
const suggestedFee = computed(() => serviceFee(account.value?.fees[operation.value], amountP.value))
watch([suggestedFee, accountId, operation], () => {
  if (!feeTouched.value) {
    feeText.value = suggestedFee.value ? String(suggestedFee.value / 100) : '0'
  }
}, { immediate: true })
const feeP = computed(() => (feeTouched.value ? (toPiasters(feeText.value) ?? 0) : suggestedFee.value))

/** Same arithmetic as RecordOperationAction. */
const preview = computed(() => {
  const a = account.value
  const amt = amountP.value
  const fee = feeP.value
  if (!a) {
    return { cash: 0, balanceChange: 0, balanceAfter: 0 }
  }
  if (operation.value === 'withdraw') {
    const received = a.withdraw_fee_mode === 'wallet' ? amt + fee : amt
    return { cash: -(a.withdraw_fee_mode === 'wallet' ? amt : amt - fee), balanceChange: received, balanceAfter: a.balance + received }
  }
  return { cash: amt + fee, balanceChange: -amt, balanceAfter: a.balance - amt }
})
const limitPassed = computed(() => {
  const a = account.value
  return !!a?.daily_limit && amountP.value > 0 && a.today_used + amountP.value > a.daily_limit
})
const canSave = computed(() => !!account.value && amountP.value > 0 && preview.value.balanceAfter >= 0 && !saving.value)

function pickAccount(id: string) {
  accountId.value = id
  try {
    localStorage.setItem(ACCOUNT_KEY.value, id)
  }
  catch {}
  focusAmount()
}
function pickOperation(op: ServiceOperation) {
  operation.value = op
  focusAmount()
}
function resetFee() {
  feeTouched.value = false
  feeText.value = String(suggestedFee.value / 100)
}
function focusAmount() {
  nextTick(() => amountInput.value?.inputRef?.focus())
}

const saving = ref(false)
const error = ref<string | null>(null)
const errorCode = ref<string | null>(null)
const last = ref<ServiceTransaction | null>(null)
const lastWarning = ref<string | null>(null)

async function save() {
  if (!canSave.value || !account.value) {
    return
  }
  saving.value = true
  error.value = null
  errorCode.value = null
  try {
    const res = await api<{ data: ServiceTransaction, meta: { warning: string | null } }>('/services/transactions', {
      method: 'POST',
      body: {
        account_id: account.value.id,
        type: operation.value,
        amount: amountP.value,
        // Omitted = the suggested fee; the API checks services.fees for anything else.
        fee: feeTouched.value && canEditFee.value ? feeP.value : undefined,
        customer_phone: phone.value.trim() || null,
        customer_name: customerName.value.trim() || null,
        reference: reference.value.trim() || null,
      },
    })
    last.value = res.data
    lastWarning.value = res.meta.warning
    toast.add({ color: 'success', title: `اتسجّل ${res.data.type_label} ${formatMoney(res.data.amount)}`, description: res.data.cash >= 0 ? `خد ${formatMoney(res.data.cash)} كاش` : `ادّي العميل ${formatMoney(-res.data.cash)} كاش` })
    amount.value = ''
    phone.value = ''
    customerName.value = ''
    reference.value = ''
    feeTouched.value = false
    await Promise.all([refreshAccounts(), refreshRecent()])
    focusAmount()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    errorCode.value = apiErrorCode(e)
  }
  finally {
    saving.value = false
  }
}

// Alt+1…9 picks an account, Alt+D / S / T the operation (by key position, whatever the keyboard language).
function onKey(e: KeyboardEvent) {
  if (!e.altKey || e.ctrlKey || e.metaKey) {
    return
  }
  const digit = /^Digit([1-9])$/.exec(e.code)
  if (digit) {
    const a = accounts.value[Number(digit[1]) - 1]
    if (a) {
      e.preventDefault()
      pickAccount(a.id)
    }
    return
  }
  const op = operations.value.find(o => `Key${o.key}` === e.code)
  if (op) {
    e.preventDefault()
    pickOperation(op.value)
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

const { printing, print: printSheet } = usePrint()
const printed = ref<ServiceTransaction | null>(null)
async function printTx(t: ServiceTransaction) {
  printed.value = t
  await printSheet()
}
function print() {
  if (last.value) {
    printTx(last.value)
  }
}

const reverseOpen = ref(false)
const reversing = ref<ServiceTransaction | null>(null)
function rowItems(t: ServiceTransaction): DropdownMenuItem[] {
  return [
    { label: 'اطبع الإيصال', icon: 'i-lucide-printer', onSelect: () => printTx(t) },
    ...(!t.reversed ? [{ label: 'إلغاء العملية', icon: 'i-lucide-undo-2', color: 'error' as const, onSelect: () => { reversing.value = t; reverseOpen.value = true } }] : []),
  ]
}
async function onReversed() {
  if (last.value?.id === reversing.value?.id) {
    last.value = null
  }
  await Promise.all([refreshAccounts(), refreshRecent()])
}

function time(iso: string) {
  return new Date(iso).toLocaleTimeString('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit' })
}
</script>

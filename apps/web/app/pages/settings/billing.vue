<template>
  <div v-if="billing" class="space-y-6">
    <PageHeader title="الاشتراك والفواتير" description="باقتك، والدفع بـ InstaPay أو من رصيدك، ونقاطك وكود الدعوة، وفواتيرك." />

    <!-- Current subscription -->
    <UCard>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <p class="text-lg font-extrabold">
              {{ sub.plan_name ? `باقة ${sub.plan_name}` : 'تجربة مجانية' }}
            </p>
            <UBadge :color="subscriptionStatusColor(sub.status)" variant="subtle">
              {{ sub.status_label }}
            </UBadge>
            <UBadge v-if="sub.beta" color="info" variant="subtle">
              Beta مجانية
            </UBadge>
            <UBadge v-if="owned" color="warning" variant="soft" icon="i-lucide-crown">
              مدى الحياة · رقم <span class="num ms-1">{{ owned.number }}</span>
            </UBadge>
          </div>
          <p class="text-sm text-(--ui-text-muted)">
            <template v-if="sub.paid_up">
              {{ sub.on_trial ? 'التجربة لحد' : sub.beta ? 'مجاناً لحد' : 'مدفوع لحد' }} <span class="num">{{ formatDate(sub.paid_until) }}</span>
              <template v-if="sub.days_left > 0">
                (فاضل <span class="num">{{ dayCount(sub.days_left) }}</span>)
              </template>
            </template>
            <template v-else>
              خلص <span class="num">{{ formatDate(sub.paid_until) }}</span>
            </template>
            <span v-if="sub.cycle"> · {{ cycleLabel(sub.cycle) }}</span>
          </p>
          <p v-if="owned" class="text-sm text-(--ui-text-muted)">
            <template v-if="owned.remaining">
              دفعت <span class="num">{{ formatMoney(owned.paid) }}</span> من <span class="num">{{ formatMoney(owned.price) }}</span> — باقي <span class="num">{{ formatMoney(owned.remaining) }}</span>،
            </template>
            <template v-else>
              السيستم بتاعك خالص،
            </template>
            وبعدها الصيانة والدعم <span class="num">{{ formatMoney(owned.maintenance) }}</span> بس في السنة.
          </p>
          <p v-if="sub.suspended_reason" class="text-sm text-(--ui-error)">
            {{ sub.suspended_reason }}
          </p>
        </div>
        <div v-if="sub.modules.length" class="flex flex-wrap gap-1.5">
          <UBadge v-for="m in sub.modules" :key="m.key" color="neutral" variant="outline">
            + {{ m.name }}
          </UBadge>
        </div>
      </div>
    </UCard>

    <UAlert
      v-if="pending"
      color="info"
      variant="subtle"
      icon="i-lucide-hourglass"
      :title="`طلب الدفع بتاعك بيتراجع (رقم العملية ${pending.reference})`"
      :description="`${pending.plan_name} · ${cycleLabel(pending.cycle)} · ${formatMoney(pending.amount)} — هنفعّل الاشتراك أول ما نتأكد من التحويل.`"
      :actions="[{ label: 'إلغاء الطلب', color: 'neutral', variant: 'outline', onClick: cancelPending }]"
    />
    <UAlert
      v-else-if="lastRejected"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-x"
      :title="`طلب الدفع (رقم العملية ${lastRejected.reference}) اترفض`"
      :description="lastRejected.rejection_reason ?? undefined"
    />

    <BillingRewards :yearly="billing.cycles.includes('yearly')" :wallet="billing.wallet" :referral="billing.referral" :discounts="billing.discounts" :shop-name="shop.name" @changed="refreshAll" />

    <!-- Choose -->
    <section v-if="!pending" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold">
          {{ sub.on_trial || !sub.paid_up ? 'اختار باقتك' : 'جدّد أو غيّر باقتك' }}
        </h2>
        <UFieldGroup>
          <UButton v-if="!owned" :color="cycle === 'monthly' ? 'primary' : 'neutral'" :variant="cycle === 'monthly' ? 'solid' : 'outline'" label="شهري" @click="cycle = 'monthly'" />
          <UButton v-if="!owned && billing.cycles.includes('yearly')" :color="cycle === 'yearly' ? 'primary' : 'neutral'" :variant="cycle === 'yearly' ? 'solid' : 'outline'" :label="`سنوي (${12 - billing.yearly_months} شهور هدية)`" @click="cycle = 'yearly'" />
          <UButton v-if="owned || offer" icon="i-lucide-crown" :color="cycle === 'lifetime' ? 'warning' : 'neutral'" :variant="cycle === 'lifetime' ? 'solid' : 'outline'" :label="offer?.name ?? 'مدى الحياة'" @click="cycle = 'lifetime'" />
        </UFieldGroup>
      </div>

      <UCard v-if="cycle === 'lifetime' && offer && !owned" class="ring-1 ring-(--ui-warning)/40">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <UIcon name="i-lucide-crown" class="size-5 text-(--ui-warning)" />
            <p class="text-lg font-extrabold">
              {{ offer.name }}
            </p>
            <UBadge v-if="offer.launch" color="warning" variant="subtle">
              خصم الإطلاق <span class="num mx-1">{{ offer.launch.percent }}%</span>
              <template v-if="offer.launch.seats_left !== null">
                · فاضل <span class="num mx-1">{{ offer.launch.seats_left }}</span> محل
              </template>
              <template v-if="offer.launch.ends_at">
                · لحد <span class="num ms-1">{{ formatDate(offer.launch.ends_at) }}</span>
              </template>
            </UBadge>
          </div>
          <p v-if="offer.tagline" class="text-sm text-(--ui-text-muted)">
            {{ offer.tagline }}
          </p>
          <ul v-if="offer.perks.length" class="grid gap-1 text-sm sm:grid-cols-2">
            <li v-for="perk in offer.perks" :key="perk" class="flex items-start gap-1.5">
              <UIcon name="i-lucide-check" class="mt-0.5 size-4 shrink-0 text-(--ui-success)" /> {{ perk }}
            </li>
          </ul>
          <div v-if="offer.installments.length > 1" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold">الدفع:</span>
            <UFieldGroup>
              <UButton
                v-for="n in offer.installments"
                :key="n"
                :color="installments === n ? 'primary' : 'neutral'"
                :variant="installments === n ? 'solid' : 'outline'"
                :label="n === 1 ? 'مرة واحدة' : `${n} أقساط`"
                @click="installments = n"
              />
            </UFieldGroup>
            <span v-if="installments > 1" class="text-xs text-(--ui-text-muted)">قسط كل <span class="num">{{ 12 / installments }}</span> شهور</span>
          </div>
        </div>
      </UCard>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <button
          v-for="plan in shownPlans"
          :key="plan.key"
          type="button"
          class="app-card relative flex flex-col gap-3 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 text-start transition"
          :class="planKey === plan.key ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-(--ui-border-accented)'"
          @click="planKey = plan.key"
        >
          <UBadge v-if="recommended?.plan === plan.key" color="success" class="absolute -top-2.5 start-4">
            الأنسب ليك
          </UBadge>
          <UBadge v-else-if="plan.featured && !recommended" color="primary" class="absolute -top-2.5 start-4">
            الأكثر اختياراً
          </UBadge>
          <div>
            <p class="text-lg font-extrabold">
              {{ plan.name }}
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ plan.description }}
            </p>
          </div>
          <p v-if="cycle === 'lifetime' && owned">
            <span class="num text-2xl font-extrabold">{{ formatMoney(owned.remaining || owned.maintenance) }}</span>
            <span class="text-sm text-(--ui-text-muted)"> {{ owned.remaining ? 'باقي من سعر الشراء' : 'صيانة / سنة' }}</span>
          </p>
          <p v-else-if="cycle === 'lifetime'">
            <span class="num text-2xl font-extrabold">{{ formatMoney(plan.lifetime) }}</span>
            <span class="text-sm text-(--ui-text-muted)"> مرة واحدة</span>
            <span class="block text-xs text-(--ui-text-muted)">
              شامل أول سنة، وبعدها صيانة <span class="num">{{ formatMoney(plan.maintenance) }}</span> / سنة
              (بدل <span class="num">{{ formatMoney(plan.monthly * 12) }}</span> في السنة شهري)
            </span>
          </p>
          <p v-else>
            <span class="num text-2xl font-extrabold">{{ formatMoney(cycle === 'yearly' ? plan.yearly : plan.monthly) }}</span>
            <span class="text-sm text-(--ui-text-muted)"> / {{ cycle === 'yearly' ? 'سنة' : 'شهر' }}</span>
          </p>
          <ul class="space-y-1 text-sm">
            <li class="flex items-center gap-1.5">
              <UIcon name="i-lucide-check" class="size-4 text-(--ui-success)" /> الكاشير والمخزون والتقارير
            </li>
            <li v-for="m in plan.modules" :key="m.key" class="flex items-center gap-1.5">
              <UIcon name="i-lucide-check" class="size-4 text-(--ui-success)" /> {{ m.name }}
            </li>
            <li class="flex items-center gap-1.5">
              <UIcon name="i-lucide-check" class="size-4 text-(--ui-success)" /> ظهور تلقائي في سوق محاسبي
              <UBadge size="sm" color="neutral" variant="subtle" label="قريباً" />
            </li>
          </ul>
        </button>
      </div>

      <UCard v-if="extras.length">
        <p class="mb-3 font-bold">
          أقسام إضافية
        </p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <UCheckbox
            v-for="m in extras"
            :key="m.key"
            :model-value="chosenExtras.includes(m.key)"
            :label="m.name"
            :description="extraPrice(m)"
            @update:model-value="toggleExtra(m.key)"
          />
        </div>
      </UCard>

      <!-- Pay -->
      <div class="grid gap-6 lg:grid-cols-2">
        <UCard>
          <template #header>
            <div class="flex items-center justify-between">
              <p class="font-bold">
                {{ quote && quote.due === 0 ? 'ادفع من رصيدك' : 'ادفع بـ InstaPay' }}
              </p>
              <p v-if="quote" class="num text-2xl font-extrabold">
                {{ formatMoney(quote.due) }}
              </p>
            </div>
          </template>
          <div v-if="quote && quote.due === 0" class="space-y-3 text-sm">
            <p>رصيدك بيغطي الاشتراك كله، والتجديد بيتم على طول من غير تحويل.</p>
            <UButton block size="lg" icon="i-lucide-wallet" :label="`جدّد من الرصيد (${formatMoney(quote.credit_used)})`" :loading="sending" @click="payWithCredit" />
          </div>
          <ol v-else class="list-inside list-decimal space-y-3 text-sm">
            <li>
              حوّل <b class="num">{{ quote ? formatMoney(quote.due) : '—' }}</b> من تطبيق InstaPay على:
              <div v-if="billing.instapay.address" class="mt-2 flex items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
                <span class="num flex-1 font-bold" dir="ltr">{{ billing.instapay.address }}</span>
                <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-copy" aria-label="نسخ" @click="copy(billing.instapay.address)" />
              </div>
              <p v-else class="mt-1 text-(--ui-warning)">
                عنوان InstaPay لسه متحطش — كلّمنا.
              </p>
              <p v-if="billing.instapay.name" class="mt-1 text-(--ui-text-muted)">
                باسم {{ billing.instapay.name }}<template v-if="billing.instapay.phone">
                  · <span class="num" dir="ltr">{{ billing.instapay.phone }}</span>
                </template>
              </p>
            </li>
            <li>اكتب رقم العملية اللي ظهرلك في التطبيق، وارفع صورة التحويل.</li>
            <li>هنراجع التحويل ونفعّل الاشتراك، وتطلعلك فاتورة.</li>
          </ol>
          <div v-if="quote" class="mt-4 space-y-1 border-t border-(--ui-border) pt-3 text-sm">
            <div v-for="(line, i) in quote.lines" :key="i" class="flex justify-between" :class="line.amount < 0 ? 'text-(--ui-success)' : ''">
              <span>{{ line.description }}</span><span class="num">{{ formatMoney(line.amount) }}</span>
            </div>
            <div v-if="quote.credit_used" class="flex justify-between text-(--ui-success)">
              <span>من رصيدك</span><span class="num">−{{ formatMoney(quote.credit_used) }}</span>
            </div>
            <div v-if="quote.discount || quote.credit_used" class="flex justify-between font-bold">
              <span>المطلوب</span><span class="num">{{ formatMoney(quote.due) }}</span>
            </div>
            <p class="text-xs text-(--ui-text-muted)">
              شامل ضريبة القيمة المضافة (<span class="num">{{ formatMoney(quote.vat) }}</span>).
            </p>
          </div>
        </UCard>

        <UCard v-if="!quote || quote.due > 0">
          <form class="space-y-4" @submit.prevent="submit">
            <UFormField label="رقم العملية" required>
              <UInput v-model="form.reference" dir="ltr" class="w-full" placeholder="من رسالة InstaPay" />
            </UFormField>
            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField label="اسم اللي حوّل" hint="اختياري">
                <UInput v-model="form.sender_name" class="w-full" />
              </UFormField>
              <UFormField label="موبايله" hint="اختياري">
                <UInput v-model="form.sender_phone" dir="ltr" inputmode="tel" class="w-full" />
              </UFormField>
            </div>
            <UFormField label="صورة التحويل" hint="صورة أو PDF">
              <div class="flex items-center gap-2">
                <UButton color="neutral" variant="outline" icon="i-lucide-image-up" label="اختار الصورة" @click="fileInput?.click()" />
                <span class="truncate text-sm text-(--ui-text-muted)">{{ proof?.name ?? 'مفيش صورة لسه' }}</span>
                <input ref="fileInput" type="file" accept="image/*,application/pdf" class="hidden" @change="onFile">
              </div>
            </UFormField>
            <UAlert v-if="error" color="error" variant="subtle" :title="error" />
            <UButton type="submit" block size="lg" icon="i-lucide-send" label="بعت طلب التفعيل" :disabled="!planKey || !form.reference.trim()" :loading="sending" />
          </form>
        </UCard>
      </div>
    </section>

    <!-- Invoices -->
    <UCard v-if="billing.invoices.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          الفواتير
        </p>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الفاتورة
              </th>
              <th class="p-3 text-start font-bold">
                الفترة
              </th>
              <th class="p-3 text-start font-bold">
                الدفع
              </th>
              <th class="p-3 text-start font-bold">
                المبلغ
              </th>
              <th class="w-10" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in billing.invoices" :key="inv.id" class="border-t border-(--ui-border)">
              <td class="p-3">
                <p class="num font-bold" dir="ltr">
                  {{ inv.reference }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  {{ inv.plan_name }} · {{ formatDate(inv.paid_at) }}
                </p>
              </td>
              <td class="num p-3">
                {{ formatDate(inv.period_start) }} ← {{ formatDate(inv.period_end) }}
              </td>
              <td class="p-3">
                {{ inv.method_label }}
              </td>
              <td class="num p-3 font-bold">
                {{ formatMoney(inv.total) }}
              </td>
              <td class="p-3">
                <UButton color="neutral" variant="ghost" icon="i-lucide-printer" square aria-label="اطبع الفاتورة" @click="printInvoice(inv)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <PrintSheet v-if="printing && printed" page-size="A4" margin="15mm">
      <BillingInvoiceSheet :invoice="printed" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { BillingCycle, BillingInvoiceInfo, BillingOverview } from '~/types/api'

definePageMeta({ ownerOnly: true })

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const subscription = useSubscription()

const { data, refresh } = await useAsyncData('billing', () => api<{ data: BillingOverview }>('/billing'))
const billing = computed(() => data.value?.data)
const sub = computed(() => billing.value!.subscription)
const pending = computed(() => billing.value?.requests.find(r => r.status === 'pending'))
const lastRejected = computed(() => {
  const latest = billing.value?.requests[0]
  return latest?.status === 'rejected' ? latest : undefined
})
const shop = computed(() => ({ name: store.session?.tenant.name ?? '', phone: store.session?.tenant.phone ?? null }))

// The choice: the current plan (or the featured one), the same cycle.
// A shop that hasn't paid yet starts on what fits what it said it does at sign-up.
const recommended = computed(() => (sub.value?.plan ? null : billing.value?.recommended) ?? null)
const planKey = ref<string>(sub.value?.plan ?? recommended.value?.plan ?? billing.value?.plans.find(p => p.featured)?.key ?? billing.value?.plans[0]?.key ?? '')
const cycle = ref<BillingCycle>(sub.value?.cycle ?? 'monthly')
const chosenExtras = ref<string[]>(sub.value?.plan ? (sub.value.modules ?? []).map(m => m.key) : (recommended.value?.modules ?? []).map(m => m.key))
const plan = computed(() => billing.value?.plans.find(p => p.key === planKey.value))

// «مدى الحياة»: a shop that bought it pays its installments / maintenance on its own plan; others see the option.
const owned = computed(() => sub.value?.lifetime ?? null)
const offer = computed(() => billing.value?.lifetime ?? null)
if (owned.value) {
  cycle.value = 'lifetime'
}
else if (!billing.value?.cycles.includes(cycle.value)) {
  // e.g. a yearly subscriber after yearly was switched off
  cycle.value = 'monthly'
}
const installments = ref<number>(offer.value?.installments[0] ?? 1)
const shownPlans = computed(() => {
  const plans = billing.value?.plans ?? []
  if (cycle.value !== 'lifetime') {
    return plans
  }
  return owned.value ? plans.filter(p => p.key === owned.value!.plan) : plans.filter(p => p.lifetime > 0)
})
watch(cycle, () => {
  if (!shownPlans.value.some(p => p.key === planKey.value)) {
    planKey.value = shownPlans.value.find(p => p.featured)?.key ?? shownPlans.value[0]?.key ?? planKey.value
  }
}, { immediate: true })
function extraPrice(m: { monthly: number, lifetime: number, maintenance: number }) {
  if (cycle.value === 'lifetime') {
    return m.lifetime ? `${formatMoney(m.lifetime)} مرة واحدة + ${formatMoney(m.maintenance)} صيانة / سنة` : 'مش متاح مدى الحياة'
  }
  return `${formatMoney(m.monthly * (cycle.value === 'yearly' ? billing.value!.yearly_months : 1))} / ${cycle.value === 'yearly' ? 'سنة' : 'شهر'}`
}
/** What the request sends: the plan, the cycle, the extras and, for a first lifetime payment, the installments. */
const choice = () => ({
  plan: planKey.value,
  cycle: cycle.value,
  modules: validExtras.value,
  ...(cycle.value === 'lifetime' && !owned.value ? { installments: installments.value } : {}),
})
const extras = computed(() => (billing.value?.extra_modules ?? []).filter(m => !plan.value?.modules.some(pm => pm.key === m.key)))

function toggleExtra(key: string) {
  chosenExtras.value = chosenExtras.value.includes(key) ? chosenExtras.value.filter(k => k !== key) : [...chosenExtras.value, key]
}

interface Quote { total: number, vat: number, price: number, discount: number, credit_used: number, due: number, lines: { description: string, amount: number }[] }
const quote = ref<Quote | null>(null)
const validExtras = computed(() => chosenExtras.value.filter(k => extras.value.some(m => m.key === k)))
watch([planKey, cycle, validExtras, installments], () => requote(), { immediate: true })
async function requote() {
  if (!planKey.value) {
    return
  }
  try {
    quote.value = (await api<{ data: Quote }>('/billing/quote', { method: 'POST', body: choice() })).data
  }
  catch {
    quote.value = null
  }
}

// Credit, points or a coupon changed: the page and the price follow.
async function refreshAll() {
  await refresh()
  await requote()
}

async function payWithCredit() {
  sending.value = true
  try {
    await api('/billing/pay-with-credit', { method: 'POST', body: choice() })
    toast.add({ color: 'success', title: 'اتجدد الاشتراك من رصيدك' })
    await refreshAll()
    await subscription.refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    sending.value = false
  }
}

const form = reactive({ reference: '', sender_name: '', sender_phone: '' })
const proof = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const sending = ref(false)
const error = ref<string | null>(null)

function onFile(e: Event) {
  proof.value = (e.target as HTMLInputElement).files?.[0] ?? null
}

async function submit() {
  sending.value = true
  error.value = null
  try {
    const body = new FormData()
    body.append('plan', planKey.value)
    body.append('cycle', cycle.value)
    const chosen = choice()
    if ('installments' in chosen) {
      body.append('installments', String(chosen.installments))
    }
    validExtras.value.forEach(m => body.append('modules[]', m))
    body.append('reference', form.reference.trim())
    if (form.sender_name) {
      body.append('sender_name', form.sender_name)
    }
    if (form.sender_phone) {
      body.append('sender_phone', form.sender_phone)
    }
    if (proof.value) {
      body.append('proof', proof.value)
    }
    await api('/billing/requests', { method: 'POST', body })
    toast.add({ color: 'success', title: 'اتبعت طلب التفعيل', description: 'هنفعّل الاشتراك أول ما نتأكد من التحويل.' })
    Object.assign(form, { reference: '', sender_name: '', sender_phone: '' })
    proof.value = null
    if (fileInput.value) {
      fileInput.value.value = ''
    }
    await refresh()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    sending.value = false
  }
}

async function cancelPending() {
  if (!pending.value) {
    return
  }
  await api(`/billing/requests/${pending.value.id}/cancel`, { method: 'POST' })
  await refresh()
}

async function copy(text: string) {
  await navigator.clipboard.writeText(text).catch(() => {})
  toast.add({ color: 'success', title: 'اتنسخ' })
}

const { printing, print } = usePrint()
const printed = ref<BillingInvoiceInfo | null>(null)
async function printInvoice(inv: BillingInvoiceInfo) {
  printed.value = inv
  await print()
}

onMounted(() => subscription.refresh())
</script>

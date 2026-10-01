<template>
  <div class="space-y-6">
    <PageHeader title="تقسيط جديد" description="قسّط فاتورة آجل أو حساب عميل على شهور. المبلغ لازم يكون عليه فعلاً (اتباع آجل أو رصيد قديم).">
      <UButton to="/installments" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="التقسيط" />
    </PageHeader>

    <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="save">
      <div class="space-y-6">
        <UCard>
          <template #header>
            <p class="font-bold">
              العميل والمبلغ
            </p>
          </template>
          <div class="space-y-4">
            <UFormField label="العميل" required>
              <PosCustomerPicker v-model="customer" placeholder="دوّر بالاسم أو الموبايل" />
            </UFormField>
            <UAlert
              v-if="customer && available"
              :color="available.available > 0 ? 'neutral' : 'warning'"
              variant="subtle"
              icon="i-lucide-info"
              :title="available.available > 0 ? `ينفع تقسّط لحد ${formatMoney(available.sale ? available.sale.available : available.available)}` : 'مفيش حاجة على العميل برّه التقسيط'"
              :description="availableHint"
            />
            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField label="المبلغ المقسّط" hint="بالجنيه" required>
                <UInput v-model="form.principal" type="number" min="1" step="any" inputmode="decimal" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField v-if="sale" label="الفاتورة">
                <div class="flex h-9 items-center gap-2 text-sm">
                  <UIcon name="i-lucide-receipt" class="size-4 text-(--ui-text-muted)" />
                  <span class="font-bold num">{{ sale.reference }}</span>
                  <span class="text-(--ui-text-muted)">آجل <span class="num">{{ formatMoney(sale.credit) }}</span></span>
                </div>
              </UFormField>
            </div>
          </div>
        </UCard>

        <UCard>
          <template #header>
            <p class="font-bold">
              الأقساط
            </p>
          </template>
          <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
              <UFormField label="عدد الأقساط" required>
                <UInput v-model.number="form.count" type="number" min="1" max="60" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="كل قد إيه">
                <USelect v-model="form.interval" :items="intervals" class="w-full" />
              </UFormField>
              <UFormField label="أول قسط" required>
                <UInput v-model="form.firstDue" type="date" :min="today" class="w-full" />
              </UFormField>
            </div>
            <div class="flex flex-wrap gap-2">
              <UButton v-for="n in [3, 6, 9, 12]" :key="n" size="xs" :color="form.count === n ? 'primary' : 'neutral'" :variant="form.count === n ? 'soft' : 'outline'" :label="`${n} شهور`" @click="form.count = n" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField label="الزيادة (فوايد)">
                <div class="flex gap-2">
                  <USelect v-model="form.markupMode" :items="markupModes" class="w-36 shrink-0" />
                  <UInput v-if="form.markupMode === 'rate'" v-model="form.rate" type="number" min="0" max="20" step="0.5" dir="ltr" class="w-full">
                    <template #trailing>
                      <span class="text-xs text-(--ui-text-muted)">% في الشهر</span>
                    </template>
                  </UInput>
                  <UInput v-else v-model="form.markup" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full">
                    <template #trailing>
                      <span class="text-xs text-(--ui-text-muted)">ج</span>
                    </template>
                  </UInput>
                </div>
              </UFormField>
              <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
                <p>الزيادة <b class="num">{{ formatMoney(markup) }}</b></p>
                <p>الإجمالي <b class="num">{{ formatMoney(total) }}</b></p>
              </div>
            </div>
          </div>
        </UCard>

        <UCard>
          <template #header>
            <p class="font-bold">
              الضامن والملاحظات
            </p>
          </template>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="اسم الضامن" :required="guarantorRequired">
              <UInput v-model="form.guarantorName" class="w-full" />
            </UFormField>
            <UFormField label="موبايل الضامن" :required="guarantorRequired">
              <UInput v-model="form.guarantorPhone" type="tel" dir="ltr" placeholder="01xxxxxxxxx" class="w-full" />
            </UFormField>
            <UFormField label="ملاحظات" class="sm:col-span-2" hint="بتتطبع على الاتفاق">
              <UTextarea v-model="form.notes" :rows="2" class="w-full" />
            </UFormField>
          </div>
        </UCard>
      </div>

      <div class="space-y-4">
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              جدول الأقساط
            </p>
          </template>
          <div class="max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
              <tbody>
                <tr v-for="i in schedule" :key="i.seq" class="border-t border-(--ui-border) first:border-t-0">
                  <td class="w-10 p-2 text-center text-(--ui-text-muted) num">
                    {{ i.seq }}
                  </td>
                  <td class="p-2 num">
                    {{ formatDate(i.due_on) }}
                  </td>
                  <td class="p-2 text-end font-bold num">
                    {{ formatMoney(i.amount) }}
                  </td>
                </tr>
                <tr v-if="!schedule.length">
                  <td class="p-6 text-center text-(--ui-text-muted)">
                    اكتب المبلغ وعدد الأقساط.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </UCard>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" block size="xl" icon="i-lucide-check" label="اعمل التقسيط واطبع الاتفاق" :loading="saving" :disabled="!customer || !principal || !schedule.length" />
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { InstallmentPlan, PosCustomer } from '~/types/api'

definePageMeta({ module: 'installments', permission: 'installments.manage' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()

const suggestedRate = computed(() => store.hasFeature('installments.suggested_markup') ? Number(store.featureSetting('installments.suggested_markup') ?? 0) : 0)
const guarantorRequired = computed(() => store.hasFeature('installments.guarantor_required'))
const today = dateInMonths(0)

const intervals = [
  { label: 'كل شهر', value: 1 },
  { label: 'كل شهرين', value: 2 },
  { label: 'كل 3 شهور', value: 3 },
]
const markupModes = [
  { label: 'نسبة', value: 'rate' },
  { label: 'مبلغ', value: 'amount' },
]

const customer = ref<PosCustomer | null>(null)
const form = reactive({
  principal: '',
  count: 6,
  interval: 1,
  firstDue: dateInMonths(1),
  markupMode: (suggestedRate.value ? 'rate' : 'amount') as 'rate' | 'amount',
  rate: suggestedRate.value ? String(suggestedRate.value) : '',
  markup: '',
  guarantorName: '',
  guarantorPhone: '',
  notes: '',
})
const saleId = computed(() => typeof route.query.sale === 'string' ? route.query.sale : null)

interface Available { customer: PosCustomer & { is_active: boolean }, in_plans: number, available: number, sale: { reference: string, credit: number, available: number } | null }
const available = ref<Available | null>(null)
const sale = computed(() => available.value?.sale ?? null)
const availableHint = computed(() => {
  const a = available.value
  if (!a) {
    return undefined
  }
  const parts = [`عليه ${formatMoney(a.customer.balance)}`]
  if (a.in_plans) {
    parts.push(`منهم ${formatMoney(a.in_plans)} في تقسيط تاني`)
  }
  return parts.join('، ')
})

async function loadAvailable(customerId: string) {
  try {
    available.value = (await api<{ data: Available }>('/installments/available', { query: { customer_id: customerId, sale_id: saleId.value ?? undefined } })).data
    const max = available.value.sale ? available.value.sale.available : available.value.available
    if (!form.principal && max > 0) {
      form.principal = String(max / 100)
    }
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
}

watch(customer, (c) => {
  available.value = null
  if (c) {
    loadAvailable(c.id)
  }
})

if (typeof route.query.customer === 'string') {
  await loadAvailable(route.query.customer)
  if (available.value) {
    const c = available.value.customer
    customer.value = { id: c.id, name: c.name, phone: c.phone, balance: c.balance, credit_limit: c.credit_limit }
  }
}

const principal = computed(() => toPiasters(form.principal) ?? 0)
const months = computed(() => Math.max(1, Number(form.count) || 1) * form.interval)
const markup = computed(() => form.markupMode === 'rate'
  ? installmentMarkup(principal.value, Number(form.rate) || 0, months.value)
  : toPiasters(form.markup) ?? 0)
const total = computed(() => principal.value + markup.value)
const schedule = computed(() => installmentSchedule(total.value, Number(form.count) || 0, form.firstDue, form.interval))

const saving = ref(false)
const error = ref<string | null>(null)

async function save() {
  if (!customer.value) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: InstallmentPlan }>('/installments', {
      method: 'POST',
      body: {
        customer_id: customer.value.id,
        sale_id: sale.value ? saleId.value : null,
        principal: principal.value,
        markup: markup.value,
        markup_rate: form.markupMode === 'rate' && form.rate ? Math.round(Number(form.rate) * 100) : null,
        count: Number(form.count),
        interval_months: form.interval,
        first_due_on: form.firstDue,
        guarantor_name: form.guarantorName || null,
        guarantor_phone: form.guarantorPhone || null,
        notes: form.notes || null,
      },
    })
    toast.add({ color: 'success', title: `اتعمل التقسيط ${res.data.reference}` })
    await navigateTo({ path: `/installments/${res.data.id}`, query: { print: '1' } })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

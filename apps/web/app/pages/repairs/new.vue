<template>
  <div class="max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="استلام جهاز" description="سجّل الجهاز وحالته قدام العميل، واطبعله إيصال فيه كود يتابع بيه." class="flex-1" />
    </div>

    <form class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="save">
      <div class="space-y-6">
        <!-- Customer -->
        <UCard>
          <p class="mb-3 font-bold">
            العميل
          </p>
          <PosCustomerPicker v-if="canCustomers" v-model="customer" />
          <div v-if="!customer" class="mt-3 grid gap-3 sm:grid-cols-2">
            <UFormField label="الموبايل" required hint="بندوّر بيه لو العميل موجود">
              <UInput v-model="form.customer_phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
            </UFormField>
            <UFormField label="الاسم" required>
              <UInput v-model="form.customer_name" class="w-full" />
            </UFormField>
            <CustomersConsentCheckbox v-model="form.consent" class="sm:col-span-2" />
          </div>
        </UCard>

        <!-- Device -->
        <UCard>
          <p class="mb-3 font-bold">
            الجهاز
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="الموديل" hint="من القايمة، أو اكتبه تحت">
              <DeviceModelPicker v-model="form.device_model_id" placeholder="دوّر على الموديل" @pick="m => { if (m) form.device_name = m.full_name }" />
            </UFormField>
            <UFormField label="اسم الجهاز" required>
              <UInput v-model="form.device_name" placeholder="مثلاً Samsung A54" class="w-full" />
            </UFormField>
            <UFormField label="IMEI / السيريال">
              <UInput v-model="form.imei" dir="ltr" inputmode="numeric" placeholder="*#06#" class="w-full" />
            </UFormField>
            <UFormField label="اللون">
              <UInput v-model="form.color" class="w-full" />
            </UFormField>
          </div>

          <div class="mt-4 space-y-3">
            <UFormField label="قفل الشاشة">
              <div class="flex flex-wrap gap-2">
                <UButton
                  v-for="t in unlockTypes"
                  :key="t.value"
                  size="sm"
                  :color="form.unlock_type === t.value ? 'primary' : 'neutral'"
                  :variant="form.unlock_type === t.value ? 'soft' : 'outline'"
                  :label="t.label"
                  @click="form.unlock_type = t.value; form.unlock_code = ''"
                />
              </div>
            </UFormField>
            <RepairsPatternInput v-if="form.unlock_type === 'pattern'" v-model="form.unlock_code" />
            <UInput v-else-if="form.unlock_type !== 'none'" v-model="form.unlock_code" dir="ltr" :placeholder="form.unlock_type === 'pin' ? 'الرقم السري' : 'الباسورد'" class="w-full sm:w-64" />
            <p v-if="form.unlock_type !== 'none'" class="text-xs text-(--ui-text-muted)">
              بيتحفظ متشفّر، وبيظهر للفني بس.
            </p>
          </div>
        </UCard>

        <!-- Condition and checks -->
        <UCard v-if="options">
          <p class="mb-3 font-bold">
            حالة الجهاز وهو داخل
          </p>
          <UFormField label="مع الجهاز">
            <div class="flex flex-wrap gap-2">
              <UButton v-for="o in options.accessories" :key="o.value" size="sm" :color="form.accessories.includes(o.value) ? 'primary' : 'neutral'" :variant="form.accessories.includes(o.value) ? 'soft' : 'outline'" :label="o.label" @click="toggle(form.accessories, o.value)" />
            </div>
          </UFormField>
          <UFormField label="شكله من برّه" class="mt-4">
            <div class="flex flex-wrap gap-2">
              <UButton v-for="o in options.condition" :key="o.value" size="sm" :color="form.condition.includes(o.value) ? 'warning' : 'neutral'" :variant="form.condition.includes(o.value) ? 'soft' : 'outline'" :label="o.label" @click="toggle(form.condition, o.value)" />
            </div>
          </UFormField>
          <div class="mt-4">
            <p class="mb-2 text-sm font-medium">
              فحص سريع قبل الاستلام
            </p>
            <div class="grid gap-2 sm:grid-cols-2">
              <div v-for="c in options.checks" :key="c.value" class="flex items-center justify-between gap-2 rounded-(--ui-radius) border border-(--ui-border) px-3 py-1.5">
                <span class="text-sm">{{ c.label }}</span>
                <UFieldGroup size="xs">
                  <UButton v-for="v in checkValues" :key="v.value" :color="form.checks[c.value] === v.value ? v.color : 'neutral'" :variant="form.checks[c.value] === v.value ? 'soft' : 'outline'" :label="v.label" @click="form.checks[c.value] = v.value" />
                </UFieldGroup>
              </div>
            </div>
          </div>
        </UCard>

        <!-- Faults -->
        <UCard v-if="options">
          <p class="mb-3 font-bold">
            العطل حسب كلام العميل
          </p>
          <RepairsFaultPicker v-model="form.fault_ids" :categories="options.faults" />
          <UFormField label="ملاحظات" class="mt-4">
            <UTextarea v-model="form.reported_note" :rows="2" placeholder="أي تفاصيل تانية قالها العميل" class="w-full" />
          </UFormField>
        </UCard>
      </div>

      <!-- Promise, price, deposit -->
      <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <UCard>
          <div class="space-y-4">
            <UFormField label="ميعاد التسليم المتوقع">
              <UInput v-model="form.expected_at" type="datetime-local" dir="ltr" class="w-full" />
              <div class="mt-2 flex flex-wrap gap-1">
                <UButton v-for="p in promises" :key="p.label" size="xs" color="neutral" variant="outline" :label="p.label" @click="form.expected_at = p.value()" />
              </div>
            </UFormField>
            <UFormField label="التكلفة المبدئية" hint="بالجنيه">
              <UInput v-model="form.estimate" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField v-if="options?.technicians.length" label="الفني">
              <USelect v-model="form.technician_id" :items="[{ label: 'بعدين', value: 'none' }, ...options.technicians.map(t => ({ label: t.name, value: t.id }))]" class="w-full" />
            </UFormField>
            <UFormField v-if="store.hasFeature('repairs.deposits')" label="عربون" hint="بالجنيه">
              <div class="flex gap-2">
                <UInput v-model="form.deposit" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="flex-1" />
                <USelect v-model="form.deposit_method" :items="CASH_METHODS" class="w-28" />
              </div>
            </UFormField>
          </div>
        </UCard>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
        <UButton type="submit" block size="xl" icon="i-lucide-check" label="استلام واطبع الإيصال" :loading="saving" />
      </div>
    </form>

    <PrintSheet v-if="printing && created" page-size="80mm auto">
      <RepairsIntakeReceipt :ticket="created" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { CashMethod, PosCustomer, RepairOptions, RepairTicket } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.create' })

const api = useApi()
const store = useSessionStore()
const canCustomers = computed(() => store.can('customers.view'))
const shop = useReceiptShop()

const { data: optionsData } = await useAsyncData('repair-options', () => api<{ data: RepairOptions }>('/repairs/options'))
const options = computed(() => optionsData.value?.data)

const customer = ref<PosCustomer | null>(null)
const form = reactive({
  customer_name: '',
  customer_phone: '',
  consent: true,
  device_model_id: undefined as number | undefined,
  device_name: '',
  imei: '',
  color: '',
  unlock_type: 'none' as 'none' | 'pin' | 'pattern' | 'password',
  unlock_code: '',
  accessories: [] as string[],
  condition: [] as string[],
  checks: {} as Record<string, 'yes' | 'no' | 'unknown'>,
  fault_ids: [] as number[],
  reported_note: '',
  expected_at: '',
  estimate: '',
  technician_id: 'none',
  deposit: '',
  deposit_method: 'cash' as CashMethod,
})

const unlockTypes = [
  { value: 'none', label: 'مفيش' },
  { value: 'pin', label: 'رقم سري' },
  { value: 'pattern', label: 'نمط' },
  { value: 'password', label: 'باسورد' },
] as const
const checkValues = [
  { value: 'yes', label: 'آه', color: 'success' },
  { value: 'no', label: 'لأ', color: 'error' },
  { value: 'unknown', label: 'مش عارف', color: 'neutral' },
] as const

function localInput(date: Date) {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
const at = (days: number, hour: number) => () => {
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setHours(hour, 0, 0, 0)
  return localInput(d)
}
const promises = [
  { label: 'بعد ساعتين', value: () => localInput(new Date(Date.now() + 2 * 3600_000)) },
  { label: 'بالليل', value: at(0, 21) },
  { label: 'بكرة', value: at(1, 18) },
  { label: 'بعد 3 أيام', value: at(3, 18) },
]

function toggle(list: string[], value: string) {
  const i = list.indexOf(value)
  i === -1 ? list.push(value) : list.splice(i, 1)
}

const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)
const created = ref<RepairTicket | null>(null)
const { printing, print } = usePrint()

async function save() {
  saving.value = true
  error.value = null
  needsShift.value = false
  try {
    const deposit = store.hasFeature('repairs.deposits') ? toPiasters(form.deposit) ?? 0 : 0
    const res = await api<{ data: RepairTicket }>('/repairs/tickets', {
      method: 'POST',
      body: {
        ...(customer.value ? { customer_id: customer.value.id } : { customer_name: form.customer_name, customer_phone: form.customer_phone, consent: form.consent }),
        device_model_id: form.device_model_id ?? null,
        device_name: form.device_name,
        imei: form.imei || null,
        color: form.color || null,
        unlock_type: form.unlock_type,
        unlock_code: form.unlock_type === 'none' ? null : form.unlock_code || null,
        accessories: form.accessories,
        condition: form.condition,
        checks: form.checks,
        fault_ids: form.fault_ids,
        reported_note: form.reported_note || null,
        expected_at: form.expected_at ? new Date(form.expected_at).toISOString() : null,
        estimate: toPiasters(form.estimate),
        technician_id: form.technician_id === 'none' ? null : form.technician_id,
        deposits: deposit > 0 ? [{ method: form.deposit_method, amount: deposit }] : [],
      },
    })
    created.value = res.data
    await print()
    await navigateTo(`/repairs/${res.data.id}`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
  }
  finally {
    saving.value = false
  }
}
</script>

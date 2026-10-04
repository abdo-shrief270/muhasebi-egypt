<template>
  <div class="mx-auto max-w-6xl space-y-4">
    <div class="flex items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" size="xl" square aria-label="رجوع" />
      <h1 class="flex-1 text-2xl font-extrabold">
        استلام سريع
      </h1>
      <UButton to="/repairs/new" color="neutral" variant="ghost" icon="i-lucide-list" label="الفورم الكامل" />
    </div>

    <!-- Done: print again or the next device -->
    <UCard v-if="created" :ui="{ body: 'py-10 text-center space-y-5' }">
      <UIcon name="i-lucide-circle-check" class="mx-auto size-16 text-success" />
      <div>
        <p class="text-2xl font-extrabold">
          اتسجّل الجهاز
        </p>
        <p class="num mt-1 text-xl" dir="ltr">
          {{ created.reference }}
        </p>
        <p class="mt-1 text-(--ui-text-muted)">
          {{ created.customer_name }} · {{ created.device_name }}
        </p>
      </div>
      <div class="mx-auto grid max-w-xl gap-3 sm:grid-cols-3">
        <UButton size="xl" block icon="i-lucide-plus" label="جهاز تاني" @click="restart" />
        <UButton size="xl" block color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع تاني" @click="print" />
        <UButton size="xl" block color="neutral" variant="outline" icon="i-lucide-arrow-left" label="افتح التذكرة" :to="`/repairs/${created.id}`" />
      </div>
    </UCard>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
      <div class="space-y-4">
        <!-- Steps: tap one to go back to it -->
        <ol class="grid grid-cols-5 gap-1.5">
          <li v-for="(s, i) in steps" :key="s.key">
            <button
              type="button"
              class="w-full rounded-(--ui-radius) px-2 py-2.5 text-sm font-semibold transition"
              :class="i === step ? 'bg-(--ui-primary) text-(--ui-bg)' : i < step || (i < 4 && done(i)) ? 'bg-(--app-primary-soft) text-(--ui-primary)' : 'bg-(--ui-bg-elevated) text-(--ui-text-muted)'"
              :disabled="i > step && !canJump(i)"
              @click="step = i"
            >
              <UIcon v-if="i !== step && i < 4 && done(i)" name="i-lucide-check" class="size-4 align-[-2px]" />
              {{ s.label }}
            </button>
          </li>
        </ol>

        <UCard :ui="{ body: 'space-y-4 min-h-[26rem]' }">
          <!-- 1. Phone -->
          <template v-if="step === 0">
            <p class="text-xl font-bold">
              رقم موبايل العميل
            </p>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-3">
                <div class="num flex h-16 items-center justify-center rounded-(--ui-radius) border-2 border-(--ui-primary) bg-(--ui-bg) text-3xl font-bold tracking-wider" dir="ltr">
                  {{ phoneShown || '01' }}<span class="animate-pulse text-(--ui-primary)">|</span>
                </div>
                <RepairsKeypad v-model="form.phone" :max-length="11" />
              </div>
              <div class="space-y-2">
                <p class="text-sm text-(--ui-text-muted)">
                  {{ matches.length ? 'عملاء بالرقم ده — دوس على اللي معاك:' : form.phone.length >= 4 ? 'مفيش عميل بالرقم ده — هيتضاف جديد.' : 'اكتب الرقم وهندوّر عليه.' }}
                </p>
                <button
                  v-for="c in matches"
                  :key="c.id"
                  type="button"
                  class="flex w-full items-center justify-between gap-3 rounded-(--ui-radius) border border-(--ui-border) p-4 text-start transition hover:bg-(--ui-bg-elevated) active:scale-[.99]"
                  @click="pickCustomer(c)"
                >
                  <span>
                    <span class="block text-lg font-bold">{{ c.name }}</span>
                    <span class="num block text-sm text-(--ui-text-muted)" dir="ltr">{{ localPhone(c.phone) }}</span>
                  </span>
                  <UIcon name="i-lucide-chevron-left" class="size-6" />
                </button>
              </div>
            </div>
          </template>

          <!-- 2. Name -->
          <template v-else-if="step === 1">
            <p class="text-xl font-bold">
              اسم العميل
            </p>
            <UInput ref="nameInput" v-model="form.name" size="xl" placeholder="الاسم" class="w-full" :ui="{ base: 'h-16 text-2xl' }" @keydown.enter.prevent="next" />
            <CustomersConsentCheckbox v-model="form.consent" />
          </template>

          <!-- 3. Device -->
          <template v-else-if="step === 2">
            <p class="text-xl font-bold">
              الجهاز
            </p>
            <UInput v-model="deviceQuery" size="xl" icon="i-lucide-search" placeholder="دوّر أو اكتب اسم الجهاز" class="w-full" :ui="{ base: 'h-14 text-lg' }" @keydown.enter.prevent="useTyped" />
            <div v-if="deviceQuery.trim()" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
              <button v-for="m in found" :key="m.id" type="button" :class="tile(form.device_model_id === m.id)" @click="pickDevice(m.id, m.full_name)">
                {{ m.full_name }}
              </button>
              <button type="button" :class="tile(false)" class="border-dashed" @click="useTyped">
                <UIcon name="i-lucide-pencil" class="me-1 size-4 align-[-2px]" /> «{{ deviceQuery.trim() }}»
              </button>
            </div>
            <template v-else-if="brand">
              <UButton color="neutral" variant="ghost" icon="i-lucide-arrow-right" :label="`كل الماركات · ${brand.name}`" @click="brand = null" />
              <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <button v-for="m in brand.models ?? []" :key="m.id" type="button" :class="tile(form.device_model_id === m.id)" @click="pickDevice(m.id, m.full_name || `${brand.name} ${m.name}`)">
                  {{ m.name }}
                </button>
              </div>
            </template>
            <template v-else>
              <div v-if="options?.recent_devices.length">
                <p class="mb-2 text-sm text-(--ui-text-muted)">
                  الأكتر عندك
                </p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  <button v-for="d in options.recent_devices" :key="`${d.device_model_id}-${d.device_name}`" type="button" :class="tile(form.device_name === d.device_name)" @click="pickDevice(d.device_model_id, d.device_name)">
                    {{ d.device_name }}
                  </button>
                </div>
              </div>
              <div v-if="brands.length">
                <p class="mb-2 text-sm text-(--ui-text-muted)">
                  الماركة
                </p>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  <button v-for="b in brands" :key="b.id" type="button" :class="tile(false)" @click="brand = b">
                    {{ b.name }}
                  </button>
                </div>
              </div>
            </template>
          </template>

          <!-- 4. Fault -->
          <template v-else-if="step === 3">
            <p class="text-xl font-bold">
              العطل <span class="text-base font-normal text-(--ui-text-muted)">(اختار واحد أو أكتر)</span>
            </p>
            <div v-if="topFaults.length">
              <p class="mb-2 text-sm text-(--ui-text-muted)">
                الأكتر عندك
              </p>
              <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <button v-for="f in topFaults" :key="f.id" type="button" :class="tile(form.fault_ids.includes(f.id))" @click="toggleFault(f.id)">
                  {{ f.name }}
                </button>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <UButton
                v-for="c in faultCategories"
                :key="c.id"
                size="xl"
                :color="category === c.id ? 'primary' : 'neutral'"
                :variant="category === c.id ? 'soft' : 'outline'"
                :label="c.name"
                @click="category = category === c.id ? null : c.id"
              />
            </div>
            <div v-if="category !== null" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
              <button v-for="f in faultsOf(category)" :key="f.id" type="button" :class="tile(form.fault_ids.includes(f.id))" @click="toggleFault(f.id)">
                {{ f.name }}
              </button>
            </div>
            <UTextarea v-model="form.note" :rows="2" placeholder="حاجة تانية العميل قالها؟ (اختياري)" class="w-full" :ui="{ base: 'text-lg' }" />
          </template>

          <!-- 5. Lock, price, promise (all optional) -->
          <template v-else>
            <p class="text-xl font-bold">
              تفاصيل <span class="text-base font-normal text-(--ui-text-muted)">(كلها اختيارية)</span>
            </p>
            <div class="grid gap-6 md:grid-cols-2">
              <div class="space-y-3">
                <p class="font-semibold">
                  قفل الشاشة
                </p>
                <div class="grid grid-cols-4 gap-2">
                  <button v-for="t in unlockTypes" :key="t.value" type="button" :class="tile(form.unlock_type === t.value)" class="!min-h-14 !text-base" @click="form.unlock_type = t.value; form.unlock_code = ''">
                    {{ t.label }}
                  </button>
                </div>
                <RepairsPatternInput v-if="form.unlock_type === 'pattern'" v-model="form.unlock_code" />
                <template v-else-if="form.unlock_type === 'pin'">
                  <div class="num flex h-14 items-center justify-center rounded-(--ui-radius) border border-(--ui-border) text-2xl font-bold tracking-[.4em]" dir="ltr">
                    {{ form.unlock_code || '—' }}
                  </div>
                  <RepairsKeypad v-model="form.unlock_code" :max-length="12" />
                </template>
                <UInput v-else-if="form.unlock_type === 'password'" v-model="form.unlock_code" size="xl" dir="ltr" placeholder="الباسورد" class="w-full" />
              </div>

              <div class="space-y-3">
                <p class="font-semibold">
                  التكلفة المبدئية <span class="font-normal text-(--ui-text-muted)">(ج)</span>
                </p>
                <div class="num flex h-14 items-center justify-center rounded-(--ui-radius) border border-(--ui-border) text-2xl font-bold" dir="ltr">
                  {{ form.estimate || '—' }}
                </div>
                <div class="flex flex-wrap gap-2">
                  <UButton v-if="suggested" size="xl" variant="soft" :label="`المقترح ${suggested}`" @click="form.estimate = String(suggested)" />
                  <UButton v-for="p in [100, 200, 300, 500]" :key="p" size="xl" color="neutral" variant="outline" :label="String(p)" @click="form.estimate = String(p)" />
                </div>
                <RepairsKeypad v-model="form.estimate" decimal :max-length="7" />

                <p class="pt-2 font-semibold">
                  هيستلمه إمتى
                </p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  <button v-for="p in promises" :key="p.label" type="button" :class="tile(form.promise === p.label)" class="!min-h-14 !text-base" @click="form.promise = form.promise === p.label ? '' : p.label">
                    {{ p.label }}
                  </button>
                </div>

                <template v-if="store.hasFeature('repairs.deposits')">
                  <p class="pt-2 font-semibold">
                    عربون <span class="font-normal text-(--ui-text-muted)">(كاش، ج)</span>
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <UButton v-for="p in [0, 50, 100, 200]" :key="p" size="xl" :color="Number(form.deposit || 0) === p ? 'primary' : 'neutral'" :variant="Number(form.deposit || 0) === p ? 'soft' : 'outline'" :label="p ? String(p) : 'من غير'" @click="form.deposit = p ? String(p) : ''" />
                  </div>
                </template>
              </div>
            </div>
          </template>
        </UCard>

        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />

        <div class="grid grid-cols-2 gap-3">
          <UButton size="xl" block color="neutral" variant="outline" icon="i-lucide-arrow-right" label="رجوع" :disabled="step === 0" class="h-16 text-lg" @click="back" />
          <UButton v-if="step < 4" size="xl" block trailing-icon="i-lucide-arrow-left" label="التالي" :disabled="!done(step)" class="h-16 text-lg" @click="next" />
          <UButton v-else size="xl" block icon="i-lucide-check" label="استلم واطبع" :loading="saving" :disabled="!ready" class="h-16 text-lg" @click="save" />
        </div>
      </div>

      <!-- What's been picked so far; tap a line to change it -->
      <UCard class="lg:sticky lg:top-20 lg:self-start" :ui="{ body: 'space-y-1' }">
        <p class="mb-2 font-bold">
          التذكرة
        </p>
        <button v-for="(line, i) in summary" :key="i" type="button" class="flex w-full items-start gap-2 rounded-(--ui-radius) p-2 text-start hover:bg-(--ui-bg-elevated)" @click="step = line.step">
          <UIcon :name="line.icon" class="mt-0.5 size-5 shrink-0 text-(--ui-text-muted)" />
          <span class="min-w-0" :class="line.value ? '' : 'text-(--ui-text-dimmed)'">{{ line.value || line.empty }}</span>
        </button>
        <UButton v-if="step >= 3" size="xl" block icon="i-lucide-check" label="استلم واطبع دلوقتي" class="mt-3 h-14" :loading="saving" :disabled="!ready" @click="save" />
      </UCard>
    </div>

    <PrintSheet v-if="printing && created" :page-size="paper.page.value">
      <RepairsIntakeReceipt :ticket="created" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { Brand, Customer, DeviceModel, RepairOptions, RepairTicket } from '~/types/api'

/**
 * Touch-first intake: one question per screen with big buttons — phone (number pad; a known customer is
 * picked from the matches), name, device (the shop's most received first, then brand → model), fault
 * (most used first), then the optional lock / price / promise / deposit. The full form stays at /repairs/new.
 */
definePageMeta({ module: 'repairs', permission: 'repairs.create' })

const api = useApi()
const store = useSessionStore()
const paper = useThermalPaper()
const shop = useReceiptShop()
const { printing, print } = usePrint()

const { data: optionsData } = await useAsyncData('repair-options', () => api<{ data: RepairOptions }>('/repairs/options'))
const options = computed(() => optionsData.value?.data)
const { data: brandsData } = await useAsyncData('quick-intake-brands', () => store.can('products.view')
  ? api<{ data: Brand[] }>('/catalog/brands').catch(() => ({ data: [] as Brand[] }))
  : Promise.resolve({ data: [] as Brand[] }))
const brands = computed(() => (brandsData.value?.data ?? []).filter(b => (b.models ?? []).length))

const steps = [
  { key: 'phone', label: 'الموبايل' },
  { key: 'name', label: 'الاسم' },
  { key: 'device', label: 'الجهاز' },
  { key: 'fault', label: 'العطل' },
  { key: 'extra', label: 'تفاصيل' },
] as const
const step = ref(0)

const blank = () => ({
  phone: '',
  customer: null as Customer | null,
  name: '',
  consent: true,
  device_model_id: null as number | null,
  device_name: '',
  fault_ids: [] as number[],
  note: '',
  unlock_type: 'none' as 'none' | 'pin' | 'pattern' | 'password',
  unlock_code: '',
  estimate: '',
  promise: '',
  deposit: '',
})
const form = reactive(blank())

const phoneOk = computed(() => /^01[0125]\d{8}$/.test(form.phone))
const phoneShown = computed(() => form.phone.replace(/^(\d{4})(\d{0,3})(\d{0,4}).*/, (_, a, b, c) => [a, b, c].filter(Boolean).join(' ')))

function done(i: number): boolean {
  switch (i) {
    case 0: return form.customer !== null || phoneOk.value
    case 1: return form.customer !== null || form.name.trim().length >= 2
    case 2: return form.device_name.trim() !== ''
    case 3: return form.fault_ids.length > 0 || form.note.trim() !== ''
    default: return true
  }
}
const canJump = (i: number) => Array.from({ length: i }, (_, k) => k).every(done)
const ready = computed(() => [0, 1, 2, 3].every(done))

const nameInput = useTemplateRef<{ inputRef?: HTMLInputElement }>('nameInput')
function next() {
  if (!done(step.value)) return
  // A customer picked from the list already has a name.
  step.value = step.value === 0 && form.customer ? 2 : Math.min(step.value + 1, 4)
}
function back() {
  step.value = step.value === 2 && form.customer ? 0 : Math.max(step.value - 1, 0)
}
watch(step, async (s) => {
  if (s === 1) {
    await nextTick()
    nameInput.value?.inputRef?.focus()
  }
})

// Phone → known customers.
const matches = ref<Customer[]>([])
let lookup: ReturnType<typeof setTimeout> | undefined
watch(() => form.phone, (phone) => {
  form.customer = null
  clearTimeout(lookup)
  if (phone.length < 4 || !store.can('customers.view')) {
    matches.value = []
    return
  }
  lookup = setTimeout(async () => {
    matches.value = (await api<{ data: Customer[] }>('/customers', { query: { q: phone, active: 1, per_page: 4 } }).catch(() => ({ data: [] }))).data
  }, 250)
})
function pickCustomer(c: Customer) {
  form.customer = c
  form.name = c.name
  step.value = 2
}

// Device.
const deviceQuery = ref('')
const brand = ref<Brand | null>(null)
const found = ref<DeviceModel[]>([])
let search: ReturnType<typeof setTimeout> | undefined
watch(deviceQuery, (q) => {
  clearTimeout(search)
  if (!q.trim() || !store.can('products.view')) {
    found.value = []
    return
  }
  search = setTimeout(async () => {
    found.value = (await api<{ data: DeviceModel[] }>('/catalog/device-models', { query: { q } }).catch(() => ({ data: [] }))).data.slice(0, 8)
  }, 200)
})
function pickDevice(id: number | null, name: string) {
  form.device_model_id = id
  form.device_name = name
  deviceQuery.value = ''
  brand.value = null
  step.value = 3
}
function useTyped() {
  const name = deviceQuery.value.trim()
  if (name) pickDevice(null, name)
}

// Faults.
const faultCategories = computed(() => (options.value?.faults ?? []).filter(c => c.types.some(t => t.is_active)))
const allFaults = computed(() => faultCategories.value.flatMap(c => c.types.filter(t => t.is_active)))
const topFaults = computed(() => (options.value?.top_faults ?? []).map(id => allFaults.value.find(f => f.id === id)).filter(f => f !== undefined).slice(0, 9))
const category = ref<number | null>(null)
const faultsOf = (id: number) => faultCategories.value.find(c => c.id === id)?.types.filter(t => t.is_active) ?? []
function toggleFault(id: number) {
  const i = form.fault_ids.indexOf(id)
  i === -1 ? form.fault_ids.push(id) : form.fault_ids.splice(i, 1)
}
// The faults' default labor, as a price suggestion.
const suggested = computed(() => {
  const sum = form.fault_ids.reduce((n, id) => n + (allFaults.value.find(f => f.id === id)?.default_labor_price ?? 0), 0)
  return sum > 0 ? sum / 100 : null
})

const unlockTypes = [
  { value: 'none', label: 'مفيش' },
  { value: 'pin', label: 'رقم' },
  { value: 'pattern', label: 'نمط' },
  { value: 'password', label: 'باسورد' },
] as const

const at = (days: number, hour: number) => () => {
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setHours(hour, 0, 0, 0)
  return d
}
const promises = [
  { label: 'بعد ساعتين', when: () => new Date(Date.now() + 2 * 3600_000) },
  { label: 'بالليل', when: at(0, 21) },
  { label: 'بكرة', when: at(1, 18) },
  { label: 'بعد 3 أيام', when: at(3, 18) },
]

const summary = computed(() => [
  { step: 0, icon: 'i-lucide-phone', value: form.customer ? localPhone(form.customer.phone) : phoneOk.value ? form.phone : '', empty: 'الموبايل' },
  { step: form.customer ? 0 : 1, icon: 'i-lucide-user', value: form.customer?.name ?? form.name.trim(), empty: 'الاسم' },
  { step: 2, icon: 'i-lucide-smartphone', value: form.device_name, empty: 'الجهاز' },
  { step: 3, icon: 'i-lucide-wrench', value: [...form.fault_ids.map(id => allFaults.value.find(f => f.id === id)?.name), form.note.trim()].filter(Boolean).join('، '), empty: 'العطل' },
  { step: 4, icon: 'i-lucide-banknote', value: [form.estimate ? `${form.estimate} ج` : '', form.promise, form.deposit ? `عربون ${form.deposit} ج` : '', form.unlock_type !== 'none' ? 'عليه قفل' : ''].filter(Boolean).join(' · '), empty: 'السعر والميعاد' },
])

const tile = (on: boolean) => [
  'min-h-16 rounded-(--ui-radius) border-2 px-3 py-2 text-lg font-semibold transition select-none active:scale-[.98]',
  on ? 'border-(--ui-primary) bg-(--app-primary-soft) text-(--ui-primary)' : 'border-(--ui-border) bg-(--ui-bg) hover:bg-(--ui-bg-elevated)',
]

const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)
const created = ref<RepairTicket | null>(null)

async function save() {
  if (!ready.value || saving.value) return
  saving.value = true
  error.value = null
  needsShift.value = false
  try {
    const deposit = store.hasFeature('repairs.deposits') ? toPiasters(form.deposit) ?? 0 : 0
    const when = promises.find(p => p.label === form.promise)?.when()
    const res = await api<{ data: RepairTicket }>('/repairs/tickets', {
      method: 'POST',
      body: {
        ...(form.customer ? { customer_id: form.customer.id } : { customer_name: form.name.trim(), customer_phone: form.phone, consent: form.consent }),
        device_model_id: form.device_model_id,
        device_name: form.device_name,
        unlock_type: form.unlock_type,
        unlock_code: form.unlock_type === 'none' ? null : form.unlock_code || null,
        fault_ids: form.fault_ids,
        reported_note: form.note.trim() || null,
        expected_at: when ? when.toISOString() : null,
        estimate: toPiasters(form.estimate),
        deposits: deposit > 0 ? [{ method: 'cash', amount: deposit }] : [],
      },
    })
    created.value = res.data
    await print()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
  }
  finally {
    saving.value = false
  }
}

function restart() {
  Object.assign(form, blank())
  matches.value = []
  deviceQuery.value = ''
  brand.value = null
  category.value = null
  created.value = null
  error.value = null
  step.value = 0
}

// A hardware keyboard still works: digits / Backspace type the phone, Enter = next.
function onKey(e: KeyboardEvent) {
  const typing = ['INPUT', 'TEXTAREA'].includes((e.target as HTMLElement | null)?.tagName ?? '')
  if (created.value || typing || e.ctrlKey || e.metaKey || e.altKey) return
  if (e.key === 'Enter') {
    next()
  }
  else if (step.value === 0 && /^\d$/.test(e.key) && form.phone.length < 11) {
    form.phone += e.key
  }
  else if (step.value === 0 && e.key === 'Backspace') {
    form.phone = form.phone.slice(0, -1)
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

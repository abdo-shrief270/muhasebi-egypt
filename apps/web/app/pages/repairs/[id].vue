<template>
  <div v-if="ticket" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold">
          <span class="num">{{ ticket.reference }}</span>
          <UBadge :color="ticketStatusColor(ticket.status)" variant="subtle" size="lg">
            {{ ticket.status_label }}
          </UBadge>
          <UBadge v-if="ticket.is_overdue" color="error" variant="soft" icon="i-lucide-alarm-clock">
            متأخر
          </UBadge>
          <UBadge v-if="ticket.warranty_of_id" color="info" variant="soft" icon="i-lucide-shield-check">
            رجوع في الضمان
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          {{ ticket.device_name }} · استلمه {{ ticket.received_by_name ?? '—' }} {{ formatDate(ticket.received_at, true) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton
          v-if="ticket.status === 'ready' && !ticket.ready_notified_at"
          color="warning"
          icon="i-lucide-message-circle"
          label="بلّغ العميل إنه جاهز"
          @click="notify(ticket)"
        />
        <UButton v-else color="neutral" variant="outline" icon="i-lucide-message-circle" label="واتساب" @click="notify(ticket)" />
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="الإيصال" @click="print" />
        <UButton v-if="canWork && ticket.next_statuses.length" color="neutral" variant="outline" icon="i-lucide-arrow-left-right" label="تغيير الحالة" @click="openStatus(null)" />
        <UButton v-if="canWork && ticket.next_statuses.some(s => s.value === 'ready')" color="success" variant="soft" icon="i-lucide-check-circle" label="جاهز" @click="openStatus('ready')" />
        <UButton v-if="canOutsource && !['delivered', 'rejected'].includes(ticket.status) && !ticket.outsourced?.active" color="neutral" variant="outline" icon="i-lucide-send" label="ابعته لمحل شريك" @click="outsourceOpen = true" />
        <UButton v-if="canDeliver && ticket.can_deliver" icon="i-lucide-hand-helping" label="تسليم للعميل" @click="deliverOpen = true" />
        <UButton v-if="ticket.under_warranty && store.can('repairs.create')" color="warning" variant="soft" icon="i-lucide-shield-alert" label="رجوع في الضمان" :loading="returning" @click="warrantyReturn" />
      </div>
    </div>

    <UAlert
      v-if="ticket.outsourced"
      :color="ticket.outsourced.active && ticket.outsourced.status !== 'delivered' ? 'info' : 'neutral'"
      variant="subtle"
      icon="i-lucide-send"
      :title="`${ticket.outsourced.active ? 'الجهاز عند' : 'اتبعت لـ'} «${ticket.outsourced.shop}» — ${partnerStatusLabel(ticket.outsourced.status)}`"
      :description="ticket.outsourced.cost ? `حساب المحل: ${formatMoney(ticket.outsourced.cost)} (بيتحسب تكلفة على التذكرة)` : undefined"
      :actions="store.can('shop_orders.view') ? [{ label: `طلب ${ticket.outsourced.reference}`, to: `/shop-orders/${ticket.outsourced.order_id}`, color: 'neutral', variant: 'outline' }] : []"
    />
    <UAlert
      v-if="ticket.partner"
      color="info"
      variant="subtle"
      icon="i-lucide-handshake"
      title="جاي من محل شريك"
      :description="`العميل هنا هو المحل. لما تغيّر الحالة أو تسلّمه، طلبه ${ticket.partner.reference} بيتحدّث لوحده.`"
      :actions="store.can('shop_orders.view') ? [{ label: `طلب ${ticket.partner.reference}`, to: `/shop-orders/${ticket.partner.order_id}`, color: 'neutral', variant: 'outline' }] : []"
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
      <div class="space-y-6">
        <!-- Faults and diagnosis -->
        <UCard>
          <p class="mb-2 font-bold">
            العطل
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            حسب كلام العميل
          </p>
          <div class="mt-1 flex flex-wrap gap-1.5">
            <UBadge v-for="f in ticket.reported_faults" :key="f.id" color="neutral" variant="subtle">
              {{ f.category }} · {{ f.name }}
            </UBadge>
          </div>
          <p v-if="ticket.reported_note" class="mt-2 text-sm">
            {{ ticket.reported_note }}
          </p>

          <USeparator class="my-4" />
          <div class="flex items-center justify-between">
            <p class="text-xs text-(--ui-text-muted)">
              تشخيص الفني
            </p>
            <UButton v-if="canWork && ticket.status !== 'delivered' && !editingDiagnosis" size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="startDiagnosis" />
          </div>
          <template v-if="editingDiagnosis && options">
            <RepairsFaultPicker v-model="diagnosis.fault_ids" :categories="options.faults" class="mt-2" />
            <UTextarea v-model="diagnosis.note" :rows="2" placeholder="اللي لقيته" class="mt-3 w-full" />
            <div class="mt-2 flex gap-2">
              <UButton size="sm" label="حفظ التشخيص" :loading="saving" @click="saveDiagnosis" />
              <UButton size="sm" color="neutral" variant="ghost" label="إلغاء" @click="editingDiagnosis = false" />
            </div>
          </template>
          <template v-else>
            <div v-if="ticket.diagnosed_faults?.length" class="mt-1 flex flex-wrap gap-1.5">
              <UBadge v-for="f in ticket.diagnosed_faults" :key="f.id" color="primary" variant="subtle">
                {{ f.category }} · {{ f.name }}
              </UBadge>
            </div>
            <p v-if="ticket.diagnosis_note" class="mt-2 text-sm">
              {{ ticket.diagnosis_note }}
            </p>
            <p v-if="!ticket.diagnosed_faults?.length && !ticket.diagnosis_note" class="mt-1 text-sm text-(--ui-text-muted)">
              لسه متفحصش.
            </p>
          </template>
        </UCard>

        <!-- Parts -->
        <UCard>
          <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">
              قطع الغيار
            </p>
            <span class="text-sm text-(--ui-text-muted)">بتتخصم من مخزون الفرع</span>
          </div>
          <div v-if="canWork && ticket.status !== 'delivered'" class="relative mb-3">
            <UInput v-model="partTerm" icon="i-lucide-search" placeholder="امسح أو دوّر على القطعة…" class="w-full" @keydown.enter.prevent="addFirstPart" />
            <div v-if="partResults.length && partTerm" class="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
              <button v-for="p in partResults" :key="p.id" type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm hover:bg-(--ui-bg-elevated)" @click="addPart(p)">
                <span class="font-bold">{{ p.display_name }}</span>
                <span class="shrink-0 text-(--ui-text-muted)"><span class="num">{{ formatMoney(p.prices.retail) }}</span><template v-if="p.stock"> · مخزون <span class="num">{{ p.stock.find(s => s.current)?.qty ?? 0 }}</span></template></span>
              </button>
            </div>
          </div>
          <ul class="divide-y divide-(--ui-border)">
            <li v-for="p in ticket.parts" :key="p.id" class="flex items-center justify-between gap-3 py-2 text-sm">
              <span><span class="num">{{ p.qty }}</span> × {{ p.name }}</span>
              <span class="flex items-center gap-2">
                <span class="font-bold num">{{ formatMoney(p.line_total) }}</span>
                <UButton v-if="canWork && ticket.status !== 'delivered'" size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" square :aria-label="`شيل ${p.name}`" @click="removePart(p.id)" />
              </span>
            </li>
            <li v-if="!ticket.parts?.length" class="py-3 text-center text-sm text-(--ui-text-muted)">
              مفيش قطع.
            </li>
          </ul>
        </UCard>

        <!-- Device -->
        <UCard>
          <p class="mb-3 font-bold">
            الجهاز وهو داخل
          </p>
          <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div v-if="ticket.imei">
              <dt class="text-(--ui-text-muted)">
                IMEI
              </dt><dd class="num" dir="ltr">
                {{ ticket.imei }}
              </dd>
            </div>
            <div v-if="ticket.color">
              <dt class="text-(--ui-text-muted)">
                اللون
              </dt><dd>{{ ticket.color }}</dd>
            </div>
            <div v-if="ticket.accessories_labels.length">
              <dt class="text-(--ui-text-muted)">
                مع الجهاز
              </dt><dd>{{ ticket.accessories_labels.join('، ') }}</dd>
            </div>
            <div v-if="ticket.condition_labels.length">
              <dt class="text-(--ui-text-muted)">
                شكله
              </dt><dd class="text-warning">
                {{ ticket.condition_labels.join('، ') }}
              </dd>
            </div>
          </dl>
          <div v-if="Object.keys(ticket.checks).length && options" class="mt-3 flex flex-wrap gap-1.5">
            <UBadge
              v-for="c in options.checks.filter(c => ticket!.checks[c.value])"
              :key="c.value"
              :color="ticket.checks[c.value] === 'yes' ? 'success' : ticket.checks[c.value] === 'no' ? 'error' : 'neutral'"
              variant="subtle"
              :icon="ticket.checks[c.value] === 'yes' ? 'i-lucide-check' : ticket.checks[c.value] === 'no' ? 'i-lucide-x' : 'i-lucide-circle-help'"
            >
              {{ c.label }}
            </UBadge>
          </div>
          <div v-if="ticket.unlock_type !== 'none'" class="mt-4">
            <p class="text-sm text-(--ui-text-muted)">
              قفل الشاشة ({{ ({ pin: 'رقم سري', pattern: 'نمط', password: 'باسورد' } as Record<string, string>)[ticket.unlock_type] }})
            </p>
            <template v-if="ticket.unlock_code">
              <RepairsPatternInput v-if="ticket.unlock_type === 'pattern'" :model-value="ticket.unlock_code" readonly class="mt-2" />
              <p v-else class="mt-1 text-lg font-extrabold num" dir="ltr">
                {{ ticket.unlock_code }}
              </p>
            </template>
            <p v-else class="mt-1 text-sm text-(--ui-text-muted)">
              مخفي — بيظهر للفني بس.
            </p>
          </div>
        </UCard>

        <!-- Timeline -->
        <UCard>
          <p class="mb-3 font-bold">
            اللي حصل
          </p>
          <ol class="relative space-y-4 border-s border-(--ui-border) ps-4">
            <li v-for="(e, i) in [...(ticket.events ?? [])].reverse()" :key="i" class="relative">
              <span class="absolute -start-[1.4rem] top-1 size-2.5 rounded-full" :class="e.to_status ? 'bg-primary' : 'bg-(--ui-border-accented)'" />
              <p class="text-sm">
                <span class="font-bold">{{ e.to_status_label && e.type !== 'received' ? `${e.from_status_label ?? ''} ← ${e.to_status_label}` : e.type_label }}</span>
                <span v-if="e.note" class="text-(--ui-text-muted)"> — {{ e.note }}</span>
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ e.user_name }} · {{ formatDate(e.created_at, true) }}
              </p>
            </li>
          </ol>
        </UCard>
      </div>

      <!-- Side -->
      <div class="space-y-4">
        <UCard>
          <p class="text-sm text-(--ui-text-muted)">
            العميل
          </p>
          <NuxtLink v-if="store.can('customers.view')" :to="`/customers/${ticket.customer_id}`" class="block font-bold hover:text-primary">
            {{ ticket.customer_name }}
          </NuxtLink>
          <p v-else class="font-bold">
            {{ ticket.customer_name }}
          </p>
          <a v-if="ticket.customer_phone" :href="`tel:${ticket.customer_phone}`" class="block text-sm text-(--ui-text-muted) num hover:text-primary" dir="ltr">{{ localPhone(ticket.customer_phone) }}</a>

          <USeparator class="my-3" />
          <div class="space-y-3">
            <UFormField label="الفني">
              <USelect
                v-if="canWork && ticket.status !== 'delivered' && options"
                :model-value="ticket.technician_id ?? 'none'"
                :items="[{ label: 'مش متسند', value: 'none' }, ...options.technicians.map(t => ({ label: t.name, value: t.id }))]"
                class="w-full"
                @update:model-value="v => update({ technician_id: v === 'none' ? null : v })"
              />
              <p v-else class="text-sm">
                {{ ticket.technician_name ?? '—' }}
              </p>
            </UFormField>
            <UFormField label="ميعاد التسليم">
              <UInput
                v-if="canWork && ticket.status !== 'delivered'"
                :model-value="ticket.expected_at ? toLocalInput(ticket.expected_at) : ''"
                type="datetime-local"
                dir="ltr"
                class="w-full"
                @change="(e: Event) => update({ expected_at: (e.target as HTMLInputElement).value ? new Date((e.target as HTMLInputElement).value).toISOString() : null })"
              />
              <p v-else class="text-sm num">
                {{ ticket.expected_at ? formatDate(ticket.expected_at, true) : '—' }}
              </p>
            </UFormField>
          </div>
        </UCard>

        <UCard>
          <p class="mb-3 font-bold">
            الحساب
          </p>
          <div class="space-y-2 text-sm">
            <div v-if="ticket.estimate" class="flex justify-between text-(--ui-text-muted)">
              <span>التكلفة المبدئية</span><span class="num">{{ formatMoney(ticket.estimate) }}</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span>المصنعية</span>
              <UInput
                v-if="canWork && ticket.status !== 'delivered'"
                v-model="labor"
                size="sm"
                type="number"
                min="0"
                step="any"
                dir="ltr"
                class="w-28"
                aria-label="المصنعية"
                @change="update({ labor: toPiasters(labor) ?? 0 })"
              />
              <span v-else class="num">{{ formatMoney(ticket.labor) }}</span>
            </div>
            <button
              v-if="canWork && ticket.status !== 'delivered' && ticket.suggested_labor && ticket.suggested_labor !== ticket.labor"
              type="button"
              class="text-xs text-primary"
              @click="update({ labor: ticket.suggested_labor })"
            >
              المقترح من الأعطال: <span class="num">{{ formatMoney(ticket.suggested_labor) }}</span>
            </button>
            <div class="flex justify-between">
              <span>قطع الغيار</span><span class="num">{{ formatMoney(ticket.parts_total) }}</span>
            </div>
            <div v-if="ticket.parts_cost !== null && ticket.parts_total" class="flex justify-between text-xs text-(--ui-text-muted)">
              <span>تكلفة القطع</span><span class="num">{{ formatMoney(ticket.parts_cost) }}</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span>خصم</span>
              <UInput
                v-if="canWork && store.can('sales.discount') && ticket.status !== 'delivered'"
                v-model="discount"
                size="sm"
                type="number"
                min="0"
                step="any"
                dir="ltr"
                class="w-28"
                aria-label="خصم"
                @change="update({ discount: toPiasters(discount) ?? 0 })"
              />
              <span v-else class="num">{{ ticket.discount ? `−${formatMoney(ticket.discount)}` : '—' }}</span>
            </div>
            <div class="flex justify-between border-t border-(--ui-border) pt-2 text-base font-extrabold">
              <span>الإجمالي</span><span class="num">{{ formatMoney(ticket.total) }}</span>
            </div>
            <div v-if="ticket.paid" class="flex justify-between">
              <span>اتدفع</span><span class="num">{{ formatMoney(ticket.paid) }}</span>
            </div>
            <div v-if="ticket.credit" class="flex justify-between">
              <span>آجل</span><span class="num">{{ formatMoney(ticket.credit) }}</span>
            </div>
            <div class="flex justify-between text-lg font-extrabold" :class="ticket.due > 0 ? 'text-warning' : ''">
              <span>{{ ticket.due < 0 ? 'يرجع للعميل' : 'الباقي' }}</span><span class="num">{{ formatMoney(Math.abs(ticket.due)) }}</span>
            </div>
          </div>
          <p v-if="ticket.status === 'delivered' && ticket.commission !== null && ticket.commission_rule" class="mt-3 text-sm text-(--ui-text-muted)">
            عمولة {{ ticket.technician_name }}: <span class="font-bold text-(--ui-text) num">{{ formatMoney(ticket.commission) }}</span> ({{ ticket.commission_rule }})
          </p>
          <p v-if="ticket.warranty_until" class="mt-3 text-sm" :class="ticket.under_warranty ? 'text-success' : 'text-(--ui-text-muted)'">
            <UIcon name="i-lucide-shield-check" class="size-4 align-middle" />
            ضمان {{ ticket.warranty_days }} يوم — لحد {{ formatDate(ticket.warranty_until) }}
          </p>
        </UCard>

        <UCard v-if="canWork && ticket.status !== 'delivered'">
          <UFormField label="ملاحظة على التذكرة">
            <UTextarea v-model="note" :rows="2" class="w-full" />
          </UFormField>
          <UButton size="sm" class="mt-2" label="إضافة" :disabled="!note.trim()" @click="update({ note }).then(() => { note = '' })" />
        </UCard>
      </div>
    </div>

    <RepairsStatusModal v-model:open="statusOpen" :ticket="ticket" :preset="statusPreset" @changed="onChanged" />
    <RepairsDeliverModal v-model:open="deliverOpen" :ticket="ticket" @delivered="onChanged" />
    <RepairsOutsourceModal v-model:open="outsourceOpen" :ticket="ticket" @saved="onOutsourced" />
    <PrintSheet v-if="printing" page-size="80mm auto">
      <RepairsIntakeReceipt :ticket="ticket" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { PriceCheckItem, RepairOptions, RepairTicket, TicketStatus } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const id = computed(() => String(route.params.id))
const canWork = computed(() => store.can('repairs.update_status'))
const canDeliver = computed(() => store.can('repairs.deliver'))
const messages = useMessages()
const canOutsource = computed(() => store.can('repairs.update_status') && store.can('shop_orders.place'))
const outsourceOpen = ref(false)

function notify(t: RepairTicket) {
  messages.sendTicket(t)
  if (t.status === 'ready' && data.value?.data) {
    data.value.data.ready_notified_at = new Date().toISOString()
  }
}
const shop = computed(() => store.session ? { name: store.session.tenant.name, phone: store.session.tenant.phone ?? null } : null)

const [{ data }, { data: optionsData }] = await Promise.all([
  useAsyncData(`repair-${id.value}`, () => api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`)),
  useAsyncData('repair-options', () => api<{ data: RepairOptions }>('/repairs/options')),
])
const ticket = computed(() => data.value?.data)
const options = computed(() => optionsData.value?.data)

const labor = ref('')
const discount = ref('')
const note = ref('')
watch(ticket, (t) => {
  labor.value = t ? String(t.labor / 100) : ''
  discount.value = t?.discount ? String(t.discount / 100) : ''
}, { immediate: true })

function toLocalInput(iso: string) {
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const saving = ref(false)
async function update(body: Record<string, unknown>) {
  saving.value = true
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`, { method: 'PATCH', body })
    data.value = res
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}

// Diagnosis
const editingDiagnosis = ref(false)
const diagnosis = reactive({ fault_ids: [] as number[], note: '' })
function startDiagnosis() {
  diagnosis.fault_ids = (ticket.value?.diagnosed_faults ?? ticket.value?.reported_faults ?? []).map(f => f.id)
  diagnosis.note = ticket.value?.diagnosis_note ?? ''
  editingDiagnosis.value = true
}
async function saveDiagnosis() {
  await update({ diagnosed_fault_ids: diagnosis.fault_ids, diagnosis_note: diagnosis.note || null })
  editingDiagnosis.value = false
}

// Parts
const partTerm = ref('')
const partResults = ref<PriceCheckItem[]>([])
let partTimer: ReturnType<typeof setTimeout> | undefined
watch(partTerm, (value) => {
  clearTimeout(partTimer)
  if (value.trim().length < 2) {
    partResults.value = []
    return
  }
  partTimer = setTimeout(async () => {
    partResults.value = (await api<{ data: PriceCheckItem[] }>('/inventory/price-check', { query: { q: value.trim() } })).data
  }, 250)
})
onBeforeUnmount(() => clearTimeout(partTimer))

async function addPart(item: PriceCheckItem) {
  partTerm.value = ''
  partResults.value = []
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/parts`, { method: 'POST', body: { variant_id: item.id, qty: 1 } })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
function addFirstPart() {
  const hit = partResults.value.find(p => p.exact_barcode) ?? partResults.value[0]
  if (hit) {
    addPart(hit)
  }
}
async function removePart(partId: number) {
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/parts/${partId}`, { method: 'DELETE' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

// Status and delivery
const statusOpen = ref(false)
const statusPreset = ref<TicketStatus | null>(null)
const deliverOpen = ref(false)
function openStatus(preset: TicketStatus | null) {
  statusPreset.value = preset
  statusOpen.value = true
}
function onOutsourced(updated: RepairTicket) {
  data.value = { data: updated }
}

function onChanged(updated: RepairTicket) {
  data.value = { data: updated }
  // Offer the matching WhatsApp message right away.
  toast.add({
    color: 'success',
    title: `بقت «${updated.status_label}»`,
    actions: [{ label: 'ابعت للعميل واتساب', icon: 'i-lucide-message-circle', onClick: () => notify(updated) }],
  })
}

const returning = ref(false)
async function warrantyReturn() {
  returning.value = true
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/warranty`, { method: 'POST', body: {} })
    await navigateTo(`/repairs/${res.data.id}`)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    returning.value = false
  }
}

const { printing, print } = usePrint()
</script>

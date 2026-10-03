<template>
  <div v-if="s" class="space-y-6">
    <PageHeader :title="`شحنة ${s.reference}`" :description="`${s.contact.name} · لفرع ${s.branch.name}${s.original_amount ? ' · ' + s.original_amount : ''}`">
      <UButton to="/imports" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الاستيراد" />
      <UButton v-if="canManage" color="neutral" variant="outline" icon="i-lucide-banknote" label="دفعة للمورد" @click="paying = true" />
    </PageHeader>

    <!-- Where it is -->
    <UCard>
      <div class="flex flex-wrap items-center gap-3">
        <UBadge size="lg" :color="IMPORT_STATUS_COLORS[s.status]" variant="subtle" :label="s.status_label" />
        <UBadge v-if="s.late" color="error" variant="soft" label="متأخرة عن ميعادها" />
        <p class="text-sm text-(--ui-text-muted)">
          اتطلبت {{ formatDate(s.ordered_on) }}<template v-if="s.expected_on">
            · متوقعة {{ formatDate(s.expected_on) }}
          </template><template v-if="s.received_at">
            · اتستلمت {{ formatDate(s.received_at, true) }} ({{ s.received_by_name }})
          </template>
        </p>
        <p v-if="s.cancel_reason" class="text-sm">
          السبب: {{ s.cancel_reason }}
        </p>
      </div>
      <div v-if="open && canManage" class="mt-4 flex flex-wrap items-center gap-2">
        <UButton
          v-for="step in IMPORT_STEPS"
          :key="step.value"
          size="sm"
          :color="step.value === s.status ? 'primary' : 'neutral'"
          :variant="step.value === s.status ? 'solid' : 'outline'"
          :label="step.label"
          @click="step.value !== s.status && move(step.value)"
        />
        <UButton class="ms-auto" icon="i-lucide-package-check" label="استلام في المخزن" @click="receiveOpen = true" />
        <UButton color="error" variant="ghost" icon="i-lucide-x" label="الغي" @click="cancelOpen = true" />
      </div>
      <p v-if="s.notes" class="mt-3 whitespace-pre-line text-sm text-(--ui-text-muted)">
        {{ s.notes }}
      </p>
    </UCard>

    <div class="grid gap-3 sm:grid-cols-4">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          البضاعة
        </p>
        <p class="num text-xl font-extrabold">
          {{ formatMoney(s.goods_total) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          المصاريف
        </p>
        <p class="num text-xl font-extrabold">
          {{ formatMoney(s.costs_total) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          الإجمالي
        </p>
        <p class="num text-xl font-extrabold">
          {{ formatMoney(s.total) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          اتدفع للمورد عليها
        </p>
        <p class="num text-xl font-extrabold">
          {{ formatMoney(s.paid) }}
        </p>
      </UCard>
    </div>

    <!-- Items -->
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          الأصناف
        </h2>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                الصنف
              </th>
              <th class="p-3 text-end">
                الكمية
              </th>
              <th class="p-3 text-end">
                السعر
              </th>
              <th class="p-3 text-end">
                الإجمالي
              </th>
              <template v-if="s.status === 'received'">
                <th class="p-3 text-end">
                  وصل سليم
                </th>
                <th class="p-3 text-end">
                  التكلفة النهائية
                </th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in s.items" :key="item.id" class="border-t border-(--ui-border)">
              <td class="p-3 font-semibold">
                {{ item.name }}
                <p v-if="item.serials.length" class="num text-xs font-normal text-(--ui-text-muted)" dir="ltr">
                  {{ item.serials.join(' · ') }}
                </p>
              </td>
              <td class="num p-3 text-end">
                {{ item.qty }}
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(item.unit_price) }}
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(item.line_total) }}
              </td>
              <template v-if="s.status === 'received'">
                <td class="num p-3 text-end" :class="item.received_qty < item.qty ? 'font-bold text-(--ui-error)' : ''">
                  {{ item.received_qty }}<span v-if="item.damaged_qty" class="text-xs"> (+{{ item.damaged_qty }} تالف)</span>
                </td>
                <td class="num p-3 text-end font-bold">
                  {{ formatMoney(item.landed_unit_cost) }}
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Costs -->
      <UCard>
        <template #header>
          <h2 class="font-bold">
            المصاريف
          </h2>
        </template>
        <ul class="divide-y divide-(--ui-border) text-sm">
          <li v-for="c in s.costs" :key="c.id" class="flex items-center gap-3 py-2">
            <span class="flex-1">{{ c.kind_label }}<span v-if="c.contact" class="text-(--ui-text-muted)"> · لـ {{ c.contact.name }}</span><span v-if="c.note" class="text-(--ui-text-muted)"> · {{ c.note }}</span></span>
            <span class="num font-bold">{{ formatMoney(c.amount) }}</span>
            <UButton v-if="open && canManage" size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${c.kind_label}`" @click="removeCost(c.id)" />
          </li>
          <li v-if="!s.costs.length" class="py-2 text-(--ui-text-muted)">
            لسه مفيش مصاريف.
          </li>
        </ul>
        <form v-if="open && canManage" class="mt-3 grid gap-2 sm:grid-cols-2" @submit.prevent="addCost">
          <USelect v-model="cost.kind" :items="[...IMPORT_COST_KINDS]" aria-label="النوع" />
          <UInput v-model="cost.amount" type="number" min="0" step="any" dir="ltr" placeholder="المبلغ (ج)" aria-label="المبلغ" />
          <USelect v-model="cost.contact_id" :items="[{ value: 'none', label: 'اتدفعت على طول' }, ...payees.map(c => ({ value: c.id, label: `على حساب ${c.name}` }))]" aria-label="على حساب مين" />
          <UInput v-model="cost.note" maxlength="255" placeholder="ملاحظة" aria-label="ملاحظة" />
          <UButton type="submit" class="sm:col-span-2" block icon="i-lucide-plus" label="ضيف المصروف" :disabled="!(toPiasters(cost.amount) ?? 0)" />
        </form>
      </UCard>

      <!-- Papers -->
      <UCard>
        <template #header>
          <h2 class="font-bold">
            المرفقات
          </h2>
        </template>
        <ul class="divide-y divide-(--ui-border) text-sm">
          <li v-for="a in s.attachments" :key="a.id" class="flex items-center gap-3 py-2">
            <UIcon :name="a.mime === 'application/pdf' ? 'i-lucide-file-text' : 'i-lucide-image'" class="size-4" />
            <button type="button" class="flex-1 truncate text-start hover:underline" @click="openAttachment(a.id)">
              {{ a.kind_label }} · {{ a.name }}
            </button>
            <UButton v-if="canManage" size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${a.name}`" @click="removeAttachment(a.id)" />
          </li>
          <li v-if="!s.attachments.length" class="py-2 text-(--ui-text-muted)">
            الفاتورة، قايمة التعبئة، البوليصة، صور البضاعة…
          </li>
        </ul>
        <div v-if="canManage" class="mt-3 flex flex-wrap items-center gap-2">
          <USelect v-model="attachmentKind" :items="[...IMPORT_ATTACHMENT_KINDS]" class="w-40" aria-label="نوع المرفق" />
          <label class="cursor-pointer">
            <span class="inline-flex items-center gap-1 rounded-(--ui-radius) border border-(--ui-border) px-3 py-1.5 text-sm hover:bg-(--ui-bg-elevated)">
              <UIcon name="i-lucide-upload" class="size-4" /> ارفع ملف (PDF أو صورة)
            </span>
            <input type="file" accept="application/pdf,image/*" class="sr-only" @change="upload">
          </label>
        </div>
      </UCard>
    </div>

    <!-- Payments for this shipment -->
    <UCard v-if="s.payments.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          الدفعات على الشحنة
        </h2>
      </template>
      <ul class="divide-y divide-(--ui-border) text-sm">
        <li v-for="p in s.payments" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-2" :class="p.reversed ? 'opacity-50 line-through' : ''">
          <span class="num">{{ formatDate(p.paid_on) }}</span>
          <span class="flex-1">{{ p.method_label }}<template v-if="p.reference"> · <span class="num" dir="ltr">{{ p.reference }}</span></template></span>
          <span class="num font-bold">{{ formatMoney(p.amount) }}</span>
        </li>
      </ul>
    </UCard>

    <!-- Receive -->
    <UModal v-model:open="receiveOpen" title="استلام الشحنة في المخزن" :ui="{ content: 'sm:max-w-3xl' }">
      <template #body>
        <div class="space-y-4">
          <p class="text-sm text-(--ui-text-muted)">
            اكتب اللي وصل سليم والتالف لكل صنف (الباقي يتحسب ناقص). التكلفة النهائية = سعر الشراء + نصيب الصنف من المصاريف ({{ s.allocation === 'qty' ? 'بالعدد' : 'بالقيمة' }}).
          </p>
          <div v-for="(item, i) in s.items" :key="item.id" class="space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <div class="flex flex-wrap items-center gap-3 text-sm">
              <p class="min-w-0 flex-1 font-bold">
                {{ item.name }} <span class="num font-normal text-(--ui-text-muted)">(مطلوب {{ item.qty }})</span>
              </p>
              <UFormField v-if="!item.track_serial" label="سليم">
                <UInput v-model="receipt[item.id]!.received" type="number" min="0" dir="ltr" class="w-24" />
              </UFormField>
              <UFormField label="تالف">
                <UInput v-model="receipt[item.id]!.damaged" type="number" min="0" dir="ltr" class="w-24" />
              </UFormField>
              <p class="text-xs">
                التكلفة النهائية: <span class="num font-bold">{{ formatMoney(preview[i] ?? 0) }}</span>
              </p>
            </div>
            <InventorySerialsInput v-if="item.track_serial" v-model="receipt[item.id]!.serials" size="sm" />
          </div>
          <USwitch v-model="claim" label="طالب المورد بالنواقص والتالف" description="قيمتهم بتتخصم من حسابه، ومش بتتحمّل على الأصناف اللي وصلت." />
          <UAlert v-if="receiveError" color="error" variant="subtle" :title="receiveError" />
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="receiveOpen = false" />
          <UButton icon="i-lucide-package-check" label="استلمت ودخّل المخزن" :loading="busy" @click="receive" />
        </div>
      </template>
    </UModal>

    <UModal v-model:open="cancelOpen" title="إلغاء الشحنة" description="البضاعة والمصاريف هتتشال من حسابات الجهات.">
      <template #body>
        <UFormField label="السبب">
          <UInput v-model="reason" class="w-full" maxlength="255" />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelOpen = false" />
          <UButton color="error" label="الغي الشحنة" :disabled="!reason.trim()" :loading="busy" @click="cancel" />
        </div>
      </template>
    </UModal>

    <ImportsPaymentModal v-model:open="paying" :contact-id="s.contact.id" :contact-name="s.contact.name ?? ''" :shipments="[{ id: s.id, reference: s.reference }]" :shipment-id="s.id" @saved="refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { ImportContact, ImportShipmentDetail, ImportShipmentStatus } from '~/types/api'

definePageMeta({ permission: 'imports.view', module: 'imports' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canManage = computed(() => store.can('imports.manage'))

const { data, refresh } = await useAsyncData(`import-shipment-${route.params.id}`, () => api<{ data: ImportShipmentDetail }>(`/imports/shipments/${route.params.id}`))
const s = computed(() => data.value?.data ?? null)
const open = computed(() => !!s.value && !['received', 'cancelled'].includes(s.value.status))
const { data: contactData } = await useAsyncData('import-contacts-all', () => api<{ data: ImportContact[] }>('/imports/contacts'))
const payees = computed(() => (contactData.value?.data ?? []).filter(c => c.is_active))

async function run(fn: () => Promise<{ data: ImportShipmentDetail }>) {
  try {
    data.value = await fn()
    return true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    return false
  }
}
const url = `/imports/shipments/${route.params.id}`
const move = (status: ImportShipmentStatus) => run(() => api(`${url}/status`, { method: 'POST', body: { status } }))

const cost = reactive({ kind: 'shipping', amount: '', contact_id: 'none', note: '' })
async function addCost() {
  if (await run(() => api(`${url}/costs`, { method: 'POST', body: { kind: cost.kind, amount: toPiasters(cost.amount), contact_id: cost.contact_id === 'none' ? null : cost.contact_id, note: cost.note.trim() || null } }))) {
    Object.assign(cost, { amount: '', note: '' })
  }
}
const removeCost = (id: string) => run(() => api(`${url}/costs/${id}`, { method: 'DELETE' }))

const attachmentKind = ref('invoice')
async function upload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) {
    return
  }
  const body = new FormData()
  body.append('kind', attachmentKind.value)
  body.append('file', file.type.startsWith('image/') ? await shrinkPhoto(file) : file, file.name)
  await run(() => api(`${url}/attachments`, { method: 'POST', body }))
  ;(e.target as HTMLInputElement).value = ''
}
async function openAttachment(id: string) {
  try {
    const blob = await api<Blob>(`${url}/attachments/${id}`, { responseType: 'blob' })
    window.open(URL.createObjectURL(blob), '_blank')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
async function removeAttachment(id: string) {
  try {
    await api(`${url}/attachments/${id}`, { method: 'DELETE' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

// Receiving
const receiveOpen = ref(false)
const receipt = reactive<Record<string, { received: string, damaged: string, serials: string[] }>>({})
const claim = ref(true)
watch(receiveOpen, (isOpen) => {
  if (isOpen) {
    for (const item of s.value?.items ?? []) {
      receipt[item.id] = { received: String(item.qty), damaged: '0', serials: [] }
    }
    receiveError.value = null
  }
})
const receivedOf = (id: string, trackSerial: boolean) => (trackSerial ? receipt[id]?.serials.length ?? 0 : Number(receipt[id]?.received) || 0)
const preview = computed(() => landedCosts(
  (s.value?.items ?? []).map(i => ({ qty: i.qty, unitPrice: i.unit_price, received: receivedOf(i.id, i.track_serial) })),
  s.value?.costs_total ?? 0,
  s.value?.allocation ?? 'value',
  claim.value,
))
const busy = ref(false)
const receiveError = ref<string | null>(null)
async function receive() {
  busy.value = true
  receiveError.value = null
  try {
    data.value = await api(`${url}/receive`, {
      method: 'POST',
      body: {
        claim: claim.value,
        lines: (s.value?.items ?? []).map(i => ({
          item_id: i.id,
          received: receivedOf(i.id, i.track_serial),
          damaged: Number(receipt[i.id]?.damaged) || 0,
          serials: i.track_serial ? receipt[i.id]?.serials : undefined,
        })),
      },
    })
    receiveOpen.value = false
    toast.add({ color: 'success', title: 'الشحنة دخلت المخزن بالتكلفة النهائية' })
  }
  catch (e) {
    receiveError.value = apiErrorMessage(e)
  }
  finally {
    busy.value = false
  }
}

const cancelOpen = ref(false)
const reason = ref('')
async function cancel() {
  busy.value = true
  if (await run(() => api(`${url}/cancel`, { method: 'POST', body: { reason: reason.value.trim() } }))) {
    cancelOpen.value = false
  }
  busy.value = false
}

const paying = ref(false)
</script>

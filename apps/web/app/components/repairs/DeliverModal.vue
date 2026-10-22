<template>
  <UModal v-model:open="open" :title="`تسليم ${ticket?.device_name ?? ''}`" :description="ticket?.status === 'rejected' ? 'الجهاز راجع من غير إصلاح.' : 'راجع الحساب، وحدد الضمان.'">
    <template #body>
      <div v-if="ticket" class="space-y-4">
        <dl class="space-y-1 rounded-(--ui-radius) bg-(--ui-bg-muted) p-3 text-sm">
          <div class="flex justify-between">
            <dt>المصنعية</dt><dd class="num">
              {{ formatMoney(ticket.labor) }}
            </dd>
          </div>
          <div v-if="ticket.parts_total" class="flex justify-between">
            <dt>قطع الغيار</dt><dd class="num">
              {{ formatMoney(ticket.parts_total) }}
            </dd>
          </div>
          <div v-if="ticket.discount" class="flex justify-between">
            <dt>خصم</dt><dd class="num">
              −{{ formatMoney(ticket.discount) }}
            </dd>
          </div>
          <div class="flex justify-between font-bold">
            <dt>الإجمالي</dt><dd class="num">
              {{ formatMoney(ticket.total) }}
            </dd>
          </div>
          <div v-if="ticket.paid" class="flex justify-between">
            <dt>اتدفع (عربون)</dt><dd class="num">
              −{{ formatMoney(ticket.paid) }}
            </dd>
          </div>
          <div class="flex justify-between border-t border-(--ui-border) pt-1 text-lg font-extrabold">
            <dt>{{ ticket.due < 0 ? 'يرجع للعميل' : 'المطلوب' }}</dt>
            <dd class="num" :class="ticket.due < 0 ? 'text-warning' : ''">
              {{ formatMoney(Math.abs(ticket.due)) }}
            </dd>
          </div>
        </dl>
        <div v-if="ticket.status === 'ready' && withWarranty" class="space-y-3">
          <p class="font-bold">
            الضمان <span class="text-sm font-normal text-(--ui-text-muted)">(بالأيام — صفر = من غير ضمان)</span>
          </p>
          <UFormField v-for="w in items" :key="w.key" :label="w.name">
            <div class="flex flex-wrap items-center gap-2">
              <UInput v-model="w.days" type="number" min="0" max="730" dir="ltr" class="w-20" :aria-label="`ضمان ${w.name} بالأيام`" />
              <UButton v-for="d in [0, 7, 14, 30, 90, 180]" :key="d" size="xs" color="neutral" :variant="Number(w.days) === d ? 'soft' : 'outline'" :label="d ? (d === 180 ? '6 شهور' : `${d} يوم`) : 'مفيش'" @click="w.days = String(d)" />
            </div>
          </UFormField>
          <p v-if="ticket.warranty_defaults?.warranty_terms" class="text-xs text-(--ui-text-muted)">
            الشروط: {{ ticket.warranty_defaults.warranty_terms }}
          </p>
        </div>
        <p v-if="ticket.due < 0" class="text-sm text-(--ui-text-muted)">
          العربون أكتر من الحساب؛ الفرق هيرجع كاش من درجك.
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </div>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton v-if="(ticket?.due ?? 0) > 0" icon="i-lucide-banknote" :label="`تحصيل ${formatMoney(ticket?.due ?? 0)}`" @click="payOpen = true" />
        <UButton v-else icon="i-lucide-check" label="تسليم" :loading="saving" @click="deliver([])" />
      </div>
    </template>
  </UModal>

  <PosPaymentModal
    v-model:open="payOpen"
    :total="ticket?.due ?? 0"
    :methods="methods"
    :credit-available="null"
    :customer-name="ticket?.customer_name ?? null"
    :loading="saving"
    :error="error"
    @pay="deliver"
  />
</template>

<script setup lang="ts">
import type { RepairTicket } from '~/types/api'

const props = defineProps<{ ticket: RepairTicket | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ delivered: [ticket: RepairTicket] }>()

const api = useApi()
const store = useSessionStore()
// Item by item: each part fitted (its product's days, else the labor's) and the labor (the shop's default).
const items = ref<{ key: string, part_id: number | null, name: string, days: string }[]>([])
function resetItems() {
  const labor = props.ticket?.warranty_defaults?.labor_warranty_days ?? 30
  items.value = [
    ...(props.ticket?.parts ?? []).map(p => ({ key: `p${p.id}`, part_id: p.id, name: p.name, days: String(p.warranty_days ?? labor) })),
    ...(props.ticket && props.ticket.labor > 0 ? [{ key: 'labor', part_id: null, name: 'المصنعية', days: String(labor) }] : []),
  ]
  if (!items.value.length) {
    items.value = [{ key: 'labor', part_id: null, name: 'الصيانة', days: String(labor) }]
  }
}
const payOpen = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

// آجل only for whoever may give credit (the customer always has an account on a ticket).
const methods = computed(() => [...CASH_METHODS, ...(store.can('customers.credit') && store.hasFeature('customers.credit_sales') ? [{ value: 'credit', label: 'آجل' }] : [])])
// The owner's «الضمان على الصيانة»: off, devices go back without a warranty.
const withWarranty = computed(() => store.hasFeature('repairs.warranty'))

watch(open, (isOpen) => {
  if (isOpen) {
    error.value = null
    needsShift.value = false
    resetItems()
  }
})

async function deliver(payments: { method: string, amount: number }[]) {
  if (!props.ticket) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${props.ticket.id}/deliver`, {
      method: 'POST',
      body: { payments, warranties: props.ticket.status === 'ready' && withWarranty.value ? items.value.map(w => ({ part_id: w.part_id, days: Number(w.days) || 0 })) : [] },
    })
    payOpen.value = false
    open.value = false
    emit('delivered', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
    payOpen.value = false
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="t" class="space-y-6">
    <PageHeader :title="`تحويل ${t.reference}`" :description="`${t.from.name} ← ${t.to.name} · ${formatDate(t.created_at, true)}`">
      <UButton to="/transfers" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="التحويلات" />
      <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع إذن التحويل" @click="print" />
    </PageHeader>

    <UCard>
      <div class="flex flex-wrap items-center gap-3">
        <UBadge size="lg" :color="TRANSFER_COLORS[t.status]" variant="subtle" :label="t.status_label" />
        <p v-if="t.notes" class="text-sm">
          {{ t.notes }}
        </p>
        <p v-if="t.cancel_reason" class="text-sm text-(--ui-text-muted)">
          السبب: {{ t.cancel_reason }}
        </p>
        <p v-if="t.value !== null && t.status !== 'requested'" class="ms-auto text-sm">
          قيمة البضاعة (بالتكلفة): <span class="num font-bold">{{ formatMoney(t.value) }}</span>
        </p>
      </div>
      <ol class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
        <li><span class="text-(--ui-text-muted)">اتطلب:</span> {{ formatDate(t.created_at, true) }}<template v-if="t.requested_by_name"> · {{ t.requested_by_name }}</template></li>
        <li v-if="t.shipped_at"><span class="text-(--ui-text-muted)">خرج:</span> {{ formatDate(t.shipped_at, true) }} · {{ t.shipped_by_name }}</li>
        <li v-if="t.received_at"><span class="text-(--ui-text-muted)">اتستلم:</span> {{ formatDate(t.received_at, true) }} · {{ t.received_by_name }}</li>
      </ol>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          {{ action === 'ship' ? 'ابعت من «' + t.from.name + '»' : action === 'receive' ? 'استلم في «' + t.to.name + '»' : 'الأصناف' }}
        </h2>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="item in t.items" :key="item.id" class="space-y-2 px-4 py-3">
          <div class="flex flex-wrap items-center gap-3 text-sm">
            <p class="min-w-0 flex-1 font-bold">
              {{ item.name }}
            </p>
            <span class="text-(--ui-text-muted)">مطلوب <span class="num">{{ item.qty_requested }}</span></span>
            <span v-if="t.status !== 'requested'" class="text-(--ui-text-muted)">اتبعت <span class="num">{{ item.qty_shipped }}</span></span>
            <span v-if="t.status === 'received'" :class="item.qty_received < item.qty_shipped ? 'font-bold text-(--ui-error)' : 'text-(--ui-text-muted)'">
              وصل <span class="num">{{ item.qty_received }}</span>
            </span>
            <template v-if="action && !(item.track_serial && action === 'ship')">
              <UInput
                v-if="!(action === 'receive' && item.qty_shipped === 0)"
                v-model="form[item.id]!.qty"
                type="number"
                min="0"
                dir="ltr"
                class="w-24"
                :aria-label="`كمية ${item.name}`"
              />
            </template>
          </div>
          <!-- Shipping a phone: scan the units that leave. Receiving: tick the ones that arrived. -->
          <InventorySerialsInput v-if="action === 'ship' && item.track_serial" v-model="form[item.id]!.serials" size="sm" :required="item.qty_requested" />
          <div v-else-if="action === 'receive' && item.track_serial && item.serials.length" class="flex flex-wrap gap-3">
            <UCheckbox
              v-for="s in item.serials"
              :key="s"
              :model-value="form[item.id]!.serials.includes(s)"
              :label="s"
              class="num"
              @update:model-value="v => toggleSerial(item.id, s, !!v)"
            />
          </div>
          <p v-else-if="item.serials.length" class="num text-xs text-(--ui-text-muted)" dir="ltr">
            {{ item.serials.join(' · ') }}
          </p>
        </li>
      </ul>
    </UCard>

    <UAlert v-if="error" color="error" variant="subtle" :title="error" />
    <div class="flex flex-wrap justify-end gap-2">
      <UButton v-if="t.status === 'requested' || t.status === 'shipped'" color="error" variant="ghost" icon="i-lucide-x" label="إلغاء التحويل" @click="cancelOpen = true" />
      <UButton v-if="action === 'ship'" icon="i-lucide-truck" label="خرج من الفرع" :loading="busy" @click="submit('ship')" />
      <UButton v-if="action === 'receive'" icon="i-lucide-package-check" label="استلمت" :loading="busy" @click="submit('receive')" />
    </div>

    <UModal v-model:open="cancelOpen" title="إلغاء التحويل" :description="t.status === 'shipped' ? 'البضاعة هترجع لمخزون «' + t.from.name + '» زي ما خرجت.' : undefined">
      <template #body>
        <UFormField label="السبب">
          <UInput v-model="reason" class="w-full" maxlength="255" />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelOpen = false" />
          <UButton color="error" label="ألغي التحويل" :disabled="!reason.trim()" :loading="busy" @click="cancel" />
        </div>
      </template>
    </UModal>

    <PrintSheet v-if="printing" page-size="A4" margin="14mm">
      <TransfersNotePrint :transfer="t" :shop-name="store.session?.tenant.name ?? ''" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { StockTransfer } from '~/types/api'

definePageMeta({ permission: 'transfers.manage', module: 'multi_branch' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const { printing, print } = usePrint()

const { data } = await useAsyncData(`transfer-${route.params.id}`, () => api<{ data: StockTransfer }>(`/transfers/${route.params.id}`))
const t = computed(() => data.value?.data ?? null)
const action = computed<'ship' | 'receive' | null>(() => (t.value?.can_ship ? 'ship' : t.value?.can_receive ? 'receive' : null))

// Prefilled: ship what was asked; receive everything that left.
const form = reactive<Record<string, { qty: string, serials: string[] }>>({})
watch(t, (transfer) => {
  for (const item of transfer?.items ?? []) {
    form[item.id] = action.value === 'receive'
      ? { qty: String(item.qty_shipped), serials: [...item.serials] }
      : { qty: String(item.qty_requested), serials: [] }
  }
}, { immediate: true })

function toggleSerial(itemId: string, serial: string, on: boolean) {
  const line = form[itemId]!
  line.serials = on ? [...line.serials, serial] : line.serials.filter(s => s !== serial)
  line.qty = String(line.serials.length)
}

const busy = ref(false)
const error = ref<string | null>(null)
async function submit(kind: 'ship' | 'receive') {
  busy.value = true
  error.value = null
  try {
    const lines = (t.value?.items ?? []).map(item => ({
      item_id: item.id,
      qty: item.track_serial ? form[item.id]!.serials.length : Number(form[item.id]!.qty) || 0,
      serials: item.track_serial ? form[item.id]!.serials : undefined,
    }))
    data.value = await api<{ data: StockTransfer }>(`/transfers/${route.params.id}/${kind}`, { method: 'POST', body: { lines } })
    toast.add({ color: 'success', title: kind === 'ship' ? 'التحويل خرج من الفرع' : 'اتستلم التحويل ودخل المخزون' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    busy.value = false
  }
}

const cancelOpen = ref(false)
const reason = ref('')
async function cancel() {
  busy.value = true
  try {
    data.value = await api<{ data: StockTransfer }>(`/transfers/${route.params.id}/cancel`, { method: 'POST', body: { reason: reason.value.trim() } })
    cancelOpen.value = false
    toast.add({ color: 'success', title: 'التحويل اتلغى' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = false
  }
}
</script>

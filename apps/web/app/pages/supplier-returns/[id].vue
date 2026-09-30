<template>
  <div v-if="note" class="max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/supplier-returns?tab=notes" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold">
          إذن مرتجع <span class="num">{{ note.reference }}</span>
          <UBadge :color="noteStatusColor(note.status)" variant="subtle" class="ms-2 align-middle">
            {{ note.status_label }}
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          <NuxtLink v-if="note.source.type === 'supplier' && store.can('suppliers.view')" :to="`/suppliers/${note.source.id}`" class="font-bold hover:text-primary">{{ note.source.name }}</NuxtLink>
          <span v-else class="font-bold">{{ note.source.name }}</span>
          ({{ note.source.type === 'shop' ? 'محل شريك' : 'مورد' }})
          · <span class="num">{{ formatDate(note.created_at) }}</span>
          <span v-if="note.created_by_name"> · {{ note.created_by_name }}</span>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة" @click="print" />
        <UButton v-if="store.can('messages.send')" color="neutral" variant="outline" icon="i-lucide-message-circle" label="واتساب" @click="sendReturnNote(note)" />
      </div>
    </div>

    <div v-if="canManage && open" class="app-card flex flex-wrap items-center gap-3 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
      <p class="min-w-0 flex-1 text-sm">
        <template v-if="note.status === 'pending'">
          جهّز القطع وابعتها للمورد (أو ابعتله الإذن على واتساب)، وبعد ما يستلمها سجّل «اتسلّم».
        </template>
        <template v-else>
          اتسلّم للمورد <span class="num">{{ formatDate(note.sent_at, true) }}</span>. لما يرد سجّل قبل إيه ورفض إيه.
        </template>
      </p>
      <UButton color="neutral" variant="ghost" icon="i-lucide-x" label="إلغاء الإذن" :loading="busy === 'cancel'" @click="cancel" />
      <UButton v-if="note.status === 'pending'" color="neutral" variant="outline" icon="i-lucide-truck" label="اتسلّم للمورد" :loading="busy === 'send'" @click="send" />
      <UButton icon="i-lucide-clipboard-check" label="رد المورد" @click="settleOpen = true" />
    </div>

    <div v-if="!open && note.status !== 'cancelled'" class="grid gap-3 sm:grid-cols-3">
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          اتقبل
        </p>
        <p class="text-xl font-extrabold text-success num">
          {{ formatMoney(note.accepted_value) }}
        </p>
        <p v-if="note.resolution" class="text-xs text-(--ui-text-muted)">
          {{ resolutionLabel }}
        </p>
      </div>
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          اترفض
        </p>
        <p class="text-xl font-extrabold num" :class="note.rejected_value ? 'text-error' : ''">
          {{ formatMoney(note.rejected_value) }}
        </p>
        <p v-if="note.rejected_action" class="text-xs text-(--ui-text-muted)">
          {{ note.rejected_action === 'restock' ? 'رجع المخزون' : 'اتعدم' }}
        </p>
      </div>
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          اتسوّى
        </p>
        <p class="font-bold num">
          {{ formatDate(note.settled_at, true) }}
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          {{ note.settled_by_name }}<span v-if="note.settle_note"> · {{ note.settle_note }}</span>
        </p>
      </div>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <ul>
        <li v-for="item in note.items" :key="item.id" class="flex flex-wrap items-start gap-3 border-t border-(--ui-border) p-3 first:border-t-0 sm:px-4">
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              <span class="num">{{ item.qty }}</span> × {{ item.name }}
            </p>
            <p v-if="item.serial" class="num text-xs text-(--ui-text-muted)" dir="ltr">
              IMEI {{ item.serial }}
            </p>
            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-(--ui-text-muted)">
              <UBadge color="warning" variant="subtle" size="sm">
                {{ item.reason_label }}
              </UBadge>
              <span>{{ item.origin_label }}</span>
              <span v-if="item.source_doc">· <span class="num">{{ item.source_doc }}</span></span>
            </div>
            <p v-if="item.note" class="mt-1 text-xs">
              {{ item.note }}
            </p>
          </div>
          <div class="text-end">
            <p v-if="item.value !== null" class="font-bold num">
              {{ formatMoney(item.value) }}
            </p>
            <p v-if="item.unit_cost !== null && item.qty > 1" class="text-xs text-(--ui-text-muted) num">
              {{ formatMoney(item.unit_cost) }} للقطعة
            </p>
            <UBadge v-if="item.outcome" :color="outcomeColor(item.outcome)" variant="subtle" size="sm" class="mt-1">
              {{ outcomeLabel(item) }}
            </UBadge>
          </div>
        </li>
      </ul>
      <div class="flex justify-between border-t border-(--ui-border) bg-(--ui-bg-elevated) p-3 font-extrabold sm:px-4">
        <span>الإجمالي · <span class="num">{{ note.units }}</span> قطعة</span>
        <span v-if="note.total_cost !== null" class="num">{{ formatMoney(note.total_cost) }}</span>
      </div>
    </UCard>

    <SupplierReturnsSettleModal v-model:open="settleOpen" :note="note" @saved="n => { data = { data: n } }" />

    <PrintSheet v-if="printing" page-size="A4" margin="14mm">
      <SupplierReturnsNotePrint :note="note" :shop-name="shopName" :with-cost="store.can('products.view_cost')" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { BinItem, ReturnNote } from '~/utils/supplierReturns'

definePageMeta({ permission: 'supplier_returns.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const { sendReturnNote } = useMessages()
const { printing, print } = usePrint()
const canManage = computed(() => store.can('supplier_returns.manage'))
const shopName = computed(() => store.session?.tenant.name ?? '')

const { data } = await useAsyncData(`supplier-return-${route.params.id}`, () => api<{ data: ReturnNote }>(`/supplier-returns/notes/${route.params.id}`))
const note = computed(() => data.value?.data)
const open = computed(() => note.value?.status === 'pending' || note.value?.status === 'sent')
const settleOpen = ref(false)

const resolutionLabel = computed(() => {
  const r = RESOLUTIONS.find(x => x.value === note.value?.resolution)?.label ?? ''
  const m = REFUND_METHODS.find(x => x.value === note.value?.refund_method)?.label
  return m ? `${r} (${m})` : r
})

function outcomeColor(outcome: NonNullable<BinItem['outcome']>) {
  return outcome === 'accepted' ? 'success' : outcome === 'partial' ? 'warning' : 'error'
}
function outcomeLabel(item: BinItem): string {
  switch (item.outcome) {
    case 'accepted': return 'اتقبلت'
    case 'partial': return `اتقبل ${item.accepted_qty} من ${item.qty}`
    default: return 'اترفضت'
  }
}

const busy = ref<string | null>(null)
async function transition(action: 'send' | 'cancel') {
  busy.value = action
  try {
    data.value = await api<{ data: ReturnNote }>(`/supplier-returns/notes/${note.value!.id}/${action}`, { method: 'POST' })
    toast.add({ color: 'success', title: action === 'send' ? 'اتسجّل إنه اتسلّم للمورد' : 'اتلغى الإذن والقطع رجعت السلة' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
const send = () => transition('send')
function cancel() {
  if (window.confirm('تلغي الإذن؟ القطع هترجع سلة المرتجعات.')) {
    transition('cancel')
  }
}
</script>

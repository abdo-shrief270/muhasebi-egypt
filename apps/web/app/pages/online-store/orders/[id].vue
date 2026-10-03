<template>
  <div v-if="order" class="space-y-6">
    <PageHeader :title="`طلب ${order.reference}`" :description="`${formatDate(order.created_at, true)} · من المتجر الأونلاين`">
      <UButton to="/online-store/orders" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الطلبات" />
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
      <div class="space-y-6">
        <UCard>
          <div class="flex flex-wrap items-center gap-3">
            <UBadge size="lg" :color="ONLINE_ORDER_COLORS[order.status]" variant="subtle" :label="order.status_label" />
            <p v-if="order.sale_reference" class="text-sm">
              الفاتورة <span class="num font-bold" dir="ltr">{{ order.sale_reference }}</span>
            </p>
            <p v-if="order.cancel_reason" class="text-sm text-(--ui-text-muted)">
              السبب: {{ order.cancel_reason }}
            </p>
          </div>
          <div v-if="isOpen" class="mt-4 flex flex-wrap gap-2">
            <UButton
              v-if="canInvoice"
              icon="i-lucide-receipt"
              label="حوّل لفاتورة"
              :to="`/pos?order=${order.id}`"
            />
            <UButton
              v-for="s in forward"
              :key="s.value"
              :color="canInvoice ? 'neutral' : 'primary'"
              :variant="canInvoice ? 'outline' : 'solid'"
              :icon="STATUS_ICONS[s.value]"
              :label="ACTIONS[s.value] ?? s.label"
              :loading="moving === s.value"
              @click="move(s.value)"
            />
            <UButton v-if="canCancel" color="error" variant="ghost" icon="i-lucide-x" label="إلغاء الطلب" @click="cancelOpen = true" />
          </div>
          <p v-if="isOpen && branchNote" class="mt-3 text-sm text-(--ui-warning)">
            {{ branchNote }}
          </p>
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h2 class="font-bold">
              الأصناف
            </h2>
          </template>
          <ul class="divide-y divide-(--ui-border)">
            <li v-for="item in order.items" :key="item.id" class="flex items-center gap-3 px-4 py-3 text-sm">
              <span class="num w-8 font-bold">{{ item.qty }}×</span>
              <span class="flex-1">{{ item.name }}</span>
              <span class="num text-(--ui-text-muted)">{{ formatMoney(item.unit_price) }}</span>
              <span class="num w-24 text-end font-semibold">{{ formatMoney(item.line_total) }}</span>
            </li>
          </ul>
          <dl class="space-y-1 border-t border-(--ui-border) px-4 py-3 text-sm">
            <div class="flex justify-between">
              <dt>الأصناف</dt><dd class="num">
                {{ formatMoney(order.subtotal) }}
              </dd>
            </div>
            <div v-if="order.fulfilment === 'delivery'" class="flex justify-between">
              <dt>التوصيل</dt><dd class="num">
                {{ order.delivery_fee ? formatMoney(order.delivery_fee) : 'ببلاش' }}
              </dd>
            </div>
            <div class="flex justify-between text-base font-extrabold">
              <dt>الإجمالي</dt><dd class="num">
                {{ formatMoney(order.total) }}
              </dd>
            </div>
          </dl>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-bold">
              اللي حصل
            </h2>
          </template>
          <ol class="space-y-3">
            <li v-for="(e, i) in order.timeline" :key="i" class="flex gap-3 text-sm">
              <UIcon :name="STATUS_ICONS[e.status] ?? 'i-lucide-circle'" class="mt-0.5 size-4 text-(--ui-text-muted)" />
              <div>
                <p class="font-semibold">
                  {{ e.label }}<span v-if="e.note" class="font-normal"> — {{ e.note }}</span>
                </p>
                <p class="text-(--ui-text-muted)">
                  {{ formatDate(e.at, true) }} · {{ e.user_name ?? 'الزبون' }}
                </p>
              </div>
            </li>
          </ol>
        </UCard>
      </div>

      <aside class="space-y-4">
        <UCard>
          <div class="space-y-3 text-sm">
            <div>
              <p class="text-base font-bold">
                {{ order.customer_name }}
              </p>
              <a v-if="order.customer_phone" :href="`tel:${order.customer_phone}`" class="num text-(--ui-primary)" dir="ltr">{{ localPhone(order.customer_phone) }}</a>
            </div>
            <div class="flex flex-wrap gap-2">
              <UButton v-if="order.customer_phone && canMessage" color="success" variant="soft" icon="i-lucide-message-circle" :label="messageLabel" @click="messages.sendOnlineOrder(order)" />
              <UButton v-if="order.customer_id && store.can('customers.view')" :to="`/customers/${order.customer_id}`" color="neutral" variant="outline" icon="i-lucide-user" label="حساب العميل" />
            </div>
            <USeparator />
            <p><span class="text-(--ui-text-muted)">الاستلام:</span> {{ onlineOrderFulfilment(order) }}</p>
            <p v-if="order.address" class="whitespace-pre-line">
              <span class="text-(--ui-text-muted)">العنوان:</span> {{ order.address }}
            </p>
            <p v-if="order.notes" class="whitespace-pre-line">
              <span class="text-(--ui-text-muted)">ملاحظات:</span> {{ order.notes }}
            </p>
            <p><span class="text-(--ui-text-muted)">الدفع:</span> {{ onlineOrderPayment(order) }}</p>
            <UsedDevicesSecureImage v-if="order.has_proof" :src="`/online-store/orders/${order.id}/proof`" alt="صورة التحويل" class="h-48 w-full" />
            <p v-if="order.payment === 'transfer'" class="text-xs text-(--ui-text-muted)">
              اتأكد إن التحويل وصل قبل ما تسلّم الطلب، وفي الفاتورة اختار طريقة الدفع اللي اتحوّل بيها.
            </p>
            <UButton v-if="order.track_url" :to="order.track_url" target="_blank" color="neutral" variant="link" icon="i-lucide-external-link" label="صفحة متابعة الزبون" class="px-0" />
          </div>
        </UCard>
      </aside>
    </div>

    <UModal v-model:open="cancelOpen" title="إلغاء الطلب" description="الزبون هيشوف السبب في صفحة الطلب.">
      <template #body>
        <div class="space-y-3">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="r in REASONS" :key="r" size="sm" color="neutral" :variant="reason === r ? 'solid' : 'outline'" :label="r" @click="reason = r" />
          </div>
          <UFormField label="السبب">
            <UInput v-model="reason" class="w-full" maxlength="255" />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelOpen = false" />
          <UButton color="error" label="ألغي الطلب" :disabled="!reason.trim()" :loading="moving === 'cancelled'" @click="move('cancelled', reason)" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { OnlineOrderDetail, OnlineOrderStatus } from '~/types/api'

definePageMeta({ permission: 'online_store.orders', module: 'online_store' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const messages = useMessages()

const { data, refresh } = await useAsyncData(`online-order-${route.params.id}`, () => api<{ data: OnlineOrderDetail }>(`/online-store/orders/${route.params.id}`))
const order = computed(() => data.value?.data ?? null)

const STATUS_ICONS: Record<string, string> = {
  new: 'i-lucide-inbox',
  confirmed: 'i-lucide-phone-call',
  preparing: 'i-lucide-package',
  out_for_delivery: 'i-lucide-truck',
  ready: 'i-lucide-package-check',
  delivered: 'i-lucide-check-check',
  cancelled: 'i-lucide-ban',
}
const ACTIONS: Partial<Record<OnlineOrderStatus, string>> = {
  confirmed: 'أكّدت مع الزبون',
  preparing: 'بدأت أجهّز',
  out_for_delivery: 'خرج للتوصيل',
  ready: 'جاهز للاستلام',
}
const REASONS = ['مش متوفر', 'الرقم مش بيرد', 'الزبون لغى', 'التوصيل مش متاح للمنطقة']

const isOpen = computed(() => !!order.value && !['delivered', 'cancelled'].includes(order.value.status))
const forward = computed(() => (order.value?.next ?? []).filter(s => s.value !== 'cancelled'))
const canCancel = computed(() => (order.value?.next ?? []).some(s => s.value === 'cancelled'))
const canInvoice = computed(() => isOpen.value && store.can('sales.sell') && order.value?.status !== 'new')
const canMessage = computed(() => order.value?.status !== 'new' && store.can('messages.send'))
const messageLabel = computed(() => order.value?.status === 'cancelled' ? 'ابعت الإلغاء على واتساب' : `ابعت «${order.value?.status_label}» على واتساب`)
const branchNote = computed(() => {
  const o = order.value
  const current = store.session?.current_branch_id
  if (!o?.branch_id || !current || o.branch_id === current) {
    return null
  }
  const name = store.session?.branches.find(b => b.id === o.branch_id)?.name
  return `الطلب ده من مخزون ${name ?? 'فرع تاني'}؛ اعمل الفاتورة وانت في الفرع ده عشان المخزون يتخصم منه.`
})

const moving = ref<OnlineOrderStatus | null>(null)
const cancelOpen = ref(false)
const reason = ref('')

async function move(to: OnlineOrderStatus, why?: string) {
  moving.value = to
  try {
    const res = await api<{ data: OnlineOrderDetail }>(`/online-store/orders/${route.params.id}/status`, { method: 'POST', body: { status: to, reason: why } })
    data.value = res
    cancelOpen.value = false
    reason.value = ''
    toast.add({
      color: 'success',
      title: `الطلب بقى «${res.data.status_label}»`,
      actions: res.data.customer_phone && store.can('messages.send')
        ? [{ label: 'بلّغ الزبون على واتساب', icon: 'i-lucide-message-circle', onClick: () => messages.sendOnlineOrder(res.data) }]
        : undefined,
    })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    await refresh()
  }
  finally {
    moving.value = null
  }
}
</script>

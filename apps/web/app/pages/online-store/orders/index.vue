<template>
  <div class="space-y-6">
    <PageHeader title="طلبات المتجر" description="الطلبات اللي الزباين عملوها من المتجر الأونلاين. أكّد مع الزبون، جهّز، وحوّل الطلب لفاتورة لما يتسلّم.">
      <UButton v-if="store.can('online_store.manage')" to="/online-store" color="neutral" variant="outline" icon="i-lucide-settings" label="إعدادات المتجر" />
    </PageHeader>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="h-10 rounded-full px-4 font-semibold transition"
        :class="filter === tab.value ? 'bg-primary text-white' : 'bg-(--ui-bg-elevated) hover:bg-(--ui-border)'"
        @click="filter = tab.value"
      >
        {{ tab.label }}
        <span v-if="tab.value === 'new' && counts.new" class="num ms-1 rounded-full bg-(--ui-warning) px-1.5 text-xs text-white">{{ counts.new }}</span>
      </button>
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم، موبايل، أو رقم الطلب" class="ms-auto w-full sm:w-64" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="status === 'pending' && !orders.length" class="space-y-3 p-6">
        <USkeleton v-for="i in 4" :key="i" class="h-12 w-full" />
      </div>
      <div v-else-if="!orders.length" class="space-y-3 py-16 text-center">
        <UIcon name="i-lucide-shopping-bag" class="size-10 text-(--ui-text-muted)" />
        <p class="font-semibold">
          {{ filter === 'open' ? 'مفيش طلبات مستنية' : 'مفيش طلبات هنا' }}
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          شارك لينك المتجر على فيسبوك وواتساب، والطلبات هتوصلك هنا بإشعار.
        </p>
      </div>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="order in orders" :key="order.id">
          <NuxtLink :to="`/online-store/orders/${order.id}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-(--ui-bg-muted)">
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                <span class="num" dir="ltr">{{ order.reference }}</span> · {{ order.customer_name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                <span class="num">{{ order.items_count }}</span> قطعة · {{ onlineOrderFulfilment(order) }} · {{ order.payment === 'transfer' ? 'تحويل' : 'كاش' }}
                · {{ timeAgo(order.created_at) }}
              </p>
            </div>
            <span class="num font-bold">{{ formatMoney(order.total) }}</span>
            <UBadge :color="ONLINE_ORDER_COLORS[order.status]" variant="subtle" :label="order.status_label" />
          </NuxtLink>
        </li>
      </ul>
    </UCard>

    <div v-if="lastPage > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="total" :items-per-page="30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { OnlineOrder } from '~/types/api'

definePageMeta({ permission: 'online_store.orders', module: 'online_store' })

const api = useApi()
const store = useSessionStore()
const tabs = [
  { value: 'open', label: 'المفتوحة' },
  { value: 'new', label: 'جديدة' },
  { value: 'delivered', label: 'اتسلّمت' },
  { value: 'cancelled', label: 'اتلغت' },
] as const
const filter = ref<(typeof tabs)[number]['value']>('open')
const q = ref('')
const debounced = ref('')
let typing: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(typing)
  typing = setTimeout(() => {
    debounced.value = value.trim()
  }, 300)
})
const page = ref(1)
watch([filter, debounced], () => {
  page.value = 1
})

type Page = { data: OnlineOrder[], meta: { total: number, page: number, last_page: number, counts: { new: number, open: number } } }
const { data, status, refresh } = await useAsyncData('online-orders', () => api<Page>('/online-store/orders', {
  query: { status: filter.value, q: debounced.value || undefined, page: page.value },
}), { watch: [filter, debounced, page] })
const orders = computed(() => data.value?.data ?? [])
const counts = computed(() => data.value?.meta.counts ?? { new: 0, open: 0 })
const total = computed(() => data.value?.meta.total ?? 0)
const lastPage = computed(() => data.value?.meta.last_page ?? 1)

// New orders come in all day: look again every 30 s while the page is shown.
const timer = setInterval(() => document.visibilityState === 'visible' && refresh(), 30_000)
onBeforeUnmount(() => {
  clearInterval(timer)
  clearTimeout(typing)
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold">
          الطلبات بين المحلات
        </h1>
        <p class="text-(--ui-text-muted)">
          اطلب بضاعة أو شغل صيانة من محل شريك، وتابع التجهيز لحد الاستلام.
        </p>
      </div>
      <div class="flex gap-2">
        <UButton to="/shop-orders/partners" color="neutral" variant="outline" icon="i-lucide-users" label="الشركاء" />
        <UButton v-if="store.can('shop_orders.place')" to="/shop-orders/new" icon="i-lucide-plus" label="طلب جديد" />
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="h-10 rounded-full px-4 font-semibold transition"
        :class="box === tab.value ? 'bg-primary text-white' : 'bg-(--ui-bg-elevated) hover:bg-(--ui-border)'"
        @click="box = tab.value"
      >
        <UIcon :name="tab.icon" class="size-4 align-middle me-1" />
        {{ tab.label }}
      </button>
      <USwitch v-model="openOnly" label="المفتوحة بس" class="ms-auto" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="status === 'pending'" class="p-6 space-y-3">
        <USkeleton v-for="i in 4" :key="i" class="h-12 w-full" />
      </div>

      <div v-else-if="!orders.length" class="py-16 text-center space-y-3">
        <UIcon name="i-lucide-inbox" class="size-10 text-(--ui-text-muted)" />
        <p class="font-semibold">
          {{ box === 'incoming' ? 'مفيش طلبات واردة لسه' : 'مطلبتش حاجة من محلات تانية لسه' }}
        </p>
        <UButton v-if="box === 'outgoing'" to="/shop-orders/new" variant="soft" label="اعمل أول طلب" />
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              الطلب
            </th>
            <th class="p-3 text-start font-bold">
              {{ box === 'incoming' ? 'من محل' : 'إلى محل' }}
            </th>
            <th class="p-3 text-start font-bold">
              النوع
            </th>
            <th class="p-3 text-start font-bold">
              أصناف
            </th>
            <th class="p-3 text-start font-bold">
              الإجمالي
            </th>
            <th class="p-3 text-start font-bold">
              الحالة
            </th>
            <th class="p-3 text-start font-bold">
              التاريخ
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="order in orders"
            :key="order.id"
            class="border-t border-(--ui-border) hover:bg-(--ui-bg-muted) cursor-pointer"
            @click="navigateTo(`/shop-orders/${order.id}`)"
          >
            <td class="p-3 num text-start font-semibold">
              {{ order.reference }}
            </td>
            <td class="p-3 font-bold">
              {{ order.counterparty?.name }}
            </td>
            <td class="p-3">
              <UBadge color="neutral" variant="subtle">
                {{ order.type_label }}
              </UBadge>
            </td>
            <td class="p-3 num text-start">
              {{ order.items_count }}
            </td>
            <td class="p-3 font-bold">
              <span class="num">{{ formatMoney(order.total) }}</span>
            </td>
            <td class="p-3">
              <UBadge :color="shopOrderStatusColor[order.status]" variant="subtle">
                {{ order.status_label }}
              </UBadge>
            </td>
            <td class="p-3 text-(--ui-text-muted)">
              {{ formatDate(order.created_at, true) }}
            </td>
          </tr>
        </tbody>
      </table>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { ShopOrder } from '~/types/api'

definePageMeta({ module: 'shop_orders', permission: 'shop_orders.view' })

const api = useApi()
const store = useSessionStore()
const tabs = [
  { value: 'incoming', label: 'الطلبات الواردة', icon: 'i-lucide-inbox' },
  { value: 'outgoing', label: 'طلباتي من محلات تانية', icon: 'i-lucide-send' },
] as const

const box = ref<'incoming' | 'outgoing'>('incoming')
const openOnly = ref(false)

const { data, status } = await useAsyncData(
  'shop-orders',
  () => api<{ data: ShopOrder[] }>('/shop-orders', { query: { box: box.value, open: openOnly.value ? 1 : 0 } }),
  { watch: [box, openOnly] },
)
const orders = computed(() => data.value?.data ?? [])
</script>

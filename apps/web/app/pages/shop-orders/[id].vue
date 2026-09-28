<template>
  <div v-if="order" class="space-y-6 max-w-5xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <UButton to="/shop-orders" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square />
        <div>
          <div class="flex items-center gap-2">
            <h1 class="num text-2xl font-extrabold">
              {{ order.reference }}
            </h1>
            <UBadge :color="shopOrderStatusColor[order.status]" variant="subtle" size="lg">
              {{ order.status_label }}
            </UBadge>
          </div>
          <p class="text-(--ui-text-muted)">
            {{ order.type_label }} · {{ order.my_party === 'buyer' ? 'طلب منك' : 'طلب واردلك' }} · {{ formatDate(order.created_at, true) }}
          </p>
        </div>
      </div>
      <UButton
        v-if="order.counterparty"
        color="neutral"
        variant="outline"
        icon="i-lucide-message-circle"
        label="WhatsApp"
        :to="whatsappLink(order.counterparty.phone, whatsappText)"
        target="_blank"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div class="space-y-6">
        <UCard :ui="{ body: 'p-0 sm:p-0', header: 'font-bold' }">
          <template #header>
            {{ order.type === 'repair' ? 'الأجهزة' : 'الأصناف' }}
          </template>
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  {{ order.type === 'repair' ? 'الجهاز / العطل' : 'الصنف' }}
                </th>
                <th class="p-3 text-start font-bold">
                  الكمية
                </th>
                <th class="p-3 text-start font-bold w-40">
                  سعر القطعة
                </th>
                <th class="p-3 text-start font-bold">
                  الإجمالي
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id" class="border-t border-(--ui-border)">
                <td class="p-3">
                  <p class="font-bold">
                    {{ item.device_model ? `${item.device_model} — ` : '' }}{{ item.description }}
                  </p>
                  <p v-if="item.imei" class="text-xs text-(--ui-text-muted)">
                    IMEI <span class="num">{{ item.imei }}</span>
                  </p>
                </td>
                <td class="p-3 num text-start">
                  {{ item.quantity }}
                </td>
                <td class="p-3">
                  <UInput v-if="pricing" v-model.number="prices[item.id]" type="number" min="0" dir="ltr" size="sm" placeholder="بالجنيه" />
                  <span v-else class="num">{{ formatMoney(item.unit_price) }}</span>
                </td>
                <td class="p-3 font-bold">
                  <span class="num">{{ item.unit_price !== null ? formatMoney(item.unit_price * item.quantity) : '—' }}</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t border-(--ui-border)">
                <td colspan="3" class="p-3 font-bold">
                  المطلوب
                </td>
                <td class="p-3 text-lg font-extrabold">
                  <span class="num">{{ formatMoney(pricing ? pricedTotal : order.total) }}</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </UCard>

        <UCard v-if="order.notes">
          <p class="text-sm text-(--ui-text-muted)">
            ملاحظات
          </p>
          <p>{{ order.notes }}</p>
        </UCard>

        <UCard v-if="order.allowed_transitions.length">
          <UFormField label="ملاحظة (اختياري)">
            <UInput v-model="note" placeholder="مثلاً: هيوصل مع المندوب الساعة 5" class="w-full" />
          </UFormField>
          <div class="mt-4 flex flex-wrap gap-2">
            <UButton
              v-for="t in order.allowed_transitions"
              :key="t.status"
              :icon="shopOrderActionIcon[t.status]"
              :label="actionLabel(t.status, t.label)"
              :color="['rejected', 'cancelled'].includes(t.status) ? 'error' : 'primary'"
              :variant="['rejected', 'cancelled'].includes(t.status) ? 'soft' : 'solid'"
              :loading="busy === t.status"
              @click="move(t.status)"
            />
          </div>
        </UCard>
      </div>

      <div class="space-y-6">
        <UCard>
          <p class="mb-2 text-sm text-(--ui-text-muted)">
            {{ order.my_party === 'buyer' ? 'المحل اللي بيجهّز' : 'المحل اللي طالب' }}
          </p>
          <ShopLine :shop="order.counterparty" />
          <p v-if="order.needed_by" class="mt-3 text-sm">
            <UIcon name="i-lucide-calendar" class="size-4 align-middle" /> محتاجه قبل {{ formatDate(order.needed_by) }}
          </p>
        </UCard>

        <UCard :ui="{ header: 'font-bold' }">
          <template #header>
            المتابعة
          </template>
          <ol class="relative space-y-4 border-s-2 border-(--ui-border) ps-4">
            <li v-for="(a, i) in order.activities" :key="i" class="relative">
              <span class="absolute -start-[23px] top-1.5 size-3 rounded-full ring-4 ring-(--ui-bg)" :class="i === (order.activities?.length ?? 0) - 1 ? 'bg-primary' : 'bg-(--ui-border)'" />
              <p class="font-bold">
                {{ a.to_status_label }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ a.by === order.my_party ? 'انت' : order.counterparty?.name }} · {{ formatDate(a.at, true) }}
              </p>
              <p v-if="a.note" class="mt-1 text-sm">
                {{ a.note }}
              </p>
            </li>
          </ol>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ShopOrder, ShopOrderStatus } from '~/types/api'

definePageMeta({ module: 'shop_orders' })

const api = useApi()
const route = useRoute()
const toast = useToast()
const id = computed(() => String(route.params.id))

const { data, refresh } = await useAsyncData(`shop-order-${id.value}`, () => api<{ data: ShopOrder }>(`/shop-orders/${id.value}`))
const order = computed(() => data.value?.data)

const note = ref('')
const busy = ref<ShopOrderStatus | null>(null)
const prices = reactive<Record<number, number | undefined>>({})

/** The seller prices the items while the order is waiting for acceptance. */
const pricing = computed(() => order.value?.my_party === 'seller' && order.value.status === 'placed')
const pricedTotal = computed(() => (order.value?.items ?? []).reduce(
  (sum, item) => sum + Math.round((prices[item.id] ?? 0) * 100) * item.quantity, 0,
))

const whatsappText = computed(() => `بخصوص الطلب ${order.value?.reference} (${order.value?.status_label})`)

function actionLabel(status: ShopOrderStatus, label: string): string {
  return ({ accepted: 'قبول الطلب', rejected: 'رفض', preparing: 'بدأ التجهيز', ready: 'جاهز', delivered: 'اتسلّم للمحل', completed: 'استلمت الطلب', cancelled: 'إلغاء الطلب' } as Partial<Record<ShopOrderStatus, string>>)[status] ?? label
}

async function move(status: ShopOrderStatus) {
  busy.value = status
  try {
    const body: Record<string, unknown> = { status, note: note.value || null }
    if (status === 'accepted') {
      body.prices = Object.fromEntries(Object.entries(prices)
        .filter(([, v]) => typeof v === 'number' && !Number.isNaN(v))
        .map(([k, v]) => [k, Math.round((v as number) * 100)]))
    }
    await api(`/shop-orders/${id.value}/transition`, { method: 'POST', body })
    note.value = ''
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

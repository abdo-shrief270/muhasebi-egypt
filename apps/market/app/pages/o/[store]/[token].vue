<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <p v-if="!order" class="card p-6 text-center text-muted">
      الطلب ده مش موجود.
    </p>
    <template v-else>
      <div class="card space-y-2 p-5 text-center">
        <p class="text-sm text-muted">
          طلب <span class="num" dir="ltr">{{ order.reference }}</span>
        </p>
        <p class="text-2xl font-bold">
          {{ order.status_label }}
        </p>
        <p v-if="order.cancel_reason" class="text-sm text-warn">
          {{ order.cancel_reason }}
        </p>
        <p v-else-if="order.status === 'new'" class="text-sm text-muted">
          الطلب وصل للمحل، وهيكلّمك يأكده.
        </p>
      </div>

      <ol class="card space-y-3 p-5">
        <li v-for="t in order.timeline" :key="t.at + t.status" class="flex items-center gap-3">
          <span class="size-2.5 rounded-full bg-teal" />
          <span class="flex-1 font-semibold">{{ t.label }}</span>
          <span class="num text-sm text-muted">{{ when(t.at) }}</span>
        </li>
      </ol>

      <div class="card divide-y divide-line">
        <div v-for="i in order.items" :key="i.name" class="flex justify-between px-4 py-2.5 text-sm">
          <span><span class="num">{{ i.qty }}</span> × {{ i.name }}</span>
          <span class="num">{{ formatPrice(i.line_total) }}</span>
        </div>
        <div v-if="order.discount" class="flex justify-between px-4 py-2.5 text-sm text-ok">
          <span>الخصم</span><span class="num">− {{ formatPrice(order.discount) }}</span>
        </div>
        <div v-if="order.fulfilment === 'delivery'" class="flex justify-between px-4 py-2.5 text-sm">
          <span>التوصيل{{ order.zone_name ? ` (${order.zone_name})` : '' }}</span><span class="num">{{ order.delivery_fee ? formatPrice(order.delivery_fee) : 'ببلاش' }}</span>
        </div>
        <div class="flex justify-between px-4 py-3 font-bold">
          <span>الإجمالي</span><span class="num">{{ formatPrice(order.total) }}</span>
        </div>
      </div>
      <p class="text-center text-sm text-muted">
        {{ order.payment === 'transfer' ? 'الدفع: تحويل' : order.fulfilment === 'delivery' ? 'الدفع: كاش عند الاستلام' : 'الدفع: في المحل' }}
      </p>
      <NuxtLink to="/" class="btn-line w-full">
        كمّل تسوّق
      </NuxtLink>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { OrderView } from '~/types'

useSeoMeta({ title: 'متابعة الطلب', robots: 'noindex' })

const route = useRoute()
const { data, refresh } = await useAsyncData(`order:${route.params.token}`, () => marketFetch<{ data: OrderView }>(`/stores/${route.params.store}/orders/${route.params.token}`).then(r => r.data).catch(() => null), { server: false })
const order = computed(() => data.value ?? null)

// It moves as the shop works on it.
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  timer = setInterval(() => refresh(), 30_000)
})
onBeforeUnmount(() => clearInterval(timer))

const when = (iso: string) => new Date(iso).toLocaleString('ar-EG-u-nu-latn', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })
</script>

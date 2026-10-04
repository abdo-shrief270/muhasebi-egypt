<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <div v-if="pending && !order" class="card px-4 py-12 text-center text-muted">
      بنجيب الطلب…
    </div>
    <div v-else-if="!order" class="card space-y-4 px-4 py-12 text-center">
      <p class="text-muted">
        الطلب ده مش موجود.
      </p>
      <NuxtLink :to="place.path()" class="btn-brand">
        ارجع للمتجر
      </NuxtLink>
    </div>

    <template v-else>
      <div class="card space-y-1 p-4 text-center">
        <p v-if="justPlaced" class="mb-3 inline-flex items-center gap-2 rounded-full bg-ok/10 px-3 py-1 text-sm font-bold text-ok">
          <StoreIcon name="check" :size="16" /> طلبك وصل المحل
        </p>
        <h1 class="text-2xl font-extrabold">
          طلب <span class="num" dir="ltr">{{ order.reference }}</span>
        </h1>
        <p class="text-lg font-bold" :class="order.status === 'cancelled' ? 'text-bad' : 'text-brand'">
          {{ order.status_label }}
        </p>
        <p v-if="order.cancel_reason" class="text-sm text-muted">
          السبب: {{ order.cancel_reason }}
        </p>
        <p class="text-xs text-muted">
          {{ formatDateTime(order.created_at) }}
        </p>
      </div>

      <ol v-if="order.status !== 'cancelled'" class="card space-y-0 p-4">
        <li v-for="(step, i) in steps" :key="step.status" class="flex gap-3">
          <div class="flex flex-col items-center">
            <span class="flex size-7 items-center justify-center rounded-full text-white" :class="step.at ? 'bg-brand' : 'bg-line'">
              <StoreIcon v-if="step.at" name="check" :size="14" />
            </span>
            <span v-if="i < steps.length - 1" class="w-0.5 flex-1" :class="steps[i + 1]?.at ? 'bg-brand' : 'bg-line'" />
          </div>
          <div class="pb-5">
            <p class="font-bold" :class="step.at ? '' : 'text-muted'">
              {{ step.label }}
            </p>
            <p v-if="step.at" class="num text-xs text-muted">
              {{ formatDateTime(step.at) }}
            </p>
          </div>
        </li>
      </ol>

      <div class="card divide-y divide-line">
        <div v-for="item in order.items" :key="item.product_id + item.name" class="flex items-center justify-between gap-3 p-3 text-sm">
          <NuxtLink :to="place.path(`/p/${item.product_id}`)" class="min-w-0 flex-1">
            <span class="num">{{ item.qty }} ×</span> {{ item.name }}
          </NuxtLink>
          <span class="num font-bold">{{ formatPrice(item.line_total) }}</span>
        </div>
        <dl class="space-y-1 p-3 text-sm">
          <div class="flex justify-between">
            <dt>الأصناف</dt><dd class="num">
              {{ formatPrice(order.subtotal) }}
            </dd>
          </div>
          <div v-if="order.discount" class="flex justify-between text-ok">
            <dt>كود الخصم <span class="num" dir="ltr">{{ order.coupon_code }}</span></dt><dd class="num">
              − {{ formatPrice(order.discount) }}
            </dd>
          </div>
          <div v-if="order.fulfilment === 'delivery'" class="flex justify-between">
            <dt>التوصيل ({{ order.zone_name }})</dt><dd class="num">
              {{ order.delivery_fee ? formatPrice(order.delivery_fee) : 'ببلاش' }}
            </dd>
          </div>
          <div class="flex justify-between text-lg font-extrabold">
            <dt>الإجمالي</dt><dd class="num text-brand">
              {{ formatPrice(order.total) }}
            </dd>
          </div>
          <p class="text-muted">
            {{ order.fulfilment === 'delivery' ? 'توصيل' : 'استلام من المحل' }} ·
            {{ order.payment === 'transfer' ? 'تحويل (المحل هيراجع التحويل)' : order.fulfilment === 'delivery' ? 'كاش عند الاستلام' : 'كاش في المحل' }}
          </p>
        </dl>
      </div>

      <a v-if="store.whatsapp" :href="whatsappLink(store.whatsapp, `السلام عليكم، بخصوص طلبي ${order.reference} من المتجر.`)" target="_blank" rel="noopener" class="btn-wa w-full">
        <StoreIcon name="whatsapp" :size="18" /> كلّم المحل على واتساب
      </a>
      <p class="text-center text-xs text-muted">
        احفظ الصفحة دي عشان تتابع طلبك.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PlacedOrder } from '~/types'

const slug = useStoreSlug()
const place = useStorePlace()
const route = useRoute()
const home = await useStoreHome()
const store = computed(() => home.value.store)
const token = String(route.params.token ?? '')

const order = ref<PlacedOrder | null>(null)
const pending = ref(true)
const justPlaced = ref(false)

useSeoMeta({ title: 'متابعة الطلب', robots: 'noindex' })

async function load() {
  try {
    order.value = (await $fetch<{ data: PlacedOrder }>(`/api/stores/${slug}/orders/${token}`)).data
  }
  catch {
    // Not found, or the connection dropped: keep what's shown.
  }
  finally {
    pending.value = false
  }
}

const STEPS = ['new', 'confirmed', 'preparing', 'ready', 'delivered'] as const
const LABELS: Record<string, string> = {
  new: 'الطلب وصل المحل',
  confirmed: 'المحل أكد الطلب',
  preparing: 'بيتجهّز',
  out_for_delivery: 'خرج للتوصيل',
  ready: 'جاهز تستلمه',
  delivered: 'اتسلّم',
}
const steps = computed(() => {
  const o = order.value
  if (!o) {
    return []
  }
  const at = new Map(o.timeline.map(t => [t.status, t.at]))
  return STEPS.map(s => (s === 'ready' && o.fulfilment === 'delivery' ? 'out_for_delivery' : s))
    .map(s => ({ status: s, label: LABELS[s] ?? s, at: at.get(s) ?? null }))
})

function formatDateTime(iso: string): string {
  return new Intl.DateTimeFormat('ar-EG-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
}

// The shop moves it along: look again every 30 s while the page is open and shown.
let timer: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await load()
  justPlaced.value = order.value?.status === 'new' && Date.now() - new Date(order.value.created_at).getTime() < 5 * 60_000
  timer = setInterval(() => {
    if (document.visibilityState === 'visible' && order.value && !['delivered', 'cancelled'].includes(order.value.status)) {
      load()
    }
  }, 30_000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

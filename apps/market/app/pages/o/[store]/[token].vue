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

      <div v-if="order.auto_cancelled && order.items.length" class="card space-y-2 p-5">
        <p class="font-bold">
          اطلبها من محل تاني قريب
        </p>
        <NuxtLink v-for="i in order.items" :key="i.name" :to="{ path: '/search', query: { q: i.name } }" class="flex items-center justify-between rounded-xl bg-paper px-3 py-2 text-ink hover:text-ink">
          <span>{{ i.name }}</span><span class="text-sm text-teal">شوف المحلات ←</span>
        </NuxtLink>
      </div>

      <div v-if="order.review" class="card space-y-1 p-5 text-center">
        <p class="text-sm text-muted">
          تقييمك
        </p>
        <StarRating :rating="order.review.rating" />
        <p v-if="order.review.comment" class="text-sm">
          «{{ order.review.comment }}»
        </p>
      </div>
      <form v-else-if="order.can_review" class="card space-y-3 p-5" @submit.prevent="sendReview">
        <p class="font-bold">
          قيّم المحل
        </p>
        <div class="flex justify-center gap-1" role="radiogroup" aria-label="التقييم">
          <button
            v-for="n in 5"
            :key="n"
            type="button"
            role="radio"
            :aria-checked="rating === n"
            :aria-label="`${n} من 5`"
            class="text-4xl leading-none transition-colors"
            :class="n <= rating ? 'text-[#d97706]' : 'text-line-strong'"
            @click="rating = n"
          >
            ★
          </button>
        </div>
        <textarea v-model="comment" rows="2" maxlength="500" placeholder="كلمتين عن التعامل (اختياري)" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" aria-label="رأيك" />
        <p v-if="reviewError" class="text-sm text-warn" role="alert">
          {{ reviewError }}
        </p>
        <button type="submit" class="btn-teal w-full" :disabled="!rating || sending">
          {{ sending ? 'بيتبعت…' : 'ابعت التقييم' }}
        </button>
      </form>

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

const rating = ref(0)
const comment = ref('')
const sending = ref(false)
const reviewError = ref<string | null>(null)
async function sendReview() {
  sending.value = true
  reviewError.value = null
  try {
    await $fetch(`/api/market/stores/${route.params.store}/orders/${route.params.token}/review`, { method: 'POST', body: { rating: rating.value, comment: comment.value || null } })
    await refresh()
  }
  catch (e) {
    reviewError.value = (e as { data?: { message?: string } }).data?.message ?? 'التقييم ما اتبعتش، جرّب تاني.'
  }
  finally {
    sending.value = false
  }
}

const when = (iso: string) => new Date(iso).toLocaleString('ar-EG-u-nu-latn', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })
</script>

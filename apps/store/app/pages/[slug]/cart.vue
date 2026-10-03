<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <h1 class="text-2xl font-extrabold">
      السلة
    </h1>

    <div v-if="!cart.lines.value.length" class="card space-y-4 px-4 py-12 text-center">
      <p class="text-muted">
        السلة فاضية.
      </p>
      <NuxtLink :to="place.path()" class="btn-brand">
        كمّل تسوّق
      </NuxtLink>
    </div>

    <template v-else>
      <ul class="card divide-y divide-line">
        <li v-for="line in cart.lines.value" :key="line.variantId" class="flex items-center gap-3 p-3">
          <img v-if="line.image" :src="line.image" alt="" width="64" height="64" class="size-16 shrink-0 rounded-xl bg-soft object-contain p-1">
          <div class="min-w-0 flex-1">
            <NuxtLink :to="place.path(`/p/${line.productId}`)" class="line-clamp-2 text-sm font-semibold">
              {{ line.name }}
            </NuxtLink>
            <p v-if="line.variant" class="text-xs text-muted">
              {{ line.variant }}
            </p>
            <p class="num text-sm font-bold text-brand">
              {{ formatPrice(line.price * line.qty) }}
            </p>
            <p v-if="gone.has(line.variantId)" class="text-xs font-bold text-bad">
              مش متوفر دلوقتي
            </p>
          </div>
          <div class="flex items-center rounded-xl border border-line">
            <button type="button" class="flex size-9 items-center justify-center" :aria-label="`أقل ${line.name}`" @click="cart.setQty(line.variantId, line.qty - 1)">
              <StoreIcon :name="line.qty === 1 ? 'x' : 'minus'" :size="16" />
            </button>
            <span class="num w-6 text-center text-sm font-bold">{{ line.qty }}</span>
            <button type="button" class="flex size-9 items-center justify-center" :aria-label="`أكتر ${line.name}`" @click="cart.setQty(line.variantId, line.qty + 1)">
              <StoreIcon name="plus" :size="16" />
            </button>
          </div>
        </li>
      </ul>

      <p class="flex items-center justify-between text-lg font-extrabold">
        الإجمالي <span class="num text-brand">{{ formatPrice(cart.total.value) }}</span>
      </p>
      <p v-if="changed" class="rounded-xl bg-warn/10 px-3 py-2 text-sm text-warn">
        الأسعار اتحدثت من المحل.
      </p>

      <form class="card space-y-3 p-4" @submit.prevent="send">
        <h2 class="font-extrabold">
          بياناتك
        </h2>
        <input v-model="name" class="input" autocomplete="name" placeholder="اسمك" maxlength="80" required>
        <div class="flex gap-2">
          <button type="button" class="chip flex-1 justify-center" :class="delivery ? '' : 'chip-on'" @click="delivery = false">
            هستلم من المحل
          </button>
          <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'chip-on' : ''" @click="delivery = true">
            توصيل
          </button>
        </div>
        <input v-if="delivery" v-model="address" class="input" autocomplete="street-address" placeholder="العنوان (المنطقة، الشارع، علامة مميزة)" maxlength="200" required>
        <textarea v-model="notes" class="input" rows="2" placeholder="ملاحظات (اختياري)" maxlength="300" />
        <button type="submit" class="btn-wa w-full" :disabled="!store.whatsapp">
          <StoreIcon name="whatsapp" :size="18" /> ابعت الطلب للمحل على واتساب
        </button>
        <p class="text-xs text-muted">
          الطلب بيروح للمحل رسالة على واتساب، وهو بيأكد معاك السعر والتوصيل.
        </p>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ProductDetail } from '~/types'

const slug = useStoreSlug()
const place = useStorePlace()
const home = await useStoreHome()
const store = computed(() => home.value.store)
const cart = useCart(slug)
const name = ref('')
const address = ref('')
const notes = ref('')
const delivery = ref(false)
const gone = ref(new Set<string>())
const changed = ref(false)

useSeoMeta({ title: 'السلة', robots: 'noindex' })

// Prices and stock from the store now, not from when the line was added.
onMounted(async () => {
  cart.load()
  const ids = [...new Set(cart.lines.value.map(l => l.productId))]
  const products = await Promise.all(ids.map(id => $fetch<{ data: ProductDetail }>(`/api/stores/${slug}/products/${id}`).then(r => r.data).catch(() => null)))
  const variants = new Map(products.flatMap(p => p?.variants ?? []).map(v => [v.id, v]))
  for (const line of cart.lines.value) {
    const v = variants.get(line.variantId)
    if (!v || v.availability === 'out') {
      gone.value.add(line.variantId)
    }
    else if (v.price !== line.price) {
      line.price = v.price
      changed.value = true
    }
  }
})

function send() {
  if (!store.value.whatsapp) {
    return
  }
  const lines = cart.lines.value.map(l => `• ${l.qty} × ${l.name}${l.variant ? ` (${l.variant})` : ''} — ${formatPrice(l.price * l.qty)}`)
  const text = [
    `السلام عليكم، طلب جديد من متجر ${store.value.name}:`,
    ...lines,
    `الإجمالي: ${formatPrice(cart.total.value)}`,
    '',
    `الاسم: ${name.value.trim()}`,
    delivery.value ? `توصيل إلى: ${address.value.trim()}` : 'هستلم من المحل',
    ...(notes.value.trim() ? [`ملاحظات: ${notes.value.trim()}`] : []),
  ].join('\n')
  window.open(whatsappLink(store.value.whatsapp, text), '_blank', 'noopener')
}
</script>

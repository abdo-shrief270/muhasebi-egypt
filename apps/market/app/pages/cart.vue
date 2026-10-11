<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-2xl font-bold sm:text-3xl">
      السلة
    </h1>

    <div v-if="!cart.lines.value.length" class="card space-y-4 px-4 py-12 text-center">
      <p class="text-muted">
        السلة فاضية.
      </p>
      <NuxtLink to="/" class="btn-teal">
        دوّر على حاجة
      </NuxtLink>
    </div>

    <template v-else>
      <p v-if="cart.groups.value.length > 1" class="rounded-xl bg-mint px-3 py-2 text-sm">
        السلة فيها حاجات من <span class="num">{{ cart.groups.value.length }}</span> محلات: كل محل بيوصله طلبه لوحده.
      </p>

      <section v-for="g in cart.groups.value" :key="g.key" class="card overflow-hidden">
        <header class="flex items-center gap-3 border-b border-line bg-paper px-4 py-3">
          <span class="grid size-10 place-items-center rounded-xl bg-mint font-bold text-teal">{{ initial(g.shop.name) }}</span>
          <NuxtLink :to="`/s/${g.shop.slug}`" class="flex-1 font-bold text-ink hover:text-ink">{{ g.shop.name }}</NuxtLink>
          <span class="num font-bold">{{ formatPrice(g.total) }}</span>
        </header>
        <ul class="divide-y divide-line">
          <li v-for="line in g.lines" :key="line.offerId" class="flex items-center gap-3 px-4 py-3">
            <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-sand">
              <img v-if="line.image" :src="line.image" alt="" class="size-full object-cover">
              <MarketIcon v-else name="phone" :size="24" :stroke="1.4" class="text-teal" />
            </span>
            <span class="min-w-0 flex-1">
              <NuxtLink :to="`/p/${encodeURIComponent(line.offerId)}`" class="line-clamp-2 text-sm font-semibold text-ink hover:text-ink">{{ line.title }}</NuxtLink>
              <span class="num block text-sm font-bold">{{ formatPrice(line.price * line.qty) }}</span>
              <span v-if="gone.has(line.offerId)" class="block text-xs font-bold text-warn">مش متاح دلوقتي — شيله وكمّل</span>
              <span v-else-if="changed.has(line.offerId)" class="block text-xs text-warn">السعر اتغيّر من المحل</span>
            </span>
            <span class="flex items-center rounded-xl border border-line">
              <button type="button" class="grid size-9 place-items-center" :aria-label="`أقل ${line.title}`" @click="cart.setQty(line.offerId, line.qty - 1)">{{ line.qty === 1 ? '×' : '−' }}</button>
              <span class="num w-6 text-center text-sm font-bold">{{ line.qty }}</span>
              <button type="button" class="grid size-9 place-items-center" :aria-label="`أكتر ${line.title}`" @click="cart.setQty(line.offerId, line.qty + 1)">+</button>
            </span>
          </li>
        </ul>
        <footer class="space-y-3 border-t border-line p-4">
          <template v-if="shops[g.shop.slug]?.store?.takes_orders">
            <NuxtLink :to="`/checkout/${g.key}`" class="btn-teal w-full py-3 text-base" :class="hasGone(g) ? 'pointer-events-none opacity-50' : ''">
              كمّل الطلب من {{ g.shop.name }}
            </NuxtLink>
            <p class="text-xs text-muted">
              توصيل أو استلام من المحل، والدفع للمحل نفسه.
            </p>
          </template>
          <WhatsappOrder v-else-if="shops[g.shop.slug]" :group="g" :phone="shops[g.shop.slug]!.store?.whatsapp ?? shops[g.shop.slug]!.phone" />
          <p v-else class="text-sm text-muted">
            بنجيب بيانات المحل…
          </p>
        </footer>
      </section>
    </template>

    <section v-if="mine.length" class="space-y-2">
      <h2 class="font-bold">
        طلباتك
      </h2>
      <ul class="card divide-y divide-line">
        <li v-for="o in mine" :key="o.token">
          <NuxtLink :to="`/o/${o.store}/${o.token}`" class="flex items-center justify-between px-4 py-3 text-ink hover:text-ink">
            <span>{{ o.shop }} · <span class="num" dir="ltr">{{ o.reference }}</span></span>
            <span class="text-sm text-teal">تابع الطلب</span>
          </NuxtLink>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { Offer, ShopPage } from '~/types'

useSeoMeta({ title: 'السلة', robots: 'noindex' })

const cart = useCart()
const shops = ref<Record<string, ShopPage>>({})
const gone = ref(new Set<string>())
const changed = ref(new Set<string>())
const mine = ref<{ store: string, token: string, reference: string, shop: string }[]>([])

const hasGone = (g: { lines: { offerId: string }[] }) => g.lines.some(l => gone.value.has(l.offerId))

// Prices and availability from the shops now, not from when a line was added; each shop's way of ordering.
onMounted(async () => {
  cart.load()
  try {
    mine.value = JSON.parse(localStorage.getItem('souq:orders') ?? '[]')
  }
  catch {
    mine.value = []
  }
  const slugs = [...new Set(cart.lines.value.map(l => l.shop.slug))]
  await Promise.all([
    ...slugs.map(async (slug) => {
      const shop = await marketFetch<{ data: ShopPage }>(`/shops/${slug}`).then(r => r.data).catch(() => null)
      if (shop) {
        shops.value = { ...shops.value, [slug]: shop }
      }
    }),
    ...cart.lines.value.map(async (line) => {
      const res = await marketFetch<{ data: { offer: Offer } }>(`/offers/${encodeURIComponent(line.offerId)}`).catch((e: { statusCode?: number }) => (e.statusCode === 404 ? 'gone' : null))
      if (res === 'gone') {
        gone.value = new Set([...gone.value, line.offerId])
      }
      else if (res && res.data.offer.price !== line.price) {
        cart.setPrice(line.offerId, res.data.offer.price)
        changed.value = new Set([...changed.value, line.offerId])
      }
    }),
  ])
})
</script>

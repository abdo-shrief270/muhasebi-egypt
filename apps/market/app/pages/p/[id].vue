<template>
  <div v-if="offer" class="space-y-8">
    <nav class="text-sm text-muted" aria-label="المسار">
      <NuxtLink to="/">السوق</NuxtLink> ›
      <NuxtLink :to="{ path: '/search', query: { category: offer.category ?? undefined } }">{{ offer.category_name ?? 'العروض' }}</NuxtLink>
    </nav>

    <section class="grid gap-6 lg:grid-cols-2">
      <div class="card grid aspect-square place-items-center overflow-hidden">
        <img v-if="photo" :src="photo" :alt="offer.title" class="size-full object-contain">
        <MarketIcon v-else name="phone" :size="96" :stroke="1" class="text-teal" />
      </div>
      <div class="space-y-4">
        <h1 class="text-2xl leading-snug font-bold sm:text-3xl">
          {{ offer.title }}
        </h1>
        <p class="flex flex-wrap items-center gap-2 text-sm">
          <span class="rounded-full bg-sand px-3 py-1">{{ CONDITION[offer.condition] }}<template v-if="offer.grade"> · درجة {{ offer.grade }}</template></span>
          <span class="font-semibold" :class="AVAILABILITY[offer.availability]?.class">{{ AVAILABILITY[offer.availability]?.label }}</span>
          <span v-if="offer.brand" class="text-muted">{{ offer.brand }}</span>
        </p>
        <p class="num text-4xl font-bold">
          {{ formatPrice(offer.price) }}
        </p>
        <NuxtLink v-if="phone" :to="`/phones/${phone.slug}`" class="flex items-center gap-2 rounded-xl bg-mint px-3 py-2 text-sm font-semibold text-teal">
          <MarketIcon name="phone" /> قارن سعر <bdi>{{ phone.name }}</bdi> في كل المحلات ←
        </NuxtLink>

        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center rounded-xl border border-line-strong bg-white">
            <button type="button" class="grid size-11 place-items-center text-lg" aria-label="أقل" @click="qty = Math.max(1, qty - 1)">
              −
            </button>
            <span class="num w-8 text-center font-bold">{{ qty }}</span>
            <button type="button" class="grid size-11 place-items-center text-lg" aria-label="أكتر" @click="qty = Math.min(offer.availability === 'low' ? 2 : 50, qty + 1)">
              +
            </button>
          </div>
          <button type="button" class="btn-ink h-11 flex-1 text-base" @click="addToCart">
            أضف للسلة
          </button>
        </div>
        <p v-if="added" class="rounded-xl bg-mint px-3 py-2 text-sm" role="status">
          اتضافت للسلة. <NuxtLink to="/cart" class="font-semibold">روح للسلة</NuxtLink> أو كمّل تسوّق.
        </p>

        <div class="card space-y-3 p-4">
          <NuxtLink :to="`/s/${offer.shop.slug}`" class="flex items-center gap-3 text-ink hover:text-ink">
            <span class="grid size-11 place-items-center rounded-xl bg-mint font-bold text-teal">{{ initial(offer.shop.name) }}</span>
            <span class="flex-1">
              <span class="block font-bold">{{ offer.shop.name }}</span>
              <span class="block text-sm text-muted">
                {{ [offer.place.area, offer.place.governorate_label].filter(Boolean).join('، ') }}
                <template v-if="offer.distance_km !== null"> · <span class="num">{{ formatKm(offer.distance_km) }}</span> ({{ tripTime(offer.distance_km) }})</template>
              </span>
            </span>
          </NuxtLink>
          <div class="flex flex-wrap gap-2">
            <a v-if="shop?.phone" :href="whatsappLink(shop.phone, `السلام عليكم، شفت «${offer.title}» بـ ${formatPrice(offer.price)} في سوق محاسبي. لسه متاح؟`)" target="_blank" rel="noopener" class="btn-teal flex-1">
              <MarketIcon name="whatsapp" /> اسأل المحل
            </a>
            <a v-if="shop?.phone" :href="`tel:${localPhone(shop.phone)}`" class="btn-line">
              <MarketIcon name="call" /> اتصل
            </a>
            <a v-if="offer.location" :href="directionsLink(offer.location)" target="_blank" rel="noopener" class="btn-line">
              <MarketIcon name="route" /> الطريق
            </a>
            <a v-if="shop?.store_url" :href="shop.store_url" target="_blank" rel="noopener" class="btn-line">
              <MarketIcon name="store" /> متجر المحل
            </a>
          </div>
          <p class="text-xs text-muted">
            السعر والمتاح من مخزون المحل وبيتحدّثوا كل دقيقة. الدفع للمحل نفسه.
          </p>
        </div>

        <p v-if="offer.description" class="leading-relaxed whitespace-pre-line text-[#33494A]">
          {{ offer.description }}
        </p>
        <p v-if="models.length" class="text-sm text-muted">
          متوافق مع: {{ models.join('، ') }}
        </p>
      </div>
    </section>

    <section v-if="elsewhere.length" class="space-y-3">
      <h2 class="text-xl font-bold">
        نفس الحاجة في محلات تانية
      </h2>
      <div class="card overflow-hidden">
        <OfferRow v-for="o in elsewhere" :key="o.id" :offer="o" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { Offer, OfferDetail, Phone, ShopPage } from '~/types'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { query } = usePlace()

const { data, error } = await useAsyncData(`offer:${id.value}`, () => marketFetch<{ data: { offer: OfferDetail, elsewhere: Offer[], phone?: Phone | null } }>(`/offers/${encodeURIComponent(id.value)}`, query.value).then(r => r.data), { watch: [query] })
if (error.value && !data.value) {
  throw createError({ statusCode: (error.value as { statusCode?: number }).statusCode ?? 404, fatal: true })
}
const offer = computed(() => data.value?.offer ?? null)
const elsewhere = computed(() => data.value?.elsewhere ?? [])
const phone = computed(() => data.value?.phone ?? null)
const { data: shopData } = await useAsyncData(`offer-shop:${id.value}`, () => (offer.value?.shop.slug ? marketFetch<{ data: ShopPage }>(`/shops/${offer.value.shop.slug}`).then(r => r.data).catch(() => null) : Promise.resolve(null)))
const shop = computed(() => shopData.value ?? null)

const cart = useCart()
const qty = ref(1)
const added = ref(false)
function addToCart() {
  const o = offer.value
  if (!o?.shop.slug) {
    return
  }
  cart.add({ offerId: o.id, shop: { slug: o.shop.slug, name: o.shop.name ?? '' }, title: o.title, image: o.image, price: o.price }, qty.value)
  added.value = true
}

const photo = computed(() => {
  const images = offer.value?.images
  const first = Array.isArray(images) ? images[0] : null
  return (first && (first['800'] ?? first['1600'] ?? Object.values(first)[0])) ?? offer.value?.image ?? null
})
const models = computed(() => {
  const m = offer.value?.models
  return Array.isArray(m) ? m : (typeof m === 'string' && m ? m.split(/[,،]\s*/) : [])
})

const base = String(useRuntimeConfig().public.marketUrl).replace(/\/$/, '')
useSeoMeta({
  title: () => `${offer.value?.title ?? ''} بـ ${offer.value ? formatPrice(offer.value.price) : ''} — ${offer.value?.shop.name ?? ''}`,
  description: () => `${offer.value?.title} ${offer.value?.condition === 'used' ? 'مستعمل' : 'جديد'} في ${offer.value?.shop.name}${offer.value?.place.area ? `، ${offer.value.place.area}` : ''}. السعر ${offer.value ? formatPrice(offer.value.price) : ''}، ${AVAILABILITY[offer.value?.availability ?? 'in']?.label}.`,
  ogImage: () => (photo.value ? `${base}${photo.value}` : undefined),
})
useHead(() => ({
  script: offer.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Product',
          'name': offer.value.title,
          ...(offer.value.brand ? { brand: { '@type': 'Brand', 'name': offer.value.brand } } : {}),
          ...(photo.value ? { image: `${base}${photo.value}` } : {}),
          'offers': {
            '@type': 'Offer',
            'price': (offer.value.price / 100).toFixed(2),
            'priceCurrency': 'EGP',
            'availability': 'https://schema.org/InStock',
            'itemCondition': offer.value.condition === 'used' ? 'https://schema.org/UsedCondition' : 'https://schema.org/NewCondition',
            'seller': { '@type': 'Store', 'name': offer.value.shop.name },
          },
        }),
      }]
    : [],
}))
</script>

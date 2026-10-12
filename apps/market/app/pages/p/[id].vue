<template>
  <div v-if="offer" class="space-y-8">
    <nav class="text-sm text-muted" aria-label="المسار">
      <NuxtLink to="/">السوق</NuxtLink> ›
      <NuxtLink :to="{ path: '/search', query: { category: offer.category ?? undefined } }">{{ offer.category_name ?? 'العروض' }}</NuxtLink>
    </nav>

    <section class="grid gap-6 lg:grid-cols-2">
      <div class="space-y-2">
        <div class="card grid aspect-square place-items-center overflow-hidden">
          <img v-if="shown" :src="shown" :alt="offer.title" class="size-full object-contain">
          <MarketIcon v-else name="phone" :size="96" :stroke="1" class="text-teal" />
        </div>
        <div v-if="gallery.length > 1" class="flex gap-2 overflow-x-auto" role="group" aria-label="الصور">
          <button
            v-for="(src, i) in gallery"
            :key="src"
            type="button"
            class="size-16 shrink-0 overflow-hidden rounded-xl border-2"
            :class="shown === src ? 'border-teal' : 'border-transparent'"
            :aria-label="`صورة ${i + 1}`"
            @click="current = src"
          >
            <img :src="src" alt="" class="size-full object-cover">
          </button>
        </div>
      </div>
      <div class="space-y-4">
        <h1 class="text-2xl leading-snug font-bold sm:text-3xl">
          {{ offer.title }}
        </h1>
        <p class="flex flex-wrap items-center gap-2 text-sm">
          <span class="rounded-full bg-sand px-3 py-1">{{ CONDITION[offer.condition] }}<template v-if="offer.grade"> · {{ GRADE_LABELS[offer.grade] ?? `درجة ${offer.grade}` }}</template></span>
          <span class="font-semibold" :class="AVAILABILITY[offer.availability]?.class">{{ AVAILABILITY[offer.availability]?.label }}</span>
          <span v-if="offer.brand" class="text-muted">{{ offer.brand }}</span>
        </p>
        <p class="num text-4xl font-bold">
          {{ formatPrice(offer.price) }}
        </p>
        <div v-if="used" class="card space-y-3 p-4">
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-mint px-3 py-1 text-sm font-bold text-teal">{{ used.grade_label }}</span>
            <span v-if="used.battery_health" class="num rounded-full bg-sand px-3 py-1 text-sm" :class="used.battery_health >= 85 ? 'text-ok' : ''">صحة البطارية <bdi dir="ltr">{{ used.battery_health }}%</bdi></span>
            <span v-if="used.storage" class="num rounded-full bg-sand px-3 py-1 text-sm">{{ used.storage }}</span>
            <span v-if="used.color" class="rounded-full bg-sand px-3 py-1 text-sm">{{ used.color }}</span>
          </div>
          <div v-if="used.checks.length">
            <p class="mb-1.5 text-sm font-bold">
              فحص المحل
            </p>
            <ul class="grid gap-1 text-sm sm:grid-cols-2">
              <li v-for="c in used.checks" :key="c.label" class="flex items-start gap-1.5">
                <span aria-hidden="true" class="font-bold" :class="c.ok ? 'text-ok' : 'text-warn'">{{ c.ok ? '✓' : '✗' }}</span>
                <span :class="c.ok ? '' : 'text-warn'">{{ c.label }}<span class="sr-only">{{ c.ok ? ': أيوه' : ': لأ' }}</span></span>
              </li>
            </ul>
          </div>
          <p class="text-xs text-muted">
            حساب iCloud / جوجل متشال من الجهاز<template v-if="used.imei_registered">، والـ IMEI بتاعه متسجل عند المحل (اطلب تشوفه وانت بتستلم)</template>. معروض من {{ since(used.listed_at) }}.
          </p>
        </div>
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
import type { Offer, OfferDetail, Phone, ShopPage, UsedListing } from '~/types'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { query } = usePlace()

const { data, error } = await useAsyncData(`offer:${id.value}`, () => marketFetch<{ data: { offer: OfferDetail, elsewhere: Offer[], phone?: Phone | null, used?: UsedListing | null } }>(`/offers/${encodeURIComponent(id.value)}`, query.value).then(r => r.data), { watch: [query] })
if (error.value && !data.value) {
  throw createError({ statusCode: (error.value as { statusCode?: number }).statusCode ?? 404, fatal: true })
}
const offer = computed(() => data.value?.offer ?? null)
const elsewhere = computed(() => data.value?.elsewhere ?? [])
const phone = computed(() => data.value?.phone ?? null)
const used = computed(() => data.value?.used ?? null)
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
  if (used.value?.photos.length) {
    return used.value.photos[0]!
  }
  const images = offer.value?.images
  const first = Array.isArray(images) ? images[0] : null
  return (first && (first['800'] ?? first['1600'] ?? Object.values(first)[0])) ?? offer.value?.image ?? null
})
// A used device's own photos; a product's photos (800 wide).
const gallery = computed<string[]>(() => {
  if (used.value?.photos.length) {
    return used.value.photos
  }
  const images = offer.value?.images
  return Array.isArray(images) ? images.map(i => i['800'] ?? Object.values(i)[0]).filter((x): x is string => !!x) : []
})
const current = ref<string | null>(null)
const shown = computed(() => current.value ?? photo.value)

/** «النهارده», «من 3 أيام», «من أسبوعين»… */
function since(iso: string): string {
  const days = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000))
  if (days === 0) return 'النهارده'
  if (days === 1) return 'إمبارح'
  if (days === 2) return 'يومين'
  if (days <= 10) return `${days} أيام`
  if (days < 30) return `${days} يوم`
  return `${Math.floor(days / 30)} شهر`
}
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

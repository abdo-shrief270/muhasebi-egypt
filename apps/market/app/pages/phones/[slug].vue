<template>
  <div v-if="page" class="space-y-8">
    <nav class="text-sm text-muted" aria-label="المسار">
      <NuxtLink to="/">السوق</NuxtLink> ›
      <NuxtLink to="/prices">أسعار الموبايلات</NuxtLink> ›
      <NuxtLink :to="{ path: '/prices', query: { brand: phone.brand } }">{{ phone.brand }}</NuxtLink>
    </nav>

    <section class="space-y-3">
      <h1 class="text-2xl leading-snug font-bold sm:text-3xl">
        سعر <bdi>{{ phone.name }}</bdi> في مصر
      </h1>
      <p class="flex flex-wrap gap-2 text-sm">
        <span v-if="phone.year" class="num rounded-full bg-sand px-3 py-1">{{ phone.year }}</span>
        <span v-if="phone.network" class="rounded-full bg-sand px-3 py-1">{{ phone.network }}</span>
        <span v-if="phone.storage_gb.length" class="num rounded-full bg-sand px-3 py-1">{{ phone.storage_gb.map(storageLabel).join(' / ') }}</span>
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="card p-4">
          <p class="text-sm text-muted">
            جديد
          </p>
          <template v-if="prices.new">
            <p class="num text-2xl font-bold">
              {{ range(prices.new) }}
            </p>
            <p class="text-sm text-muted">
              في <span class="num">{{ prices.new.shops }}</span> {{ prices.new.shops === 1 ? 'محل' : 'محلات' }}
            </p>
          </template>
          <p v-else class="text-lg font-semibold text-muted">
            مش متاح جديد دلوقتي
          </p>
        </div>
        <div class="card p-4">
          <p class="text-sm text-muted">
            مستعمل
          </p>
          <template v-if="prices.used">
            <p class="num text-2xl font-bold">
              {{ range(prices.used) }}
            </p>
            <p class="text-sm text-muted">
              <span class="num">{{ prices.used.offers }}</span> جهاز في <span class="num">{{ prices.used.shops }}</span> {{ prices.used.shops === 1 ? 'محل' : 'محلات' }}
            </p>
          </template>
          <p v-else class="text-lg font-semibold text-muted">
            مفيش مستعمل دلوقتي
          </p>
        </div>
      </div>
      <NuxtLink v-if="prices.fits" :to="{ path: '/search', query: { fits: phone.slug } }" class="btn-line inline-flex">
        <MarketIcon name="phone" /> إكسسوارات وقطع غيار ليه · <span class="num">{{ prices.fits }}</span>
      </NuxtLink>
    </section>

    <div v-if="storages.length > 1" class="flex flex-wrap gap-2" role="group" aria-label="المساحة">
      <button type="button" class="chip" :class="storage === null ? 'chip-on' : 'chip-off'" @click="storage = null">
        كل المساحات
      </button>
      <button v-for="gb in storages" :key="gb" type="button" class="chip num" :class="storage === gb ? 'chip-on' : 'chip-off'" @click="storage = gb">
        {{ storageLabel(gb) }}
      </button>
    </div>

    <section class="space-y-3">
      <h2 class="text-xl font-bold">
        جديد في المحلات
      </h2>
      <div v-if="newOffers.length" class="card overflow-hidden">
        <OfferRow v-for="o in newOffers" :key="o.id" :offer="o" />
      </div>
      <p v-else class="text-muted">
        مفيش محل عنده دلوقتي{{ storage ? ' بالمساحة دي' : '' }}.
      </p>
    </section>

    <section v-if="usedOffers.length" class="space-y-3">
      <h2 class="text-xl font-bold">
        مستعمل
      </h2>
      <div class="card overflow-hidden">
        <OfferRow v-for="o in usedOffers" :key="o.id" :offer="o" />
      </div>
    </section>

    <p class="text-xs text-muted">
      الأسعار من مخزون المحلات المشتركة في محاسبي وبتتحدّث كل دقيقة.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { Offer, PhonePage, PriceRange } from '~/types'

const route = useRoute()
const slug = computed(() => String(route.params.slug))
const { query } = usePlace()

const { data, error } = await useAsyncData(`phone:${slug.value}`, () => marketFetch<{ data: PhonePage }>(`/phones/${slug.value}`, query.value).then(r => r.data), { watch: [query] })
if (error.value && !data.value) {
  throw createError({ statusCode: (error.value as { statusCode?: number }).statusCode ?? 404, fatal: true })
}
const page = computed(() => data.value ?? null)
const phone = computed(() => page.value!.phone)
const prices = computed(() => page.value!.prices)

const range = (r: PriceRange) => (r.min === r.max ? formatPrice(r.min) : `${formatPrice(r.min)} – ${formatPrice(r.max)}`)

const storage = ref<number | null>(null)
const storages = computed(() => [...new Set([...(page.value?.new ?? []), ...(page.value?.used ?? [])].map(o => o.storage_gb).filter((g): g is number => !!g))].sort((a, b) => a - b))
const bySize = (list: Offer[]) => (storage.value === null ? list : list.filter(o => o.storage_gb === storage.value))
const newOffers = computed(() => bySize(page.value?.new ?? []))
const usedOffers = computed(() => bySize(page.value?.used ?? []))

const lowest = computed(() => prices.value?.new?.min ?? prices.value?.used?.min ?? null)
useSeoMeta({
  title: () => `سعر ${phone.value?.name ?? ''} في مصر${lowest.value ? ` من ${formatPrice(lowest.value)}` : ''}`,
  description: () => `أسعار ${phone.value?.name} جديد ومستعمل في محلات مصر النهارده${prices.value?.new ? `: جديد من ${formatPrice(prices.value.new.min)} في ${prices.value.new.shops} محل` : ''}${prices.value?.used ? `، مستعمل من ${formatPrice(prices.value.used.min)}` : ''}. قارن واعرف أقرب محل ليك.`,
})
useHead(() => {
  const all = [prices.value?.new, prices.value?.used].filter((r): r is PriceRange => !!r)
  return {
    script: page.value && all.length
      ? [{
          type: 'application/ld+json',
          innerHTML: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Product',
            'name': phone.value.name,
            'brand': { '@type': 'Brand', 'name': phone.value.brand },
            'offers': {
              '@type': 'AggregateOffer',
              'priceCurrency': 'EGP',
              'lowPrice': (Math.min(...all.map(r => r.min)) / 100).toFixed(2),
              'highPrice': (Math.max(...all.map(r => r.max)) / 100).toFixed(2),
              'offerCount': all.reduce((n, r) => n + r.offers, 0),
            },
          }),
        }]
      : [],
  }
})
</script>

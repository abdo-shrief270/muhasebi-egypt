<template>
  <div v-if="shop" class="space-y-8">
    <section class="grid gap-6 lg:grid-cols-5">
      <div class="space-y-4 lg:col-span-2">
        <div class="flex items-center gap-4">
          <span class="grid size-16 place-items-center rounded-2xl bg-mint text-2xl font-bold text-teal">{{ initial(shop.name) }}</span>
          <div>
            <h1 class="text-2xl font-bold sm:text-3xl">
              {{ shop.name }}
            </h1>
            <p class="text-sm text-muted">
              <span class="num">{{ shop.offers }}</span> صنف متاح · <span class="num">{{ shop.branches.length }}</span> {{ shop.branches.length === 1 ? 'فرع' : 'فروع' }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <a :href="whatsappLink(shop.phone, `السلام عليكم، شفت محلكم في سوق محاسبي.`)" target="_blank" rel="noopener" class="btn-teal">
            <MarketIcon name="whatsapp" /> واتساب
          </a>
          <a :href="`tel:${localPhone(shop.phone)}`" class="btn-line"><MarketIcon name="call" /> <span class="num" dir="ltr">{{ localPhone(shop.phone) }}</span></a>
          <a v-if="shop.store_url" :href="shop.store_url" target="_blank" rel="noopener" class="btn-line"><MarketIcon name="store" /> متجر المحل</a>
        </div>
        <ul class="space-y-2">
          <li v-for="b in shop.branches" :key="b.branch_id" class="card flex items-center gap-3 p-3 text-sm">
            <MarketIcon name="pin" class="shrink-0 text-teal" />
            <span class="flex-1">{{ [b.area, b.governorate_label].filter(Boolean).join('، ') || 'الفرع' }} · <span class="num">{{ b.offers }}</span> صنف</span>
            <span v-if="b.distance_km !== null" class="num font-semibold">{{ formatKm(b.distance_km) }}</span>
            <a v-if="b.location" :href="directionsLink(b.location)" target="_blank" rel="noopener" class="font-semibold">الطريق</a>
          </li>
        </ul>
      </div>
      <ClientOnly>
        <MarketMap class="min-h-72 border border-line lg:col-span-3" :pins="pins" :me="place?.point ?? null" />
      </ClientOnly>
    </section>

    <section class="space-y-3">
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="flex-1 text-xl font-bold">
          أصناف {{ shop.name }}
        </h2>
        <form role="search" class="flex gap-2" @submit.prevent="page = 1; term = draft">
          <label for="shop-q" class="sr-only">دوّر في المحل</label>
          <input id="shop-q" v-model="draft" type="search" placeholder="دوّر في المحل…" class="w-48 rounded-xl border border-line-strong bg-white px-3 py-2 text-sm">
          <button type="submit" class="btn-line">
            دوّر
          </button>
        </form>
      </div>
      <div class="card overflow-hidden">
        <OfferRow v-for="o in items?.items ?? []" :key="o.id" :offer="o" />
        <p v-if="items && !items.items.length" class="p-6 text-center text-muted">
          ملقيناش حاجة.
        </p>
      </div>
      <nav v-if="pages > 1" class="flex items-center justify-center gap-2" aria-label="الصفحات">
        <button type="button" class="btn-line" :disabled="page <= 1" @click="page--">
          السابق
        </button>
        <span class="num text-sm text-muted">{{ page }} / {{ pages }}</span>
        <button type="button" class="btn-line" :disabled="page >= pages" @click="page++">
          التالي
        </button>
      </nav>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { SearchResult, ShopPage } from '~/types'

const route = useRoute()
const slug = computed(() => String(route.params.slug).toLowerCase())
const { place, query } = usePlace()

const { data, error } = await useAsyncData(`shop:${slug.value}`, () => marketFetch<{ data: ShopPage }>(`/shops/${slug.value}`, query.value).then(r => r.data), { watch: [query] })
if (error.value && !data.value) {
  throw createError({ statusCode: (error.value as { statusCode?: number }).statusCode ?? 404, fatal: true })
}
const shop = computed(() => data.value ?? null)
const pins = computed(() => (shop.value?.branches ?? []).filter(b => b.location).map(b => ({ ...b.location!, label: b.area ?? shop.value!.name })))

const term = ref('')
const draft = ref('')
const page = ref(1)
const { data: items } = await useAsyncData(`shop-items:${slug.value}`, () => marketFetch<{ data: SearchResult }>('/search', {
  shop: slug.value, q: term.value || undefined, page: page.value, per_page: 24, sort: term.value ? 'relevance' : 'new',
}).then(r => r.data).catch(() => null), { watch: [term, page] })
const pages = computed(() => (items.value ? Math.min(100, Math.ceil(items.value.total / items.value.per_page)) : 0))

const base = String(useRuntimeConfig().public.marketUrl).replace(/\/$/, '')
useSeoMeta({
  title: () => `${shop.value?.name ?? ''} — أصنافه وأسعاره`,
  description: () => `${shop.value?.name}: ${shop.value?.offers} صنف متاح${shop.value?.branches[0]?.area ? ` في ${shop.value.branches[0].area}` : ''}. الأسعار والعنوان والتواصل.`,
})
useHead(() => ({
  script: shop.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Store',
          'name': shop.value.name,
          'url': `${base}/s/${shop.value.slug}`,
          'telephone': shop.value.phone,
          'address': shop.value.branches.map(b => ({ '@type': 'PostalAddress', 'addressLocality': b.area ?? undefined, 'addressRegion': b.governorate_label ?? undefined, 'addressCountry': 'EG' })),
        }),
      }]
    : [],
}))
</script>

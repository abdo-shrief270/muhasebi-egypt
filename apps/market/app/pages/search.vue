<template>
  <div class="space-y-4">
    <div class="space-y-3">
      <SearchBox :initial="q" />
      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="s in sorts"
          :key="s"
          type="button"
          class="chip"
          :class="sort === s ? 'border-ink bg-ink text-white' : 'border-line-strong bg-white text-ink'"
          @click="set({ sort: s })"
        >
          {{ SORT_LABELS[s] }}
        </button>
        <button type="button" class="chip ms-auto border-line-strong bg-white text-ink lg:hidden" :aria-expanded="showFilters" @click="showFilters = !showFilters">
          <MarketIcon name="filter" :size="15" /> فلاتر
        </button>
      </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">
      <aside class="space-y-5 text-sm" :class="showFilters ? '' : 'hidden lg:block'" aria-label="الفلاتر">
        <fieldset v-for="group in filterGroups" :key="group.key" class="space-y-1.5">
          <legend class="mb-1 font-bold">
            {{ group.title }}
          </legend>
          <button
            v-for="f in group.items"
            :key="f.key"
            type="button"
            class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-start"
            :class="route.query[group.key] === f.key ? 'bg-mint font-semibold text-teal' : 'hover:bg-sand'"
            @click="set({ [group.key]: route.query[group.key] === f.key ? undefined : f.key })"
          >
            <span>{{ f.label }}</span><span class="num text-xs text-muted">{{ f.count }}</span>
          </button>
        </fieldset>
        <fieldset class="space-y-1.5">
          <legend class="mb-1 font-bold">
            السعر (ج)
          </legend>
          <form class="flex items-center gap-2" @submit.prevent="setPrice">
            <input v-model="minPrice" type="number" min="0" inputmode="numeric" placeholder="من" class="w-full rounded-lg border border-line-strong bg-white px-2 py-1.5" aria-label="من">
            <input v-model="maxPrice" type="number" min="0" inputmode="numeric" placeholder="لحد" class="w-full rounded-lg border border-line-strong bg-white px-2 py-1.5" aria-label="لحد">
            <button type="submit" class="btn-line px-3 py-1.5">
              تمام
            </button>
          </form>
        </fieldset>
        <button v-if="hasFilters" type="button" class="text-muted underline" @click="clearFilters">
          امسح الفلاتر
        </button>
      </aside>

      <div class="min-w-0 space-y-3">
        <ClientOnly>
          <MarketMap v-if="pricePins.length" class="h-40 border border-line sm:h-56" :pins="pricePins" :me="place?.point ?? null" />
        </ClientOnly>
        <p class="text-sm text-muted" aria-live="polite">
          <template v-if="pending">
            بندوّر…
          </template>
          <template v-else-if="result">
            <span class="num">{{ result.total }}</span> نتيجة<template v-if="q">
              لـ «{{ q }}»
            </template><template v-if="place">
              · {{ place.label }}
            </template>
          </template>
        </p>
        <div v-if="unavailable" class="card p-6 text-center text-muted">
          البحث واقف دقيقة، جرّب كمان شوية.
        </div>
        <div v-else class="card overflow-hidden">
          <OfferRow v-for="o in result?.items ?? []" :key="o.id" :offer="o" />
          <p v-if="result && !result.items.length" class="p-8 text-center text-muted">
            ملقيناش حاجة. جرّب كلمة تانية أو شيل فلتر.
          </p>
        </div>
        <nav v-if="pages > 1" class="flex items-center justify-center gap-2" aria-label="الصفحات">
          <button type="button" class="btn-line" :disabled="page <= 1" @click="set({ page: page - 1 }, true)">
            السابق
          </button>
          <span class="num text-sm text-muted">{{ page }} / {{ pages }}</span>
          <button type="button" class="btn-line" :disabled="page >= pages" @click="set({ page: page + 1 }, true)">
            التالي
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SearchResult } from '~/types'

const route = useRoute()
const { place, query: placeQuery } = usePlace()
const showFilters = ref(false)

const q = computed(() => String(route.query.q ?? ''))
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
// The nearest first when the visitor's spot is known and nothing else was asked.
const sort = computed(() => String(route.query.sort ?? (q.value ? 'relevance' : place.value?.point ? 'nearest' : 'new')))
const sorts = computed(() => (place.value?.point ? ['nearest', 'price_asc', 'relevance', 'new'] : ['relevance', 'price_asc', 'new']))

const FILTERS = ['category', 'condition', 'governorate', 'brand', 'min', 'max'] as const
const params = computed(() => {
  const out: Record<string, unknown> = { q: q.value || undefined, sort: sort.value, page: page.value, per_page: 24 }
  for (const k of FILTERS) {
    if (route.query[k]) {
      out[k] = route.query[k]
    }
  }
  // A chosen governorate in the filters wins over the visitor's own.
  return { ...placeQuery.value, ...out }
})

const { data, pending, error } = await useAsyncData('search', () => marketFetch<{ data: SearchResult }>('/search', params.value).then(r => r.data), { watch: [params] })
const result = computed(() => data.value ?? null)
const unavailable = computed(() => !!error.value)
const pages = computed(() => (result.value ? Math.min(100, Math.ceil(result.value.total / result.value.per_page)) : 0))

const filterGroups = computed(() => {
  const f = result.value?.facets
  return [
    { key: 'category', title: 'القسم', items: f?.category ?? [] },
    { key: 'condition', title: 'الحالة', items: f?.condition ?? [] },
    { key: 'brand', title: 'الماركة', items: (f?.brand ?? []).slice(0, 12) },
    { key: 'governorate', title: 'المحافظة', items: f?.governorate ?? [] },
  ].filter(g => g.items.length > 1 || route.query[g.key])
})

const minPrice = ref(route.query.min ? String(Number(route.query.min) / 100) : '')
const maxPrice = ref(route.query.max ? String(Number(route.query.max) / 100) : '')
const hasFilters = computed(() => FILTERS.some(k => route.query[k]))

function set(changes: Record<string, unknown>, keepPage = false) {
  navigateTo({ query: { ...route.query, ...(keepPage ? {} : { page: undefined }), ...changes } })
}
function setPrice() {
  set({ min: minPrice.value ? Math.round(Number(minPrice.value) * 100) : undefined, max: maxPrice.value ? Math.round(Number(maxPrice.value) * 100) : undefined })
}
function clearFilters() {
  minPrice.value = ''
  maxPrice.value = ''
  navigateTo({ query: { q: route.query.q, sort: route.query.sort } })
}

const pricePins = computed(() => (result.value?.items ?? []).filter(o => o.location).slice(0, 15).map(o => ({ ...o.location!, label: formatPrice(o.price), to: `/p/${encodeURIComponent(o.id)}` })))

useSeoMeta({
  title: () => (q.value ? `${q.value} — أسعار في المحلات القريبة` : 'دوّر في كل المحلات'),
  description: () => (q.value ? `أسعار «${q.value}» في محلات الموبايلات والإكسسوارات، بالمسافة والمتاح دلوقتي.` : 'دوّر على إكسسوارات وقطع غيار وموبايلات في كل المحلات.'),
  robots: () => (page.value > 1 || hasFilters.value ? 'noindex, follow' : 'index, follow'),
})
</script>

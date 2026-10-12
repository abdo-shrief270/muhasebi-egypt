<template>
  <div class="space-y-5">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold sm:text-3xl">
        موبايلات مستعملة من محلات حقيقية
      </h1>
      <p class="text-muted">
        كل جهاز متصوّر، ومتفحوص في المحل (الشاشة والبطارية والكاميرات…)، وحساب iCloud / جوجل متشال منه، والـ IMEI بتاعه متسجل عند المحل.
      </p>
    </div>

    <div class="space-y-3">
      <div class="flex flex-wrap gap-2" role="group" aria-label="الحالة">
        <button v-for="g in GRADES" :key="g" type="button" class="chip" :class="grades.includes(g) ? 'chip-on' : 'chip-off'" :aria-pressed="grades.includes(g)" @click="toggleGrade(g)">
          {{ GRADE_LABELS[g] }}
        </button>
        <span class="mx-1 hidden w-px self-stretch bg-line sm:block" />
        <button v-for="b in BATTERY" :key="b" type="button" class="chip" :class="battery === b ? 'chip-on' : 'chip-off'" @click="set({ battery: battery === b ? undefined : b })">
          بطارية من <bdi dir="ltr" class="num">{{ b }}%</bdi>
        </button>
      </div>
      <div v-if="storages.length > 1 || brands.length > 1" class="flex flex-wrap gap-2">
        <button v-for="s in storages" :key="s.key" type="button" class="chip num" :class="String(route.query.storage) === s.key ? 'chip-on' : 'chip-off'" @click="set({ storage: String(route.query.storage) === s.key ? undefined : s.key })">
          {{ storageLabel(Number(s.key)) }}
        </button>
        <button v-for="b in brands" :key="b.key" type="button" class="chip capitalize" :class="route.query.brand === b.key ? 'chip-on' : 'chip-off'" @click="set({ brand: route.query.brand === b.key ? undefined : b.key })">
          {{ b.label }}
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button v-for="s in sorts" :key="s" type="button" class="chip" :class="sort === s ? 'border-ink bg-ink text-white' : 'chip-off'" @click="set({ sort: s })">
          {{ SORT_LABELS[s] }}
        </button>
        <button v-if="hasFilters" type="button" class="ms-auto text-sm text-muted underline" @click="navigateTo({ query: {} })">
          امسح الفلاتر
        </button>
      </div>
    </div>

    <p class="text-sm text-muted" aria-live="polite">
      <template v-if="pending">
        بندوّر…
      </template>
      <template v-else-if="result">
        <span class="num">{{ result.total }}</span> جهاز<template v-if="place">
          · {{ place.label }}
        </template>
      </template>
    </p>
    <div v-if="error" class="card p-6 text-center text-muted">
      البحث واقف دقيقة، جرّب كمان شوية.
    </div>
    <div v-else-if="result && !result.items.length" class="card p-8 text-center text-muted">
      مفيش أجهزة بالمواصفات دي دلوقتي. جرّب تشيل فلتر.
    </div>
    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <UsedCard v-for="o in result?.items ?? []" :key="o.id" :offer="o" />
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
</template>

<script setup lang="ts">
import type { SearchResult } from '~/types'

const route = useRoute()
const { place, query: placeQuery } = usePlace()

const GRADES = ['A', 'B', 'C']
const BATTERY = [80, 85, 90]

const grades = computed(() => [route.query.grade].flat().filter((g): g is string => typeof g === 'string' && GRADES.includes(g)))
const battery = computed(() => (route.query.battery ? Number(route.query.battery) : null))
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const sort = computed(() => String(route.query.sort ?? (place.value?.point ? 'nearest' : 'new')))
const sorts = computed(() => (place.value?.point ? ['nearest', 'price_asc', 'new'] : ['new', 'price_asc', 'price_desc']))
const hasFilters = computed(() => ['grade', 'battery', 'storage', 'brand'].some(k => route.query[k]))

const params = computed(() => ({
  ...placeQuery.value,
  category: 'used',
  'grade[]': grades.value.length ? grades.value : undefined,
  battery: battery.value ?? undefined,
  storage: route.query.storage || undefined,
  brand: route.query.brand || undefined,
  sort: sort.value,
  page: page.value,
  per_page: 24,
}))

const { data, pending, error } = await useAsyncData('used', () => marketFetch<{ data: SearchResult }>('/search', params.value).then(r => r.data), { watch: [params] })
const result = computed(() => data.value ?? null)
const pages = computed(() => (result.value ? Math.min(100, Math.ceil(result.value.total / result.value.per_page)) : 0))
const storages = computed(() => result.value?.facets.storage ?? [])
const brands = computed(() => (result.value?.facets.brand ?? []).slice(0, 10))

function set(changes: Record<string, unknown>, keepPage = false) {
  navigateTo({ query: { ...route.query, ...(keepPage ? {} : { page: undefined }), ...changes } })
}
function toggleGrade(g: string) {
  const next = grades.value.includes(g) ? grades.value.filter(x => x !== g) : [...grades.value, g]
  set({ grade: next.length ? next : undefined })
}

useSeoMeta({
  title: 'موبايلات مستعملة بحالتها وسعرها من المحلات',
  description: 'موبايلات مستعملة متصوّرة ومتفحوصة في محلات قريبة منك: الحالة، صحة البطارية، المساحة والسعر. الحساب متشال والـ IMEI متسجل في المحل.',
  robots: () => (page.value > 1 || hasFilters.value ? 'noindex, follow' : 'index, follow'),
})
</script>

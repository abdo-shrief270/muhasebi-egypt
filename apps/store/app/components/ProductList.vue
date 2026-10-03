<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
      <p class="text-sm text-muted">
        <span class="num">{{ total }}</span> صنف
      </p>
      <div class="ms-auto flex flex-wrap items-center gap-2">
        <button
          v-for="q in qualities"
          :key="q.value"
          type="button"
          class="chip"
          :class="route.query.quality === q.value ? 'chip-on' : ''"
          @click="setQuery({ quality: route.query.quality === q.value ? undefined : q.value })"
        >
          {{ q.label }}
        </button>
        <select :value="String(route.query.sort ?? 'new')" class="input w-auto py-1.5 text-sm" aria-label="الترتيب" @change="setQuery({ sort: ($event.target as HTMLSelectElement).value })">
          <option value="new">
            الأحدث
          </option>
          <option value="price_asc">
            السعر: الأقل الأول
          </option>
          <option value="price_desc">
            السعر: الأعلى الأول
          </option>
          <option value="name">
            الاسم
          </option>
        </select>
      </div>
    </div>

    <ProductGrid :products="items" :slug="slug" :empty="empty" />

    <nav v-if="pages > 1" class="flex items-center justify-center gap-2" aria-label="الصفحات">
      <button type="button" class="btn-line" :disabled="page <= 1" @click="setQuery({ page: page - 1 }, false)">
        السابق
      </button>
      <span class="num text-sm text-muted">{{ page }} / {{ pages }}</span>
      <button type="button" class="btn-line" :disabled="page >= pages" @click="setQuery({ page: page + 1 }, false)">
        التالي
      </button>
    </nav>
  </section>
</template>

<script setup lang="ts">
import type { ProductPage } from '~/types'

/** A filtered product list (category, model, search) driven by the page's query string. */
const props = withDefaults(defineProps<{ filters: Record<string, string | number | undefined>, empty?: string }>(), { empty: 'مفيش أصناف بالشكل ده.' })

const route = useRoute()
const slug = useStoreSlug()
const qualities = [
  { value: 'original', label: 'أصلي' },
  { value: 'high_copy', label: 'هاي كوبي' },
]

const query = computed(() => ({
  ...props.filters,
  sort: route.query.sort as string | undefined,
  quality: route.query.quality as string | undefined,
  page: route.query.page as string | undefined,
  per_page: 24,
}))
const { data } = await useFetch<ProductPage>(`/api/stores/${slug}/products`, { query, key: `list:${slug}:${JSON.stringify(props.filters)}` })

const items = computed(() => data.value?.data ?? [])
const total = computed(() => data.value?.meta.total ?? 0)
const page = computed(() => data.value?.meta.page ?? 1)
const pages = computed(() => Math.max(1, Math.ceil(total.value / 24)))

function setQuery(change: Record<string, string | number | undefined>, resetPage = true) {
  navigateTo({ query: { ...route.query, ...(resetPage ? { page: undefined } : {}), ...change } })
}
</script>

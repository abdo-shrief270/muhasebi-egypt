<template>
  <div class="space-y-6">
    <PageHeader title="الأصناف" description="كل صنف بأنواعه وأسعاره والموديلات اللي بيركب عليها.">
      <UButton v-if="canManage" to="/products/setup" color="neutral" variant="outline" icon="i-lucide-tags" label="التصنيفات والماركات" />
      <UButton v-if="canImport" to="/products/import" color="neutral" variant="outline" icon="i-lucide-file-spreadsheet" label="استيراد من Excel" />
      <UButton v-if="canManage" to="/products/prices" color="neutral" variant="outline" icon="i-lucide-percent" label="تعديل الأسعار" />
      <UButton to="/products/labels" color="neutral" variant="outline" icon="i-lucide-tag" label="ليبلات باركود" />
      <UButton v-if="canManage" to="/products/new" icon="i-lucide-plus" label="صنف جديد" />
    </PageHeader>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_220px_260px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر بالاسم أو الباركود أو الموديل… (مثلاً جراب iphone 13)" class="w-full" />
      <USelect v-model="categoryId" :items="categoryItems" class="w-full" />
      <DeviceModelPicker v-model="deviceModelId" placeholder="كل الموديلات" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الصنف
              </th>
              <th class="p-3 text-start font-bold">
                التصنيف
              </th>
              <th class="p-3 text-start font-bold">
                بيركب على
              </th>
              <th class="p-3 text-start font-bold">
                الأنواع
              </th>
              <th class="p-3 text-start font-bold">
                سعر القطاعي
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in products"
              :key="p.id"
              class="border-t border-(--ui-border)"
              :class="[{ 'opacity-60': !p.is_active }, canManage ? 'cursor-pointer hover:bg-(--ui-bg-elevated)' : '']"
              @click="canManage && navigateTo(`/products/${p.id}`)"
            >
              <td class="p-3">
                <p class="font-bold">
                  {{ p.name }}
                  <UBadge v-if="!p.is_active" color="neutral" variant="subtle" size="sm" class="ms-1">
                    موقوف
                  </UBadge>
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span v-if="p.brand">{{ p.brand.name }}</span>
                  <span v-if="p.brand && p.sku"> · </span>
                  <span v-if="p.sku" class="num" dir="ltr">{{ p.sku }}</span>
                </p>
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                {{ p.category.name }}
              </td>
              <td class="p-3">
                <div class="flex flex-wrap gap-1">
                  <UBadge v-for="m in p.device_models.slice(0, 3)" :key="m.id" color="neutral" variant="outline" size="sm">
                    {{ m.name }}
                  </UBadge>
                  <UBadge v-if="p.device_models.length > 3" color="neutral" variant="subtle" size="sm">
                    +{{ p.device_models.length - 3 }}
                  </UBadge>
                  <span v-if="!p.device_models.length" class="text-(--ui-text-muted)">عام</span>
                </div>
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                {{ variantSummary(p) }}
              </td>
              <td class="p-3 font-bold num">
                {{ retailPriceRange(p.variants) }}
              </td>
            </tr>
            <tr v-if="!products.length && status !== 'pending'">
              <td colspan="5" class="p-10 text-center text-(--ui-text-muted)">
                <template v-if="filtered">
                  مفيش أصناف مطابقة.
                </template>
                <template v-else>
                  لسه مفيش أصناف.
                  <template v-if="canManage">
                    <ULink to="/products/new" class="font-bold text-primary">
                      ضيف أول صنف
                    </ULink>
                    <template v-if="canImport">
                      أو
                      <ULink to="/products/import" class="font-bold text-primary">
                        استوردهم من Excel
                      </ULink>
                    </template>
                  </template>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="(meta?.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="meta?.total ?? 0" :items-per-page="meta?.per_page ?? 30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Category, Paginated, Product } from '~/types/api'

definePageMeta({ permission: 'products.view' })

const api = useApi()
const store = useSessionStore()
const canManage = computed(() => store.can('products.manage'))
const canImport = computed(() => canManage.value && store.hasFeature('catalog.excel_import'))

const route = useRoute()
const ALL = 0
// Opened from the quick search with ?q=
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const debouncedQ = ref(q.value.trim())
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(searchTimer))
const categoryId = ref<number>(ALL)
const deviceModelId = ref<number | number[] | undefined>()
const page = ref(1)

const filtered = computed(() => !!debouncedQ.value || categoryId.value !== ALL || !!deviceModelId.value)
watch([debouncedQ, categoryId, deviceModelId], () => {
  page.value = 1
})

const { data: categoriesData } = await useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories'))
const categoryItems = computed(() => [{ label: 'كل التصنيفات', value: ALL }, ...(categoriesData.value?.data ?? []).map(c => ({ label: c.name, value: c.id }))])

const { data, status } = await useAsyncData('products', () => api<Paginated<Product>>('/products', {
  query: {
    q: debouncedQ.value || undefined,
    category_id: categoryId.value || undefined,
    device_model_id: typeof deviceModelId.value === 'number' ? deviceModelId.value : undefined,
    page: page.value,
  },
}), { watch: [debouncedQ, categoryId, deviceModelId, page] })

const products = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

function variantSummary(p: Product): string {
  if (p.variants.length === 1) {
    return p.variants[0]?.name ?? p.variants[0]?.quality_label ?? '—'
  }
  return `${p.variants.length} أنواع`
}
</script>

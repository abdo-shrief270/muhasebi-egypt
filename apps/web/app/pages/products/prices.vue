<template>
  <div class="space-y-6">
    <PageHeader title="تعديل الأسعار" description="غيّر سعر أصناف كتير مرة واحدة: زوّد نسبة، أو حط هامش على التكلفة، أو سعر ثابت — وشوف النتيجة قبل ما تحفظ.">
      <UButton to="/products" color="neutral" variant="outline" icon="i-lucide-package" label="الأصناف" />
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-2">
      <UCard>
        <template #header>
          <h2 class="font-bold">
            1. الأصناف
          </h2>
        </template>
        <div class="space-y-4">
          <UInput v-model="q" icon="i-lucide-search" placeholder="اسم أو باركود أو موديل… (فاضي = الكل)" class="w-full" />
          <div class="grid gap-4 sm:grid-cols-2">
            <USelect v-model="categoryId" :items="categoryItems" class="w-full" />
            <USelect v-model="brandId" :items="brandItems" class="w-full" />
          </div>
          <DeviceModelPicker v-model="deviceModelId" placeholder="كل الموديلات" />
          <UCheckbox v-model="includeInactive" label="حتى الأصناف الموقوفة" />
        </div>
      </UCard>

      <UCard>
        <template #header>
          <h2 class="font-bold">
            2. التعديل
          </h2>
        </template>
        <div class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="السعر اللي هيتغير">
              <USelect v-model="field" :items="fieldItems" class="w-full" />
            </UFormField>
            <UFormField label="الطريقة">
              <USelect v-model="change" :items="changeItems" class="w-full" />
            </UFormField>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField v-if="change !== 'set'" label="محسوب من">
              <USelect v-model="base" :items="baseItems" class="w-full" />
            </UFormField>
            <UFormField :label="valueLabel" :hint="valueHint">
              <UInput v-model="value" type="number" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="تقريب لـ">
              <USelect v-model="roundTo" :items="roundItems" class="w-full" />
            </UFormField>
            <UFormField v-if="roundTo > 0" label="اتجاه التقريب">
              <USelect v-model="rounding" :items="roundingItems" class="w-full" />
            </UFormField>
          </div>
          <p class="text-sm text-(--ui-text-muted)">
            {{ example }}
          </p>
        </div>
      </UCard>
    </div>

    <UAlert v-if="error" color="error" variant="subtle" icon="i-lucide-circle-alert" :title="error" />

    <UCard v-if="preview" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="space-y-1">
            <h2 class="font-bold">
              3. المعاينة — {{ preview.description }}
            </h2>
            <div class="flex flex-wrap gap-2 text-sm">
              <UBadge color="neutral" variant="subtle">
                <span class="num">{{ preview.summary.matched }}</span>&nbsp;صنف
              </UBadge>
              <UBadge color="primary" variant="subtle">
                <span class="num">{{ changingCount }}</span>&nbsp;هيتغير
              </UBadge>
              <UBadge v-if="preview.summary.skipped" color="neutral" variant="outline">
                <span class="num">{{ preview.summary.skipped }}</span>&nbsp;مش هيتغير
              </UBadge>
              <UBadge v-if="belowCostCount" color="warning" variant="subtle" icon="i-lucide-triangle-alert">
                <span class="num">{{ belowCostCount }}</span>&nbsp;تحت التكلفة
              </UBadge>
            </div>
          </div>
          <UButton icon="i-lucide-check" label="احفظ الأسعار الجديدة" :disabled="!changingCount" :loading="applying" @click="confirmOpen = true" />
        </div>
      </template>
      <div class="max-h-[60vh] overflow-auto">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="w-10 p-3">
                <UCheckbox :model-value="allIncluded" aria-label="الكل" @update:model-value="toggleAll" />
              </th>
              <th class="p-3 text-start font-bold">
                الصنف
              </th>
              <th v-if="canViewCost" class="p-3 text-start font-bold">
                التكلفة
              </th>
              <th class="p-3 text-start font-bold">
                الحالي
              </th>
              <th class="p-3 text-start font-bold">
                الجديد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in preview.rows"
              :key="row.variant_id"
              class="border-t border-(--ui-border)"
              :class="{ 'opacity-50': row.skip || excluded.has(row.variant_id) }"
            >
              <td class="p-3">
                <UCheckbox
                  v-if="!row.skip"
                  :model-value="!excluded.has(row.variant_id)"
                  :aria-label="row.name"
                  @update:model-value="toggle(row.variant_id)"
                />
              </td>
              <td class="p-3">
                <p class="font-bold">
                  {{ row.name }}
                </p>
                <p v-if="row.barcode" class="num text-xs text-(--ui-text-muted)" dir="ltr">
                  {{ row.barcode }}
                </p>
              </td>
              <td v-if="canViewCost" class="num p-3 text-(--ui-text-muted)">
                {{ formatMoney(row.cost) }}
              </td>
              <td class="num p-3">
                {{ formatMoney(row.old) }}
              </td>
              <td class="p-3">
                <span v-if="row.skip" class="text-xs text-(--ui-text-muted)">{{ skipLabels[row.skip] }}</span>
                <span v-else class="num font-bold" :class="(row.new ?? 0) > (row.old ?? 0) ? 'text-(--ui-success)' : 'text-(--ui-error)'">
                  {{ formatMoney(row.new) }}
                </span>
                <UBadge v-if="row.below_cost" color="warning" variant="subtle" size="sm" class="ms-2">
                  تحت التكلفة
                </UBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
    <p v-else-if="loading" class="py-6 text-center text-sm text-(--ui-text-muted)">
      بنحسب…
    </p>

    <UModal v-model:open="confirmOpen" title="تأكيد تعديل الأسعار" :description="preview?.description">
      <template #body>
        <p>
          هتتغير أسعار <span class="num font-bold">{{ changingCount }}</span> صنف.
          <span v-if="belowCostCount" class="text-(--ui-warning)">منهم <span class="num">{{ belowCostCount }}</span> هيتباع تحت التكلفة.</span>
          كل تغيير بيتسجل في سجل أسعار الصنف وفي سجل العمليات.
        </p>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="confirmOpen = false" />
          <UButton icon="i-lucide-check" label="احفظ" :loading="applying" @click="apply" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { Brand, Category } from '~/types/api'

definePageMeta({ permission: 'products.manage', feature: 'catalog.bulk_prices' })

interface PreviewRow {
  variant_id: string
  product_id: string
  name: string
  barcode: string | null
  old: number | null
  new: number | null
  skip: 'no_cost' | 'no_base' | 'not_positive' | 'unchanged' | null
  cost: number | null
  below_cost: boolean
}
interface Preview {
  rows: PreviewRow[]
  summary: { matched: number, changing: number, skipped: number, below_cost: number }
  description: string
}

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canViewCost = computed(() => store.can('products.view_cost'))

const ALL = 0
const q = ref('')
const categoryId = ref<number>(ALL)
const brandId = ref<number>(ALL)
const deviceModelId = ref<number | number[] | undefined>()
const includeInactive = ref(false)

const field = ref('price_retail')
const change = ref<'percent' | 'amount' | 'set'>('percent')
const base = ref('price_retail')
const value = ref('')
const roundTo = ref(0)
const rounding = ref<'nearest' | 'up' | 'down'>('nearest')

const { data: categoriesData } = await useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories'))
const { data: brandsData } = await useAsyncData('catalog-brands', () => api<{ data: Brand[] }>('/catalog/brands'))
const categoryItems = computed(() => [{ label: 'كل التصنيفات', value: ALL }, ...(categoriesData.value?.data ?? []).map(c => ({ label: c.name, value: c.id }))])
const brandItems = computed(() => [{ label: 'كل الماركات', value: ALL }, ...(brandsData.value?.data ?? []).map(b => ({ label: b.name, value: b.id }))])

const priceFields = [
  { label: 'سعر القطاعي', value: 'price_retail' },
  { label: 'سعر الجملة', value: 'price_wholesale' },
  { label: 'سعر الفني', value: 'price_technician' },
  { label: 'سعر الأونلاين', value: 'price_online' },
]
const fieldItems = priceFields
const baseItems = computed(() => [...priceFields, ...(canViewCost.value ? [{ label: 'متوسط التكلفة (هامش ربح)', value: 'cost' }] : [])])
const changeItems = [
  { label: 'نسبة %', value: 'percent' },
  { label: 'مبلغ ثابت (زيادة أو نقص)', value: 'amount' },
  { label: 'سعر واحد للكل', value: 'set' },
]
const roundItems = [
  { label: 'من غير تقريب', value: 0 },
  { label: 'نص جنيه', value: 50 },
  { label: 'جنيه', value: 100 },
  { label: '5 جنيه', value: 500 },
  { label: '10 جنيه', value: 1000 },
  { label: '50 جنيه', value: 5000 },
  { label: '100 جنيه', value: 10000 },
]
const roundingItems = [
  { label: 'لأقرب رقم', value: 'nearest' },
  { label: 'لفوق', value: 'up' },
  { label: 'لتحت', value: 'down' },
]
const skipLabels: Record<string, string> = {
  no_cost: 'مالوش تكلفة في الفرع ده',
  no_base: 'مفيش سعر يتحسب منه',
  not_positive: 'السعر هيبقى صفر أو أقل',
  unchanged: 'نفس السعر',
}

// Starting a field from itself is the usual case; keep the base in step until the user picks another.
watch(field, (next, previous) => {
  if (base.value === previous) {
    base.value = next
  }
})

const valueLabel = computed(() => ({ percent: 'النسبة', amount: 'المبلغ', set: 'السعر' })[change.value])
const valueHint = computed(() => ({ percent: '% — بالسالب للتخفيض', amount: 'بالجنيه — بالسالب للتخفيض', set: 'بالجنيه' })[change.value])
const example = computed(() => {
  const target = priceFields.find(f => f.value === field.value)?.label ?? ''
  const from = baseItems.value.find(f => f.value === base.value)?.label ?? ''
  if (change.value === 'set') {
    return `${target} = السعر اللي كتبته لكل الأصناف المختارة.`
  }
  if (base.value === 'cost') {
    return `${target} = متوسط التكلفة في الفرع + النسبة (مثلاً 30% على تكلفة 100 ج = 130 ج).`
  }
  return `${target} = ${from} ${change.value === 'percent' ? '± النسبة' : '± المبلغ'}.`
})

function payload() {
  const pounds = Number(value.value)
  return {
    q: q.value.trim() || undefined,
    category_id: categoryId.value || undefined,
    brand_id: brandId.value || undefined,
    device_model_id: typeof deviceModelId.value === 'number' ? deviceModelId.value : undefined,
    include_inactive: includeInactive.value,
    field: field.value,
    base: change.value === 'set' ? undefined : base.value,
    change: change.value,
    value: change.value === 'percent' ? pounds : toPiasters(pounds),
    round_to: roundTo.value,
    rounding: rounding.value,
  }
}

const preview = ref<Preview | null>(null)
const excluded = ref(new Set<string>())
const loading = ref(false)
const applying = ref(false)
const confirmOpen = ref(false)
const error = ref<string | null>(null)

const selectable = computed(() => (preview.value?.rows ?? []).filter(r => !r.skip))
const changingCount = computed(() => selectable.value.filter(r => !excluded.value.has(r.variant_id)).length)
const belowCostCount = computed(() => selectable.value.filter(r => r.below_cost && !excluded.value.has(r.variant_id)).length)
const allIncluded = computed(() => excluded.value.size === 0)

function toggle(id: string) {
  const next = new Set(excluded.value)
  if (next.has(id)) {
    next.delete(id)
  }
  else {
    next.add(id)
  }
  excluded.value = next
}
function toggleAll() {
  excluded.value = allIncluded.value ? new Set(selectable.value.map(r => r.variant_id)) : new Set()
}

let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0
async function load() {
  const valid = value.value !== '' && Number.isFinite(Number(value.value)) && (change.value !== 'set' || Number(value.value) > 0)
  if (!valid) {
    preview.value = null
    return
  }
  const id = ++requestId
  loading.value = true
  error.value = null
  try {
    const res = await api<{ data: Preview }>('/products/prices/preview', { method: 'POST', body: payload() })
    if (id === requestId) {
      preview.value = res.data
      excluded.value = new Set()
    }
  }
  catch (e) {
    if (id === requestId) {
      preview.value = null
      error.value = apiErrorMessage(e)
    }
  }
  finally {
    if (id === requestId) {
      loading.value = false
    }
  }
}
watch([q, categoryId, brandId, deviceModelId, includeInactive, field, change, base, value, roundTo, rounding], () => {
  clearTimeout(timer)
  timer = setTimeout(load, 400)
})
onBeforeUnmount(() => clearTimeout(timer))

async function apply() {
  applying.value = true
  error.value = null
  try {
    const res = await api<{ data: { changed: number } }>('/products/prices', {
      method: 'POST',
      body: { ...payload(), exclude: [...excluded.value] },
    })
    confirmOpen.value = false
    toast.add({ color: 'success', title: `اتغيرت أسعار ${res.data.changed} صنف` })
    value.value = ''
    preview.value = null
  }
  catch (e) {
    confirmOpen.value = false
    error.value = apiErrorMessage(e)
  }
  finally {
    applying.value = false
  }
}
</script>

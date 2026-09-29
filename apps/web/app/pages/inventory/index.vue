<template>
  <div class="space-y-6 pb-24">
    <PageHeader title="المخزون" :description="`أرصدة فرع «${store.currentBranch?.name ?? ''}» وحركة كل صنف.`">
      <UButton
        v-if="canAdjust"
        :color="counting ? 'neutral' : 'primary'"
        :variant="counting ? 'outline' : 'solid'"
        :icon="counting ? 'i-lucide-x' : 'i-lucide-clipboard-check'"
        :label="counting ? 'خروج من الجرد' : 'جرد'"
        @click="toggleCounting"
      />
    </PageHeader>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
      <button
        v-for="card in cards"
        :key="card.status"
        type="button"
        class="app-card rounded-[calc(var(--ui-radius)*2)] border bg-(--ui-bg) p-4 text-start transition"
        :class="status === card.status ? 'border-primary' : 'border-transparent hover:border-(--ui-border)'"
        @click="status = card.status"
      >
        <p class="text-sm text-(--ui-text-muted)">
          {{ card.label }}
        </p>
        <p class="text-2xl font-extrabold num" :class="card.class">
          {{ card.value }}
        </p>
      </button>
      <div v-if="summary?.value !== null && summary?.value !== undefined" class="app-card col-span-2 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 lg:col-span-1">
        <p class="text-sm text-(--ui-text-muted)">
          قيمة المخزون (بالتكلفة)
        </p>
        <p class="text-2xl font-extrabold num">
          {{ formatMoney(summary.value) }}
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          <span class="num">{{ summary.units }}</span> قطعة
        </p>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-[1fr_240px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر بالاسم أو الباركود أو الموديل…" class="w-full" />
      <USelect v-model="categoryId" :items="categoryItems" class="w-full" />
    </div>

    <UAlert
      v-if="counting"
      color="info"
      variant="subtle"
      icon="i-lucide-clipboard-check"
      title="وضع الجرد"
      description="اكتب الكمية اللي لقيتها فعلاً قدام كل صنف (ممكن تدوّر أو تمسح الباركود). الأصناف اللي متكتبلهاش حاجة مش هتتغيّر. لما تخلص دوس «حفظ الجرد»."
    />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الصنف
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                التصنيف
              </th>
              <th class="p-3 text-start font-bold">
                الرصيد
              </th>
              <th v-if="counting" class="p-3 text-start font-bold">
                الفعلي
              </th>
              <th v-if="canCost && !counting" class="hidden p-3 text-start font-bold lg:table-cell">
                متوسط التكلفة
              </th>
              <th v-if="canCost && !counting" class="hidden p-3 text-start font-bold lg:table-cell">
                القيمة
              </th>
              <th v-if="!counting" class="p-3" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="border-t border-(--ui-border)"
              :class="{ 'cursor-pointer hover:bg-(--ui-bg-elevated)': !counting }"
              @click="!counting && showMovements(row)"
            >
              <td class="p-3">
                <p class="font-bold">
                  {{ row.display_name }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span v-if="row.barcode" class="num">{{ row.barcode }}</span>
                  <span v-if="row.barcode && row.min_stock"> · </span>
                  <span v-if="row.min_stock">حد النواقص <span class="num">{{ row.min_stock }}</span></span>
                </p>
              </td>
              <td class="hidden p-3 text-(--ui-text-muted) md:table-cell">
                {{ row.category.name }}
              </td>
              <td class="p-3">
                <UBadge :color="statusColor(row.status)" variant="subtle" size="lg" class="num">
                  {{ row.qty }}
                </UBadge>
              </td>
              <td v-if="counting" class="p-3">
                <div class="flex items-center gap-2">
                  <UInput
                    :model-value="counts[row.id] === undefined ? '' : String(counts[row.id])"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    dir="ltr"
                    class="w-24"
                    :aria-label="`الكمية الفعلية لـ ${row.display_name}`"
                    @update:model-value="setCount(row, $event)"
                  />
                  <span v-if="counts[row.id] !== undefined" class="text-xs font-bold num" :class="diffClass(row)">
                    {{ diffLabel(row) }}
                  </span>
                </div>
                <div v-if="row.track_serial && serialsNeeded(row.id)" class="mt-2 max-w-sm">
                  <p class="mb-1 text-xs text-(--ui-text-muted)">
                    {{ (counts[row.id] ?? 0) > (known[row.id] ?? 0) ? 'IMEI / سيريال القطع الزيادة' : 'IMEI / سيريال القطع الناقصة' }}
                  </p>
                  <InventorySerialsInput v-model="countSerials[row.id]!" :required="serialsNeeded(row.id)" size="sm" />
                </div>
              </td>
              <td v-if="canCost && !counting" class="hidden p-3 num lg:table-cell">
                {{ formatMoney(row.avg_cost) }}
              </td>
              <td v-if="canCost && !counting" class="hidden p-3 font-bold num lg:table-cell">
                {{ formatMoney(row.value) }}
              </td>
              <td v-if="!counting" class="p-3 text-end" @click.stop>
                <UDropdownMenu :items="rowActions(row)" :content="{ align: 'end' }">
                  <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" square :aria-label="`إجراءات ${row.display_name}`" />
                </UDropdownMenu>
              </td>
            </tr>
            <tr v-if="!rows.length && loadStatus !== 'pending'">
              <td colspan="6" class="p-10 text-center text-(--ui-text-muted)">
                <template v-if="filtered">
                  مفيش أصناف مطابقة.
                </template>
                <template v-else>
                  لسه مفيش أصناف.
                  <ULink v-if="store.can('products.manage')" to="/products/import" class="font-bold text-primary">
                    استوردها من Excel مع الكميات
                  </ULink>
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

    <div v-if="counting && countedIds.length" class="fixed inset-x-0 bottom-0 z-30 border-t border-(--ui-border) bg-(--ui-bg) p-3 shadow-lg lg:ps-64">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
        <p class="text-sm">
          <span class="font-bold num">{{ countedIds.length }}</span> صنف اتعدّ ·
          <span class="num">{{ changedCount }}</span> فيهم فرق
        </p>
        <div class="flex flex-wrap gap-2">
          <UInput v-model="countNote" placeholder="ملاحظة (مثلاً: جرد آخر الشهر)" class="w-64" />
          <UButton icon="i-lucide-check" label="حفظ الجرد" :loading="savingCount" @click="saveCount" />
        </div>
      </div>
    </div>

    <InventoryMovementsSlideover v-model:open="movementsOpen" :variant-id="selected?.id ?? null" />
    <InventoryAdjustModal v-model:open="adjustOpen" :row="selected" :mode="adjustMode" :reasons="reasonItems" @saved="reload" />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Category, Paginated, StockRow, StockStatus, StockSummary } from '~/types/api'

definePageMeta({ permission: 'inventory.view' })

type Filter = 'all' | 'in' | 'low' | 'out'

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const canAdjust = computed(() => store.can('inventory.adjust'))
const canCost = computed(() => store.can('products.view_cost'))

const ALL = 0
const q = ref('')
const debouncedQ = ref('')
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

const categoryId = ref<number>(ALL)
const status = ref<Filter>((['low', 'out'] as const).find(f => f === useRoute().query.status) ?? 'all')
const page = ref(1)
const filtered = computed(() => !!debouncedQ.value || categoryId.value !== ALL || status.value !== 'all')
watch([debouncedQ, categoryId, status], () => {
  page.value = 1
})

const [{ data: categoriesData }, { data: reasonsData }] = await Promise.all([
  useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories')),
  useAsyncData('inventory-reasons', () => api<{ data: { value: string, label: string }[] }>('/inventory/reasons')),
])
const categoryItems = computed(() => [{ label: 'كل التصنيفات', value: ALL }, ...(categoriesData.value?.data ?? []).map(c => ({ label: c.name, value: c.id }))])
const reasonItems = computed(() => (reasonsData.value?.data ?? []).map(r => ({ label: r.label, value: r.value })))

const branchKey = computed(() => store.session?.current_branch_id ?? '')

const { data, status: loadStatus, refresh } = await useAsyncData('inventory', () => api<Paginated<StockRow>>('/inventory', {
  query: {
    q: debouncedQ.value || undefined,
    category_id: categoryId.value || undefined,
    status: status.value,
    page: page.value,
  },
}), { watch: [debouncedQ, categoryId, status, page, branchKey] })

const { data: summaryData, refresh: refreshSummary } = await useAsyncData('inventory-summary', () => api<{ data: StockSummary }>('/inventory/summary'), { watch: [branchKey] })

const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const summary = computed(() => summaryData.value?.data)

const cards = computed<{ status: Filter, label: string, value: number, class: string }[]>(() => [
  { status: 'all', label: 'كل الأصناف', value: summary.value?.variants ?? 0, class: '' },
  { status: 'in', label: 'متوفر', value: summary.value?.in_stock ?? 0, class: '' },
  { status: 'low', label: 'قرب يخلص', value: summary.value?.low ?? 0, class: summary.value?.low ? 'text-warning' : '' },
  { status: 'out', label: 'خلصان', value: summary.value?.out_of_stock ?? 0, class: summary.value?.out_of_stock ? 'text-error' : '' },
])

function statusColor(s: StockStatus) {
  return s === 'out' ? 'error' : s === 'low' ? 'warning' : 'success'
}

async function reload() {
  await Promise.all([refresh(), refreshSummary()])
}

// Row actions
const selected = ref<StockRow | null>(null)
const movementsOpen = ref(false)
const adjustOpen = ref(false)
const adjustMode = ref<'adjust' | 'opening'>('adjust')

function showMovements(row: StockRow) {
  selected.value = row
  movementsOpen.value = true
}

function rowActions(row: StockRow): DropdownMenuItem[][] {
  const open = (mode: 'adjust' | 'opening') => () => {
    selected.value = row
    adjustMode.value = mode
    adjustOpen.value = true
  }
  return [
    [{ label: 'الحركات', icon: 'i-lucide-history', onSelect: () => showMovements(row) }],
    canAdjust.value
      ? [
          { label: 'إضافة أو خصم', icon: 'i-lucide-plus-minus', onSelect: open('adjust') },
          ...(row.qty === 0 ? [{ label: 'رصيد افتتاحي', icon: 'i-lucide-package-plus', onSelect: open('opening') }] : []),
        ]
      : [],
  ].filter(group => group.length)
}

// Stocktake: counted quantities survive paging and filtering until saved. ?count=1 (a home
// quick action) opens straight into it.
const counting = ref(canAdjust.value && useRoute().query.count === '1')
const counts = reactive<Record<string, number>>({})
const known = reactive<Record<string, number>>({})
// Products that track serials: which units the difference is (the extra ones, or the missing ones).
const tracked = reactive<Record<string, string>>({})
const countSerials = reactive<Record<string, string[]>>({})
const countNote = ref('')
const savingCount = ref(false)

const countedIds = computed(() => Object.keys(counts))
const changedCount = computed(() => countedIds.value.filter(id => counts[id] !== known[id]).length)

function toggleCounting() {
  if (counting.value && countedIds.value.length && !window.confirm('فيه كميات اتكتبت ومتحفظتش. تخرج من الجرد؟')) {
    return
  }
  counting.value = !counting.value
  clearCounts()
}

function clearCounts() {
  for (const bag of [counts, countSerials, tracked]) {
    Object.keys(bag).forEach(id => delete bag[id])
  }
}

function serialsNeeded(id: string): number {
  return counts[id] === undefined ? 0 : Math.abs(counts[id] - (known[id] ?? 0))
}

function setCount(row: StockRow, value: string | number | null | undefined) {
  const n = value === '' || value === null || value === undefined ? Number.NaN : Number(value)
  if (Number.isInteger(n) && n >= 0) {
    counts[row.id] = n
    known[row.id] = row.qty
    if (row.track_serial) {
      tracked[row.id] = row.display_name
      countSerials[row.id] ??= []
    }
  }
  else {
    delete counts[row.id]
    delete tracked[row.id]
    delete countSerials[row.id]
  }
}

function diffLabel(row: StockRow): string {
  const d = (counts[row.id] ?? row.qty) - row.qty
  return d === 0 ? 'مظبوط' : d > 0 ? `+${d}` : `${d}`
}

function diffClass(row: StockRow): string {
  const d = (counts[row.id] ?? row.qty) - row.qty
  return d === 0 ? 'text-success' : 'text-warning'
}

async function saveCount() {
  const missing = Object.keys(tracked).find(id => serialsNeeded(id) !== (countSerials[id]?.length ?? 0))
  if (missing) {
    toast.add({ color: 'warning', title: `«${tracked[missing]}» محتاج IMEI / سيريال لكل قطعة فرق (${serialsNeeded(missing)}).` })
    return
  }
  savingCount.value = true
  try {
    const res = await api<{ data: { changes: unknown[] } }>('/inventory/adjustments', {
      method: 'POST',
      body: {
        reason: 'count',
        note: countNote.value || null,
        items: countedIds.value.map(id => ({ variant_id: id, counted: counts[id], serials: tracked[id] && serialsNeeded(id) ? countSerials[id] : undefined })),
      },
    })
    toast.add({ color: 'success', title: 'اتحفظ الجرد', description: `${res.data.changes.length} صنف اتعدّل رصيده` })
    clearCounts()
    countNote.value = ''
    counting.value = false
    await reload()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    savingCount.value = false
  }
}

</script>

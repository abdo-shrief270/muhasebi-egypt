<template>
  <div class="max-w-6xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/products" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="طباعة ليبلات الباركود" description="اختار الأصناف وعدد الليبلات، وحجم الورق اللي في الطابعة." class="flex-1" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
      <div class="space-y-4">
        <div class="relative">
          <UInput v-model="term" icon="i-lucide-search" size="lg" placeholder="دوّر على صنف أو امسح باركوده…" class="w-full" @keydown.enter.prevent="addFirst" />
          <div v-if="results.length && term" class="absolute inset-x-0 top-full z-10 mt-1 max-h-72 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
            <button v-for="v in results" :key="v.id" type="button" class="flex w-full justify-between gap-3 px-3 py-2 text-start hover:bg-(--ui-bg-elevated)" @click="add(v)">
              <span class="font-bold">{{ v.display_name }}</span>
              <span class="text-xs text-(--ui-text-muted) num">{{ v.barcode ?? 'من غير باركود' }}</span>
            </button>
          </div>
        </div>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  الصنف
                </th>
                <th class="p-3 text-start font-bold">
                  الباركود
                </th>
                <th class="w-28 p-3 text-start font-bold">
                  عدد الليبلات
                </th>
                <th class="w-10" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in rows" :key="row.variant.id" class="border-t border-(--ui-border)">
                <td class="p-3">
                  <p class="font-bold">
                    {{ row.variant.display_name }}
                  </p>
                  <p class="text-xs text-(--ui-text-muted) num">
                    {{ formatMoney(row.variant.price_retail) }}
                  </p>
                </td>
                <td class="p-3">
                  <span v-if="row.variant.barcode" class="num">{{ row.variant.barcode }}</span>
                  <UBadge v-else color="warning" variant="subtle">
                    من غير باركود
                  </UBadge>
                </td>
                <td class="p-3">
                  <UInput v-model="row.copies" type="number" min="1" step="1" dir="ltr" :aria-label="`عدد ليبلات ${row.variant.display_name}`" />
                </td>
                <td class="p-3">
                  <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" square :aria-label="`شيل ${row.variant.display_name}`" @click="rows.splice(i, 1)" />
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                  دوّر على الأصناف اللي عايز تطبعلها ليبلات.
                </td>
              </tr>
            </tbody>
          </table>
        </UCard>

        <UAlert
          v-if="missing.length"
          color="warning"
          variant="subtle"
          icon="i-lucide-scan-barcode"
          :title="`${missing.length} صنف من غير باركود`"
          description="اعملهم باركود داخلي (بيبدأ بـ 2) عشان يتطبعوا ويتمسحوا في الكاشير."
          :actions="canManage ? [{ label: 'اعمل باركود', loading: generating, onClick: generate }] : []"
        />
      </div>

      <div class="space-y-4">
        <UCard>
          <div class="space-y-4">
            <UFormField label="حجم الليبل">
              <USelect v-model="sizeKey" :items="sizes.map(s => ({ label: s.label, value: s.key }))" class="w-full" />
            </UFormField>
            <UFormField label="الكود">
              <div class="grid grid-cols-2 gap-2">
                <UButton :color="codeType === 'barcode' ? 'primary' : 'neutral'" :variant="codeType === 'barcode' ? 'soft' : 'outline'" icon="i-lucide-barcode" label="باركود" class="justify-center" @click="codeType = 'barcode'" />
                <UButton :color="codeType === 'qr' ? 'primary' : 'neutral'" :variant="codeType === 'qr' ? 'soft' : 'outline'" icon="i-lucide-qr-code" label="QR" class="justify-center" @click="codeType = 'qr'" />
              </div>
            </UFormField>
            <div class="space-y-2">
              <UCheckbox v-model="show.shop" label="اسم المحل" />
              <UCheckbox v-model="show.name" label="اسم الصنف" />
              <UCheckbox v-model="show.price" label="السعر" />
            </div>
          </div>
        </UCard>

        <UCard>
          <p class="mb-3 text-sm font-bold">
            معاينة
          </p>
          <div class="flex justify-center rounded-(--ui-radius) bg-(--ui-bg-muted) p-4">
            <div class="overflow-hidden rounded-sm bg-white shadow" :style="{ width: `${size.w}mm`, height: `${size.h}mm` }">
              <ProductLabel v-if="preview" :variant="preview" :size="size" :code-type="codeType" :show="show" :shop="shopName" />
            </div>
          </div>
        </UCard>

        <UButton block size="lg" icon="i-lucide-printer" :label="`اطبع ${totalLabels} ليبل`" :disabled="!printable.length" @click="print" />
        <p v-if="rows.length > printable.length" class="text-center text-xs text-(--ui-text-muted)">
          الأصناف اللي من غير باركود مش هتتطبع.
        </p>
      </div>
    </div>

    <PrintSheet v-if="printing" :page-size="size.sheet ? 'A4' : `${size.w}mm ${size.h}mm`" :margin="size.sheet ? '8mm 6mm' : '0'">
      <div :class="size.sheet ? 'grid grid-cols-3 gap-x-[2mm] gap-y-0' : ''">
        <div v-for="(label, i) in labels" :key="i" class="overflow-hidden bg-white" :style="{ width: `${size.w}mm`, height: `${size.h}mm`, breakAfter: size.sheet ? 'auto' : 'page', breakInside: 'avoid' }">
          <ProductLabel :variant="label" :size="size" :code-type="codeType" :show="show" :shop="shopName" />
        </div>
      </div>
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { LabelSize, LabelVariant } from '~/components/ProductLabel.vue'

definePageMeta({ permission: 'products.view', feature: 'catalog.labels' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const canManage = computed(() => store.can('products.manage'))
const shopName = computed(() => store.session?.tenant.name ?? '')
const { printing, print } = usePrint()

// Thermal label rolls, and a 3×8 A4 sticker sheet.
const sizes: (LabelSize & { key: string, label: string })[] = [
  { key: '38x25', label: 'رول 38 × 25 مم', w: 38, h: 25 },
  { key: '50x30', label: 'رول 50 × 30 مم', w: 50, h: 30 },
  { key: '58x40', label: 'رول 58 × 40 مم', w: 58, h: 40 },
  { key: 'a4', label: 'ورقة A4 (24 ليبل 64 × 34 مم)', w: 64, h: 34, sheet: true },
]
const sizeKey = ref('38x25')
const size = computed(() => sizes.find(s => s.key === sizeKey.value) ?? sizes[0]!)
const codeType = ref<'barcode' | 'qr'>('barcode')
const show = reactive({ shop: false, name: true, price: true })

interface Row { variant: LabelVariant, copies: string }
const rows = ref<Row[]>([])

// Opened from a product: ?ids[]=…
const initialIds = [route.query['ids[]'] ?? route.query.ids].flat().filter((v): v is string => typeof v === 'string')
if (initialIds.length) {
  const res = await api<{ data: LabelVariant[] }>('/products/labels', { query: { 'ids[]': initialIds } })
  rows.value = res.data.map(v => ({ variant: v, copies: '1' }))
}

const term = ref('')
const results = ref<LabelVariant[]>([])
let timer: ReturnType<typeof setTimeout> | undefined
watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (q.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    results.value = (await api<{ data: LabelVariant[] }>('/products/labels', { query: { q } })).data
  }, 250)
})
onBeforeUnmount(() => clearTimeout(timer))

function add(variant: LabelVariant) {
  const existing = rows.value.find(r => r.variant.id === variant.id)
  if (existing) {
    existing.copies = String((Number(existing.copies) || 0) + 1)
  }
  else {
    rows.value.push({ variant, copies: '1' })
  }
  term.value = ''
  results.value = []
}

function addFirst() {
  const exact = results.value.find(v => v.barcode === term.value.trim()) ?? results.value[0]
  if (exact) {
    add(exact)
  }
}

const missing = computed(() => rows.value.filter(r => !r.variant.barcode))
const printable = computed(() => rows.value.filter(r => r.variant.barcode))
const labels = computed(() => printable.value.flatMap(r => Array.from({ length: Math.min(500, Math.max(0, Number(r.copies) || 0)) }, () => r.variant)))
const totalLabels = computed(() => labels.value.length)
const preview = computed(() => printable.value[0]?.variant ?? rows.value[0]?.variant ?? null)

const generating = ref(false)
async function generate() {
  generating.value = true
  try {
    const res = await api<{ data: Record<string, string> }>('/products/barcodes', { method: 'POST', body: { variant_ids: missing.value.map(r => r.variant.id) } })
    for (const row of rows.value) {
      row.variant.barcode = res.data[row.variant.id] ?? row.variant.barcode
    }
    toast.add({ color: 'success', title: `اتعمل باركود لـ ${Object.keys(res.data).length} صنف` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    generating.value = false
  }
}
</script>

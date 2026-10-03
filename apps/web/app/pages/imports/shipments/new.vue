<template>
  <div class="space-y-6">
    <PageHeader title="شحنة جديدة" description="الأصناف بسعر الشراء بالجنيه. المصاريف (شحن، جمارك…) بتضيفها في صفحة الشحنة، والتكلفة النهائية بتتحسب وقت الاستلام.">
      <UButton to="/imports" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الاستيراد" />
    </PageHeader>

    <UCard>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <UFormField label="المورد (مصنع / تاجر / وسيط)" required :error="errors.contact_id">
          <USelect v-model="form.contact_id" :items="suppliers.map(c => ({ value: c.id, label: c.name }))" class="w-full" placeholder="اختار" />
        </UFormField>
        <UFormField label="هتتستلم في فرع" required>
          <USelect v-model="form.branch_id" :items="branches.map(b => ({ value: b.id, label: b.name }))" class="w-full" />
        </UFormField>
        <UFormField label="المبلغ الأصلي (للرجوع ليه بس)" hint="اختياري">
          <UInput v-model="form.original_amount" class="w-full" maxlength="60" placeholder="12,000 USD" dir="ltr" />
        </UFormField>
        <UFormField label="تاريخ الطلب" required>
          <UInput v-model="form.ordered_on" type="date" class="w-full" />
        </UFormField>
        <UFormField label="الوصول المتوقع" hint="اختياري">
          <UInput v-model="form.expected_on" type="date" class="w-full" />
        </UFormField>
        <UFormField label="المصاريف بتتوزع على الأصناف">
          <URadioGroup v-model="form.allocation" orientation="horizontal" :items="[{ value: 'value', label: 'بالقيمة' }, { value: 'qty', label: 'بالعدد' }]" />
        </UFormField>
      </div>
      <p v-if="!suppliers.length" class="mt-3 text-sm text-(--ui-text-muted)">
        مفيش موردين لسه. <ULink to="/imports/contacts" class="font-bold text-primary">ضيف جهة</ULink> الأول.
      </p>
    </UCard>

    <UCard :ui="{ body: 'space-y-4' }">
      <div class="relative">
        <UInput v-model="term" icon="i-lucide-scan-barcode" placeholder="اكتب اسم الصنف أو الباركود…" class="w-full" size="lg" />
        <div v-if="results.length && term" class="absolute inset-x-0 top-full z-10 mt-1 max-h-80 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
          <button v-for="v in results" :key="v.id" type="button" class="block w-full px-3 py-2 text-start hover:bg-(--ui-bg-elevated)" @click="add(v)">
            <span class="font-bold">{{ v.display_name }}</span>
            <span class="block text-xs text-(--ui-text-muted)">{{ v.category.name }}</span>
          </button>
        </div>
        <p v-if="term.length >= 2 && !results.length" class="mt-1 text-sm text-(--ui-text-muted)">
          مفيش صنف كده. <ULink v-if="store.can('products.manage')" to="/products/new" target="_blank" class="font-bold text-primary">ضيفه</ULink>
        </p>
      </div>

      <table v-if="lines.length" class="w-full text-sm">
        <thead class="text-(--ui-text-muted)">
          <tr>
            <th class="p-2 text-start">
              الصنف
            </th>
            <th class="w-28 p-2 text-start">
              الكمية
            </th>
            <th class="w-36 p-2 text-start">
              سعر الشراء (ج)
            </th>
            <th class="w-28 p-2 text-start">
              الإجمالي
            </th>
            <th class="w-10" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, i) in lines" :key="line.id" class="border-t border-(--ui-border)">
            <td class="p-2 font-bold">
              {{ line.name }}
            </td>
            <td class="p-2">
              <UInput v-model="line.qty" type="number" min="1" dir="ltr" class="w-full" :aria-label="`كمية ${line.name}`" />
            </td>
            <td class="p-2">
              <UInput v-model="line.price" type="number" min="0" step="any" dir="ltr" class="w-full" :aria-label="`سعر ${line.name}`" />
            </td>
            <td class="num p-2">
              {{ formatMoney(lineTotal(line)) }}
            </td>
            <td class="p-2">
              <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${line.name}`" @click="lines.splice(i, 1)" />
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t border-(--ui-border) font-extrabold">
            <td class="p-2" colspan="3">
              إجمالي البضاعة
            </td>
            <td class="num p-2" colspan="2">
              {{ formatMoney(total) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </UCard>

    <UFormField label="ملاحظات">
      <UTextarea v-model="form.notes" :rows="2" autoresize class="w-full" maxlength="1000" />
    </UFormField>
    <UAlert v-if="error" color="error" variant="subtle" :title="error" />
    <div class="flex justify-end gap-2">
      <UButton to="/imports" color="neutral" variant="ghost" label="إلغاء" />
      <UButton icon="i-lucide-check" label="سجّل الشحنة" :loading="saving" :disabled="!form.contact_id || !lines.length" @click="save" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ImportContact, ImportShipmentDetail } from '~/types/api'

definePageMeta({ permission: 'imports.manage', module: 'imports' })

const api = useApi()
const store = useSessionStore()
const branches = computed(() => store.session?.branches ?? [])

const { data: contactData } = await useAsyncData('import-suppliers', () => api<{ data: ImportContact[] }>('/imports/contacts'))
const suppliers = computed(() => (contactData.value?.data ?? []).filter(c => c.is_active && (c.type === 'supplier' || c.type === 'agent')))

const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' })
const form = reactive({
  contact_id: undefined as string | undefined,
  branch_id: store.session?.current_branch_id ?? branches.value[0]?.id,
  ordered_on: today,
  expected_on: '',
  allocation: 'value' as 'value' | 'qty',
  original_amount: '',
  notes: '',
})

interface Line { id: string, name: string, qty: string, price: string }
const lines = ref<Line[]>([])
const lineTotal = (l: Line) => (Number(l.qty) || 0) * (toPiasters(l.price) ?? 0)
const total = computed(() => lines.value.reduce((s, l) => s + lineTotal(l), 0))

const term = ref('')
const results = ref<{ id: string, display_name: string, category: { name: string } }[]>([])
let timer: ReturnType<typeof setTimeout> | undefined
watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (q.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    results.value = (await api<{ data: typeof results.value }>('/imports/variants', { query: { q } }).catch(() => ({ data: [] }))).data
  }, 200)
})
onBeforeUnmount(() => clearTimeout(timer))

function add(v: { id: string, display_name: string }) {
  if (!lines.value.some(l => l.id === v.id)) {
    lines.value.push({ id: v.id, name: v.display_name, qty: '1', price: '' })
  }
  term.value = ''
  results.value = []
}

const saving = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
async function save() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: ImportShipmentDetail }>('/imports/shipments', {
      method: 'POST',
      body: {
        ...form,
        expected_on: form.expected_on || null,
        original_amount: form.original_amount.trim() || null,
        notes: form.notes.trim() || null,
        items: lines.value.map(l => ({ variant_id: l.id, qty: Number(l.qty) || 0, unit_price: toPiasters(l.price) ?? 0 })),
      },
    })
    await navigateTo(`/imports/shipments/${res.data.id}`)
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl">
    <div class="flex items-center gap-3">
      <UButton to="/purchases" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <h1 class="text-2xl font-extrabold">
        فاتورة شراء
      </h1>
      <UBadge color="neutral" variant="subtle">
        فرع {{ store.currentBranch?.name }}
      </UBadge>
    </div>

    <UCard>
      <div class="grid gap-4 md:grid-cols-3">
        <UFormField label="المورد" required>
          <USelectMenu v-model="form.supplier_id" :items="supplierItems" value-key="value" placeholder="اختار المورد" class="w-full" />
        </UFormField>
        <UFormField label="تاريخ الفاتورة" required>
          <UInput v-model="form.invoice_date" type="date" class="w-full" />
        </UFormField>
        <UFormField label="رقم فاتورة المورد" hint="اختياري">
          <UInput v-model="form.supplier_invoice_no" dir="ltr" class="w-full" />
        </UFormField>
      </div>
      <p v-if="!suppliers.length" class="mt-3 text-sm text-(--ui-text-muted)">
        مفيش موردين لسه. <ULink to="/suppliers" class="font-bold text-primary">ضيف مورد</ULink> الأول.
      </p>
    </UCard>

    <UCard :ui="{ body: 'space-y-4' }">
      <div class="relative">
        <UInput
          ref="searchInput"
          v-model="term"
          icon="i-lucide-scan-barcode"
          placeholder="امسح الباركود أو اكتب اسم الصنف…"
          class="w-full"
          size="lg"
          @keydown.enter.prevent="addFirst"
        />
        <div v-if="results.length && term" class="absolute inset-x-0 top-full z-10 mt-1 max-h-80 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
          <button
            v-for="v in results"
            :key="v.id"
            type="button"
            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start hover:bg-(--ui-bg-elevated)"
            @click="add(v)"
          >
            <span>
              <span class="font-bold">{{ v.display_name }}</span>
              <span class="block text-xs text-(--ui-text-muted)">{{ v.category.name }}<span v-if="v.barcode"> · <span class="num">{{ v.barcode }}</span></span></span>
            </span>
            <span v-if="v.avg_cost !== null" class="text-xs text-(--ui-text-muted)">آخر تكلفة <span class="num">{{ formatMoney(v.avg_cost) }}</span></span>
          </button>
        </div>
        <p v-else-if="term.length >= 2 && !searching" class="mt-1 text-sm text-(--ui-text-muted)">
          مفيش صنف كده.<template v-if="store.can('products.manage')">
            <ULink to="/products/new" target="_blank" class="font-bold text-primary">ضيفه</ULink> وارجع.
          </template>
        </p>
      </div>

      <div v-if="form.items.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-(--ui-text-muted)">
            <tr>
              <th class="p-2 text-start font-bold">
                الصنف
              </th>
              <th class="w-28 p-2 text-start font-bold">
                الكمية
              </th>
              <th class="w-36 p-2 text-start font-bold">
                سعر الشراء
              </th>
              <th class="w-28 p-2 text-start font-bold">
                الإجمالي
              </th>
              <th class="w-10" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, i) in form.items" :key="line.variant.id" class="border-t border-(--ui-border)">
              <td class="p-2">
                <p class="font-bold">
                  {{ line.variant.display_name }}
                </p>
                <p v-if="line.variant.avg_cost !== null" class="text-xs" :class="costUp(line) ? 'text-warning' : 'text-(--ui-text-muted)'">
                  آخر تكلفة <span class="num">{{ formatMoney(line.variant.avg_cost) }}</span>
                  <span v-if="costUp(line)"> · التكلفة زادت</span>
                </p>
                <InventorySerialsInput v-if="line.variant.track_serial" v-model="line.serials" class="mt-2" />
              </td>
              <td class="p-2 align-top">
                <UInput
                  v-if="!line.variant.track_serial"
                  v-model="line.qty"
                  type="number"
                  min="1"
                  step="1"
                  inputmode="numeric"
                  dir="ltr"
                  class="w-full"
                  :aria-label="`كمية ${line.variant.display_name}`"
                />
                <p v-else class="num p-2 font-bold" title="الكمية = عدد السيريالات">
                  {{ line.serials.length }}
                </p>
              </td>
              <td class="p-2">
                <UInput v-model="line.cost" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" :aria-label="`سعر شراء ${line.variant.display_name}`" />
              </td>
              <td class="p-2 font-bold num">
                {{ formatMoney(lineTotal(line)) }}
              </td>
              <td class="p-2">
                <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" square :aria-label="`شيل ${line.variant.display_name}`" @click="form.items.splice(i, 1)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="py-6 text-center text-(--ui-text-muted)">
        امسح أو دوّر على الأصناف اللي في الفاتورة.
      </p>
    </UCard>

    <UCard>
      <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <UFormField label="خصم على الفاتورة" hint="بالجنيه">
              <UInput v-model="form.discount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="دفعت دلوقتي" hint="بالجنيه">
              <UInput v-model="form.paid" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
          </div>
          <UFormField v-if="paidPiasters > 0" label="طريقة الدفع" required>
            <USelect v-model="form.payment_method" :items="methods" class="w-full" />
          </UFormField>
          <UCheckbox
            v-if="paidPiasters > 0 && form.payment_method !== 'bank_transfer'"
            v-model="form.from_drawer"
            label="من درج الوردية"
            description="اتدفعت من فلوس الدرج — شيلها لو دفعت من الخزنة أو من جيبك"
          />
          <UFormField label="ملاحظات">
            <UTextarea v-model="form.notes" :rows="2" class="w-full" />
          </UFormField>
        </div>
        <dl class="space-y-2 self-end rounded-[calc(var(--ui-radius)*1.5)] bg-(--ui-bg-muted) p-4">
          <div class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              الإجمالي
            </dt>
            <dd class="num">
              {{ formatMoney(subtotal) }}
            </dd>
          </div>
          <div v-if="discountPiasters" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              خصم
            </dt>
            <dd class="text-error num">
              −{{ formatMoney(discountPiasters) }}
            </dd>
          </div>
          <div class="flex justify-between text-lg font-extrabold">
            <dt>المطلوب</dt>
            <dd class="num">
              {{ formatMoney(total) }}
            </dd>
          </div>
          <div class="flex justify-between border-t border-(--ui-border) pt-2">
            <dt class="text-(--ui-text-muted)">
              يتسجّل على حساب المورد
            </dt>
            <dd class="font-bold text-warning num">
              {{ formatMoney(Math.max(0, total - paidPiasters)) }}
            </dd>
          </div>
        </dl>
      </div>
    </UCard>

    <UAlert v-if="error" color="error" variant="subtle" icon="i-lucide-circle-alert" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />

    <div class="flex justify-end gap-2">
      <UButton to="/purchases" color="neutral" variant="ghost" label="إلغاء" />
      <UButton size="lg" icon="i-lucide-check" :label="`حفظ الفاتورة (${formatMoney(total)})`" :loading="saving" :disabled="!form.items.length || !form.supplier_id" @click="save" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PurchasableVariant, Purchase, Supplier } from '~/types/api'

definePageMeta({ permission: 'suppliers.manage' })

interface Line { variant: PurchasableVariant, qty: string, cost: string, serials: string[] }

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()

const [{ data: suppliersData }, { data: methodsData }] = await Promise.all([
  useAsyncData('suppliers-active', () => api<{ data: Supplier[] }>('/suppliers', { query: { active: 1 } })),
  useAsyncData('payment-methods', () => api<{ data: { value: string, label: string }[] }>('/suppliers/payment-methods')),
])
const suppliers = computed(() => suppliersData.value?.data ?? [])
const supplierItems = computed(() => suppliers.value.map(s => ({ label: s.name, value: s.id })))
const methods = computed(() => (methodsData.value?.data ?? []).map(m => ({ label: m.label, value: m.value })))

const form = reactive({
  supplier_id: typeof route.query.supplier === 'string' ? route.query.supplier : undefined as string | undefined,
  invoice_date: new Date().toISOString().slice(0, 10),
  supplier_invoice_no: '',
  discount: '',
  paid: '',
  payment_method: 'cash',
  from_drawer: true,
  notes: '',
  items: [] as Line[],
})

// Search / scan
const term = ref('')
const results = ref<PurchasableVariant[]>([])
const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (q.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    const id = ++requestId
    searching.value = true
    try {
      const res = await api<{ data: PurchasableVariant[] }>('/purchases/variants', { query: { q } })
      if (id === requestId) {
        results.value = res.data
        // A scanner types the barcode and presses Enter; an exact match is added straight away.
        if (res.data[0]?.exact_barcode && pendingEnter) {
          add(res.data[0])
        }
      }
    }
    finally {
      if (id === requestId) {
        searching.value = false
        pendingEnter = false
      }
    }
  }, 200)
})
onBeforeUnmount(() => clearTimeout(timer))

let pendingEnter = false
function addFirst() {
  if (results.value[0] && !searching.value) {
    add(results.value[0])
  }
  else {
    pendingEnter = true
  }
}

function add(variant: PurchasableVariant) {
  const existing = form.items.find(l => l.variant.id === variant.id)
  if (existing) {
    if (!variant.track_serial) {
      existing.qty = String((Number(existing.qty) || 0) + 1)
    }
  }
  else {
    form.items.push({ variant, qty: variant.track_serial ? '0' : '1', cost: variant.avg_cost !== null ? String(variant.avg_cost / 100) : '', serials: [] })
  }
  term.value = ''
  results.value = []
}

const lineQty = (l: Line) => l.variant.track_serial ? l.serials.length : (Number(l.qty) || 0)
const lineTotal = (l: Line) => lineQty(l) * (toPiasters(l.cost) ?? 0)
const costUp = (l: Line) => l.variant.avg_cost !== null && l.variant.avg_cost > 0 && (toPiasters(l.cost) ?? 0) > l.variant.avg_cost
const subtotal = computed(() => form.items.reduce((sum, l) => sum + lineTotal(l), 0))
const discountPiasters = computed(() => toPiasters(form.discount) ?? 0)
const paidPiasters = computed(() => toPiasters(form.paid) ?? 0)
const total = computed(() => Math.max(0, subtotal.value - discountPiasters.value))

const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

async function save() {
  const missing = form.items.find(l => l.variant.track_serial && !l.serials.length)
  if (missing) {
    error.value = `امسح IMEI / سيريال كل قطعة من «${missing.variant.display_name}».`
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: Purchase }>('/purchases', {
      method: 'POST',
      body: {
        supplier_id: form.supplier_id,
        invoice_date: form.invoice_date,
        supplier_invoice_no: form.supplier_invoice_no || null,
        discount: discountPiasters.value,
        paid: paidPiasters.value,
        payment_method: paidPiasters.value > 0 ? form.payment_method : null,
        from_drawer: form.from_drawer,
        notes: form.notes || null,
        items: form.items.map(l => ({ variant_id: l.variant.id, qty: lineQty(l), unit_cost: toPiasters(l.cost) ?? 0, serials: l.variant.track_serial ? l.serials : undefined })),
      },
    })
    const increases = res.data.items.filter(i => i.cost_increased).length
    toast.add({
      color: increases ? 'warning' : 'success',
      title: `اتحفظت الفاتورة ${res.data.reference}`,
      description: increases ? `${increases} صنف تكلفته زادت عن قبل كده — راجع أسعار البيع.` : 'البضاعة دخلت المخزون.',
    })
    await navigateTo(`/purchases/${res.data.id}`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
  }
  finally {
    saving.value = false
  }
}
</script>

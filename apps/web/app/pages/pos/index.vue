<template>
  <!-- Selling goes into the cashier's drawer: no open shift, no sale. -->
  <div v-if="shiftChecked && !hasShift" class="grid min-h-[60dvh] place-items-center">
    <CashOpenShiftCard v-if="canShift" hint="لازم وردية مفتوحة عشان تبيع. اكتب الكاش اللي في الدرج وابدأ." @opened="() => refreshShift()" />
    <UAlert v-else color="warning" variant="subtle" icon="i-lucide-lock" title="مفيش وردية مفتوحة" description="البيع محتاج وردية، ومعندكش صلاحية فتح وردية. كلّم المدير." class="max-w-md" />
  </div>
  <div v-else class="-m-4 flex min-h-[calc(100dvh-4rem)] flex-col gap-4 p-4 lg:-m-8 lg:flex-row lg:p-6">
    <!-- Items -->
    <section class="flex min-w-0 flex-1 flex-col gap-3">
      <div class="flex gap-2">
        <UInput
          ref="searchRef"
          v-model="term"
          size="xl"
          icon="i-lucide-scan-barcode"
          placeholder="امسح الباركود أو دوّر بالاسم أو الموديل… (F2)"
          class="flex-1"
          autofocus
          @keydown.enter.prevent="onEnter"
          @keydown.esc="term = ''"
        />
        <UDropdownMenu v-if="held.length" :items="heldItems" :content="{ align: 'end' }">
          <UButton size="xl" color="neutral" variant="outline" icon="i-lucide-pause" :label="`المعلّقة (${held.length})`" />
        </UDropdownMenu>
      </div>

      <div class="flex gap-2 overflow-x-auto pb-1">
        <UButton
          v-for="c in chips"
          :key="c.id"
          size="sm"
          :color="categoryId === c.id ? 'primary' : 'neutral'"
          :variant="categoryId === c.id ? 'solid' : 'outline'"
          class="shrink-0 rounded-full"
          :label="c.name"
          @click="categoryId = c.id"
        />
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="app-card flex flex-col gap-2 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-3 text-start transition hover:ring-2 hover:ring-primary active:scale-[.98]"
          @click="addItem(item)"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="line-clamp-2 font-bold leading-snug">
              {{ item.display_name }}
            </p>
            <UBadge v-if="item.quality_label" size="sm" color="neutral" variant="subtle" class="shrink-0">
              {{ item.quality_label }}
            </UBadge>
          </div>
          <p class="text-xs text-(--ui-text-muted)">
            {{ item.category.name }}
          </p>
          <div class="mt-auto flex items-end justify-between">
            <span class="text-lg font-extrabold num">{{ formatMoney(priceOf(item)) }}</span>
            <UBadge size="sm" variant="subtle" :color="item.qty <= 0 ? 'error' : item.min_stock && item.qty <= item.min_stock ? 'warning' : 'neutral'" class="num">
              {{ item.qty <= 0 ? 'خلصان' : `مخزون ${item.qty}` }}
            </UBadge>
          </div>
        </button>
      </div>
      <p v-if="!items.length && itemsStatus !== 'pending'" class="py-10 text-center text-(--ui-text-muted)">
        {{ term ? 'مفيش صنف كده.' : 'مفيش أصناف لسه.' }}
      </p>
    </section>

    <!-- Cart -->
    <aside class="app-card flex w-full flex-col rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) lg:sticky lg:top-20 lg:h-[calc(100dvh-7rem)] lg:w-[400px] lg:shrink-0">
      <div class="flex items-center justify-between gap-2 border-b border-(--ui-border) p-4">
        <div>
          <p class="text-lg font-extrabold">
            فاتورة جديدة
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            <span class="num">{{ count }}</span> قطعة
          </p>
        </div>
        <div class="flex items-center gap-1">
          <USelect v-if="canDiscount" v-model="cart.price_level" :items="priceLevels" size="sm" class="w-24" />
          <UDropdownMenu :items="cartMenu" :content="{ align: 'end' }">
            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" square aria-label="خيارات الفاتورة" />
          </UDropdownMenu>
        </div>
      </div>

      <div v-if="canCustomers" class="border-b border-(--ui-border) p-3">
        <PosCustomerPicker v-model="cart.customer" />
      </div>
      <div v-else-if="showCustomer" class="grid grid-cols-2 gap-2 border-b border-(--ui-border) p-3">
        <UInput v-model="cart.customer_name" size="sm" placeholder="اسم العميل" />
        <UInput v-model="cart.customer_phone" size="sm" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" />
      </div>

      <ul class="flex-1 divide-y divide-(--ui-border) overflow-y-auto px-4">
        <li v-for="line in cart.lines" :key="line.variant_id" class="py-3">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate font-bold">
                {{ line.name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                <span class="num">{{ formatMoney(unitPrice(line)) }}</span> للقطعة
                <span v-if="line.discount" class="text-error"> · خصم <span class="num">{{ formatMoney(line.discount) }}</span></span>
                <span v-if="line.qty > line.stock" class="text-warning"> · المخزون <span class="num">{{ line.stock }}</span></span>
              </p>
            </div>
            <span class="font-extrabold num">{{ formatMoney(lineTotal(line)) }}</span>
          </div>
          <InventorySerialsInput
            v-if="line.track_serial"
            v-model="line.serials!"
            size="sm"
            class="mt-2"
            placeholder="امسح IMEI القطعة…"
            @change="s => { line.qty = s.length; line.discount = Math.min(line.discount, unitPrice(line) * s.length) }"
          />
          <div class="mt-2 flex items-center gap-2">
            <UFieldGroup v-if="!line.track_serial" size="sm">
              <UButton color="neutral" variant="outline" icon="i-lucide-minus" :aria-label="`قلّل ${line.name}`" @click="setQty(line, line.qty - 1)" />
              <UInput :model-value="String(line.qty)" class="w-14" dir="ltr" inputmode="numeric" :ui="{ base: 'text-center' }" :aria-label="`كمية ${line.name}`" @update:model-value="v => setQty(line, Number(v) || 0)" />
              <UButton color="neutral" variant="outline" icon="i-lucide-plus" :aria-label="`زوّد ${line.name}`" @click="setQty(line, line.qty + 1)" />
            </UFieldGroup>
            <UPopover v-if="canDiscount">
              <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-percent" :aria-label="`خصم على ${line.name}`" />
              <template #content>
                <div class="w-56 space-y-2 p-3">
                  <p class="text-sm font-bold">
                    خصم على الصنف (جنيه)
                  </p>
                  <UInput
                    :model-value="line.discount ? String(line.discount / 100) : ''"
                    type="number"
                    min="0"
                    step="any"
                    dir="ltr"
                    class="w-full"
                    @update:model-value="v => line.discount = Math.min(toPiasters(v) ?? 0, unitPrice(line) * line.qty)"
                  />
                </div>
              </template>
            </UPopover>
            <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-trash-2" class="ms-auto" :aria-label="`شيل ${line.name}`" @click="remove(line)" />
          </div>
        </li>
        <li v-if="!cart.lines.length" class="py-12 text-center text-(--ui-text-muted)">
          <UIcon name="i-lucide-scan-barcode" class="mx-auto mb-2 size-10 opacity-40" />
          <p>امسح أو دوس على صنف عشان تضيفه</p>
        </li>
      </ul>

      <div class="space-y-2 border-t border-(--ui-border) p-4">
        <div class="flex justify-between text-sm">
          <span class="text-(--ui-text-muted)">الإجمالي</span>
          <span class="num">{{ formatMoney(subtotal) }}</span>
        </div>
        <div v-if="canDiscount" class="flex items-center justify-between gap-2 text-sm">
          <span class="text-(--ui-text-muted)">خصم على الفاتورة</span>
          <UInput
            :model-value="cart.discount ? String(cart.discount / 100) : ''"
            size="xs"
            type="number"
            min="0"
            step="any"
            dir="ltr"
            placeholder="0"
            class="w-24"
            aria-label="خصم على الفاتورة"
            @update:model-value="v => cart.discount = Math.min(toPiasters(v) ?? 0, subtotal)"
          />
        </div>
        <div class="flex items-center justify-between">
          <span class="text-lg font-bold">المطلوب</span>
          <span class="text-3xl font-extrabold num">{{ formatMoney(total) }}</span>
        </div>
        <div class="grid grid-cols-[auto_1fr] gap-2 pt-1">
          <UButton size="xl" color="neutral" variant="outline" icon="i-lucide-pause" label="تعليق" :disabled="!cart.lines.length" @click="hold" />
          <UButton size="xl" icon="i-lucide-check" class="justify-center" :label="`دفع ${formatMoney(total)} (F9)`" :disabled="!cart.lines.length || missingSerials.length > 0" @click="payOpen = true" />
        </div>
        <p v-if="missingSerials.length" class="text-sm text-(--ui-warning)">
          امسح IMEI / سيريال «{{ missingSerials[0]?.name }}» الأول.
        </p>
      </div>
    </aside>

    <PosPaymentModal v-model:open="payOpen" :total="total" :methods="payMethods" :credit-available="creditAvailable" :customer-name="cart.customer?.name ?? null" :loading="paying" :error="payError" @pay="checkout" />
    <PosReceiptModal ref="receiptRef" v-model:open="receiptOpen" :sale="lastSale" />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { CashShift, Category, Customer, PosItem, Sale } from '~/types/api'

definePageMeta({ permission: 'sales.sell' })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const canDiscount = computed(() => store.can('sales.discount'))
const canCustomers = computed(() => store.can('customers.view'))
const canCredit = computed(() => store.can('customers.credit'))
const canShift = computed(() => store.can('cash.shift'))
const route = useRoute()

const branchId = computed(() => store.session?.current_branch_id)
const { cart, held, subtotal, total, count, missingSerials, unitPrice, lineTotal, add, setQty, remove, clear, hold, resume, dropHeld } = usePosCart(branchId)

// Options
const [{ data: optionsData }, { data: categoriesData }] = await Promise.all([
  useAsyncData('pos-options', () => api<{ data: { payment_methods: { value: string, label: string }[], price_levels: { value: string, label: string }[] } }>('/pos/options')),
  useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories')),
])
const methods = computed(() => optionsData.value?.data.payment_methods ?? [])
// آجل only for a customer with an account, and only for whoever may give credit.
const payMethods = computed(() => methods.value.filter(m => m.value !== 'credit' || (cart.value.customer && canCredit.value)))
const creditAvailable = computed(() => {
  const c = cart.value.customer
  return c && c.credit_limit !== null ? Math.max(0, c.credit_limit - c.balance) : null
})

// The cashier's shift in this branch.
const { data: shiftData, status: shiftStatus, refresh: refreshShift } = await useAsyncData('pos-shift', () => api<{ data: CashShift | null }>('/cash/current'), { watch: [branchId] })
const shiftChecked = computed(() => shiftStatus.value !== 'pending' || shiftData.value !== undefined)
const hasShift = computed(() => !!shiftData.value?.data)

// Added from the price check: /pos?add=<variant id>
if (typeof route.query.add === 'string') {
  const res = await api<{ data: PosItem[] }>('/pos/items', { query: { 'ids[]': [route.query.add] } }).catch(() => ({ data: [] as PosItem[] }))
  if (res.data[0]) {
    add(res.data[0])
  }
  useRouter().replace({ query: { ...route.query, add: undefined } })
}

// Opened from a customer's page: /pos?customer=…
if (typeof route.query.customer === 'string' && canCustomers.value) {
  try {
    const c = (await api<{ data: Customer }>(`/customers/${route.query.customer}`)).data
    cart.value.customer = { id: c.id, name: c.name, phone: c.phone, balance: c.balance, credit_limit: c.credit_limit }
  }
  catch {
    // Not found / not allowed: sell without a customer.
  }
}
const priceLevels = computed(() => optionsData.value?.data.price_levels ?? [])
const ALL = 0
const categoryId = ref(ALL)
const chips = computed(() => [{ id: ALL, name: 'الكل' }, ...(categoriesData.value?.data ?? []).filter(c => (c.products_count ?? 0) > 0)])

// Items
const term = ref('')
const debounced = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(term, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debounced.value = value.trim()
  }, 250)
})
onBeforeUnmount(() => clearTimeout(timer))

const fetchItems = (q: string) => api<{ data: PosItem[] }>('/pos/items', { query: { q: q || undefined, category_id: categoryId.value || undefined } })
const { data: itemsData, status: itemsStatus, refresh: refreshItems } = await useAsyncData('pos-items', () => fetchItems(debounced.value), { watch: [debounced, categoryId, branchId] })
const items = computed(() => itemsData.value?.data ?? [])

const priceOf = (item: PosItem) => {
  const level = cart.value.price_level
  return (level === 'wholesale' ? item.price_wholesale : level === 'technician' ? item.price_technician : null) ?? item.price_retail
}

const searchRef = ref<{ inputRef?: HTMLInputElement } | null>(null)
const focusSearch = () => nextTick(() => searchRef.value?.inputRef?.focus())

function addItem(item: PosItem) {
  add(item)
  if (term.value) {
    term.value = ''
  }
  focusSearch()
}

/** A scanner types the code and presses Enter: add the exact match (or the only result) right away. */
async function onEnter() {
  const q = term.value.trim()
  if (!q) {
    return
  }
  const res = await fetchItems(q)
  const hit = res.data.find(i => i.exact_barcode) ?? (res.data.length === 1 ? res.data[0] : undefined)
  if (hit) {
    addItem(hit)
  }
  else if (!res.data.length) {
    // An IMEI / serial of a unit in stock here adds that phone with its serial.
    if (/^[\dA-Za-z\s-]{6,}$/.test(q) && await addBySerial(q)) {
      return
    }
    toast.add({ color: 'warning', title: `مفيش صنف بـ «${q}»` })
    term.value = ''
  }
}

async function addBySerial(q: string): Promise<boolean> {
  const serial = q.replace(/[\s-]+/g, '').toUpperCase()
  const found = await api<{ data: { serial: string, variant: { id: string } | null }[] }>('/inventory/serials', { query: { q: serial, in_stock: 1 } })
    .then(r => r.data.find(s => s.serial === serial))
    .catch(() => undefined)
  if (!found?.variant) {
    return false
  }
  const res = await api<{ data: PosItem[] }>('/pos/items', { query: { 'ids[]': [found.variant.id] } }).catch(() => ({ data: [] as PosItem[] }))
  if (!res.data[0]) {
    return false
  }
  add(res.data[0], 1, found.serial)
  term.value = ''
  focusSearch()
  return true
}

// Held invoices
const heldItems = computed<DropdownMenuItem[][]>(() => [held.value.map(c => ({
  label: `${c.customer?.name || c.customer_name || 'فاتورة'} · ${c.lines.length} صنف`,
  description: c.held_at ? formatDate(c.held_at, true) : undefined,
  icon: 'i-lucide-play',
  onSelect: () => resume(c),
})), held.value.length ? [{ label: 'امسح كل المعلّقة', icon: 'i-lucide-trash-2', color: 'error' as const, onSelect: () => held.value.forEach(dropHeld) }] : []])

const showCustomer = ref(!!cart.value.customer_name || !!cart.value.customer_phone)
const cartMenu = computed<DropdownMenuItem[][]>(() => [[
  ...(canCustomers.value ? [] : [{ label: showCustomer.value ? 'إخفاء بيانات العميل' : 'بيانات العميل', icon: 'i-lucide-user', onSelect: () => { showCustomer.value = !showCustomer.value } }]),
  { label: 'فاتورة جديدة (تفريغ)', icon: 'i-lucide-rotate-ccw', color: 'error' as const, disabled: !cart.value.lines.length, onSelect: () => { clear(); focusSearch() } },
]])

// Checkout
const payOpen = ref(false)
const paying = ref(false)
const payError = ref<string | null>(null)
const receiptOpen = ref(false)
const receiptRef = ref<{ print: () => Promise<void> } | null>(null)
const lastSale = ref<Sale | null>(null)

watch(payOpen, (isOpen) => {
  if (isOpen) {
    payError.value = null
  }
  else if (!receiptOpen.value) {
    focusSearch()
  }
})
watch(receiptOpen, (isOpen) => {
  if (!isOpen) {
    focusSearch()
  }
})

async function checkout(payments: { method: string, amount: number }[]) {
  paying.value = true
  payError.value = null
  try {
    const res = await api<{ data: Sale }>('/sales', {
      method: 'POST',
      body: {
        id: cart.value.id,
        price_level: cart.value.price_level,
        discount: cart.value.discount,
        customer_id: cart.value.customer?.id ?? null,
        customer_name: cart.value.customer ? null : cart.value.customer_name || null,
        customer_phone: cart.value.customer ? null : cart.value.customer_phone || null,
        items: cart.value.lines.map(l => ({ variant_id: l.variant_id, qty: l.qty, discount: l.discount, serials: l.track_serial ? l.serials : undefined })),
        payments,
      },
    })
    lastSale.value = res.data
    clear()
    showCustomer.value = false
    payOpen.value = false
    receiptOpen.value = true
    refreshItems()
  }
  catch (e) {
    payError.value = apiErrorMessage(e)
    if (apiErrorCode(e) === 'shift_not_open') {
      payOpen.value = false
      await refreshShift()
    }
  }
  finally {
    paying.value = false
  }
}

// The cursor lives in the search box (for the scanner), so the function keys must work inside inputs.
defineShortcuts({
  f2: { usingInput: true, handler: () => focusSearch() },
  f9: {
    usingInput: true,
    handler: () => {
      if (cart.value.lines.length && !missingSerials.value.length && !payOpen.value && !receiptOpen.value) {
        payOpen.value = true
      }
    },
  },
})
</script>

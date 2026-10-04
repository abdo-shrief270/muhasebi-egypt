<template>
  <!-- Selling goes into the cashier's drawer: no open shift, no sale. -->
  <!-- Offline the shift can't be checked or opened: keep selling, the server sorts it out on sync. -->
  <div v-if="online && shiftChecked && !hasShift" class="grid min-h-[60dvh] place-items-center">
    <CashOpenShiftCard v-if="canShift" hint="لازم وردية مفتوحة عشان تبيع. اكتب الكاش اللي في الدرج وابدأ." @opened="() => refreshShift()" />
    <UAlert v-else color="warning" variant="subtle" icon="i-lucide-lock" title="مفيش وردية مفتوحة" description="البيع محتاج وردية، ومعندكش صلاحية فتح وردية. كلّم المدير." class="max-w-md" />
  </div>
  <div v-else class="-m-4 flex min-h-[calc(100dvh-var(--app-topbar-h))] flex-col gap-4 p-4 lg:-m-8 lg:flex-row lg:p-6">
    <!-- Items -->
    <section class="flex min-w-0 flex-1 flex-col gap-3">
      <UAlert
        v-if="!online"
        color="warning"
        variant="subtle"
        icon="i-lucide-wifi-off"
        title="النت فاصل — الكاشير شغال أوفلاين"
        :description="offlineHint"
      />
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
        <UButton
          v-if="queuedHere.length"
          size="xl"
          :color="queuedHere.some(e => e.status === 'failed') ? 'error' : 'warning'"
          variant="soft"
          icon="i-lucide-cloud-upload"
          :label="`مستنية (${queuedHere.length})`"
          @click="panelOpen = true"
        />
        <!-- Held invoices stay reachable even after the owner closes holding, so none is lost. -->
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
          <USelect v-if="canPriceLevel" v-model="cart.price_level" :items="priceLevels" size="sm" class="w-24" />
          <UDropdownMenu :items="cartMenu" :content="{ align: 'end' }">
            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" square aria-label="خيارات الفاتورة" />
          </UDropdownMenu>
        </div>
      </div>

      <!-- Offline there's no looking customers up: a name / phone for the receipt only. -->
      <div v-if="canCustomers && (online || cart.customer)" class="border-b border-(--ui-border) p-3">
        <PosCustomerPicker v-model="cart.customer" />
      </div>
      <div v-else-if="showCustomer || needsCustomer || (canCustomers && !online)" class="grid grid-cols-2 gap-2 border-b border-(--ui-border) p-3">
        <UInput v-model="cart.customer_name" size="sm" placeholder="اسم العميل" />
        <UInput v-model="cart.customer_phone" size="sm" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" />
      </div>

      <div v-if="cart.online_order" class="space-y-2 border-b border-(--ui-border) bg-(--ui-bg-elevated) p-3 text-sm">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-shopping-bag" class="size-4 text-(--ui-primary)" />
          <span class="flex-1 font-bold">طلب أونلاين <span class="num" dir="ltr">{{ cart.online_order.reference }}</span></span>
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-unlink" label="فك الربط" @click="cart.online_order = null" />
        </div>
        <UCheckbox
          v-if="cart.online_order.delivery_fee > 0"
          v-model="cart.online_order.fee_collected"
          :label="`استلمت مصاريف التوصيل (${formatMoney(cart.online_order.delivery_fee)}) كاش`"
          description="بتدخل الدرج لوحدها مع الفاتورة."
        />
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
            <UPopover v-if="canLineDiscount">
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
        <div v-if="!canInvoiceDiscount && cart.discount" class="flex justify-between text-sm text-(--ui-success)">
          <span>كود الخصم <span class="num" dir="ltr">{{ cart.online_order?.coupon_code }}</span></span>
          <span class="num">− {{ formatMoney(cart.discount) }}</span>
        </div>
        <div v-if="canInvoiceDiscount" class="flex items-center justify-between gap-2 text-sm">
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
        <p v-if="cart.online_order?.fee_collected && cart.online_order.delivery_fee" class="text-end text-sm text-(--ui-text-muted)">
          + التوصيل <span class="num">{{ formatMoney(cart.online_order.delivery_fee) }}</span> كاش = <span class="num font-bold text-(--ui-text)">{{ formatMoney(total + cart.online_order.delivery_fee) }}</span>
        </p>
        <div class="grid gap-2 pt-1" :class="canHold ? 'grid-cols-[auto_1fr]' : 'grid-cols-1'">
          <UButton v-if="canHold" size="xl" color="neutral" variant="outline" icon="i-lucide-pause" label="تعليق" :disabled="!cart.lines.length" @click="hold" />
          <UButton size="xl" icon="i-lucide-check" class="justify-center" :label="`دفع ${formatMoney(total)} (F9)`" :disabled="!canPay" @click="payOpen = true" />
        </div>
        <p v-if="missingSerials.length" class="text-sm text-(--ui-warning)">
          امسح IMEI / سيريال «{{ missingSerials[0]?.name }}» الأول.
        </p>
        <p v-else-if="cart.lines.length && missingCustomer" class="text-sm text-(--ui-warning)">
          اختار العميل أو اكتب اسمه الأول (المحل طالب اسم العميل على كل فاتورة).
        </p>
      </div>
    </aside>

    <PosPaymentModal v-model:open="payOpen" :total="total" :methods="payMethods" :credit-available="creditAvailable" :customer-name="cart.customer?.name ?? null" :loading="paying" :error="payError" @pay="checkout" />
    <PosReceiptModal ref="receiptRef" v-model:open="receiptOpen" :sale="lastSale" />

    <!-- The owner's «حماية من البيع بخسارة» in warn mode: the cashier confirms. -->
    <UModal v-model:open="belowCostOpen" title="بيع بأقل من التكلفة" :ui="{ content: 'sm:max-w-md' }">
      <template #body>
        <UAlert color="warning" variant="subtle" icon="i-lucide-triangle-alert" :title="belowCostMessage" description="المحل مفعّل تحذير البيع بخسارة. لو متأكد من السعر كمّل." />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="ارجع أعدّل" @click="belowCostOpen = false" />
          <UButton color="warning" icon="i-lucide-check" label="كمّل البيع" :loading="paying" @click="confirmBelowCost" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { CashShift, Category, Customer, OnlineOrderDetail, PosItem, Sale } from '~/types/api'

definePageMeta({ permission: 'sales.sell' })

// No pull-to-refresh / bounce while scrolling the cart (a reload mid-sale).
useHead({ htmlAttrs: { class: 'app-no-overscroll' } })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
// Discounts and price levels: the role's permission AND the owner's switch for each.
const canDiscount = computed(() => store.can('sales.discount'))
const canLineDiscount = computed(() => canDiscount.value && store.hasFeature('sales.line_discounts'))
const canInvoiceDiscount = computed(() => canDiscount.value && store.hasFeature('sales.discounts'))
const canPriceLevel = computed(() => canDiscount.value && store.hasFeature('sales.price_levels'))
const canHold = computed(() => store.hasFeature('sales.hold_carts'))
const needsCustomer = computed(() => store.hasFeature('sales.require_customer'))
const canCustomers = computed(() => store.can('customers.view'))
const canCredit = computed(() => store.can('customers.credit') && store.hasFeature('customers.credit_sales'))
const canShift = computed(() => store.can('cash.shift'))
const route = useRoute()

const branchId = computed(() => store.session?.current_branch_id)
const { cart, held, subtotal, total, count, missingSerials, unitPrice, lineTotal, add, setQty, remove, clear, hold, resume, dropHeld } = usePosCart(branchId)

// A cart kept on the device may carry a discount / price level the owner has since closed: drop it.
watchEffect(() => {
  if (!canPriceLevel.value && cart.value.price_level !== 'retail') {
    cart.value.price_level = 'retail'
  }
  // Without the discount: only an online order's coupon stays.
  const coupon = cart.value.online_order?.discount ?? 0
  if (!canInvoiceDiscount.value && cart.value.discount !== coupon) {
    cart.value.discount = coupon
  }
  if (!canLineDiscount.value) {
    cart.value.lines.forEach((l) => {
      if (l.discount) {
        l.discount = 0
      }
    })
  }
})
const missingCustomer = computed(() => needsCustomer.value && !cart.value.customer && !cart.value.customer_name?.trim())
const canPay = computed(() => cart.value.lines.length > 0 && !missingSerials.value.length && !missingCustomer.value)

// Offline: items come from the catalog kept on the device, sales go to the outbox.
const { online } = useConnectivity()
const outbox = useOutbox()
const { panelOpen } = outbox
const catalog = usePosCatalog()
await catalog.load()
const queuedHere = computed(() => outbox.forBranch(branchId.value))
const offlineHint = computed(() => catalog.items.value.length
  ? `الأصناف والأسعار من آخر تحديث (${formatDate(catalog.syncedAt.value, true)}). الفواتير بتتحفظ على الجهاز وبتتسجل لوحدها أول ما النت يرجع. البيع الآجل محتاج نت.`
  : 'مفيش نسخة من الأصناف على الجهاز ده — افتح الكاشير مرة والنت شغال عشان تتحفظ.')
const offlineKey = computed(() => `${store.session?.tenant.id}_${store.session?.user.id}_${branchId.value}`)

// Options
const [{ data: optionsData }, { data: categoriesData }] = await Promise.all([
  useAsyncData('pos-options', () => withOfflineCache('pos_options', () => api<{ data: { payment_methods: { value: string, label: string }[], price_levels: { value: string, label: string }[] } }>('/pos/options'))
    .catch(() => ({ data: { payment_methods: [...CASH_METHODS], price_levels: [{ value: 'retail', label: 'قطاعي' }] } }))),
  useAsyncData('catalog-categories', () => withOfflineCache(`pos_categories_${store.session?.tenant.id}`, () => api<{ data: Category[] }>('/catalog/categories'))
    .catch(() => ({ data: [] as Category[] }))),
])
const methods = computed(() => optionsData.value?.data.payment_methods ?? [])
// آجل only for a customer with an account, and only for whoever may give credit — and never offline.
const payMethods = computed(() => methods.value.filter(m => m.value !== 'credit' || (online.value && cart.value.customer && canCredit.value)))
const creditAvailable = computed(() => {
  const c = cart.value.customer
  return c && c.credit_limit !== null ? Math.max(0, c.credit_limit - c.balance) : null
})

// The cashier's shift in this branch.
const { data: shiftData, status: shiftStatus, refresh: refreshShift } = await useAsyncData('pos-shift', () => withOfflineCache(`pos_shift_${offlineKey.value}`, () => api<{ data: CashShift | null }>('/cash/current')), { watch: [branchId] })
const shiftChecked = computed(() => shiftStatus.value !== 'pending' || shiftData.value !== undefined)
const hasShift = computed(() => !!shiftData.value?.data)

// Added from the price check: /pos?add=<variant id>
if (typeof route.query.add === 'string') {
  const id = route.query.add
  const res = await api<{ data: PosItem[] }>('/pos/items', { query: { 'ids[]': [id] } }).catch(() => ({ data: [] as PosItem[] }))
  const item = res.data[0] ?? catalog.byId(id)
  if (item) {
    add(item)
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
// «حوّل لفاتورة» from an online order: /pos?order=… fills the cart with its items (at the order's
// prices) and customer. Whatever was in the cart is held first.
if (typeof route.query.order === 'string') {
  const orderId = route.query.order
  try {
    const order = (await api<{ data: OnlineOrderDetail }>(`/online-store/orders/${orderId}`)).data
    if (order.sale_id || ['delivered', 'cancelled'].includes(order.status)) {
      toast.add({ color: 'warning', title: order.sale_reference ? `الطلب ${order.reference} اتعمله فاتورة (${order.sale_reference}).` : `الطلب ${order.reference} ${order.status_label}.` })
    }
    else if (cart.value.online_order?.id !== order.id) {
      if (cart.value.lines.length) {
        hold()
      }
      const found = (await api<{ data: PosItem[] }>('/pos/items', { query: { 'ids[]': order.items.map(i => i.variant_id) } })).data
      for (const item of order.items) {
        const posItem = found.find(f => f.id === item.variant_id)
        if (posItem) {
          add({ ...posItem, price_retail: item.unit_price }, posItem.track_serial ? 1 : item.qty)
        }
      }
      cart.value.price_level = 'retail'
      // The order's coupon becomes the invoice discount (no discount permission needed for it).
      cart.value.discount = order.discount
      cart.value.online_order = { id: order.id, reference: order.reference, delivery_fee: order.delivery_fee, fee_collected: order.delivery_fee > 0, discount: order.discount, coupon_code: order.coupon_code }
      if (order.customer_id && canCustomers.value) {
        const c = (await api<{ data: Customer }>(`/customers/${order.customer_id}`).catch(() => null))?.data
        if (c) {
          cart.value.customer = { id: c.id, name: c.name, phone: c.phone, balance: c.balance, credit_limit: c.credit_limit }
        }
      }
      if (!cart.value.customer) {
        cart.value.customer_name = order.customer_name
        cart.value.customer_phone = localPhone(order.customer_phone)
      }
      if (found.length < order.items.length) {
        toast.add({ color: 'warning', title: 'فيه أصناف من الطلب مش موجودة دلوقتي؛ راجع السلة.' })
      }
    }
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  useRouter().replace({ query: { ...route.query, order: undefined } })
}
const priceLevels = computed(() => optionsData.value?.data.price_levels ?? [])
const ALL = 0
const categoryId = ref(ALL)
const chips = computed(() => {
  const fromApi = (categoriesData.value?.data ?? []).filter(c => (c.products_count ?? 0) > 0)
  return [{ id: ALL, name: 'الكل' }, ...(fromApi.length ? fromApi : catalog.categories.value)]
})

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

/** From the API, or from the catalog on the device when offline (or the API doesn't answer). */
async function fetchItems(q: string): Promise<{ data: PosItem[] }> {
  const offline = () => ({ data: catalog.search(q, categoryId.value || null) })
  if (!online.value) {
    return offline()
  }
  try {
    return await api<{ data: PosItem[] }>('/pos/items', { query: { q: q || undefined, category_id: categoryId.value || undefined }, timeout: 8000 })
  }
  catch (e) {
    if (isNetworkError(e)) {
      return offline()
    }
    throw e
  }
}
const { data: itemsData, status: itemsStatus, refresh: refreshItems } = await useAsyncData('pos-items', () => fetchItems(debounced.value), { watch: [debounced, categoryId, branchId, online] })

// Keep the device's copy of the catalog fresh while the POS is open; send what's queued.
async function refreshCatalog() {
  await catalog.load()
  if (online.value) {
    await catalog.refresh()
  }
}
onMounted(() => {
  refreshCatalog()
  outbox.sync()
})
watch(branchId, refreshCatalog)
const catalogTimer = setInterval(() => online.value && catalog.refresh(), CATALOG_REFRESH_MS)
const stopSynced = outbox.onSynced(() => online.value && refreshItems())
onBeforeUnmount(() => {
  clearInterval(catalogTimer)
  stopSynced()
})
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

/** A unit in stock here by its IMEI / serial: asked from the API, or from the device's catalog offline. */
async function findSerial(serial: string): Promise<{ item: PosItem, serial: string } | null> {
  if (online.value) {
    try {
      const found = (await api<{ data: { serial: string, variant: { id: string } | null }[] }>('/inventory/serials', { query: { q: serial, in_stock: 1 }, timeout: 8000 }))
        .data.find(s => s.serial === serial)
      if (!found?.variant) {
        return null
      }
      const res = await api<{ data: PosItem[] }>('/pos/items', { query: { 'ids[]': [found.variant.id] }, timeout: 8000 })
      return res.data[0] ? { item: res.data[0], serial: found.serial } : null
    }
    catch (e) {
      if (!isNetworkError(e)) {
        return null
      }
    }
  }
  return catalog.bySerial(serial)
}

async function addBySerial(q: string): Promise<boolean> {
  const found = await findSerial(normalizeSerial(q))
  if (!found) {
    return false
  }
  add(found.item, 1, found.serial)
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

type Payment = { method: string, amount: number }

function done(sale: Sale) {
  lastSale.value = sale
  clear()
  showCustomer.value = false
  payOpen.value = false
  receiptOpen.value = true
  refreshItems()
  // The owner's «اطبع الإيصال لوحده».
  if (store.hasFeature('sales.auto_print')) {
    nextTick(() => receiptRef.value?.print())
  }
}

// «حماية من البيع بخسارة» (warn): the API asks first; confirmed, the same cart is sent again.
const approval = useApproval()
const belowCostOpen = ref(false)
const belowCostMessage = ref('')
let belowCostPayments: Payment[] = []
function confirmBelowCost() {
  checkout(belowCostPayments, true)
}

async function checkout(payments: Payment[], confirmBelowCost = false, approvalId: string | null = null) {
  paying.value = true
  payError.value = null
  const body = {
    confirm_below_cost: confirmBelowCost || undefined,
    id: cart.value.id,
    price_level: cart.value.price_level,
    discount: cart.value.discount,
    customer_id: cart.value.customer?.id ?? null,
    customer_name: cart.value.customer ? null : cart.value.customer_name || null,
    customer_phone: cart.value.customer ? null : cart.value.customer_phone || null,
    items: cart.value.lines.map(l => ({ variant_id: l.variant_id, qty: l.qty, unit_price: unitPrice(l), discount: l.discount, serials: l.track_serial ? l.serials : undefined })),
    payments,
    online_order_id: cart.value.online_order?.id,
    delivery_fee_collected: cart.value.online_order ? cart.value.online_order.fee_collected : undefined,
  }
  try {
    if (!online.value) {
      await queueOffline(body, payments)
      return
    }
    // A slow answer counts as none: the sale is queued, and its id keeps it from being saved twice.
    const res = await api<{ data: Sale }>('/sales', { method: 'POST', body, timeout: 15_000, headers: approvalId ? { 'X-Approval-Id': approvalId } : undefined })
    belowCostOpen.value = false
    done(res.data)
  }
  catch (e) {
    if (isNetworkError(e)) {
      await queueOffline(body, payments)
      return
    }
    // Past the owner's limits: an OK from the owner's phone or a manager's PIN, then the same cart again.
    if (approvalNeeded(e)) {
      paying.value = false
      const id = await approval.ask(e)
      if (id) {
        return checkout(payments, confirmBelowCost, id)
      }
      payError.value = 'العملية دي محتاجة موافقة، وما اتوافقش عليها.'
      return
    }
    if (apiErrorCode(e) === 'below_cost_confirm') {
      belowCostMessage.value = apiErrorMessage(e)
      belowCostPayments = payments
      belowCostOpen.value = true
      return
    }
    belowCostOpen.value = false
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

/** What the server would refuse, checked on the device before a sale is queued offline. */
function offlineProblem(payments: Payment[]): string | null {
  const paid = payments.reduce((s, p) => s + p.amount, 0)
  const cash = payments.filter(p => p.method === 'cash').reduce((s, p) => s + p.amount, 0)
  if (cart.value.online_order) {
    return 'فاتورة الطلب الأونلاين محتاجة نت. استنى النت يرجع أو فك الربط.'
  }
  if (payments.some(p => !CASH_METHODS.some(m => m.value === p.method))) {
    return 'البيع الآجل محتاج نت. خده كاش أو فيزا أو محفظة.'
  }
  const serialLine = cart.value.lines.find(l => l.track_serial && (l.serials?.length ?? 0) !== l.qty)
  if (serialLine) {
    return `«${serialLine.name}» محتاج IMEI / سيريال لكل قطعة.`
  }
  if (missingCustomer.value) {
    return 'اكتب اسم العميل الأول.'
  }
  if (paid < total.value) {
    return 'المدفوع أقل من المطلوب.'
  }
  if (paid - total.value > cash) {
    return 'الفيزا والمحافظ مينفعش يزيدوا عن المطلوب؛ الباقي بيرجع من الكاش بس.'
  }
  return null
}

/** No internet: the sale is kept on the device (outbox) and sent as soon as the API answers. */
async function queueOffline(body: Record<string, unknown>, payments: Payment[]) {
  const session = store.session
  const problem = offlineProblem(payments)
  if (problem || !session || !branchId.value) {
    payError.value = problem ?? 'حصلت مشكلة، حاول تاني.'
    return
  }
  const soldAt = new Date().toISOString()
  const paid = payments.reduce((s, p) => s + p.amount, 0)
  const lines = cart.value.lines
  const receipt: Sale = {
    id: cart.value.id,
    number: 0,
    reference: `OFF-${cart.value.id.slice(-6).toUpperCase()}`,
    status: 'completed',
    status_label: '',
    price_level: cart.value.price_level,
    price_level_label: '',
    customer_id: cart.value.customer?.id ?? null,
    customer_name: cart.value.customer?.name ?? (cart.value.customer_name || null),
    customer_phone: cart.value.customer?.phone ?? (cart.value.customer_phone || null),
    subtotal: subtotal.value,
    discount: cart.value.discount,
    total: total.value,
    paid,
    credit: 0,
    change: paid - total.value,
    refunded: 0,
    notes: null,
    cashier_name: session.user.name,
    public_token: '',
    completed_at: soldAt,
    offline: true,
    items: lines.map((l, i) => ({
      id: i,
      variant_id: l.variant_id,
      name: l.name,
      barcode: l.barcode,
      qty: l.qty,
      unit_price: unitPrice(l),
      discount: l.discount,
      line_total: lineTotal(l),
      returned_qty: 0,
      serials: l.track_serial ? [...(l.serials ?? [])] : null,
    })),
    payments: payments.map(p => ({ method: p.method, method_label: cashMethodLabel(p.method), amount: p.amount, reference: null })),
  }
  try {
    await outbox.enqueue({
      id: cart.value.id,
      tenant_id: session.tenant.id,
      branch_id: branchId.value,
      user_id: session.user.id,
      created_at: soldAt,
      body: { ...body, offline: true, sold_at: soldAt },
      receipt,
      lines: lines.map(l => ({ variant_id: l.variant_id, qty: l.qty, serials: l.track_serial ? [...(l.serials ?? [])] : [] })),
    })
  }
  catch {
    payError.value = 'مقدرتش أحفظ الفاتورة على الجهاز (المتصفح مانع التخزين). الفاتورة لسه في السلة.'
    return
  }
  done(receipt)
}

// The cursor lives in the search box (for the scanner), so the function keys must work inside inputs.
defineShortcuts({
  f2: { usingInput: true, handler: () => focusSearch() },
  f9: {
    usingInput: true,
    handler: () => {
      if (canPay.value && !payOpen.value && !receiptOpen.value) {
        payOpen.value = true
      }
    },
  },
})
</script>

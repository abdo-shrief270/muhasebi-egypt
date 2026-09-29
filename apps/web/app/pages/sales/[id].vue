<template>
  <div v-if="sale" class="max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/sales" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold num">
          {{ sale.reference }}
          <UBadge v-if="sale.status !== 'completed'" color="warning" variant="subtle" class="ms-2 align-middle">
            {{ sale.status_label }}
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          {{ formatDate(sale.completed_at, true) }} · {{ sale.cashier_name }}
          <span v-if="sale.price_level !== 'retail'"> · سعر {{ sale.price_level_label }}</span>
          <span v-if="sale.customer_name"> · <NuxtLink v-if="sale.customer_id && store.can('customers.view')" :to="`/customers/${sale.customer_id}`" class="font-bold hover:text-primary">{{ sale.customer_name }}</NuxtLink><template v-else>{{ sale.customer_name }}</template></span>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة" @click="print" />
        <UButton color="neutral" variant="outline" icon="i-lucide-message-circle" label="WhatsApp" @click="share" />
        <UButton v-if="canRefund && returnable" color="neutral" variant="outline" icon="i-lucide-undo-2" label="مرتجع" @click="openReturn" />
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الصنف
              </th>
              <th class="p-3 text-start font-bold">
                الكمية
              </th>
              <th class="p-3 text-start font-bold">
                السعر
              </th>
              <th class="p-3 text-start font-bold">
                الإجمالي
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in sale.items" :key="item.id" class="border-t border-(--ui-border)">
              <td class="p-3">
                <p class="font-bold">
                  {{ item.name }}
                </p>
                <p v-if="item.serials?.length" class="num text-xs text-(--ui-text-muted)" dir="ltr">
                  IMEI {{ item.serials.join(' · ') }}
                </p>
                <p v-if="item.returned_qty" class="text-xs text-warning">
                  اترجّع <span class="num">{{ item.returned_qty }}</span>
                </p>
              </td>
              <td class="p-3 num">
                {{ item.qty }}
              </td>
              <td class="p-3 num">
                {{ formatMoney(item.unit_price) }}
                <span v-if="item.discount" class="block text-xs text-error">خصم {{ formatMoney(item.discount) }}</span>
              </td>
              <td class="p-3 font-bold num">
                {{ formatMoney(item.line_total) }}
              </td>
            </tr>
          </tbody>
        </table>
      </UCard>

      <div class="space-y-4">
        <dl class="app-card space-y-2 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
          <div class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              الإجمالي
            </dt><dd class="num">
              {{ formatMoney(sale.subtotal) }}
            </dd>
          </div>
          <div v-if="sale.discount" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              خصم
            </dt><dd class="text-error num">
              −{{ formatMoney(sale.discount) }}
            </dd>
          </div>
          <div class="flex justify-between text-lg font-extrabold">
            <dt>المطلوب</dt><dd class="num">
              {{ formatMoney(sale.total) }}
            </dd>
          </div>
          <div v-for="(p, i) in sale.payments" :key="i" class="flex justify-between text-sm">
            <dt class="text-(--ui-text-muted)">
              {{ p.method_label }}<span v-if="p.reference" class="num"> ({{ p.reference }})</span>
            </dt><dd class="num">
              {{ formatMoney(p.amount) }}
            </dd>
          </div>
          <div v-if="sale.change" class="flex justify-between text-sm">
            <dt class="text-(--ui-text-muted)">
              الباقي
            </dt><dd class="num">
              {{ formatMoney(sale.change) }}
            </dd>
          </div>
          <div v-if="sale.refunded" class="flex justify-between text-sm">
            <dt class="text-(--ui-text-muted)">
              مرتجع
            </dt><dd class="text-warning num">
              −{{ formatMoney(sale.refunded) }}
            </dd>
          </div>
          <div v-if="sale.profit !== undefined" class="flex justify-between border-t border-(--ui-border) pt-2 text-sm">
            <dt class="text-(--ui-text-muted)">
              المكسب
            </dt><dd class="font-bold num" :class="sale.profit < 0 ? 'text-error' : 'text-success'">
              {{ formatMoney(sale.profit) }}
            </dd>
          </div>
        </dl>

        <UCard v-if="sale.returns?.length">
          <p class="mb-2 font-bold">
            المرتجعات
          </p>
          <div v-for="r in sale.returns" :key="r.id" class="text-sm">
            <div class="flex justify-between">
              <span class="num">{{ r.reference }}</span>
              <span class="font-bold num">{{ formatMoney(r.total) }}</span>
            </div>
            <p class="text-xs text-(--ui-text-muted)">
              {{ formatDate(r.created_at, true) }} · {{ r.refund_method_label }}<span v-if="r.reason"> · {{ r.reason }}</span>
            </p>
          </div>
        </UCard>
      </div>
    </div>

    <UModal v-model:open="returnOpen" title="مرتجع من العميل" :description="`من ${sale.reference} — الفلوس بترجع بالسعر اللي اتدفع فعلاً.`">
      <template #body>
        <form id="sale-return-form" class="space-y-3" @submit.prevent="saveReturn">
          <div v-for="item in (sale.items ?? []).filter(i => i.qty > i.returned_qty)" :key="item.id" class="grid grid-cols-[1fr_80px_auto] items-center gap-3">
            <div class="min-w-0">
              <p class="truncate font-bold">
                {{ item.name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                متاح <span class="num">{{ item.qty - item.returned_qty }}</span>
              </p>
            </div>
            <UInput v-if="!item.serials" v-model="lines[item.id]!.qty" type="number" min="0" :max="item.qty - item.returned_qty" step="1" dir="ltr" :aria-label="`كمية مرتجع ${item.name}`" />
            <span v-else class="num text-center font-bold">{{ lines[item.id]!.serials.length }}</span>
            <USwitch v-model="lines[item.id]!.restock" size="sm" :label="lines[item.id]!.restock ? 'سليم' : 'تالف'" />
            <UCheckboxGroup
              v-if="item.serials"
              v-model="lines[item.id]!.serials"
              :items="item.serials.filter(s => !(item.returned_serials ?? []).includes(s)).map(s => ({ label: `IMEI ${s}`, value: s }))"
              class="col-span-3 num"
              dir="ltr"
            />
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="رد الفلوس">
              <USelect v-model="refundMethod" :items="methods" class="w-full" />
            </UFormField>
            <UFormField label="السبب">
              <UInput v-model="reason" class="w-full" />
            </UFormField>
          </div>
          <p class="text-xs text-(--ui-text-muted)">
            السليم بيرجع المخزون، والتالف لأ.
          </p>
          <UAlert v-if="returnError" color="error" variant="subtle" :title="returnError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="returnOpen = false" />
          <UButton type="submit" form="sale-return-form" icon="i-lucide-undo-2" label="تسجيل المرتجع" :loading="returning" />
        </div>
      </template>
    </UModal>

    <PrintSheet v-if="printing" page-size="80mm auto">
      <PrintReceipt :data="receipt" :qr-url="receiptUrl(sale.public_token)" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { Sale } from '~/types/api'

definePageMeta({ permission: 'sales.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const canRefund = computed(() => store.can('sales.refund'))
const { printing, print } = usePrint()

const [{ data, refresh }, { data: optionsData }] = await Promise.all([
  useAsyncData(`sale-${route.params.id}`, () => api<{ data: Sale }>(`/sales/${route.params.id}`)),
  useAsyncData('pos-options', () => api<{ data: { payment_methods: { value: string, label: string }[] } }>('/pos/options').catch(() => ({ data: { payment_methods: [] } }))),
])
const sale = computed(() => data.value?.data)
// Back onto the customer's account (خصم من الآجل) only for a sale made to a customer.
const methods = computed(() => (optionsData.value?.data.payment_methods ?? [])
  .filter(m => m.value !== 'credit' || !!sale.value?.customer_id)
  .map(m => ({ label: m.value === 'credit' ? 'يتخصم من حساب العميل' : m.label, value: m.value })))
const returnable = computed(() => sale.value?.items?.some(i => i.qty > i.returned_qty) ?? false)

const shop = computed(() => store.session ? { name: store.session.tenant.name, phone: store.session.tenant.phone } : null)
const receipt = computed(() => receiptFromSale(sale.value!, shop.value, store.currentBranch?.name ?? null))

function share() {
  if (!sale.value) {
    return
  }
  const text = receiptWhatsappText(sale.value, shop.value?.name ?? '')
  window.open(sale.value.customer_phone ? whatsappLink(sale.value.customer_phone, text) : `https://wa.me/?text=${encodeURIComponent(text)}`, '_blank')
}

const returnOpen = ref(false)
const lines = reactive<Record<number, { qty: string, restock: boolean, serials: string[] }>>({})
const refundMethod = ref('cash')
const reason = ref('')
const returning = ref(false)
const returnError = ref<string | null>(null)

function openReturn() {
  for (const item of sale.value?.items ?? []) {
    lines[item.id] = { qty: '', restock: true, serials: [] }
  }
  refundMethod.value = sale.value?.credit ? 'credit' : 'cash'
  reason.value = ''
  returnError.value = null
  returnOpen.value = true
}

async function saveReturn() {
  returning.value = true
  returnError.value = null
  try {
    await api(`/sales/${sale.value!.id}/returns`, {
      method: 'POST',
      body: {
        refund_method: refundMethod.value,
        reason: reason.value || null,
        items: Object.entries(lines)
          .map(([id, l]) => l.serials.length
            ? { sale_item_id: Number(id), qty: l.serials.length, restock: l.restock, serials: l.serials }
            : { sale_item_id: Number(id), qty: Number(l.qty), restock: l.restock })
          .filter(l => l.qty > 0),
      },
    })
    toast.add({ color: 'success', title: 'اتسجّل المرتجع' })
    returnOpen.value = false
    await refresh()
  }
  catch (e) {
    returnError.value = apiErrorMessage(e)
  }
  finally {
    returning.value = false
  }
}
</script>

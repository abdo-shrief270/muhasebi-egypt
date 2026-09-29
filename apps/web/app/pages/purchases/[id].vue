<template>
  <div v-if="purchase" class="space-y-6 max-w-5xl">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/purchases" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold num">
          {{ purchase.reference }}
        </h1>
        <p class="text-(--ui-text-muted)">
          <NuxtLink v-if="purchase.supplier" :to="`/suppliers/${purchase.supplier.id}`" class="font-bold hover:text-primary">{{ purchase.supplier.name }}</NuxtLink>
          · {{ formatDate(purchase.invoice_date) }}
          <span v-if="purchase.supplier_invoice_no"> · فاتورة المورد <span class="num">{{ purchase.supplier_invoice_no }}</span></span>
          <span v-if="purchase.created_by_name"> · سجّلها {{ purchase.created_by_name }}</span>
        </p>
      </div>
      <UButton v-if="canManage && returnable" color="neutral" variant="outline" icon="i-lucide-undo-2" label="مرتجع للمورد" @click="openReturn" />
    </div>

    <UAlert
      v-if="increases.length"
      color="warning"
      variant="subtle"
      icon="i-lucide-trending-up"
      :title="`${increases.length} صنف تكلفته زادت`"
      :description="increases.map(i => `${i.name}: ${formatMoney(i.previous_cost)} ← ${formatMoney(i.net_unit_cost)}`).join(' · ')"
      :actions="[{ label: 'راجع أسعار البيع', to: '/products' }]"
    />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
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
                سعر الشراء
              </th>
              <th class="p-3 text-start font-bold">
                الإجمالي
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in purchase.items" :key="item.id" class="border-t border-(--ui-border)">
              <td class="p-3">
                <p class="font-bold">
                  {{ item.name ?? '—' }}
                </p>
                <p v-if="item.serials?.length" class="num text-xs text-(--ui-text-muted)" dir="ltr">
                  IMEI {{ item.serials.join(' · ') }}
                </p>
                <p v-if="item.returned_qty" class="text-xs text-warning">
                  اترجّع منه <span class="num">{{ item.returned_qty }}</span>
                </p>
              </td>
              <td class="p-3 num">
                {{ item.qty }}
              </td>
              <td class="p-3 num">
                {{ formatMoney(item.unit_cost) }}
                <span v-if="item.net_unit_cost !== item.unit_cost" class="block text-xs text-(--ui-text-muted)">بعد الخصم {{ formatMoney(item.net_unit_cost) }}</span>
              </td>
              <td class="p-3 font-bold num">
                {{ formatMoney(item.line_total) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div class="grid gap-6 md:grid-cols-2">
      <UCard v-if="purchase.returns.length || purchase.notes">
        <p v-if="purchase.notes" class="mb-3 text-sm">
          {{ purchase.notes }}
        </p>
        <div v-if="purchase.returns.length" class="space-y-2">
          <p class="font-bold">
            المرتجعات
          </p>
          <div v-for="r in purchase.returns" :key="r.id" class="flex justify-between text-sm">
            <span><span class="num">{{ r.reference }}</span> · {{ formatDate(r.created_at, true) }}<span v-if="r.notes"> · {{ r.notes }}</span></span>
            <span class="font-bold num">{{ formatMoney(r.total) }}</span>
          </div>
        </div>
      </UCard>
      <dl class="space-y-2 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 app-card md:col-start-2">
        <div class="flex justify-between">
          <dt class="text-(--ui-text-muted)">
            الإجمالي
          </dt><dd class="num">
            {{ formatMoney(purchase.subtotal) }}
          </dd>
        </div>
        <div v-if="purchase.discount" class="flex justify-between">
          <dt class="text-(--ui-text-muted)">
            خصم
          </dt><dd class="text-error num">
            −{{ formatMoney(purchase.discount) }}
          </dd>
        </div>
        <div class="flex justify-between text-lg font-extrabold">
          <dt>الصافي</dt><dd class="num">
            {{ formatMoney(purchase.total) }}
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-(--ui-text-muted)">
            اتدفع
          </dt><dd class="num">
            {{ formatMoney(purchase.paid) }}<span v-if="purchase.payment_method_label" class="text-xs text-(--ui-text-muted)"> ({{ purchase.payment_method_label }})</span>
          </dd>
        </div>
        <div v-if="purchase.returned" class="flex justify-between">
          <dt class="text-(--ui-text-muted)">
            مرتجع
          </dt><dd class="text-warning num">
            −{{ formatMoney(purchase.returned) }}
          </dd>
        </div>
      </dl>
    </div>

    <UModal v-model:open="returnOpen" title="مرتجع للمورد" :description="`من ${purchase.reference} — البضاعة هتخرج من المخزون وقيمتها هتتخصم من حساب المورد.`">
      <template #body>
        <form id="return-form" class="space-y-3" @submit.prevent="saveReturn">
          <div v-for="item in purchase.items.filter(i => i.qty > i.returned_qty)" :key="item.id" class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-bold">
                {{ item.name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                متاح يرجع <span class="num">{{ item.qty - item.returned_qty }}</span> · <span class="num">{{ formatMoney(item.net_unit_cost) }}</span> للقطعة
              </p>
            </div>
            <UInput v-if="!item.serials" v-model="returnQty[item.id]" type="number" min="0" :max="item.qty - item.returned_qty" step="1" inputmode="numeric" dir="ltr" class="w-24" :aria-label="`كمية مرتجع ${item.name}`" />
            <UCheckboxGroup
              v-else
              :model-value="returnSerials[item.id] ?? []"
              :items="item.serials.map(s => ({ label: `IMEI ${s}`, value: s }))"
              class="num w-full"
              dir="ltr"
              @update:model-value="v => { returnSerials[item.id] = v as string[]; returnQty[item.id] = String((v as string[]).length) }"
            />
          </div>
          <UFormField label="السبب / ملاحظات">
            <UInput v-model="returnNotes" placeholder="مثلاً: شاشات فيها عيب" class="w-full" />
          </UFormField>
          <p class="text-sm">
            قيمة المرتجع: <span class="font-bold num">{{ formatMoney(returnTotal) }}</span>
          </p>
          <UAlert v-if="returnError" color="error" variant="subtle" :title="returnError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="returnOpen = false" />
          <UButton type="submit" form="return-form" icon="i-lucide-undo-2" label="تسجيل المرتجع" :loading="returning" :disabled="!returnTotal" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { Purchase } from '~/types/api'

definePageMeta({ permission: 'suppliers.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const canManage = computed(() => store.can('suppliers.manage'))

const { data, refresh } = await useAsyncData(`purchase-${route.params.id}`, () => api<{ data: Purchase }>(`/purchases/${route.params.id}`))
const purchase = computed(() => data.value?.data)
const increases = computed(() => purchase.value?.items.filter(i => i.cost_increased) ?? [])
const returnable = computed(() => purchase.value?.items.some(i => i.qty > i.returned_qty) ?? false)

const returnOpen = ref(false)
const returnQty = reactive<Record<number, string>>({})
const returnSerials = reactive<Record<number, string[]>>({})
const returnNotes = ref('')
const returning = ref(false)
const returnError = ref<string | null>(null)

const returnTotal = computed(() => (purchase.value?.items ?? []).reduce((sum, i) => sum + (Number(returnQty[i.id]) || 0) * i.net_unit_cost, 0))

function openReturn() {
  Object.keys(returnQty).forEach(k => delete returnQty[Number(k)])
  Object.keys(returnSerials).forEach(k => delete returnSerials[Number(k)])
  returnNotes.value = ''
  returnError.value = null
  returnOpen.value = true
}

async function saveReturn() {
  returning.value = true
  returnError.value = null
  try {
    await api(`/purchases/${purchase.value!.id}/returns`, {
      method: 'POST',
      body: {
        notes: returnNotes.value || null,
        items: Object.entries(returnQty).filter(([, qty]) => Number(qty) > 0).map(([id, qty]) => ({ purchase_item_id: Number(id), qty: Number(qty), serials: returnSerials[Number(id)] })),
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

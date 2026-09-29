<template>
  <div v-if="supplier" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/suppliers" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold">
          {{ supplier.name }}
          <UBadge v-if="!supplier.is_active" color="neutral" variant="subtle" class="ms-2 align-middle">
            موقوف
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          <span v-if="supplier.phone" class="num">{{ supplier.phone }}</span>
          <span v-if="supplier.phone && supplier.notes"> · </span>
          {{ supplier.notes }}
        </p>
      </div>
      <div v-if="canManage" class="flex flex-wrap gap-2">
        <UButton color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="editOpen = true" />
        <UButton color="neutral" variant="outline" icon="i-lucide-banknote" label="دفعة" @click="payOpen = true" />
        <UButton :to="`/purchases/new?supplier=${supplier.id}`" icon="i-lucide-receipt-text" label="فاتورة شراء" :disabled="!supplier.is_active" />
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          الرصيد
        </p>
        <p class="text-2xl font-extrabold">
          <SuppliersBalanceBadge :balance="supplier.balance" />
        </p>
      </div>
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          فواتير الشراء
        </p>
        <p class="text-2xl font-extrabold num">
          {{ supplier.purchases_count ?? 0 }}
        </p>
      </div>
    </div>

    <UTabs v-model="tab" :items="tabs" class="w-full" />

    <UCard v-if="tab === 'statement'" :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                التاريخ
              </th>
              <th class="p-3 text-start font-bold">
                البيان
              </th>
              <th class="p-3 text-start font-bold">
                المبلغ
              </th>
              <th class="p-3 text-start font-bold">
                الرصيد بعدها
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in statement" :key="t.id" class="border-t border-(--ui-border)">
              <td class="p-3 whitespace-nowrap text-(--ui-text-muted)">
                {{ formatDate(t.created_at, true) }}
              </td>
              <td class="p-3">
                <NuxtLink v-if="t.ref_type === 'purchase' && t.ref_id" :to="`/purchases/${t.ref_id}`" class="font-bold hover:text-primary">
                  {{ t.type_label }}
                </NuxtLink>
                <span v-else class="font-bold">{{ t.type_label }}</span>
                <span v-if="t.payment_method_label" class="text-(--ui-text-muted)"> · {{ t.payment_method_label }}</span>
                <p v-if="t.note" class="text-xs text-(--ui-text-muted)">
                  {{ t.note }}
                </p>
              </td>
              <td class="p-3 font-bold num" :class="t.amount > 0 ? 'text-warning' : 'text-success'">
                {{ t.amount > 0 ? '+' : '−' }}{{ formatMoney(Math.abs(t.amount)) }}
              </td>
              <td class="p-3">
                <SuppliersBalanceBadge :balance="t.balance_after" />
              </td>
            </tr>
            <tr v-if="!statement.length">
              <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                مفيش حركات على الحساب لسه.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <SuppliersPurchasesTable v-else :supplier-id="supplier.id" />

    <SuppliersSupplierFormModal v-model:open="editOpen" :supplier="supplier" @saved="reload" />
    <SuppliersPaymentModal v-model:open="payOpen" :supplier="supplier" :methods="methods" @saved="reload" />
  </div>
</template>

<script setup lang="ts">
import type { Supplier, SupplierTransaction } from '~/types/api'

definePageMeta({ permission: 'suppliers.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const canManage = computed(() => store.can('suppliers.manage'))
const id = computed(() => String(route.params.id))

const [{ data: supplierData, refresh: refreshSupplier }, { data: statementData, refresh: refreshStatement }, { data: methodsData }] = await Promise.all([
  useAsyncData(`supplier-${id.value}`, () => api<{ data: Supplier }>(`/suppliers/${id.value}`)),
  useAsyncData(`supplier-statement-${id.value}`, () => api<{ data: SupplierTransaction[] }>(`/suppliers/${id.value}/statement`)),
  useAsyncData('payment-methods', () => api<{ data: { value: string, label: string }[] }>('/suppliers/payment-methods')),
])
const supplier = computed(() => supplierData.value?.data)
const statement = computed(() => statementData.value?.data ?? [])
const methods = computed(() => (methodsData.value?.data ?? []).map(m => ({ label: m.label, value: m.value })))

const tab = ref('statement')
const tabs = [
  { label: 'كشف الحساب', value: 'statement', icon: 'i-lucide-scroll-text' },
  { label: 'فواتير الشراء', value: 'purchases', icon: 'i-lucide-receipt-text' },
]

const editOpen = ref(false)
const payOpen = ref(false)

async function reload() {
  await Promise.all([refreshSupplier(), refreshStatement()])
}
</script>

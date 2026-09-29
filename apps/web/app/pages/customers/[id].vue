<template>
  <div v-if="customer" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/customers" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold">
          {{ customer.name }}
          <UBadge v-if="!customer.is_active" color="neutral" variant="subtle" class="ms-2 align-middle">
            موقوف
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          <a v-if="customer.phone" :href="`tel:${customer.phone}`" class="num hover:text-primary" dir="ltr">{{ localPhone(customer.phone) }}</a>
          <span v-if="customer.phone && customer.notes"> · </span>
          {{ customer.notes }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton v-if="canManage" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="editOpen = true" />
        <UButton
          v-if="customer.phone && customer.balance > 0"
          @click="messages.sendDebtReminder(customer)"
          color="neutral"
          variant="outline"
          icon="i-lucide-message-circle"
          label="فكّره على واتساب"
        />
        <UButton v-if="canCredit" color="neutral" variant="outline" icon="i-lucide-banknote" label="تحصيل" @click="payOpen = true" />
        <UButton v-if="canSell" :to="`/pos?customer=${customer.id}`" icon="i-lucide-shopping-cart" label="بيع له" :disabled="!customer.is_active" />
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          الرصيد
        </p>
        <p class="text-2xl font-extrabold">
          <CustomersBalanceBadge :balance="customer.balance" />
        </p>
      </div>
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          حد الآجل
        </p>
        <p class="text-2xl font-extrabold num">
          {{ customer.credit_limit === null ? 'من غير حد' : formatMoney(customer.credit_limit) }}
        </p>
        <p v-if="customer.credit_limit !== null" class="text-xs text-(--ui-text-muted)">
          المتاح <span class="num">{{ formatMoney(Math.max(0, customer.credit_limit - customer.balance)) }}</span>
        </p>
      </div>
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          الفواتير
        </p>
        <p class="text-2xl font-extrabold num">
          {{ salesData?.meta.total ?? 0 }}
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
                <span class="font-bold">{{ t.type_label }}</span>{{ t.reference ? ' ' : '' }}
                <NuxtLink v-if="t.ref_type === 'sale' && t.ref_id && store.can('sales.view')" :to="`/sales/${t.ref_id}`" class="font-bold text-primary num">{{ t.reference }}</NuxtLink>
                <span v-else-if="t.reference" class="text-(--ui-text-muted) num">{{ t.reference }}</span>
                <span v-if="t.payment_method_label" class="text-(--ui-text-muted)"> · {{ t.payment_method_label }}</span>
                <p v-if="t.note || t.user_name" class="text-xs text-(--ui-text-muted)">
                  {{ [t.note, t.user_name].filter(Boolean).join(' · ') }}
                </p>
              </td>
              <td class="p-3 font-bold num" :class="t.amount > 0 ? 'text-warning' : 'text-success'">
                {{ t.amount > 0 ? '+' : '−' }}{{ formatMoney(Math.abs(t.amount)) }}
              </td>
              <td class="p-3">
                <CustomersBalanceBadge :balance="t.balance_after" />
              </td>
            </tr>
            <tr v-if="!statement.length">
              <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                مفيش آجل على الحساب ده لسه.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UCard v-else :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الفاتورة
              </th>
              <th class="p-3 text-start font-bold">
                التاريخ
              </th>
              <th class="p-3 text-start font-bold">
                الإجمالي
              </th>
              <th class="p-3 text-start font-bold">
                آجل
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sales" :key="s.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/sales/${s.id}`)">
              <td class="p-3 font-bold num">
                {{ s.reference }}
                <UBadge v-if="s.status !== 'completed'" color="warning" variant="subtle" size="sm" class="ms-1">
                  {{ s.status_label }}
                </UBadge>
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                {{ formatDate(s.completed_at, true) }}
              </td>
              <td class="p-3 num">
                {{ formatMoney(s.total) }}
              </td>
              <td class="p-3 num">
                {{ s.credit ? formatMoney(s.credit) : '—' }}
              </td>
            </tr>
            <tr v-if="!sales.length">
              <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                لسه مفيش فواتير للعميل ده.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <CustomersCustomerFormModal v-model:open="editOpen" :customer="customer" @saved="reload" />
    <CustomersPaymentModal v-model:open="payOpen" :customer="customer" @saved="reload" />
  </div>
</template>

<script setup lang="ts">
import type { Customer, CustomerTransaction, Paginated, Sale } from '~/types/api'

definePageMeta({ permission: 'customers.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const canManage = computed(() => store.can('customers.manage'))
const canCredit = computed(() => store.can('customers.credit'))
const canSell = computed(() => store.can('sales.sell'))
const messages = useMessages()
const id = computed(() => String(route.params.id))

const [{ data: customerData, refresh: refreshCustomer }, { data: statementData, refresh: refreshStatement }, { data: salesData }] = await Promise.all([
  useAsyncData(`customer-${id.value}`, () => api<{ data: Customer }>(`/customers/${id.value}`)),
  useAsyncData(`customer-statement-${id.value}`, () => api<{ data: CustomerTransaction[] }>(`/customers/${id.value}/statement`)),
  useAsyncData(`customer-sales-${id.value}`, () => store.can('sales.view')
    ? api<Paginated<Sale>>('/sales', { query: { customer_id: id.value } })
    : Promise.resolve(null)),
])
const customer = computed(() => customerData.value?.data)
const statement = computed(() => statementData.value?.data ?? [])
const sales = computed(() => salesData.value?.data ?? [])

const tab = ref('statement')
const tabs = computed(() => [
  { label: 'كشف الحساب', value: 'statement', icon: 'i-lucide-scroll-text' },
  ...(store.can('sales.view') ? [{ label: 'الفواتير', value: 'sales', icon: 'i-lucide-receipt' }] : []),
])

const editOpen = ref(false)
const payOpen = ref(false)

async function reload() {
  await Promise.all([refreshCustomer(), refreshStatement()])
}
</script>

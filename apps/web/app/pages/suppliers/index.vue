<template>
  <div class="space-y-6">
    <PageHeader title="الموردين" description="اللي عليك لكل مورد، وفواتيره ودفعاته.">
      <UButton v-if="canManage" to="/purchases/new" color="neutral" variant="outline" icon="i-lucide-receipt-text" label="فاتورة شراء" />
      <UButton v-if="canManage" icon="i-lucide-plus" label="مورد جديد" @click="formOpen = true" />
    </PageHeader>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر باسم المورد أو الموبايل…" class="w-full" />
      <div class="app-card flex items-center gap-3 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) px-4 py-2">
        <span class="text-sm text-(--ui-text-muted)">إجمالي اللي عليك للموردين</span>
        <span class="text-lg font-extrabold num">{{ formatMoney(data?.meta.total_owed ?? 0) }}</span>
      </div>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                المورد
              </th>
              <th class="hidden p-3 text-start font-bold sm:table-cell">
                الفواتير
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                آخر شراء
              </th>
              <th class="p-3 text-start font-bold">
                الرصيد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in suppliers"
              :key="s.id"
              class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)"
              :class="{ 'opacity-60': !s.is_active }"
              @click="navigateTo(`/suppliers/${s.id}`)"
            >
              <td class="p-3">
                <p class="font-bold">
                  {{ s.name }}
                </p>
                <p v-if="s.phone" class="text-xs text-(--ui-text-muted) num">
                  {{ s.phone }}
                </p>
              </td>
              <td class="hidden p-3 num sm:table-cell">
                {{ s.purchases_count ?? 0 }}
              </td>
              <td class="hidden p-3 text-(--ui-text-muted) md:table-cell">
                {{ s.last_purchase_at ? formatDate(s.last_purchase_at) : '—' }}
              </td>
              <td class="p-3">
                <SuppliersBalanceBadge :balance="s.balance" />
              </td>
            </tr>
            <tr v-if="!suppliers.length && status !== 'pending'">
              <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                {{ q ? 'مفيش مورد بالاسم ده.' : 'لسه مفيش موردين.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <SuppliersSupplierFormModal v-model:open="formOpen" @saved="s => navigateTo(`/suppliers/${s.id}`)" />
  </div>
</template>

<script setup lang="ts">
import type { Supplier } from '~/types/api'

definePageMeta({ permission: 'suppliers.view' })

const api = useApi()
const store = useSessionStore()
const canManage = computed(() => store.can('suppliers.manage'))

const q = ref('')
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const { data, status } = await useAsyncData('suppliers', () => api<{ data: Supplier[], meta: { total_owed: number } }>('/suppliers', {
  query: { q: debouncedQ.value || undefined },
}), { watch: [debouncedQ] })
const suppliers = computed(() => data.value?.data ?? [])

const formOpen = ref(false)
</script>

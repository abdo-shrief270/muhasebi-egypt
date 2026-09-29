<template>
  <div class="space-y-6">
    <PageHeader title="المبيعات" :description="`فواتير فرع «${store.currentBranch?.name ?? ''}».`">
      <UButton v-if="store.can('sales.sell')" to="/pos" icon="i-lucide-shopping-cart" label="الكاشير" />
    </PageHeader>

    <div class="grid gap-3 sm:grid-cols-[1fr_170px_170px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="رقم الفاتورة أو اسم / موبايل العميل…" class="w-full" />
      <UInput v-model="from" type="date" aria-label="من" />
      <UInput v-model="to" type="date" aria-label="إلى" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الفاتورة
              </th>
              <th class="p-3 text-start font-bold">
                الوقت
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                العميل
              </th>
              <th class="p-3 text-start font-bold">
                الإجمالي
              </th>
              <th v-if="canProfit" class="hidden p-3 text-start font-bold lg:table-cell">
                المكسب
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sales" :key="s.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/sales/${s.id}`)">
              <td class="p-3">
                <p class="font-bold num">
                  {{ s.reference }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num">{{ s.items_count }}</span> صنف · {{ s.cashier_name }}
                  <UBadge v-if="s.status !== 'completed'" size="sm" color="warning" variant="subtle" class="ms-1">
                    {{ s.status_label }}
                  </UBadge>
                </p>
              </td>
              <td class="p-3 whitespace-nowrap text-(--ui-text-muted)">
                {{ formatDate(s.completed_at, true) }}
              </td>
              <td class="hidden p-3 md:table-cell">
                {{ s.customer_name ?? '—' }}
              </td>
              <td class="p-3 font-bold num">
                {{ formatMoney(s.total - s.refunded) }}
              </td>
              <td v-if="canProfit" class="hidden p-3 num lg:table-cell" :class="(s.profit ?? 0) < 0 ? 'text-error' : 'text-success'">
                {{ formatMoney(s.profit) }}
              </td>
            </tr>
            <tr v-if="!sales.length && status !== 'pending'">
              <td colspan="5" class="p-10 text-center text-(--ui-text-muted)">
                مفيش فواتير.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="data?.meta.per_page ?? 30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Paginated, Sale } from '~/types/api'

definePageMeta({ permission: 'sales.view' })

const api = useApi()
const store = useSessionStore()
const canProfit = computed(() => store.can('reports.profit'))

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

const from = ref('')
const to = ref('')
const page = ref(1)
watch([debouncedQ, from, to], () => {
  page.value = 1
})
const branchKey = computed(() => store.session?.current_branch_id ?? '')

const { data, status } = await useAsyncData('sales', () => api<Paginated<Sale>>('/sales', {
  query: { q: debouncedQ.value || undefined, from: from.value || undefined, to: to.value || undefined, page: page.value },
}), { watch: [debouncedQ, from, to, page, branchKey] })
const sales = computed(() => data.value?.data ?? [])
</script>

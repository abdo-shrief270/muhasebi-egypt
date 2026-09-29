<template>
  <div class="space-y-8">
    <PageHeader title="التقارير" description="أرقام محلك: المبيعات والأرباح والمخزون والفلوس. كل تقرير يتصدّر Excel أو يتطبع PDF." />

    <section v-for="group in groups" :key="group.key" class="space-y-3">
      <h2 class="text-lg font-bold">
        {{ group.title }}
      </h2>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <NuxtLink v-for="r in group.reports" :key="r.key" :to="`/reports/${r.key}`" class="app-card flex items-start gap-3 p-4 transition hover:ring-1 hover:ring-primary">
          <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--app-primary-soft) text-(--app-primary-strong)">
            <UIcon :name="icons[r.key] ?? 'i-lucide-chart-column'" class="size-5" />
          </span>
          <span class="min-w-0">
            <span class="block font-bold">{{ r.title }}</span>
            <span class="block text-sm text-(--ui-text-muted)">{{ r.description }}</span>
          </span>
        </NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { ReportDefinition } from '~/types/api'

definePageMeta({ permission: 'reports.view' })

const api = useApi()
const { data } = await useAsyncData('reports', () => api<{ data: { reports: ReportDefinition[] } }>('/reports'))

const icons: Record<string, string> = {
  sales: 'i-lucide-trending-up',
  products: 'i-lucide-package',
  staff: 'i-lucide-users',
  payments: 'i-lucide-credit-card',
  repairs: 'i-lucide-wrench',
  inventory: 'i-lucide-warehouse',
  receivables: 'i-lucide-hand-coins',
  payables: 'i-lucide-truck',
  expenses: 'i-lucide-receipt',
  shifts: 'i-lucide-wallet',
}
const titles: Record<ReportDefinition['group'], string> = { sales: 'المبيعات', stock: 'المخزون', money: 'الفلوس' }
const groups = computed(() => (['sales', 'stock', 'money'] as const)
  .map(key => ({ key, title: titles[key], reports: (data.value?.data.reports ?? []).filter(r => r.group === key) }))
  .filter(g => g.reports.length))
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <PageHeader title="مرتبي" :description="mine?.period.label ?? ''" />

    <UCard v-if="mine?.current">
      <p class="mb-3 font-bold">
        الفترة دي لحد دلوقتي
      </p>
      <dl class="space-y-1 text-sm">
        <div class="flex justify-between">
          <dt>المرتب</dt><dd class="num">
            {{ formatMoney(mine.current.base_salary) }}
          </dd>
        </div>
        <div v-if="mine.current.tickets || mine.current.commission" class="flex justify-between">
          <dt>العمولة ({{ mine.current.tickets }} تذكرة)</dt><dd class="num">
            {{ formatMoney(mine.current.commission) }}
          </dd>
        </div>
        <div v-if="mine.current.clawback" class="flex justify-between text-error">
          <dt>خصم أجهزة رجعت في الضمان</dt><dd class="num">
            −{{ formatMoney(mine.current.clawback) }}
          </dd>
        </div>
        <div v-if="mine.current.bonuses" class="flex justify-between">
          <dt>مكافآت</dt><dd class="num">
            {{ formatMoney(mine.current.bonuses) }}
          </dd>
        </div>
        <div v-if="mine.current.deductions + mine.current.advances" class="flex justify-between text-error">
          <dt>خصومات وسلف</dt><dd class="num">
            −{{ formatMoney(mine.current.deductions + mine.current.advances) }}
          </dd>
        </div>
        <div class="flex justify-between border-t border-(--ui-border) pt-1 text-lg font-extrabold">
          <dt>الصافي</dt><dd class="num">
            {{ formatMoney(mine.current.net) }}
          </dd>
        </div>
      </dl>
    </UCard>
    <p v-else class="text-sm text-(--ui-text-muted)">
      لسه مفيش مرتب متحدد ليك.
    </p>

    <UCard v-if="mine?.payslips.length" :ui="{ body: 'p-0 sm:p-0' }">
      <table class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              الفترة
            </th>
            <th class="p-3 text-start font-bold">
              الصافي
            </th>
            <th class="p-3 text-start font-bold">
              اتصرف
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-(--ui-border)">
          <tr v-for="p in mine.payslips" :key="p.id ?? p.starts_on">
            <td class="num p-3">
              {{ formatDate(p.starts_on) }} – {{ formatDate(p.ends_on) }}
            </td>
            <td class="num p-3 font-bold">
              {{ formatMoney(p.net) }}
            </td>
            <td class="p-3">
              {{ p.paid_at ? formatDate(p.paid_at) : 'لسه' }}
            </td>
          </tr>
        </tbody>
      </table>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { PayrollAdjustment, PayrollPeriod, PayrollRow } from '~/types/api'

const api = useApi()
const { data } = await useAsyncData('payroll-mine', () => api<{ data: { period: PayrollPeriod, current: PayrollRow | null, payslips: PayrollRow[], adjustments: PayrollAdjustment[] } }>('/payroll/mine'))
const mine = computed(() => data.value?.data)
</script>

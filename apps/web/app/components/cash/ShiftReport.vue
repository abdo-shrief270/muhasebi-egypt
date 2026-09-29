<template>
  <div class="shift-report mx-auto bg-white text-black" :style="{ width }">
    <div class="text-center">
      <p class="text-lg font-extrabold">
        {{ shopName }}
      </p>
      <p class="text-sm font-bold">
        تقرير الوردية {{ shift.reference }}
      </p>
      <p v-if="branchName" class="text-xs">
        {{ branchName }}
      </p>
    </div>

    <div class="my-2 space-y-0.5 border-y border-dashed border-black py-1 text-xs">
      <div class="flex justify-between">
        <span>الكاشير</span><span>{{ shift.user_name }}</span>
      </div>
      <div class="flex justify-between">
        <span>من</span><span class="num">{{ formatDate(shift.opened_at, true) }}</span>
      </div>
      <div class="flex justify-between">
        <span>لـ</span><span class="num">{{ shift.closed_at ? formatDate(shift.closed_at, true) : 'لسه مفتوحة' }}</span>
      </div>
      <div v-if="shift.closed_by_name && shift.closed_by_name !== shift.user_name" class="flex justify-between">
        <span>قفلها</span><span>{{ shift.closed_by_name }}</span>
      </div>
    </div>

    <div class="space-y-0.5 text-xs">
      <div class="flex justify-between">
        <span>كاش أول الوردية</span><span class="num">{{ money(shift.opening_cash) }}</span>
      </div>
      <div v-for="row in shift.by_type ?? []" :key="`${row.type}-${row.method}`" class="flex justify-between">
        <span>{{ row.label }} · {{ cashMethodLabel(row.method) }} <span class="num">({{ row.count }})</span></span>
        <span class="num">{{ row.amount < 0 ? '−' : '' }}{{ money(Math.abs(row.amount)) }}</span>
      </div>
    </div>

    <table v-if="shift.expected" class="mt-2 w-full border-t border-dashed border-black pt-1 text-xs">
      <thead>
        <tr>
          <th class="py-0.5 text-start font-bold" />
          <th class="py-0.5 text-end font-bold">
            المفروض
          </th>
          <th v-if="shift.counted" class="py-0.5 text-end font-bold">
            الفعلي
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in rows" :key="m.value">
          <td class="py-0.5">
            {{ m.label }}
          </td>
          <td class="py-0.5 text-end num">
            {{ money(shift.expected[m.value]) }}
          </td>
          <td v-if="shift.counted" class="py-0.5 text-end num">
            {{ money(shift.counted[m.value]) }}
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="shift.cash_difference !== null" class="mt-2 border-t border-dashed border-black pt-1 text-center text-sm font-extrabold">
      <template v-if="shift.cash_difference === 0">
        الدرج مظبوط ✓
      </template>
      <template v-else>
        {{ shift.cash_difference < 0 ? 'عجز' : 'زيادة' }} في الكاش <span class="num">{{ money(Math.abs(shift.cash_difference)) }}</span> ج
      </template>
    </div>
    <p v-if="shift.note" class="mt-1 text-xs">
      ملاحظة: {{ shift.note }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { CashShift } from '~/types/api'

/** The end-of-shift (Z) report: 80mm thermal, also shown on screen. */
const props = withDefaults(defineProps<{ shift: CashShift, shopName: string, branchName?: string | null, width?: string }>(), { branchName: null, width: '72mm' })

const rows = computed(() => CASH_METHODS.filter(m => m.value === 'cash' || (props.shift.expected?.[m.value] ?? 0) !== 0 || (props.shift.counted?.[m.value] ?? 0) !== 0))

function money(piasters: number): string {
  return (piasters / 100).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.shift-report {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.5;
  padding: 2mm;
}
</style>

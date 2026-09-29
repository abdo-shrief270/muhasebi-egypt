<template>
  <div class="report-print bg-white text-black">
    <div class="mb-3 flex items-end justify-between border-b-2 border-black pb-2">
      <div>
        <p class="text-xl font-extrabold">
          {{ report.title }}
        </p>
        <p class="text-xs">
          {{ scope }}
        </p>
      </div>
      <div class="text-end text-xs">
        <p class="font-bold">
          {{ shopName }}
        </p>
        <p class="num">
          اتطبع {{ formatDate(new Date().toISOString(), true) }}
        </p>
      </div>
    </div>

    <div class="mb-3 grid grid-cols-4 gap-2">
      <div v-for="s in report.summary" :key="s.label" class="rounded border border-black/30 p-2">
        <p class="text-[10px]">
          {{ s.label }}
        </p>
        <p class="text-sm font-extrabold num">
          {{ formatReportValue(s.value, s.type) }}
        </p>
      </div>
    </div>

    <table class="w-full border-collapse text-[10px]">
      <thead>
        <tr>
          <th v-for="c in report.columns" :key="c.key" class="border border-black/40 bg-black/5 p-1" :class="numeric(c.type) ? 'text-end' : 'text-start'">
            {{ c.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in report.rows" :key="i" class="break-inside-avoid">
          <td v-for="c in report.columns" :key="c.key" class="border border-black/40 p-1" :class="numeric(c.type) ? 'text-end num' : ''">
            {{ formatReportValue(row[c.key], c.type) }}
          </td>
        </tr>
      </tbody>
      <tfoot v-if="report.totals">
        <tr class="font-extrabold">
          <td v-for="c in report.columns" :key="c.key" class="border border-black/40 bg-black/5 p-1" :class="numeric(c.type) ? 'text-end num' : ''">
            {{ report.totals[c.key] === undefined ? '' : formatReportValue(report.totals[c.key], c.type) }}
          </td>
        </tr>
      </tfoot>
    </table>
    <p v-for="n in report.notes" :key="n" class="mt-1 text-[10px]">
      * {{ n }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ReportData } from '~/types/api'

/** A report on A4 paper — also what "Save as PDF" produces. */
const props = defineProps<{ report: ReportData, shopName: string }>()

const numeric = (type: string) => ['int', 'money', 'percent'].includes(type)
const scope = computed(() => [
  props.report.from ? `من ${formatReportValue(props.report.from, 'date')} لـ ${formatReportValue(props.report.to, 'date')}` : 'دلوقتي',
  props.report.branches ? `الفروع: ${props.report.branches.join('، ')}` : null,
].filter(Boolean).join(' · '))
</script>

<style scoped>
.report-print {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.4;
}
</style>

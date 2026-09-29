<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
        <tr>
          <th v-for="c in columns" :key="c.key" class="p-0 font-bold" :class="numeric(c.type) ? 'text-end' : 'text-start'">
            <button
              v-if="sortable"
              type="button"
              class="flex w-full items-center gap-1 whitespace-nowrap p-3 hover:text-(--ui-text)"
              :class="numeric(c.type) ? 'justify-end' : 'justify-start'"
              @click="sortBy(c.key)"
            >
              {{ c.label }}
              <UIcon v-if="sort.key === c.key" :name="sort.desc ? 'i-lucide-arrow-down' : 'i-lucide-arrow-up'" class="size-3.5" />
            </button>
            <span v-else class="block whitespace-nowrap p-3">{{ c.label }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in sorted" :key="i" class="border-t border-(--ui-border)">
          <td
            v-for="(c, j) in columns"
            :key="c.key"
            class="p-3"
            :class="[numeric(c.type) ? 'text-end num tabular-nums' : '', j === 0 ? 'font-bold' : '', tone(row[c.key], c)]"
          >
            {{ formatReportValue(row[c.key], c.type) }}
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td :colspan="columns.length" class="p-10 text-center text-(--ui-text-muted)">
            مفيش بيانات في الفترة دي.
          </td>
        </tr>
      </tbody>
      <tfoot v-if="totals && rows.length" class="border-t-2 border-(--ui-border) bg-(--ui-bg-elevated) font-extrabold">
        <tr>
          <td v-for="c in columns" :key="c.key" class="p-3" :class="numeric(c.type) ? 'text-end num tabular-nums' : ''">
            {{ totals[c.key] === undefined ? '' : formatReportValue(totals[c.key], c.type) }}
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>

<script setup lang="ts">
import type { ReportData } from '~/types/api'

/** A report's table: typed cells, click a header to sort, totals at the bottom. */
const props = withDefaults(defineProps<{ columns: ReportData['columns'], rows: ReportData['rows'], totals?: ReportData['totals'], sortable?: boolean }>(), { totals: null, sortable: true })

const numeric = (type: string) => ['int', 'money', 'percent'].includes(type)
const sort = reactive<{ key: string | null, desc: boolean }>({ key: null, desc: true })

function sortBy(key: string) {
  sort.desc = sort.key === key ? !sort.desc : true
  sort.key = key
}

const sorted = computed(() => {
  if (!sort.key) {
    return props.rows
  }
  const key = sort.key
  const dir = sort.desc ? -1 : 1
  return [...props.rows].sort((a, b) => {
    const x = a[key]
    const y = b[key]
    if (x === y) {
      return 0
    }
    if (x === null || x === undefined) {
      return 1
    }
    if (y === null || y === undefined) {
      return -1
    }
    return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), 'ar')) * dir
  })
})

// Losses and shortages read as such; everything else stays in the text colour.
function tone(value: unknown, column: ReportData['columns'][number]) {
  return column.type === 'money' && typeof value === 'number' && value < 0 && ['profit', 'difference'].includes(column.key) ? 'text-error' : ''
}
</script>

<template>
  <div>
    <ul class="space-y-3">
      <li v-for="(p, i) in points" :key="i">
        <div class="mb-1 flex justify-between gap-3 text-sm">
          <span class="truncate font-bold">{{ p.label }}</span>
          <span class="shrink-0 text-(--ui-text-muted) num">{{ formatReportValue(p.value, type) }}</span>
        </div>
        <div class="h-2 rounded-full bg-(--ui-bg-elevated)">
          <div class="h-2 rounded-full bg-(--app-chart)" :style="{ width: `${width(p.value)}%` }" />
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { ReportColumnType } from '~/types/api'

/** One measure per label as thin bars in the chart accent; the value is printed beside each. */
const props = defineProps<{ points: { label: string, value: number }[], type: ReportColumnType }>()

const max = computed(() => Math.max(1, ...props.points.map(p => Math.abs(p.value))))
const width = (value: number) => Math.max(value > 0 ? 1 : 0, Math.round(Math.abs(value) / max.value * 100))
</script>

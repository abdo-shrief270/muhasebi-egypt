<template>
  <div>
    <div class="chart h-72 w-full" dir="ltr" role="img" :aria-label="label">
      <VChart :option="option" autoresize class="size-full" />
    </div>
    <details class="mt-2 text-sm">
      <summary class="cursor-pointer text-(--ui-text-muted)">
        عرض الأرقام كجدول
      </summary>
      <table class="mt-2 w-full">
        <tbody>
          <tr v-for="point in points" :key="point.date" class="border-t border-(--ui-border)">
            <td class="py-1.5">
              {{ dayLabel(point.date, true) }}
            </td>
            <td class="py-1.5 text-end num tabular-nums">
              {{ format(point.value) }}
            </td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>

<script setup lang="ts">
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { use } from 'echarts/core'
import { SVGRenderer } from 'echarts/renderers'
import type { EChartsOption } from 'echarts'
import VChart from 'vue-echarts'

use([BarChart, GridComponent, TooltipComponent, SVGRenderer])

/**
 * One measure per day as thin columns in the chart accent, with a hover tooltip and a table
 * fallback. Colours are read from the theme tokens and re-read when the theme changes.
 */
const props = defineProps<{
  points: { date: string, value: number }[]
  label: string
  format: (value: number) => string
  axisFormat: (value: number) => string
}>()

const colorMode = useColorMode()
const tokens = ref(readTokens())
watch(() => colorMode.value, () => nextTick(() => {
  tokens.value = readTokens()
}))

function readTokens() {
  const css = getComputedStyle(document.documentElement)
  const v = (name: string) => css.getPropertyValue(name).trim()
  return { mark: v('--app-chart'), grid: v('--ui-border'), muted: v('--ui-text-muted'), text: v('--ui-text'), surface: v('--ui-bg'), hover: v('--ui-bg-elevated') }
}

function dayLabel(date: string, long = false) {
  return new Date(`${date}T12:00:00`).toLocaleDateString('ar-EG-u-nu-latn', long ? { weekday: 'long', day: 'numeric', month: 'long' } : { day: 'numeric', month: 'short' })
}

const option = computed<EChartsOption>(() => {
  const t = tokens.value
  const font = { fontFamily: 'Cairo, sans-serif', color: t.muted, fontSize: 12 }
  return {
    animationDuration: 300,
    grid: { left: 8, right: 8, top: 16, bottom: 4, containLabel: true },
    xAxis: {
      type: 'category',
      data: props.points.map(p => dayLabel(p.date)),
      axisLine: { lineStyle: { color: t.grid } },
      axisTick: { show: false },
      axisLabel: { ...font, hideOverlap: true },
    },
    yAxis: {
      type: 'value',
      splitNumber: 4,
      axisLabel: { ...font, formatter: (v: number) => props.axisFormat(v) },
      splitLine: { lineStyle: { color: t.grid, width: 1, type: 'solid' } },
    },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow', z: 1, shadowStyle: { color: t.hover } },
      backgroundColor: t.surface,
      borderColor: t.grid,
      textStyle: { color: t.text, fontFamily: 'Cairo, sans-serif' },
      extraCssText: 'direction: rtl; border-radius: 8px; box-shadow: none;',
      formatter: (params) => {
        const p = (Array.isArray(params) ? params[0] : params)!
        const point = props.points[p.dataIndex]!
        return `<div style="font-size:12px;opacity:.75">${dayLabel(point.date, true)}</div><div style="font-weight:800">${props.format(point.value)}</div>`
      },
    },
    series: [{
      type: 'bar',
      name: props.label,
      data: props.points.map(p => p.value),
      barMaxWidth: 24,
      itemStyle: { color: t.mark, borderRadius: [4, 4, 0, 0] },
      emphasis: { disabled: true },
    }],
  }
})
</script>

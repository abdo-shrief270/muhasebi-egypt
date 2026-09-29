<template>
  <div v-if="definition" class="space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/reports" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader :title="definition.title" :description="definition.description" class="flex-1">
        <UButton color="neutral" variant="outline" icon="i-lucide-file-spreadsheet" label="Excel" :loading="exporting" :disabled="!report" @click="exportXlsx" />
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة / PDF" :disabled="!report" @click="print" />
      </PageHeader>
    </div>

    <!-- Filters, in one row -->
    <div class="app-card flex flex-wrap items-end gap-3 p-3">
      <template v-if="definition.uses_dates">
        <UFormField label="الفترة">
          <USelect v-model="preset" :items="presetItems" class="w-40" />
        </UFormField>
        <template v-if="preset === 'custom'">
          <UFormField label="من">
            <UInput v-model="from" type="date" dir="ltr" class="w-40" />
          </UFormField>
          <UFormField label="لـ">
            <UInput v-model="to" type="date" dir="ltr" class="w-40" />
          </UFormField>
        </template>
      </template>
      <UFormField v-if="definition.uses_branches && branches.length > 1" label="الفرع">
        <USelect v-model="branch" :items="branchItems" class="w-44" />
      </UFormField>
      <UFormField v-for="o in definition.options" :key="o.key" :label="o.label">
        <USelect v-model="options[o.key]" :items="o.choices" class="w-36" />
      </UFormField>
      <p v-if="report && definition.uses_dates" class="ms-auto self-center text-sm text-(--ui-text-muted)">
        {{ formatReportValue(report.from, 'date') }} — {{ formatReportValue(report.to, 'date') }}
      </p>
    </div>

    <UAlert v-if="error" color="error" variant="subtle" :title="error" />

    <template v-if="report">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" :class="{ 'opacity-60': status === 'pending' }">
        <div v-for="s in report.summary" :key="s.label" class="app-card p-4">
          <p class="text-sm text-(--ui-text-muted)">
            {{ s.label }}
          </p>
          <p class="mt-1 text-2xl font-extrabold num" :class="typeof s.value === 'number' && s.value < 0 ? 'text-error' : ''">
            {{ formatReportValue(s.value, s.type) }}
          </p>
          <p v-if="s.hint" class="mt-1 text-xs text-(--ui-text-muted)">
            {{ s.hint }}
          </p>
        </div>
      </div>

      <UCard v-if="report.chart && report.chart.points.length > 1">
        <p class="mb-3 font-bold">
          {{ report.chart.label }}
        </p>
        <DashboardDailyChart
          v-if="report.chart.kind === 'daily'"
          :points="report.chart.points.map(p => ({ date: p.date ?? p.label, value: p.value }))"
          :label="report.chart.label"
          :format="v => formatReportValue(v, 'money')"
          :axis-format="v => compact.format(v / 100)"
        />
        <ReportsBars v-else :points="report.chart.points" :type="report.chart.type" />
      </UCard>

      <UCard :ui="{ body: 'p-0 sm:p-0' }" :class="{ 'opacity-60': status === 'pending' }">
        <ReportsTable :columns="report.columns" :rows="report.rows" :totals="report.totals" />
      </UCard>

      <ul v-if="report.notes.length" class="space-y-1 text-xs text-(--ui-text-muted)">
        <li v-for="n in report.notes" :key="n">
          * {{ n }}
        </li>
      </ul>

      <PrintSheet v-if="printing" :page-size="report.columns.length > 6 ? 'A4 landscape' : 'A4'" margin="12mm">
        <ReportsPrintLayout :report="report" :shop-name="shopName" />
      </PrintSheet>
    </template>
  </div>
  <div v-else-if="catalogStatus !== 'pending'" class="py-24 text-center text-(--ui-text-muted)">
    التقرير ده مش موجود أو مش مسموح لك تشوفه.
  </div>
</template>

<script setup lang="ts">
import type { ReportData, ReportDefinition } from '~/types/api'

definePageMeta({ permission: 'reports.view' })

const api = useApi()
const route = useRoute()
const router = useRouter()
const store = useSessionStore()
const shopName = computed(() => store.session?.tenant.name ?? '')
const key = computed(() => String(route.params.key))
const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })

const { data: catalog, status: catalogStatus } = await useAsyncData('reports', () => api<{ data: { reports: ReportDefinition[], branches: { id: string, name: string }[] } }>('/reports'))
const definition = computed(() => catalog.value?.data.reports.find(r => r.key === key.value))
const branches = computed(() => catalog.value?.data.branches ?? [])
const branchItems = computed(() => [{ label: 'كل الفروع', value: 'all' }, ...branches.value.map(b => ({ label: b.name, value: b.id }))])

// Filters live in the URL, so a report can be bookmarked or shared as is.
const presets = periodPresets()
const presetItems = [...presets.map(p => ({ label: p.label, value: p.key })), { label: 'فترة مخصصة…', value: 'custom' }]
const q = route.query
const preset = ref(typeof q.period === 'string' ? q.period : 'month')
const from = ref(typeof q.from === 'string' ? q.from : presets.find(p => p.key === 'month')!.from)
const to = ref(typeof q.to === 'string' ? q.to : presets.find(p => p.key === 'month')!.to)
const branch = ref(typeof q.branch === 'string' ? q.branch : 'all')
const options = reactive<Record<string, string>>({})
for (const o of definition.value?.options ?? []) {
  const fromUrl = q[`o_${o.key}`]
  options[o.key] = typeof fromUrl === 'string' && o.choices.some(c => c.value === fromUrl) ? fromUrl : o.choices[0]!.value
}

watch(preset, (value) => {
  const p = presets.find(x => x.key === value)
  if (p) {
    from.value = p.from
    to.value = p.to
  }
}, { immediate: true })

const params = computed(() => ({
  ...(definition.value?.uses_dates ? { from: from.value, to: to.value } : {}),
  ...(definition.value?.uses_branches ? { branch: branch.value } : {}),
  ...Object.fromEntries(Object.entries(options).map(([k, v]) => [`options[${k}]`, v])),
}))

watch([preset, from, to, branch, options], () => {
  router.replace({ query: {
    period: preset.value,
    ...(preset.value === 'custom' ? { from: from.value, to: to.value } : {}),
    ...(branch.value !== 'all' ? { branch: branch.value } : {}),
    ...Object.fromEntries(Object.entries(options).map(([k, v]) => [`o_${k}`, v])),
  } })
}, { deep: true })

const error = ref<string | null>(null)
const { data: reportData, status } = await useAsyncData(`report-${key.value}`, async () => {
  if (!definition.value || (definition.value.uses_dates && (!from.value || !to.value))) {
    return null
  }
  error.value = null
  try {
    return (await api<{ data: ReportData }>(`/reports/${key.value}`, { query: params.value })).data
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    return null
  }
}, { watch: [params] })
const report = computed(() => reportData.value ?? null)

const exporting = ref(false)
async function exportXlsx() {
  exporting.value = true
  try {
    const blob = await api<Blob>(`/reports/${key.value}/export`, { query: params.value, responseType: 'blob' })
    const period = definition.value?.uses_dates ? `${from.value}_${to.value}` : isoDay(new Date())
    saveBlob(blob, `${definition.value?.title ?? key.value} ${period}.xlsx`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    exporting.value = false
  }
}

const { printing, print } = usePrint()
</script>

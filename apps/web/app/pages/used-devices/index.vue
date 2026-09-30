<template>
  <div class="space-y-6">
    <PageHeader title="المستعمل" description="الأجهزة المستعملة اللي اشتريتها: اللي في المخزن، واللي اتباع ومكسبه.">
      <UButton v-if="store.can('used_devices.view_seller')" to="/used-devices/sellers" color="neutral" variant="outline" icon="i-lucide-user-search" label="دوّر على بايع" />
      <UButton to="/used-devices/new" icon="i-lucide-plus" label="شراء جهاز" />
    </PageHeader>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <UCard v-for="s in stats" :key="s.label" :ui="{ body: 'p-3 sm:p-4' }">
        <p class="text-xs text-(--ui-text-muted)">
          {{ s.label }}
        </p>
        <p class="mt-1 text-xl font-extrabold num">
          {{ s.value }}
        </p>
      </UCard>
    </div>

    <div class="flex flex-wrap gap-2">
      <UButton
        v-for="t in tabs"
        :key="t.key"
        size="sm"
        :color="tab === t.key ? 'primary' : 'neutral'"
        :variant="tab === t.key ? 'soft' : 'outline'"
        :icon="t.icon"
        :aria-pressed="tab === t.key"
        :label="t.label"
        @click="tab = t.key"
      />
    </div>

    <div class="grid grid-cols-2 gap-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_8rem_11rem_11rem]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="الموديل، IMEI، أو رقم UD…" class="col-span-2 w-full lg:col-span-1" />
      <DeviceModelPicker v-model="modelId" endpoint="/used-devices/device-models" placeholder="كل الموديلات" />
      <USelect v-model="grade" :items="gradeItems" class="w-full" aria-label="الفئة" />
      <UInput v-model="from" type="date" class="w-full" aria-label="اتشرى من" :ui="{ leading: 'text-xs text-(--ui-text-muted)' }">
        <template #leading>
          من
        </template>
      </UInput>
      <UInput v-model="to" type="date" class="w-full" aria-label="اتشرى لحد" :ui="{ leading: 'text-xs text-(--ui-text-muted)' }">
        <template #leading>
          لحد
        </template>
      </UInput>
    </div>
    <div v-if="filtered" class="-mt-4">
      <UButton size="xs" color="neutral" variant="link" icon="i-lucide-x" label="شيل الفلاتر" @click="clearFilters" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                الجهاز
              </th>
              <th class="p-3 text-start font-bold">
                الفئة
              </th>
              <th class="p-3 text-end font-bold">
                {{ tab === 'in_stock' ? 'السعر' : 'اتباع بـ' }}
              </th>
              <th v-if="canCost" class="hidden p-3 text-end font-bold md:table-cell">
                {{ tab === 'in_stock' ? 'اتشرى بـ' : 'المكسب' }}
              </th>
              <th class="hidden p-3 text-start font-bold sm:table-cell">
                الحالة
              </th>
              <th class="hidden p-3 text-start font-bold lg:table-cell">
                {{ tab === 'in_stock' ? 'في المخزن من' : 'اتباع' }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in devices" :key="d.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/used-devices/${d.id}`)">
              <td class="p-3">
                <NuxtLink :to="`/used-devices/${d.id}`" class="font-bold hover:underline" @click.stop>
                  {{ d.title }}
                </NuxtLink>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num">{{ d.reference }}</span> · <span class="num" dir="ltr">{{ d.imei }}</span>
                </p>
              </td>
              <td class="p-3">
                <UBadge :color="gradeColor(d.grade)" variant="subtle">
                  {{ d.grade }}
                </UBadge>
                <p v-if="d.battery_health" class="mt-0.5 text-xs text-(--ui-text-muted)">
                  <UIcon name="i-lucide-battery-medium" class="size-3.5 align-middle" /> <span class="num">{{ d.battery_health }}%</span>
                </p>
              </td>
              <td class="p-3 text-end font-bold num">
                {{ formatMoney(tab === 'in_stock' ? d.asking_price : (d.sale?.price ?? d.asking_price)) }}
              </td>
              <td v-if="canCost" class="hidden p-3 text-end num md:table-cell">
                <template v-if="tab === 'in_stock'">
                  {{ formatMoney(d.purchase_price) }}
                </template>
                <span v-else :class="(d.profit ?? 0) >= 0 ? 'text-(--ui-success)' : 'text-(--ui-error)'">{{ formatMoney(d.profit) }}</span>
              </td>
              <td class="hidden p-3 sm:table-cell">
                <UBadge :color="usedStatusColor(d.status)" variant="subtle">
                  {{ d.status_label }}
                </UBadge>
              </td>
              <td class="hidden p-3 lg:table-cell">
                <template v-if="d.status === 'in_stock'">
                  <span :class="d.days_in_stock > 30 ? 'font-bold text-(--ui-warning)' : ''"><span class="num">{{ d.days_in_stock }}</span> يوم</span>
                </template>
                <template v-else>
                  <span class="num">{{ formatDate(d.sold_at) }}</span>
                  <span class="text-xs text-(--ui-text-muted)"> · بعد <span class="num">{{ d.days_in_stock }}</span> يوم</span>
                </template>
              </td>
            </tr>
            <tr v-if="!devices.length && status !== 'pending'">
              <td colspan="6" class="p-10 text-center text-(--ui-text-muted)">
                {{ filtered ? 'مفيش أجهزة بالفلاتر دي.' : tab === 'in_stock' ? 'مفيش أجهزة مستعملة في المخزن دلوقتي.' : 'لسه مابعتش أجهزة مستعملة.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { UsedDevice, UsedDeviceSummary } from '~/types/api'

definePageMeta({ module: 'used_devices', permission: 'used_devices.manage' })

const api = useApi()
const store = useSessionStore()
const branchKey = computed(() => store.session?.current_branch_id ?? '')
const canCost = computed(() => store.can('products.view_cost'))

type Tab = 'in_stock' | 'sold' | 'all'
const tabs = [
  { key: 'in_stock' as const, label: 'في المخزن', icon: 'i-lucide-package' },
  { key: 'sold' as const, label: 'اتباع', icon: 'i-lucide-circle-check' },
  { key: 'all' as const, label: 'الكل', icon: 'i-lucide-list' },
]
const tab = ref<Tab>('in_stock')
const q = ref('')
const debouncedQ = ref('')
const modelId = ref<number | undefined>()
const grade = ref<'all' | 'A' | 'B' | 'C'>('all')
const gradeItems = [
  { label: 'كل الفئات', value: 'all' },
  { label: 'فئة A', value: 'A' },
  { label: 'فئة B', value: 'B' },
  { label: 'فئة C', value: 'C' },
]
const from = ref('')
const to = ref('')
const page = ref(1)

let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const filtered = computed(() => !!(debouncedQ.value || modelId.value || grade.value !== 'all' || from.value || to.value))
function clearFilters() {
  q.value = ''
  debouncedQ.value = ''
  modelId.value = undefined
  grade.value = 'all'
  from.value = ''
  to.value = ''
}

const query = computed(() => ({
  status: tab.value,
  q: debouncedQ.value || undefined,
  device_model_id: modelId.value,
  grade: grade.value === 'all' ? undefined : grade.value,
  from: from.value || undefined,
  to: to.value || undefined,
  page: page.value,
}))
watch(() => [tab.value, debouncedQ.value, modelId.value, grade.value, from.value, to.value], () => {
  page.value = 1
})

const { data, status } = await useAsyncData('used-devices', () => api<{ data: UsedDevice[], meta: { current_page: number, last_page: number, total: number }, summary: UsedDeviceSummary }>('/used-devices', { query: query.value }), { watch: [query, branchKey] })
const devices = computed(() => data.value?.data ?? [])

const stats = computed(() => {
  const s = data.value?.summary
  return [
    { label: 'في المخزن', value: s ? String(s.in_stock) : '—' },
    canCost.value
      ? { label: 'تكلفتهم', value: formatMoney(s?.stock_cost) }
      : { label: 'بسعر البيع', value: formatMoney(s?.stock_asking) },
    { label: 'اشتريت الشهر ده', value: s ? String(s.bought_this_month) : '—' },
    { label: 'بعت الشهر ده', value: s ? String(s.sold_this_month) : '—' },
  ]
})
</script>

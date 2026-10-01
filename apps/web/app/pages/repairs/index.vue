<template>
  <div class="space-y-6">
    <PageHeader title="الصيانة" description="الأجهزة اللي عندك: مين مستني إيه، وإيه اللي جاهز يتسلّم.">
      <UButton v-if="store.can('repairs.settings')" to="/repairs/settings" color="neutral" variant="outline" icon="i-lucide-list-checks" label="قوايم الأعطال" />
      <UButton v-if="store.can('repairs.create')" to="/repairs/new" icon="i-lucide-plus" label="استلام جهاز" />
    </PageHeader>

    <div class="flex flex-wrap gap-2">
      <UButton
        v-for="t in tabs"
        :key="t.key"
        size="sm"
        :color="tab === t.key ? (t.tone ?? 'primary') : 'neutral'"
        :variant="tab === t.key ? 'soft' : 'outline'"
        :icon="t.icon"
        :aria-pressed="tab === t.key"
        @click="tab = t.key"
      >
        {{ t.label }}
        <span v-if="t.count !== undefined" class="num">({{ t.count }})</span>
      </UButton>
    </div>

    <UInput v-model="q" icon="i-lucide-search" placeholder="رقم التذكرة، اسم العميل، الموبايل، IMEI، أو الجهاز…" class="w-full" />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                التذكرة
              </th>
              <th class="p-3 text-start font-bold">
                الجهاز والعطل
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                العميل
              </th>
              <th class="p-3 text-start font-bold">
                الحالة
              </th>
              <th class="hidden p-3 text-start font-bold lg:table-cell">
                {{ tab === 'delivered' ? 'اتسلّم' : 'الميعاد' }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in tickets" :key="t.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/repairs/${t.id}`)">
              <td class="p-3">
                <p class="font-bold num">
                  {{ t.reference }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  {{ formatDate(t.received_at) }}
                </p>
              </td>
              <td class="p-3">
                <p class="font-bold">
                  {{ t.device_name }}
                </p>
                <p class="line-clamp-1 text-xs text-(--ui-text-muted)">
                  {{ [...(t.diagnosed_faults ?? t.reported_faults).map(f => f.name), t.reported_note].filter(Boolean).join('، ') }}
                </p>
              </td>
              <td class="hidden p-3 md:table-cell">
                <p>{{ t.customer_name }}</p>
                <p class="text-xs text-(--ui-text-muted) num" dir="ltr">
                  {{ localPhone(t.customer_phone) }}
                </p>
              </td>
              <td class="p-3">
                <UBadge :color="ticketStatusColor(t.status)" variant="subtle">
                  {{ t.status_label }}
                </UBadge>
                <UBadge v-if="t.outsourced?.active" color="info" variant="outline" size="sm" icon="i-lucide-send" class="ms-1">
                  عند {{ t.outsourced.shop }}
                </UBadge>
                <UBadge v-if="t.partner" color="neutral" variant="outline" size="sm" icon="i-lucide-handshake" class="ms-1">
                  من محل شريك
                </UBadge>
                <UBadge v-if="t.status === 'ready' && !t.ready_notified_at && store.hasFeature('repairs.status_whatsapp')" color="warning" variant="outline" size="sm" icon="i-lucide-message-circle" class="ms-1">
                  العميل ما اتبلغش
                </UBadge>
                <p v-if="t.technician_name" class="mt-1 text-xs text-(--ui-text-muted)">
                  {{ t.technician_name }}
                </p>
              </td>
              <td class="hidden p-3 lg:table-cell">
                <template v-if="tab === 'delivered'">
                  {{ t.delivered_at ? formatDate(t.delivered_at, true) : '—' }}
                </template>
                <span v-else-if="t.expected_at" :class="t.is_overdue ? 'font-bold text-error' : ''">
                  {{ formatDate(t.expected_at, true) }}
                  <UIcon v-if="t.is_overdue" name="i-lucide-alarm-clock" class="size-4 align-middle" />
                </span>
                <span v-else class="text-(--ui-text-muted)">—</span>
              </td>
            </tr>
            <tr v-if="!tickets.length && status !== 'pending'">
              <td colspan="5" class="p-10 text-center text-(--ui-text-muted)">
                {{ q ? 'مفيش تذكرة كده.' : emptyText }}
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
import type { RepairSummary, RepairTicket } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const branchKey = computed(() => store.session?.current_branch_id ?? '')

type Tab = 'open' | 'ready' | 'overdue' | 'abandoned' | 'mine' | 'delivered'
const tab = ref<Tab>((['open', 'ready', 'overdue', 'abandoned', 'mine', 'delivered'] as const).find(t => t === route.query.tab) ?? 'open')
const q = ref('')
const debouncedQ = ref('')
const page = ref(1)
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))
watch([tab, debouncedQ], () => {
  page.value = 1
})

const { data: summaryData } = await useAsyncData('repair-summary', () => api<{ data: RepairSummary }>('/repairs/summary'), { watch: [branchKey] })
const summary = computed(() => summaryData.value?.data)
const tabs = computed(() => [
  { key: 'open' as const, label: 'اللي عندي', icon: 'i-lucide-wrench', count: summary.value?.open },
  { key: 'ready' as const, label: 'جاهز للتسليم', icon: 'i-lucide-check-circle', count: summary.value?.ready, tone: 'success' as const },
  { key: 'overdue' as const, label: 'متأخر عن ميعاده', icon: 'i-lucide-alarm-clock', count: summary.value?.overdue, tone: 'error' as const },
  { key: 'abandoned' as const, label: 'متروك', icon: 'i-lucide-archive', count: summary.value?.abandoned, tone: 'warning' as const },
  ...(store.can('repairs.update_status') ? [{ key: 'mine' as const, label: 'بتاعتي', icon: 'i-lucide-user', count: summary.value?.mine }] : []),
  { key: 'delivered' as const, label: 'اتسلّم', icon: 'i-lucide-archive-restore', count: undefined },
])
const emptyText = computed(() => ({
  open: 'مفيش أجهزة عندك دلوقتي.',
  ready: 'مفيش أجهزة جاهزة.',
  overdue: 'مفيش حاجة متأخرة 👍',
  abandoned: 'مفيش أجهزة متروكة.',
  mine: 'مفيش أجهزة متسندة ليك.',
  delivered: 'لسه مسلّمتش أجهزة.',
})[tab.value])

const query = computed(() => ({
  q: debouncedQ.value || undefined,
  page: page.value,
  status: tab.value === 'ready' ? 'ready' : tab.value === 'delivered' ? 'delivered' : tab.value === 'abandoned' ? 'all' : 'open',
  overdue: tab.value === 'overdue' ? 1 : undefined,
  abandoned: tab.value === 'abandoned' ? 1 : undefined,
  mine: tab.value === 'mine' ? 1 : undefined,
}))
const { data, status } = await useAsyncData('repair-tickets', () => api<{ data: RepairTicket[], meta: { current_page: number, last_page: number, total: number } }>('/repairs/tickets', { query: query.value }), { watch: [query, branchKey] })
const tickets = computed(() => data.value?.data ?? [])
</script>

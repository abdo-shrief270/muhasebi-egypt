<template>
  <div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold">
          النهارده
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          {{ todayLabel }}
          <span v-if="today"> · اتحدّث {{ timeAgo(today.as_of, now) }}</span>
        </p>
      </div>
      <USelect v-if="branchItems.length > 2" v-model="branch" :items="branchItems" class="w-44" aria-label="الفرع" />
    </div>

    <template v-if="today">
      <!-- Sales so far against the same time yesterday. -->
      <div class="grid gap-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="app-card p-5">
          <p class="text-sm text-(--ui-text-muted)">
            صافي المبيعات لحد دلوقتي
          </p>
          <p class="mt-1 text-4xl font-extrabold num">
            {{ formatMoney(today.sales.net) }}
          </p>
          <p class="mt-1 text-sm" :class="delta.class">
            <UIcon :name="delta.icon" class="size-4 align-middle" />
            {{ delta.text }}
          </p>
          <div class="mt-4 flex h-20 items-end gap-0.5" role="img" :aria-label="`المبيعات بالساعة: ${peakLabel}`">
            <div v-for="h in hours" :key="h.hour" class="relative flex-1" :title="`${h.hour}:00 — ${formatMoney(h.today ?? 0)} (إمبارح ${formatMoney(h.yesterday)})`">
              <div class="absolute inset-x-0 bottom-0 rounded-t-sm bg-(--ui-border)" :style="{ height: `${pct(h.yesterday)}%` }" />
              <div v-if="h.today !== null" class="absolute inset-x-[15%] bottom-0 rounded-t-sm bg-primary" :style="{ height: `${pct(h.today)}%` }" />
            </div>
          </div>
          <div class="mt-1 flex justify-between text-[10px] text-(--ui-text-muted) num" dir="ltr">
            <span>0</span><span>6</span><span>12</span><span>18</span><span>23</span>
          </div>
          <p class="mt-1 text-xs text-(--ui-text-muted)">
            <span class="inline-block size-2 rounded-sm bg-primary" /> النهارده
            <span class="ms-3 inline-block size-2 rounded-sm bg-(--ui-border)" /> إمبارح
          </p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div v-for="s in salesStats" :key="s.label" class="app-card p-4">
            <p class="text-xs text-(--ui-text-muted)">
              {{ s.label }}
            </p>
            <p class="mt-1 text-xl font-extrabold num" :class="s.class">
              {{ s.value }}
            </p>
          </div>
        </div>
      </div>

      <!-- What's waiting for attention. -->
      <div v-if="attention.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <NuxtLink v-for="a in attention" :key="a.label" :to="a.to" class="app-card p-4 transition hover:ring-1 hover:ring-primary">
          <UIcon :name="a.icon" class="size-5" :class="a.class" />
          <p class="mt-2 text-xl font-extrabold num">
            {{ a.value }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ a.label }}
          </p>
        </NuxtLink>
      </div>

      <div class="grid gap-5 lg:grid-cols-2">
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              الأدراج المفتوحة
            </p>
          </template>
          <NuxtLink v-for="s in today.open_shifts" :key="s.id" :to="`/cash/${s.id}`" class="flex items-center justify-between gap-3 border-t border-(--ui-border) p-3 first:border-t-0 hover:bg-(--ui-bg-elevated)">
            <div class="min-w-0">
              <p class="truncate font-bold">
                {{ s.user_name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                <span class="num">{{ s.reference }}</span><template v-if="s.branch">
                  · {{ s.branch }}
                </template> · من {{ timeAgo(s.opened_at, now).replace('من ', '') }}
              </p>
            </div>
            <div class="text-end">
              <p class="font-bold num">
                {{ formatMoney(s.expected_cash) }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                المفروض كاش
              </p>
            </div>
          </NuxtLink>
          <p v-if="!today.open_shifts.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مفيش ورديات مفتوحة دلوقتي.
          </p>
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              الأكتر مبيعاً النهارده
            </p>
          </template>
          <div v-for="(t, i) in today.top_items" :key="t.name" class="flex items-center justify-between gap-3 border-t border-(--ui-border) p-3 first:border-t-0">
            <p class="min-w-0 truncate">
              <span class="me-2 text-(--ui-text-muted) num">{{ i + 1 }}</span>{{ t.name }}
            </p>
            <p class="shrink-0 text-sm">
              <span class="font-bold num">{{ formatMoney(t.amount) }}</span>
              <span class="text-(--ui-text-muted)"> · <span class="num">{{ t.qty }}</span> قطعة</span>
            </p>
          </div>
          <p v-if="!today.top_items.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            لسه مفيش مبيعات النهارده.
          </p>
        </UCard>
      </div>
    </template>
    <div v-else-if="todayStatus === 'pending'" class="grid gap-3 sm:grid-cols-2">
      <USkeleton class="h-48" />
      <USkeleton class="h-48" />
    </div>

    <!-- «اللي بيحصل» -->
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="flex items-center gap-2 font-bold">
            <span class="relative flex size-2.5">
              <span class="absolute inline-flex size-full animate-ping rounded-full bg-(--ui-success) opacity-60" />
              <span class="relative inline-flex size-2.5 rounded-full bg-(--ui-success)" />
            </span>
            اللي بيحصل
          </p>
          <div class="flex flex-wrap gap-1">
            <UButton
              v-for="k in kindFilters"
              :key="k.key"
              size="xs"
              :color="kinds.includes(k.key) ? 'primary' : 'neutral'"
              :variant="kinds.includes(k.key) ? 'soft' : 'ghost'"
              :aria-pressed="kinds.includes(k.key)"
              :label="k.label"
              @click="toggleKind(k.key)"
            />
          </div>
        </div>
      </template>
      <component
        :is="item.to ? NuxtLink : 'div'"
        v-for="item in feed"
        :key="item.id"
        :to="item.to ?? undefined"
        class="flex items-start gap-3 border-t border-(--ui-border) p-3 first:border-t-0"
        :class="[item.to ? 'hover:bg-(--ui-bg-elevated)' : '', fresh.has(item.id) ? 'app-fresh' : '']"
      >
        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full" :class="toneClass(item.tone)">
          <UIcon :name="item.icon" class="size-4" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold">
            {{ item.title }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ timeAgo(item.at, now) }}<template v-if="item.user_name">
              · {{ item.user_name }}
            </template><template v-if="item.branch && branchItems.length > 2">
              · {{ item.branch }}
            </template><template v-if="item.discount">
              · خصم <span class="num">{{ formatMoney(item.discount) }}</span>
            </template>
          </p>
        </div>
        <p v-if="item.amount !== null" class="shrink-0 font-bold num" :class="item.amount < 0 ? 'text-(--ui-error)' : ''">
          {{ item.amount < 0 ? '−' : '' }}{{ formatMoney(Math.abs(item.amount)) }}
        </p>
      </component>
      <p v-if="!feed.length && !feedLoading" class="p-8 text-center text-sm text-(--ui-text-muted)">
        مفيش حاجة لسه.
      </p>
      <div v-if="feed.length && !feedEnded" class="border-t border-(--ui-border) p-2 text-center">
        <UButton size="sm" color="neutral" variant="ghost" label="أقدم" :loading="feedLoading" @click="loadOlder" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { OwnerFeedItem, OwnerToday } from '~/types/api'

definePageMeta({ module: 'owner_app', permission: 'owner_app.alerts' })

const NuxtLink = resolveComponent('NuxtLink')
const api = useApi()
const now = ref(Date.now())

const branch = ref('all')
const { data: todayData, status: todayStatus, refresh: refreshToday } = await useAsyncData(
  'owner-today',
  () => api<{ data: OwnerToday, meta: { branches: Record<string, string> } }>('/owner/today', { query: { branch: branch.value } }),
  { watch: [branch] },
)
const today = computed(() => todayData.value?.data ?? null)
const branchItems = computed(() => [
  { label: 'كل الفروع', value: 'all' },
  ...Object.entries(todayData.value?.meta.branches ?? {}).map(([value, label]) => ({ label, value })),
])
const todayLabel = computed(() => new Date(now.value).toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' }))

const delta = computed(() => {
  const s = today.value?.sales
  if (!s || !s.same_time_yesterday) {
    return { icon: 'i-lucide-minus', class: 'text-(--ui-text-muted)', text: s?.net ? 'إمبارح في نفس الوقت مكانش فيه مبيعات' : 'لسه مفيش مبيعات' }
  }
  const change = Math.round((s.net - s.same_time_yesterday) / s.same_time_yesterday * 100)
  return change >= 0
    ? { icon: 'i-lucide-trending-up', class: 'text-(--ui-success)', text: `أكتر بـ ${change}% من إمبارح في نفس الوقت (${formatMoney(s.same_time_yesterday)})` }
    : { icon: 'i-lucide-trending-down', class: 'text-(--ui-error)', text: `أقل بـ ${Math.abs(change)}% من إمبارح في نفس الوقت (${formatMoney(s.same_time_yesterday)})` }
})

const hours = computed(() => today.value?.by_hour ?? [])
const peak = computed(() => Math.max(1, ...hours.value.map(h => Math.max(h.today ?? 0, h.yesterday))))
const pct = (v: number) => Math.round(v / peak.value * 100)
const peakLabel = computed(() => {
  const best = [...hours.value].sort((a, b) => (b.today ?? 0) - (a.today ?? 0))[0]
  return best && best.today ? `أعلى ساعة ${best.hour}:00 بـ ${formatMoney(best.today)}` : 'لسه مفيش مبيعات'
})

const salesStats = computed(() => {
  const s = today.value?.sales
  if (!s || !today.value) {
    return []
  }
  return [
    { label: 'الفواتير', value: String(s.invoices), class: '' },
    s.profit !== null
      ? { label: 'المكسب', value: formatMoney(s.profit), class: s.profit < 0 ? 'text-(--ui-error)' : '' }
      : { label: 'متوسط الفاتورة', value: formatMoney(s.average), class: '' },
    { label: 'المرتجع', value: formatMoney(today.value.returns.amount), class: today.value.returns.amount ? 'text-(--ui-warning)' : '' },
    { label: 'المصروفات', value: formatMoney(today.value.expenses), class: '' },
  ]
})

const attention = computed(() => {
  const t = today.value
  if (!t) {
    return []
  }
  return [
    ...(t.low_stock ? [{ label: 'صنف قرّب يخلص', value: String(t.low_stock), icon: 'i-lucide-package-x', class: 'text-(--ui-warning)', to: '/inventory?status=low' }] : []),
    ...(t.repairs ? [{ label: 'جهاز جاهز للتسليم', value: String(t.repairs.ready), icon: 'i-lucide-wrench', class: 'text-primary', to: '/repairs?tab=ready' }] : []),
    ...(t.repairs ? [{ label: 'جهاز تحت الصيانة', value: String(t.repairs.in_progress), icon: 'i-lucide-hammer', class: 'text-(--ui-text-muted)', to: '/repairs' }] : []),
    ...(t.installments ? [{ label: 'أقساط متأخرة', value: formatMoney(t.installments.late_amount), icon: 'i-lucide-calendar-clock', class: t.installments.late_amount ? 'text-(--ui-error)' : 'text-(--ui-text-muted)', to: '/installments' }] : []),
  ]
})

// «اللي بيحصل»: newest first; new items slide in on top while the page is open.
type Kind = 'sale' | 'return' | 'cash' | 'shift' | 'price'
const kindFilters: { key: Kind, label: string }[] = [
  { key: 'sale', label: 'فواتير' },
  { key: 'return', label: 'مرتجع' },
  { key: 'cash', label: 'الدرج' },
  { key: 'shift', label: 'ورديات' },
  { key: 'price', label: 'أسعار' },
]
const kinds = ref<Kind[]>(['sale', 'return', 'cash', 'shift', 'price'])
const feed = ref<OwnerFeedItem[]>([])
const feedLoading = ref(false)
const feedEnded = ref(false)
const fresh = ref(new Set<string>())

async function fetchFeed(before?: string): Promise<OwnerFeedItem[]> {
  return (await api<{ data: OwnerFeedItem[] }>('/owner/feed', { query: { 'branch': branch.value, 'kinds[]': kinds.value, before } })).data
}

async function reloadFeed() {
  feedLoading.value = true
  try {
    feed.value = await fetchFeed()
    feedEnded.value = feed.value.length < 25
  }
  finally {
    feedLoading.value = false
  }
}

async function pollFeed() {
  const latest = await fetchFeed().catch(() => null)
  if (!latest) {
    return
  }
  const known = new Set(feed.value.map(i => i.id))
  const added = latest.filter(i => !known.has(i.id))
  if (added.length) {
    fresh.value = new Set(added.map(i => i.id))
    feed.value = [...added, ...feed.value]
  }
}

async function loadOlder() {
  const last = feed.value[feed.value.length - 1]
  if (!last) {
    return
  }
  feedLoading.value = true
  try {
    const older = await fetchFeed(last.at)
    feed.value = [...feed.value, ...older.filter(i => !feed.value.some(f => f.id === i.id))]
    feedEnded.value = older.length < 25
  }
  finally {
    feedLoading.value = false
  }
}

function toggleKind(key: Kind) {
  kinds.value = kinds.value.includes(key) ? (kinds.value.length > 1 ? kinds.value.filter(k => k !== key) : kinds.value) : [...kinds.value, key]
}
watch([kinds, branch], reloadFeed)
await reloadFeed()

function toneClass(tone: OwnerFeedItem['tone']): string {
  return {
    neutral: 'bg-(--ui-bg-elevated) text-(--ui-text-muted)',
    warning: 'bg-(--ui-warning)/10 text-(--ui-warning)',
    error: 'bg-(--ui-error)/10 text-(--ui-error)',
    success: 'bg-(--ui-success)/10 text-(--ui-success)',
  }[tone]
}

// Live while the page is visible: the feed every 20 s, the figures every 60 s (cached 30 s by the API).
let ticks = 0
const timer = setInterval(() => {
  now.value = Date.now()
  if (document.visibilityState !== 'visible') {
    return
  }
  ticks++
  pollFeed()
  if (ticks % 3 === 0) {
    refreshToday()
  }
}, 20000)
function onVisible() {
  if (document.visibilityState === 'visible') {
    now.value = Date.now()
    pollFeed()
    refreshToday()
  }
}
onMounted(() => document.addEventListener('visibilitychange', onVisible))
onBeforeUnmount(() => {
  clearInterval(timer)
  document.removeEventListener('visibilitychange', onVisible)
})
</script>

<style scoped>
.app-fresh {
  animation: app-fresh 2.5s ease-out;
}
@keyframes app-fresh {
  from { background: color-mix(in oklab, var(--ui-primary) 14%, transparent); }
  to { background: transparent; }
}
</style>

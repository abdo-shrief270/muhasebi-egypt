<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">
          أهلاً {{ store.session?.user.name }} 👋
        </h1>
        <p class="text-(--ui-text-muted)">
          {{ today }}
        </p>
      </div>
      <div v-if="canReports" class="flex gap-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1" role="group" aria-label="الفترة">
        <UButton
          v-for="d in [7, 14, 30]"
          :key="d"
          size="sm"
          :color="days === d ? 'primary' : 'neutral'"
          :variant="days === d ? 'solid' : 'ghost'"
          :label="`آخر ${d} يوم`"
          :aria-pressed="days === d"
          @click="days = d"
        />
      </div>
    </div>

    <OnboardingChecklist />

    <!-- Owners without two-factor sign-in get a nudge (dismissible for a week); «ابدأ من هنا» already asks while it shows. -->
    <UAlert
      v-if="showTwoFactorNudge"
      color="warning"
      variant="subtle"
      icon="i-lucide-shield-alert"
      title="احمِ حساب المحل بالتحقق بخطوتين"
      description="كود من موبايلك مع كلمة السر، عشان محدش يدخل على فلوس المحل لو عرف كلمة السر."
      :actions="[{ label: 'فعّله دلوقتي', to: '/settings/security', color: 'warning' }, { label: 'بعدين', color: 'neutral', variant: 'ghost', onClick: dismissTwoFactorNudge }]"
    />

    <!-- Quick actions -->
    <div v-if="actions.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <component
        :is="action.to ? NuxtLink : 'button'"
        v-for="action in actions"
        :key="action.label"
        :to="action.to"
        :type="action.to ? undefined : 'button'"
        class="app-card group flex items-center gap-3 p-3 text-start transition hover:ring-1 hover:ring-primary"
        @click="action.run?.()"
      >
        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--app-primary-soft) text-(--app-primary-strong)">
          <UIcon :name="action.icon" class="size-5" />
        </span>
        <span class="min-w-0">
          <span class="block truncate font-bold">{{ action.label }}</span>
          <span class="block truncate text-xs text-(--ui-text-muted)">{{ action.description }}</span>
        </span>
        <UKbd v-if="action.kbd" :value="action.kbd" class="ms-auto hidden shrink-0 lg:inline-flex" />
      </component>
    </div>

    <template v-if="canReports && stats">
      <!-- Today -->
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="app-card p-4">
          <p class="text-sm text-(--ui-text-muted)">
            مبيعات النهارده
          </p>
          <p class="mt-1 text-2xl font-extrabold num">
            {{ formatMoney(stats.today.revenue) }}
          </p>
          <p v-if="delta !== null" class="mt-1 flex items-center gap-1 text-xs font-bold" :class="delta >= 0 ? 'text-success' : 'text-error'">
            <UIcon :name="delta >= 0 ? 'i-lucide-trending-up' : 'i-lucide-trending-down'" class="size-4" />
            <span class="num">{{ delta >= 0 ? '+' : '' }}{{ delta }}%</span>
            <span class="font-normal text-(--ui-text-muted)">عن امبارح</span>
          </p>
        </div>
        <div class="app-card p-4">
          <p class="text-sm text-(--ui-text-muted)">
            فواتير النهارده
          </p>
          <p class="mt-1 text-2xl font-extrabold num">
            {{ stats.today.sales.toLocaleString('en-US') }}
          </p>
        </div>
        <div class="app-card p-4">
          <p class="text-sm text-(--ui-text-muted)">
            متوسط الفاتورة
          </p>
          <p class="mt-1 text-2xl font-extrabold num">
            {{ formatMoney(stats.today.average) }}
          </p>
        </div>
        <div class="app-card p-4">
          <template v-if="stats.today.profit !== null">
            <p class="text-sm text-(--ui-text-muted)">
              مكسب النهارده
            </p>
            <p class="mt-1 text-2xl font-extrabold num">
              {{ formatMoney(stats.today.profit) }}
            </p>
            <p v-if="stats.today.revenue > 0" class="mt-1 text-xs text-(--ui-text-muted)">
              هامش <span class="num">{{ Math.round(stats.today.profit / stats.today.revenue * 100) }}%</span>
            </p>
          </template>
          <template v-else>
            <p class="text-sm text-(--ui-text-muted)">
              فواتير آخر {{ stats.days }} يوم
            </p>
            <p class="mt-1 text-2xl font-extrabold num">
              {{ stats.period.sales.toLocaleString('en-US') }}
            </p>
          </template>
        </div>
      </div>

      <!-- Daily chart -->
      <UCard>
        <div class="mb-2 flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-bold">
              {{ metricInfo.title }} يوم بيوم
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              آخر {{ stats.days }} يوم ·
              <span class="font-bold text-(--ui-text) num">{{ metricInfo.format(metricInfo.total) }}</span>
            </p>
          </div>
          <div class="flex gap-1" role="group" aria-label="المقياس">
            <UButton
              v-for="m in metrics"
              :key="m.key"
              size="xs"
              :color="metric === m.key ? 'primary' : 'neutral'"
              :variant="metric === m.key ? 'soft' : 'ghost'"
              :label="m.title"
              :aria-pressed="metric === m.key"
              @click="metric = m.key"
            />
          </div>
        </div>
        <DashboardDailyChart :points="points" :label="metricInfo.title" :format="metricInfo.format" :axis-format="metricInfo.axis" />
      </UCard>

      <div class="grid gap-6 lg:grid-cols-2">
        <UCard>
          <p class="mb-4 font-bold">
            الأكتر مبيعاً
          </p>
          <ul v-if="stats.top_items.length" class="space-y-3">
            <li v-for="item in stats.top_items" :key="item.name">
              <div class="mb-1 flex justify-between gap-3 text-sm">
                <span class="truncate font-bold">{{ item.name }}</span>
                <span class="shrink-0 text-(--ui-text-muted)"><span class="num">{{ item.qty }}</span> قطعة · <span class="num">{{ formatMoney(item.revenue) }}</span></span>
              </div>
              <div class="h-2 rounded-full bg-(--ui-bg-elevated)">
                <div class="h-2 rounded-full bg-(--app-chart)" :style="{ width: `${share(item.revenue, topMax)}%` }" />
              </div>
            </li>
          </ul>
          <p v-else class="py-8 text-center text-sm text-(--ui-text-muted)">
            لسه مفيش مبيعات في الفترة دي.
          </p>
        </UCard>

        <UCard>
          <p class="mb-4 font-bold">
            طرق الدفع
          </p>
          <ul v-if="stats.payments.length" class="space-y-3">
            <li v-for="p in stats.payments" :key="p.method">
              <div class="mb-1 flex justify-between gap-3 text-sm">
                <span class="font-bold">{{ p.label }}</span>
                <span class="text-(--ui-text-muted)"><span class="num">{{ formatMoney(p.amount) }}</span> · <span class="num">{{ share(p.amount, paymentsTotal) }}%</span></span>
              </div>
              <div class="h-2 rounded-full bg-(--ui-bg-elevated)">
                <div class="h-2 rounded-full bg-(--app-chart)" :style="{ width: `${share(p.amount, paymentsTotal)}%` }" />
              </div>
            </li>
          </ul>
          <p v-else class="py-8 text-center text-sm text-(--ui-text-muted)">
            لسه مفيش مدفوعات في الفترة دي.
          </p>
        </UCard>
      </div>
    </template>

    <!-- Money outside the tills' sales: drawers, expenses, credit -->
    <div v-if="moneyCards.length" class="grid grid-cols-2 gap-3 lg:grid-cols-3">
      <NuxtLink v-for="card in moneyCards" :key="card.label" :to="card.to" class="app-card p-4 transition hover:ring-1 hover:ring-primary">
        <p class="flex items-center gap-1.5 text-sm text-(--ui-text-muted)">
          <UIcon :name="card.icon" class="size-4" />
          {{ card.label }}
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ formatMoney(card.value) }}
        </p>
        <p v-if="card.hint" class="mt-1 text-xs text-(--ui-text-muted)">
          {{ card.hint }}
        </p>
      </NuxtLink>
    </div>

    <!-- Repairs at a glance -->
    <div v-if="repairs" class="grid grid-cols-2 gap-3 sm:auto-cols-fr sm:grid-flow-col sm:grid-cols-none">
      <NuxtLink v-for="card in repairCards" :key="card.label" :to="card.to" class="app-card p-4 transition hover:ring-1 hover:ring-primary">
        <p class="flex items-center gap-1.5 text-sm text-(--ui-text-muted)">
          <UIcon :name="card.icon" class="size-4" :class="card.tone" />
          {{ card.label }}
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ card.value }}
        </p>
      </NuxtLink>
    </div>

    <!-- Stock at a glance -->
    <div v-if="stock" class="grid grid-cols-3 gap-3">
      <NuxtLink v-for="card in stockCards" :key="card.label" :to="card.to" class="app-card p-4 transition hover:ring-1 hover:ring-primary">
        <p class="flex items-center gap-1.5 text-sm text-(--ui-text-muted)">
          <UIcon :name="card.icon" class="size-4" :class="card.tone" />
          {{ card.label }}
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ card.value.toLocaleString('en-US') }}
        </p>
      </NuxtLink>
    </div>

    <UAlert
      v-if="store.isOwner"
      icon="i-lucide-blocks"
      color="neutral"
      variant="subtle"
      title="محتاج قسم زيادة؟"
      description="جرّب الصيانة أو طلبات المحلات مجاناً 14 يوم من صفحة الأقسام."
      :actions="[{ label: 'الأقسام', to: '/settings/modules' }]"
    />
  </div>
</template>

<script setup lang="ts">
import type { OnboardingState, RepairSummary, SalesStats, StockSummary } from '~/types/api'
import { NuxtLink } from '#components'

const api = useApi()
const store = useSessionStore()
const actions = useQuickActions()
const canReports = computed(() => store.can('reports.view'))
const canStock = computed(() => store.can('inventory.view'))

const NUDGE_KEY = 'muhasebi:2fa-nudge-dismissed'
const nudgeDismissedAt = ref<number>(0)
onMounted(() => {
  nudgeDismissedAt.value = Number(localStorage.getItem(NUDGE_KEY) ?? 0)
})
const { data: onboarding } = useNuxtData<OnboardingState | null>('onboarding')
const showTwoFactorNudge = computed(() => store.isOwner
  && !onboarding.value?.visible
  && store.session?.user.two_factor_enabled === false
  && Date.now() - nudgeDismissedAt.value > 7 * 24 * 60 * 60 * 1000)
function dismissTwoFactorNudge() {
  nudgeDismissedAt.value = Date.now()
  localStorage.setItem(NUDGE_KEY, String(nudgeDismissedAt.value))
}

const today = new Date().toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

const days = ref(14)
const { data: stats } = await useAsyncData('dashboard-stats', async () => canReports.value
  ? (await api<{ data: SalesStats }>('/sales/stats', { query: { days: days.value } })).data
  : null, { watch: [days] })

const branchKey = computed(() => store.session?.current_branch_id ?? '')
const { data: stock } = await useAsyncData('dashboard-stock', async () => canStock.value
  ? (await api<{ data: StockSummary }>('/inventory/summary')).data
  : null, { watch: [branchKey] })

const { data: cash } = await useAsyncData('dashboard-cash', async () => store.can('cash.manage')
  ? (await api<{ data: { in_drawers: number, open_shifts: unknown[], expenses_today: number } }>('/cash/summary')).data
  : null)
const { data: receivable } = await useAsyncData('dashboard-receivable', async () => store.can('customers.view')
  ? (await api<{ meta: { receivable: number, owing_count: number } }>('/customers', { query: { owing: 1, per_page: 5 } })).meta
  : null)
const { data: repairs } = await useAsyncData('dashboard-repairs', async () => store.can('repairs.view')
  ? (await api<{ data: RepairSummary }>('/repairs/summary')).data
  : null, { watch: [branchKey] })

const moneyCards = computed(() => [
  ...(cash.value
    ? [
        { label: 'الكاش في الأدراج', value: cash.value.in_drawers, to: '/cash', icon: 'i-lucide-wallet', hint: `${cash.value.open_shifts.length} وردية مفتوحة` },
        { label: 'مصروفات النهارده', value: cash.value.expenses_today, to: '/cash', icon: 'i-lucide-receipt', hint: null },
      ]
    : []),
  // Wallets / airtime profit, apart from the goods profit above (only with reports.profit and the module).
  ...(stats.value?.services
    ? [{ label: 'مكسب الشحن والتحويلات النهارده', value: stats.value.services.today, to: '/services', icon: 'i-lucide-arrow-left-right', hint: `${stats.value.services.today_operations} عملية · آخر ${stats.value.days} يوم ${formatMoney(stats.value.services.period)}` }]
    : []),
  ...(receivable.value
    ? [{ label: 'الآجل عند العملاء', value: receivable.value.receivable, to: '/customers?owing=1', icon: 'i-lucide-hand-coins', hint: `${receivable.value.owing_count} عميل` }]
    : []),
])

const delta = computed(() => {
  const t = stats.value?.today
  return t && t.revenue_yesterday > 0 ? Math.round((t.revenue - t.revenue_yesterday) / t.revenue_yesterday * 100) : null
})

type Metric = 'revenue' | 'profit' | 'sales'
const metric = ref<Metric>('revenue')
const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })
const count = (n: number) => n.toLocaleString('en-US')
const metrics = computed(() => [
  { key: 'revenue' as const, title: 'المبيعات', total: stats.value?.period.revenue ?? 0, format: formatMoney, axis: (v: number) => compact.format(v / 100) },
  ...(stats.value?.period.profit !== null && stats.value?.period.profit !== undefined
    ? [{ key: 'profit' as const, title: 'المكسب', total: stats.value.period.profit, format: formatMoney, axis: (v: number) => compact.format(v / 100) }]
    : []),
  { key: 'sales' as const, title: 'عدد الفواتير', total: stats.value?.period.sales ?? 0, format: count, axis: count },
])
const metricInfo = computed(() => metrics.value.find(m => m.key === metric.value) ?? metrics.value[0]!)
const points = computed(() => (stats.value?.series ?? []).map(d => ({ date: d.date, value: d[metricInfo.value.key] ?? 0 })))

const topMax = computed(() => Math.max(1, ...(stats.value?.top_items ?? []).map(i => i.revenue)))
const paymentsTotal = computed(() => (stats.value?.payments ?? []).reduce((sum, p) => sum + p.amount, 0))
const share = (value: number, of: number) => (of > 0 ? Math.round(value / of * 100) : 0)

const repairCards = computed(() => repairs.value
  ? [
      { label: 'أجهزة في الصيانة', value: repairs.value.open, to: '/repairs', icon: 'i-lucide-wrench', tone: 'text-(--ui-text-muted)' },
      { label: 'جاهزة للتسليم', value: repairs.value.ready, to: '/repairs?tab=ready', icon: 'i-lucide-check-circle', tone: 'text-success' },
      ...(repairs.value.unnotified ? [{ label: 'جاهزة والعميل ما اتبلغش', value: repairs.value.unnotified, to: '/repairs?tab=ready', icon: 'i-lucide-message-circle', tone: 'text-warning' }] : []),
      { label: 'متأخرة عن ميعادها', value: repairs.value.overdue, to: '/repairs?tab=overdue', icon: 'i-lucide-alarm-clock', tone: 'text-error' },
    ]
  : [])

const stockCards = computed(() => stock.value
  ? [
      { label: 'أصناف متوفرة', value: stock.value.in_stock, to: '/inventory', icon: 'i-lucide-package', tone: 'text-(--ui-text-muted)' },
      { label: 'قربت تخلص', value: stock.value.low, to: '/inventory?status=low', icon: 'i-lucide-triangle-alert', tone: 'text-warning' },
      { label: 'خلصت', value: stock.value.out_of_stock, to: '/inventory?status=out', icon: 'i-lucide-circle-x', tone: 'text-error' },
    ]
  : [])
</script>

<template>
  <div class="space-y-4">
    <PageHeader title="الشحن والتحويلات" :description="`المحافظ وأرصدة الشحن في فرع «${store.currentBranch?.name ?? ''}».`">
      <UButton v-if="canSettings" icon="i-lucide-plus" label="إضافة محفظة / رصيد" @click="edit(null)" />
    </PageHeader>
    <ServicesTabs />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          في المحافظ
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ formatMoney(totals.wallet) }}
        </p>
      </div>
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          رصيد الشحن
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ formatMoney(totals.airtime) }}
        </p>
      </div>
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          عمليات النهارده
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ today.operations.toLocaleString('en-US') }}
        </p>
      </div>
      <div class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          عمولات النهارده
        </p>
        <p class="mt-1 text-2xl font-extrabold num">
          {{ formatMoney(today.fees) }}
        </p>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <UCard v-for="a in accounts" :key="a.id" :class="a.is_active ? '' : 'opacity-60'">
        <div class="flex items-start gap-3">
          <span class="mt-1.5 size-3 shrink-0 rounded-full" :class="providerTone(a.provider)" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <p class="truncate font-bold">
              {{ a.name }}
              <UBadge v-if="!a.is_active" size="sm" color="neutral" variant="subtle" class="ms-1">
                متوقفة
              </UBadge>
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              {{ a.name === a.provider_label ? a.kind_label : a.provider_label }}<template v-if="a.phone">
                · <span class="num">{{ localPhone(a.phone) }}</span>
              </template>
            </p>
          </div>
          <UDropdownMenu v-if="menu(a).length" :items="menu(a)">
            <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" aria-label="خيارات" />
          </UDropdownMenu>
        </div>

        <p class="mt-3 text-3xl font-extrabold num">
          {{ formatMoney(a.balance) }}
        </p>
        <p v-if="a.kind === 'airtime' && a.cost_value !== null && a.balance > 0" class="text-xs text-(--ui-text-muted)">
          تكلفته <span class="num">{{ formatMoney(a.cost_value) }}</span> · هامش <span class="num">{{ Math.round((a.balance - a.cost_value) / a.balance * 1000) / 10 }}%</span>
        </p>

        <div v-if="a.daily_limit" class="mt-3">
          <div class="mb-1 flex justify-between text-xs text-(--ui-text-muted)">
            <span>النهارده</span>
            <span><span class="num">{{ formatMoney(a.today_used) }}</span> من <span class="num">{{ formatMoney(a.daily_limit) }}</span></span>
          </div>
          <div class="h-2 rounded-full bg-(--ui-bg-elevated)">
            <div class="h-2 rounded-full" :class="a.today_used > a.daily_limit ? 'bg-error' : 'bg-(--app-chart)'" :style="{ width: `${Math.min(100, Math.round(a.today_used / a.daily_limit * 100))}%` }" />
          </div>
        </div>

        <ul class="mt-3 space-y-0.5 text-xs text-(--ui-text-muted)">
          <li v-for="op in a.operations" :key="op">
            عمولة {{ SERVICE_TYPE_LABELS[op] }}: <span class="text-(--ui-text)">{{ describeFee(a.fees[op]) }}</span>
          </li>
        </ul>

        <div v-if="canFund" class="mt-4 flex gap-2">
          <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-arrow-down-to-line" label="تمويل" @click="move(a, 'fund')" />
          <UButton v-if="a.kind === 'wallet'" size="sm" color="neutral" variant="outline" icon="i-lucide-arrow-up-from-line" label="تسييل" @click="move(a, 'cash_out')" />
        </div>
      </UCard>
    </div>

    <div v-if="!accounts.length && status !== 'pending'" class="app-card p-8 text-center text-(--ui-text-muted)">
      مفيش محافظ في الفرع ده.
    </div>

    <ServicesAccountFormModal v-model:open="formOpen" :account="editing" :options="options" @saved="refresh()" />
    <ServicesMoveMoneyModal v-model:open="moveOpen" :account="moving" :mode="moveMode" @saved="refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { ServiceAccount, ServiceFeeRule, ServiceOptions } from '~/types/api'

definePageMeta({ permission: 'services.manage' })

const api = useApi()
const store = useSessionStore()
const route = useRoute()
const canSettings = computed(() => store.can('services.settings'))
const canFund = computed(() => store.can('services.fund'))
const branchKey = computed(() => store.session?.current_branch_id ?? '')

const [{ data, status, refresh }, { data: optionsData }] = await Promise.all([
  useAsyncData('services-accounts-page', () => api<{ data: ServiceAccount[], meta: { today: { operations: number, fees: number } } }>('/services/accounts', { query: { all: 1 } }), { watch: [branchKey] }),
  useAsyncData('services-options', () => api<{ data: ServiceOptions }>('/services/options')),
])
const accounts = computed(() => data.value?.data ?? [])
const today = computed(() => data.value?.meta.today ?? { operations: 0, fees: 0 })
const options = computed(() => optionsData.value?.data)
const totals = computed(() => ({
  wallet: accounts.value.filter(a => a.kind === 'wallet').reduce((s, a) => s + a.balance, 0),
  airtime: accounts.value.filter(a => a.kind === 'airtime').reduce((s, a) => s + a.balance, 0),
}))

function describeFee(rule: ServiceFeeRule | undefined): string {
  if (!rule || (!rule.percent && !rule.fixed && !rule.min)) {
    return 'من غير'
  }
  const parts: string[] = []
  if (rule.percent) {
    parts.push(`${rule.percent / 100}%`)
  }
  if (rule.fixed) {
    parts.push(formatMoney(rule.fixed))
  }
  let text = parts.join(' + ') || formatMoney(0)
  if (rule.min) {
    text += `، أقل حاجة ${formatMoney(rule.min)}`
  }
  if (rule.max !== null) {
    text += `، أقصى ${formatMoney(rule.max)}`
  }
  return text
}

const formOpen = ref(false)
const editing = ref<ServiceAccount | null>(null)
function edit(a: ServiceAccount | null) {
  editing.value = a
  formOpen.value = true
}
onMounted(() => {
  if (route.query.new === '1' && canSettings.value) {
    edit(null)
  }
})

const moveOpen = ref(false)
const moving = ref<ServiceAccount | null>(null)
const moveMode = ref<'fund' | 'cash_out'>('fund')
function move(a: ServiceAccount, mode: 'fund' | 'cash_out') {
  moving.value = a
  moveMode.value = mode
  moveOpen.value = true
}

function menu(a: ServiceAccount): DropdownMenuItem[] {
  return [
    { label: 'الحركات', icon: 'i-lucide-list', to: `/services/transactions?account=${a.id}` },
    ...(canSettings.value ? [{ label: 'تعديل والعمولات', icon: 'i-lucide-pencil', onSelect: () => edit(a) }] : []),
  ]
}
</script>

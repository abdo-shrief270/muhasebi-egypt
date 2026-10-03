<template>
  <div class="space-y-4">
    <PageHeader title="الشحن والتحويلات" :description="`كل حركات المحافظ والأرصدة في فرع «${store.currentBranch?.name ?? ''}».`" />
    <ServicesTabs />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-[1fr_200px_160px_150px_150px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="رقم العملية، موبايل أو اسم العميل، المرجع…" class="col-span-2 w-full lg:col-span-1" />
      <USelect v-model="accountId" :items="accountItems" placeholder="كل المحافظ" class="w-full" aria-label="المحفظة" />
      <USelect v-model="type" :items="typeItems" placeholder="كل العمليات" class="w-full" aria-label="العملية" />
      <UInput v-model="from" type="date" aria-label="من" />
      <UInput v-model="to" type="date" aria-label="إلى" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                العملية
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                العميل
              </th>
              <th class="p-3 text-end font-bold">
                المبلغ
              </th>
              <th class="hidden p-3 text-end font-bold sm:table-cell">
                العمولة
              </th>
              <th class="hidden p-3 text-end font-bold lg:table-cell">
                الدرج
              </th>
              <th class="hidden p-3 text-end font-bold lg:table-cell">
                رصيد المحفظة
              </th>
              <th v-if="canProfit" class="hidden p-3 text-end font-bold xl:table-cell">
                المكسب
              </th>
              <th class="w-10 p-3">
                <span class="sr-only">خيارات</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in rows" :key="t.id" class="border-t border-(--ui-border)" :class="t.reverses_id || t.reversed ? 'text-(--ui-text-muted)' : ''">
              <td class="p-3">
                <p class="font-bold">
                  {{ t.reverses_id ? `إلغاء ${t.type_label}` : t.type_label }} · {{ t.account_name }}
                  <UBadge v-if="t.reversed" size="sm" color="neutral" variant="subtle" class="ms-1">
                    اتلغت
                  </UBadge>
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num">{{ serviceReference(t.number) }}</span> · <span class="num">{{ formatDate(t.created_at, true) }}</span> · {{ t.user_name }}
                  <template v-if="t.source_label">
                    · {{ t.type === 'fund' ? 'من' : 'إلى' }} {{ t.source_label }}
                  </template>
                </p>
                <p v-if="t.note" class="text-xs text-(--ui-text-muted)">
                  {{ t.reversed_number ? `إلغاء ${serviceReference(t.reversed_number)}: ` : '' }}{{ t.note }}
                </p>
              </td>
              <td class="hidden p-3 md:table-cell">
                <p v-if="t.customer_phone" class="num">
                  {{ localPhone(t.customer_phone) }}
                </p>
                <p v-if="t.customer_name" class="text-xs text-(--ui-text-muted)">
                  {{ t.customer_name }}
                </p>
                <p v-if="t.reference" class="text-xs text-(--ui-text-muted) num">
                  {{ t.reference }}
                </p>
                <span v-if="!t.customer_phone && !t.customer_name && !t.reference">—</span>
              </td>
              <td class="p-3 text-end font-bold whitespace-nowrap num" :class="t.reverses_id || t.reversed ? 'line-through' : ''">
                {{ formatMoney(Math.abs(t.amount)) }}
              </td>
              <td class="hidden p-3 text-end num sm:table-cell">
                {{ t.fee ? formatMoney(t.fee) : '—' }}
              </td>
              <td class="hidden p-3 text-end num lg:table-cell" :class="t.cash < 0 ? 'text-error' : ''">
                {{ t.cash === 0 ? '—' : `${t.cash < 0 ? '−' : '+'}${formatMoney(Math.abs(t.cash))}` }}
              </td>
              <td class="hidden p-3 text-end lg:table-cell">
                <span class="num" :class="t.balance_change < 0 ? 'text-error' : 'text-success'">{{ t.balance_change < 0 ? '−' : '+' }}{{ formatMoney(Math.abs(t.balance_change)) }}</span>
                <p class="text-xs text-(--ui-text-muted) num">
                  {{ formatMoney(t.balance_after) }}
                </p>
              </td>
              <td v-if="canProfit" class="hidden p-3 text-end num xl:table-cell" :class="(t.profit ?? 0) < 0 ? 'text-error' : ''">
                {{ t.profit ? formatMoney(t.profit) : '—' }}
              </td>
              <td class="p-3">
                <UDropdownMenu v-if="!t.reverses_id && t.type !== 'opening'" :items="rowItems(t)">
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" aria-label="خيارات" />
                </UDropdownMenu>
              </td>
            </tr>
            <tr v-if="!rows.length && status !== 'pending'">
              <td colspan="8" class="p-10 text-center text-(--ui-text-muted)">
                مفيش حركات.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="data?.meta.per_page ?? 50" />
    </div>

    <ServicesReverseModal v-model:open="reverseOpen" :t="reversing" @reversed="refresh()" />

    <PrintSheet v-if="printing && printed" :page-size="paper.page.value">
      <ServicesReceipt :t="printed" :shop-name="store.session?.tenant.name ?? ''" :branch-name="store.currentBranch?.name" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Paginated, ServiceAccount, ServiceTransaction } from '~/types/api'

definePageMeta({ permission: 'services.manage' })

const paper = useThermalPaper()
const api = useApi()
const store = useSessionStore()
const route = useRoute()
const canProfit = computed(() => store.can('reports.profit') || store.can('services.settings'))
const branchKey = computed(() => store.session?.current_branch_id ?? '')

const q = ref('')
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const ALL = '_all'
const accountId = ref<string>(typeof route.query.account === 'string' ? route.query.account : ALL)
const type = ref<string>(ALL)
const from = ref('')
const to = ref('')
const page = ref(1)
watch([debouncedQ, accountId, type, from, to], () => {
  page.value = 1
})

const { data: accountsData } = await useAsyncData('services-accounts-all', () => api<{ data: ServiceAccount[] }>('/services/accounts', { query: { all: 1 } }), { watch: [branchKey] })
const accountItems = computed(() => [{ label: 'كل المحافظ', value: ALL }, ...(accountsData.value?.data ?? []).map(a => ({ label: a.name, value: a.id }))])
const typeItems = [{ label: 'كل العمليات', value: ALL }, ...Object.entries(SERVICE_TYPE_LABELS).map(([value, label]) => ({ label, value }))]

const { data, status, refresh } = await useAsyncData('services-transactions', () => api<Paginated<ServiceTransaction>>('/services/transactions', {
  query: {
    q: debouncedQ.value || undefined,
    account_id: accountId.value === ALL ? undefined : accountId.value,
    type: type.value === ALL ? undefined : type.value,
    from: from.value || undefined,
    to: to.value || undefined,
    page: page.value,
  },
}), { watch: [debouncedQ, accountId, type, from, to, page, branchKey] })
const rows = computed(() => data.value?.data ?? [])

const { printing, print } = usePrint()
const printed = ref<ServiceTransaction | null>(null)
const reverseOpen = ref(false)
const reversing = ref<ServiceTransaction | null>(null)

/** Your own slip of today, or anyone's with services.settings (the API checks the same). */
function canReverse(t: ServiceTransaction): boolean {
  if (t.reversed) {
    return false
  }
  if (store.can('services.settings')) {
    return true
  }
  return t.user_id === store.session?.user.id && new Date(t.created_at).toDateString() === new Date().toDateString()
}

function rowItems(t: ServiceTransaction): DropdownMenuItem[] {
  return [
    ...(['deposit', 'withdraw', 'topup'].includes(t.type) ? [{ label: 'اطبع الإيصال', icon: 'i-lucide-printer', onSelect: async () => { printed.value = t; await print() } }] : []),
    ...(canReverse(t) ? [{ label: 'إلغاء العملية', icon: 'i-lucide-undo-2', color: 'error' as const, onSelect: () => { reversing.value = t; reverseOpen.value = true } }] : []),
  ]
}
</script>

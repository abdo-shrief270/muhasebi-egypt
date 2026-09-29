<template>
  <div class="space-y-6">
    <PageHeader title="الخزنة" :description="`ورديتك في فرع «${store.currentBranch?.name ?? ''}»: الكاش والفيزا والمصروفات لحد ما تقفل.`">
      <template v-if="shift">
        <UButton v-if="canExpense" color="neutral" variant="outline" icon="i-lucide-receipt" label="مصروف" @click="openMovement('expense')" />
        <UDropdownMenu v-if="canManage" :items="moreItems">
          <UButton color="neutral" variant="outline" icon="i-lucide-arrow-left-right" label="إيداع / سحب" />
        </UDropdownMenu>
        <UButton icon="i-lucide-lock" label="قفل الوردية" @click="closeOpen = true" />
      </template>
    </PageHeader>

    <CashOpenShiftCard v-if="!shift && currentStatus !== 'pending'" @opened="refreshAll" />

    <template v-if="shift">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="app-card col-span-2 p-4 lg:col-span-1">
          <p class="text-sm text-(--ui-text-muted)">
            الكاش في الدرج
          </p>
          <p class="mt-1 text-3xl font-extrabold num">
            {{ formatMoney(shift.expected?.cash ?? 0) }}
          </p>
          <p class="mt-1 text-xs text-(--ui-text-muted)">
            {{ shift.reference }} · من {{ formatDate(shift.opened_at, true) }}
          </p>
        </div>
        <div v-for="m in otherMethods" :key="m.value" class="app-card p-4">
          <p class="text-sm text-(--ui-text-muted)">
            {{ m.label }}
          </p>
          <p class="mt-1 text-2xl font-extrabold num">
            {{ formatMoney(shift.expected?.[m.value] ?? 0) }}
          </p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              حركات الوردية
            </p>
          </template>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <tbody>
                <tr v-for="m in shift.movements ?? []" :key="m.id" class="border-t border-(--ui-border) first:border-t-0">
                  <td class="w-16 p-3 text-(--ui-text-muted) num">
                    {{ time(m.created_at) }}
                  </td>
                  <td class="p-3">
                    <NuxtLink v-if="m.ref_type === 'sale' && m.ref_id && store.can('sales.view')" :to="`/sales/${m.ref_id}`" class="font-bold hover:text-primary">
                      {{ m.type_label }}
                    </NuxtLink>
                    <span v-else class="font-bold">{{ m.category_label ? `${m.type_label} · ${m.category_label}` : m.type_label }}</span>
                    <span class="text-(--ui-text-muted)"> · {{ m.method_label }}</span>
                    <p v-if="m.note" class="text-xs text-(--ui-text-muted)">
                      {{ m.note }}
                    </p>
                  </td>
                  <td class="p-3 text-end font-bold num" :class="m.amount < 0 ? 'text-error' : ''">
                    {{ m.amount < 0 ? '−' : '+' }}{{ formatMoney(Math.abs(m.amount)) }}
                  </td>
                </tr>
                <tr v-if="!(shift.movements ?? []).length">
                  <td colspan="3" class="p-8 text-center text-(--ui-text-muted)">
                    لسه مفيش حركات. البيع من الكاشير بيظهر هنا.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </UCard>

        <UCard>
          <p class="mb-3 font-bold">
            ملخص الوردية
          </p>
          <div class="flex justify-center rounded-(--ui-radius) bg-(--ui-bg-muted) p-3">
            <CashShiftReport :shift="shift" :shop-name="shopName" :branch-name="store.currentBranch?.name" class="rounded-sm shadow" />
          </div>
        </UCard>
      </div>
    </template>

    <div v-if="shifts.length" class="space-y-3">
      <h2 class="text-lg font-bold">
        {{ canManage ? 'ورديات الفرع' : 'وردياتك' }}
      </h2>
      <CashShiftsTable :shifts="shifts" />
    </div>

    <CashMovementModal v-model:open="movementOpen" :type="movementType" :in-drawer="shift?.expected?.cash ?? 0" :categories="options?.expense_categories ?? []" @saved="refreshAll" />
    <CashCloseShiftModal v-model:open="closeOpen" :shift="shift ?? null" @closed="onClosed" />

    <UModal v-model:open="reportOpen" title="اتقفلت الوردية" :ui="{ content: 'sm:max-w-md' }">
      <template #body>
        <div v-if="closed" class="flex justify-center rounded-(--ui-radius) bg-(--ui-bg-muted) p-3">
          <CashShiftReport :shift="closed" :shop-name="shopName" :branch-name="store.currentBranch?.name" class="rounded-sm shadow" />
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="تمام" @click="reportOpen = false" />
          <UButton icon="i-lucide-printer" label="اطبع التقرير" @click="print" />
        </div>
      </template>
    </UModal>

    <PrintSheet v-if="printing && closed" page-size="80mm auto">
      <CashShiftReport :shift="closed" :shop-name="shopName" :branch-name="store.currentBranch?.name" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { CashOptions, CashShift } from '~/types/api'

definePageMeta({ permission: 'cash.shift' })

const api = useApi()
const store = useSessionStore()
const canManage = computed(() => store.can('cash.manage'))
const canExpense = computed(() => store.can('cash.expenses'))
const shopName = computed(() => store.session?.tenant.name ?? '')
const branchKey = computed(() => store.session?.current_branch_id ?? '')

const [{ data: currentData, status: currentStatus, refresh: refreshCurrent }, { data: shiftsData, refresh: refreshShifts }, { data: optionsData }] = await Promise.all([
  useAsyncData('cash-current', () => api<{ data: CashShift | null }>('/cash/current'), { watch: [branchKey] }),
  useAsyncData('cash-shifts', () => api<{ data: CashShift[] }>('/cash/shifts'), { watch: [branchKey] }),
  useAsyncData('cash-options', () => api<{ data: CashOptions }>('/cash/options')),
])
const shift = computed(() => currentData.value?.data ?? null)
const shifts = computed(() => shiftsData.value?.data ?? [])
const options = computed(() => optionsData.value?.data)
const otherMethods = computed(() => CASH_METHODS.filter(m => m.value !== 'cash'))

async function refreshAll() {
  await Promise.all([refreshCurrent(), refreshShifts()])
}

const movementOpen = ref(false)
const movementType = ref<'expense' | 'deposit' | 'withdrawal'>('expense')
function openMovement(type: 'expense' | 'deposit' | 'withdrawal') {
  movementType.value = type
  movementOpen.value = true
}
const moreItems: DropdownMenuItem[] = [
  { label: 'إيداع في الدرج', icon: 'i-lucide-plus', onSelect: () => openMovement('deposit') },
  { label: 'سحب من الدرج', icon: 'i-lucide-minus', onSelect: () => openMovement('withdrawal') },
]

// Quick action «مصروف»: /cash?expense=1 (with an open shift; otherwise the page offers to open one)
onMounted(() => {
  if (useRoute().query.expense === '1' && shift.value && canExpense.value) {
    openMovement('expense')
  }
})

const closeOpen = ref(false)
const reportOpen = ref(false)
const closed = ref<CashShift | null>(null)
const { printing, print } = usePrint()

async function onClosed(result: CashShift) {
  closed.value = result
  reportOpen.value = true
  await refreshAll()
}

function time(iso: string) {
  return new Date(iso).toLocaleTimeString('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit' })
}
</script>

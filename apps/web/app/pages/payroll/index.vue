<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <PageHeader title="الرواتب" :description="period?.label ?? ''" class="flex-1" />
      <UButton color="neutral" variant="outline" icon="i-lucide-hand-coins" label="سلفة / خصم / مكافأة" @click="openAdjustment()" />
      <UButton color="neutral" variant="outline" icon="i-lucide-users-round" label="المرتبات" @click="employeesOpen = true" />
      <UButton v-if="openRows.length" icon="i-lucide-lock" :label="`اقفل ${cycle === 'weekly' ? 'الأسبوع' : 'الشهر'}`" :loading="closing" @click="closePeriod" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <UButton v-for="c in cycles" :key="c.value" size="sm" :color="cycle === c.value ? 'primary' : 'neutral'" :variant="cycle === c.value ? 'solid' : 'outline'" :label="c.label" @click="cycle = c.value; date = ''" />
      <span class="flex-1" />
      <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-chevron-right" aria-label="اللي قبله" @click="date = period?.previous ?? ''" />
      <span class="font-bold">{{ period?.label }}</span>
      <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-chevron-left" aria-label="اللي بعده" @click="date = period?.next ?? ''" />
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          مرتبات ثابتة
        </p>
        <p class="num text-2xl font-extrabold">
          {{ formatMoney(totals?.base_salary ?? 0) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          عمولات الفنيين
        </p>
        <p class="num text-2xl font-extrabold">
          {{ formatMoney(totals?.commission ?? 0) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          سلف اتصرفت
        </p>
        <p class="num text-2xl font-extrabold">
          {{ formatMoney(totals?.advances ?? 0) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          لسه هيتصرف
        </p>
        <p class="num text-2xl font-extrabold text-primary">
          {{ formatMoney(totals?.unpaid ?? 0) }}
        </p>
      </UCard>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th v-for="h in ['الموظف', 'المرتب', 'العمولة', 'مكافآت', 'خصومات وسلف', 'الصافي', '']" :key="h" class="p-3 text-start font-bold">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--ui-border)">
            <tr v-for="r in rows" :key="r.user_id">
              <td class="p-3 font-bold">
                {{ r.name }}
              </td>
              <td class="num p-3">
                {{ formatMoney(r.base_salary) }}
              </td>
              <td class="p-3">
                <button v-if="r.tickets || r.clawback" type="button" class="num text-start text-primary hover:underline" @click="showCommission(r)">
                  {{ formatMoney(r.commission - r.clawback) }}
                  <span class="block text-xs text-(--ui-text-muted)">{{ r.tickets }} تذكرة<template v-if="r.clawback"> · خصم ضمان {{ formatMoney(r.clawback) }}</template></span>
                </button>
                <span v-else class="text-(--ui-text-muted)">—</span>
              </td>
              <td class="num p-3">
                {{ r.bonuses ? formatMoney(r.bonuses) : '—' }}
              </td>
              <td class="num p-3 text-error">
                {{ r.deductions + r.advances ? formatMoney(r.deductions + r.advances) : '—' }}
              </td>
              <td class="num p-3 font-extrabold">
                {{ formatMoney(r.net) }}
              </td>
              <td class="p-3 text-end">
                <UBadge v-if="r.paid_at" color="success" variant="subtle">
                  اتصرف ({{ fromLabel(r.paid_from) }})
                </UBadge>
                <UDropdownMenu v-else-if="r.closed && r.id" :items="[payItems(r)]" :content="{ align: 'end' }">
                  <UButton size="xs" icon="i-lucide-banknote" label="اصرف" trailing-icon="i-lucide-chevron-down" />
                </UDropdownMenu>
                <UBadge v-else color="warning" variant="subtle">
                  الفترة لسه مفتوحة
                </UBadge>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="7" class="p-6 text-center text-(--ui-text-muted)">
                مفيش موظفين على {{ cycle === 'weekly' ? 'المرتب الأسبوعي' : 'المرتب الشهري' }}. حدّد مرتب كل موظف من «المرتبات».
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
    <p class="text-sm text-(--ui-text-muted)">
      العمولة من التذاكر اللي اتسلّمت في الفترة. السلف والخصومات والمكافآت بتنزل في أول مرتب بيتقفل بعدها. الصرف من الدرج محتاج وردية مفتوحة.
    </p>

    <!-- Each employee's pay -->
    <USlideover v-model:open="employeesOpen" title="المرتبات">
      <template #body>
        <ul class="space-y-4">
          <li v-for="e in employees" :key="e.user_id" class="space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <div class="flex items-center justify-between gap-2">
              <span class="font-bold">{{ e.name }}</span>
              <USwitch v-model="e.active" label="بيقبض" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <UInput v-model="e.input" type="number" min="0" step="any" dir="ltr" class="w-32" :aria-label="`مرتب ${e.name}`">
                <template #trailing>
                  <span class="text-xs text-(--ui-text-muted)">ج</span>
                </template>
              </UInput>
              <USelect v-model="e.pay_cycle" :items="cycles" class="w-32" :aria-label="`كل قد إيه لـ ${e.name}`" />
              <UButton size="sm" color="neutral" variant="outline" label="حفظ" @click="saveEmployee(e)" />
            </div>
          </li>
        </ul>
      </template>
    </USlideover>

    <!-- Advance / bonus / deduction -->
    <UModal v-model:open="adjOpen" title="سلفة / خصم / مكافأة">
      <template #body>
        <form class="space-y-3" @submit.prevent="saveAdjustment">
          <UFormField label="الموظف" required>
            <USelect v-model="adj.user_id" :items="employees.map(e => ({ label: e.name, value: e.user_id }))" class="w-full" />
          </UFormField>
          <div class="flex flex-wrap gap-2">
            <UButton v-for="k in kinds" :key="k.value" size="sm" :color="adj.kind === k.value ? 'primary' : 'neutral'" :variant="adj.kind === k.value ? 'solid' : 'outline'" :label="k.label" @click="adj.kind = k.value" />
          </div>
          <UFormField label="المبلغ (ج)" required>
            <UInput v-model="adj.amount" type="number" min="0" step="any" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField v-if="adj.kind === 'advance'" label="اتصرفت منين">
            <USelect v-model="adj.from" :items="froms" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظة">
            <UInput v-model="adj.note" maxlength="200" class="w-full" />
          </UFormField>
          <UAlert v-if="adjError" color="error" variant="subtle" :title="adjError" />
          <UButton type="submit" block label="سجّل" :loading="adjSaving" :disabled="!adj.user_id || !adj.amount" />
        </form>
        <div v-if="openAdjustments.length" class="mt-4 space-y-1 border-t border-(--ui-border) pt-3 text-sm">
          <p class="font-bold">
            لسه ما نزلتش في مرتب
          </p>
          <div v-for="a in openAdjustments" :key="a.id" class="flex items-center justify-between gap-2">
            <span>{{ employees.find(e => e.user_id === a.user_id)?.name }} · {{ a.kind_label }} <span class="num">{{ formatMoney(a.amount) }}</span><template v-if="a.note"> · {{ a.note }}</template></span>
            <UButton v-if="a.kind !== 'advance'" size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${a.kind_label}`" @click="removeAdjustment(a.id)" />
          </div>
        </div>
      </template>
    </UModal>

    <!-- The tickets behind a commission -->
    <USlideover v-model:open="commissionOpen" :title="`عمولة ${commissionOf?.name ?? ''}`">
      <template #body>
        <ul class="divide-y divide-(--ui-border) text-sm">
          <li v-for="l in commissionLines" :key="l.ticket_id + l.amount" class="flex items-center justify-between gap-2 py-2">
            <span>
              <NuxtLink :to="`/repairs/${l.ticket_id}`" class="num font-bold text-primary">{{ l.reference }}</NuxtLink> · {{ l.device }}
              <span class="block text-xs text-(--ui-text-muted)">{{ l.rule }}</span>
            </span>
            <span class="num font-bold" :class="l.amount < 0 ? 'text-error' : ''">{{ formatMoney(l.amount) }}</span>
          </li>
        </ul>
      </template>
    </USlideover>
  </div>
</template>

<script setup lang="ts">
import type { PayrollAdjustment, PayrollPeriod, PayrollRow } from '~/types/api'

definePageMeta({ permission: 'payroll.manage' })

const api = useApi()
const toast = useToast()

const cycles = [{ label: 'شهري', value: 'monthly' }, { label: 'أسبوعي', value: 'weekly' }] as { label: string, value: 'monthly' | 'weekly' }[]
const kinds = [{ label: 'سلفة', value: 'advance' }, { label: 'مكافأة', value: 'bonus' }, { label: 'خصم', value: 'deduction' }] as const
const froms = [{ label: 'من الدرج', value: 'drawer' }, { label: 'من الخزنة', value: 'safe' }, { label: 'تحويل بنكي', value: 'bank' }]
const fromLabel = (f: string | null) => froms.find(x => x.value === f)?.label ?? ''

const cycle = ref<'monthly' | 'weekly'>('monthly')
const date = ref('')

interface SheetTotals { base_salary: number, commission: number, advances: number, net: number, unpaid: number }
const { data, refresh } = await useAsyncData('payroll-sheet', () => api<{ data: PayrollRow[], meta: { period: PayrollPeriod, totals: SheetTotals } }>('/payroll/sheet', {
  query: { cycle: cycle.value, date: date.value || undefined },
}), { watch: [cycle, date] })
const rows = computed(() => data.value?.data ?? [])
const period = computed(() => data.value?.meta.period)
const totals = computed(() => data.value?.meta.totals)
const openRows = computed(() => rows.value.filter(r => !r.closed))

// Each employee's pay.
interface EmployeeRow { user_id: string, name: string, pay_cycle: 'monthly' | 'weekly', base_salary: number, active: boolean, input: string }
const employeesOpen = ref(false)
const employees = ref<EmployeeRow[]>([])
async function loadEmployees() {
  const res = await api<{ data: Omit<EmployeeRow, 'input'>[] }>('/payroll/employees')
  employees.value = res.data.map(e => ({ ...e, input: e.base_salary ? String(e.base_salary / 100) : '' }))
}
await loadEmployees()
async function saveEmployee(e: EmployeeRow) {
  try {
    await api(`/payroll/employees/${e.user_id}`, { method: 'PUT', body: { pay_cycle: e.pay_cycle, base_salary: toPiasters(e.input) ?? 0, active: e.active } })
    toast.add({ color: 'success', title: `اتحفظ مرتب ${e.name}` })
    await refresh()
  }
  catch (err) {
    toast.add({ color: 'error', title: apiErrorMessage(err) })
  }
}

// Advances, bonuses, deductions.
const adjOpen = ref(false)
const adj = reactive({ user_id: '', kind: 'advance' as PayrollAdjustment['kind'], amount: '', from: 'drawer', note: '' })
const adjSaving = ref(false)
const adjError = ref<string | null>(null)
const openAdjustments = ref<PayrollAdjustment[]>([])
async function openAdjustment() {
  Object.assign(adj, { user_id: '', kind: 'advance', amount: '', from: 'drawer', note: '' })
  adjError.value = null
  adjOpen.value = true
  openAdjustments.value = (await api<{ data: PayrollAdjustment[] }>('/payroll/adjustments', { query: { open: 1 } })).data
}
async function saveAdjustment() {
  adjSaving.value = true
  adjError.value = null
  try {
    await api('/payroll/adjustments', { method: 'POST', body: { user_id: adj.user_id, kind: adj.kind, amount: toPiasters(adj.amount) ?? 0, from: adj.kind === 'advance' ? adj.from : null, note: adj.note.trim() || null } })
    adjOpen.value = false
    toast.add({ color: 'success', title: 'اتسجلت' })
    await refresh()
  }
  catch (e) {
    adjError.value = apiErrorMessage(e)
  }
  finally {
    adjSaving.value = false
  }
}
async function removeAdjustment(id: string) {
  try {
    await api(`/payroll/adjustments/${id}`, { method: 'DELETE' })
    openAdjustments.value = openAdjustments.value.filter(a => a.id !== id)
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

// Closing and paying.
const closing = ref(false)
async function closePeriod() {
  if (!period.value || !window.confirm(`تقفل رواتب ${period.value.label}؟ الأرقام هتتثبت.`)) return
  closing.value = true
  try {
    data.value = await api('/payroll/close', { method: 'POST', body: { cycle: cycle.value, date: period.value.starts_on } })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    closing.value = false
  }
}
function payItems(r: PayrollRow) {
  return froms.map(f => ({ label: f.label, onSelect: () => pay(r, f.value) }))
}
async function pay(r: PayrollRow, from: string) {
  try {
    await api(`/payroll/payslips/${r.id}/pay`, { method: 'POST', body: { from } })
    toast.add({ color: 'success', title: `اتصرف مرتب ${r.name}` })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

// The tickets behind a commission.
interface CommissionLine { ticket_id: string, reference: string, device: string, rule: string | null, amount: number }
const commissionOpen = ref(false)
const commissionOf = ref<PayrollRow | null>(null)
const commissionLines = ref<CommissionLine[]>([])
async function showCommission(r: PayrollRow) {
  commissionOf.value = r
  commissionOpen.value = true
  commissionLines.value = (await api<{ data: CommissionLine[] }>('/payroll/commissions', { query: { user_id: r.user_id, cycle: cycle.value, date: period.value?.starts_on } })).data
}
</script>

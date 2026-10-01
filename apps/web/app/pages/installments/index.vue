<template>
  <div class="space-y-6">
    <PageHeader title="التقسيط" description="الأقساط اللي عليها الدور، وخطط التقسيط بتاعة العملاء.">
      <UButton v-if="canPlan" to="/installments/new" icon="i-lucide-plus" label="تقسيط جديد" />
    </PageHeader>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <UCard v-for="s in stats" :key="s.label" :ui="{ body: 'p-3 sm:p-4' }">
        <p class="text-xs text-(--ui-text-muted)">
          {{ s.label }}
        </p>
        <p class="mt-1 text-xl font-extrabold num" :class="s.class">
          {{ s.value }}
        </p>
        <p v-if="s.hint" class="text-xs text-(--ui-text-muted)">
          {{ s.hint }}
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

    <template v-if="tab === 'due'">
      <div class="flex flex-wrap gap-2">
        <UButton
          v-for="w in whens"
          :key="w.key"
          size="xs"
          :color="when === w.key ? 'primary' : 'neutral'"
          :variant="when === w.key ? 'soft' : 'ghost'"
          :aria-pressed="when === w.key"
          :label="w.label"
          @click="when = w.key"
        />
      </div>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  العميل
                </th>
                <th class="p-3 text-start font-bold">
                  الميعاد
                </th>
                <th class="p-3 text-end font-bold">
                  القسط
                </th>
                <th class="hidden p-3 text-end font-bold md:table-cell">
                  الباقي من التقسيط
                </th>
                <th class="p-3 text-end font-bold">
                  <span class="sr-only">إجراءات</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in dueItems" :key="d.id" class="border-t border-(--ui-border)">
                <td class="p-3">
                  <NuxtLink :to="`/installments/${d.plan_id}`" class="font-bold hover:underline">
                    {{ d.customer_name }}
                  </NuxtLink>
                  <p class="text-xs text-(--ui-text-muted)">
                    <span class="num">{{ d.plan_reference }}</span> · قسط <span class="num">{{ d.seq }}</span>
                    <span v-if="d.customer_phone" class="hidden sm:inline">
                      · <a :href="`tel:${d.customer_phone}`" class="num hover:text-primary" dir="ltr">{{ localPhone(d.customer_phone) }}</a>
                    </span>
                  </p>
                </td>
                <td class="whitespace-nowrap p-3">
                  <span class="num">{{ formatDate(d.due_on) }}</span>
                  <p class="text-xs font-bold" :class="d.days_late ? 'text-(--ui-error)' : 'text-(--ui-text-muted)'">
                    {{ dueLabel(d) }}
                  </p>
                </td>
                <td class="p-3 text-end font-bold num">
                  {{ formatMoney(d.remaining) }}
                  <p v-if="d.paid" class="text-xs font-normal text-(--ui-text-muted)">
                    من <span class="num">{{ formatMoney(d.amount) }}</span>
                  </p>
                </td>
                <td class="hidden p-3 text-end num md:table-cell">
                  {{ formatMoney(d.plan_remaining) }}
                </td>
                <td class="p-3">
                  <div class="flex justify-end gap-1">
                    <UButton
                      v-if="reminders && d.customer_phone"
                      size="xs"
                      color="neutral"
                      variant="ghost"
                      icon="i-lucide-message-circle"
                      square
                      :aria-label="`فكّر ${d.customer_name} على واتساب`"
                      @click="remind(d)"
                    />
                    <UButton size="xs" icon="i-lucide-banknote" label="حصّل" @click="collect({ id: d.plan_id, reference: d.plan_reference, customer_name: d.customer_name, remaining: d.plan_remaining, amount: d.remaining })" />
                  </div>
                </td>
              </tr>
              <tr v-if="!dueItems.length && dueStatus !== 'pending'">
                <td colspan="5" class="p-10 text-center text-(--ui-text-muted)">
                  {{ when === 'late' ? 'مفيش أقساط متأخرة 👌' : 'مفيش أقساط عليها الدور في الفترة دي.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
    </template>

    <template v-else>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_12rem]">
        <UInput v-model="q" icon="i-lucide-search" placeholder="اسم العميل، الموبايل، أو رقم INS…" class="w-full" />
        <USelect v-model="status" :items="statusItems" class="w-full" aria-label="الحالة" />
      </div>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  العميل
                </th>
                <th class="p-3 text-end font-bold">
                  الإجمالي
                </th>
                <th class="p-3 text-end font-bold">
                  الباقي
                </th>
                <th class="hidden p-3 text-start font-bold md:table-cell">
                  القسط الجاي
                </th>
                <th class="hidden p-3 text-start font-bold sm:table-cell">
                  الحالة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in plans" :key="p.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/installments/${p.id}`)">
                <td class="p-3">
                  <NuxtLink :to="`/installments/${p.id}`" class="font-bold hover:underline" @click.stop>
                    {{ p.customer_name }}
                  </NuxtLink>
                  <p class="text-xs text-(--ui-text-muted)">
                    <span class="num">{{ p.reference }}</span> · <span class="num">{{ p.paid_count }}/{{ p.count }}</span> قسط
                    <template v-if="p.sale_reference">
                      · <span class="num">{{ p.sale_reference }}</span>
                    </template>
                  </p>
                </td>
                <td class="p-3 text-end num">
                  {{ formatMoney(p.total) }}
                </td>
                <td class="p-3 text-end font-bold num">
                  {{ formatMoney(p.status === 'active' ? p.remaining : 0) }}
                </td>
                <td class="hidden p-3 md:table-cell">
                  <template v-if="p.status === 'active' && p.next_due">
                    <span class="num">{{ formatMoney(p.next_due.remaining) }}</span> · <span class="num">{{ formatDate(p.next_due.due_on) }}</span>
                    <p class="text-xs font-bold" :class="p.next_due.days_late ? 'text-(--ui-error)' : 'text-(--ui-text-muted)'">
                      {{ dueLabel(p.next_due) }}
                    </p>
                  </template>
                  <span v-else class="text-(--ui-text-muted)">—</span>
                </td>
                <td class="hidden p-3 sm:table-cell">
                  <UBadge :color="installmentStatusColor(p.status)" variant="subtle">
                    {{ p.status_label }}
                  </UBadge>
                  <UBadge v-if="p.status === 'active' && p.late_count" color="error" variant="subtle" class="ms-1">
                    متأخر <span class="num">{{ p.late_count }}</span>
                  </UBadge>
                </td>
              </tr>
              <tr v-if="!plans.length && plansStatus !== 'pending'">
                <td colspan="5" class="p-10 text-center text-(--ui-text-muted)">
                  {{ debouncedQ ? 'مفيش تقسيط بالبحث ده.' : status === 'active' ? 'مفيش خطط تقسيط شغّالة.' : 'مفيش خطط هنا.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
      <div v-if="(plansData?.meta.last_page ?? 1) > 1" class="flex justify-center">
        <UPagination v-model:page="page" :total="plansData?.meta.total ?? 0" :items-per-page="30" />
      </div>
    </template>

    <InstallmentsCollectModal v-model:open="collectOpen" :target="collectTarget" @saved="reload" />
  </div>
</template>

<script setup lang="ts">
import type { InstallmentDue, InstallmentPlan, InstallmentSummary, Paginated } from '~/types/api'

definePageMeta({ module: 'installments', permission: 'installments.collect' })

const api = useApi()
const store = useSessionStore()
const messages = useMessages()
const canPlan = computed(() => store.can('installments.manage'))
const reminders = computed(() => store.hasFeature('installments.reminders'))

const tabs = [
  { key: 'due' as const, label: 'عليها الدور', icon: 'i-lucide-calendar-clock' },
  { key: 'plans' as const, label: 'الخطط', icon: 'i-lucide-list' },
]
const tab = ref<'due' | 'plans'>('due')

const whens = [
  { key: 'late' as const, label: 'المتأخر' },
  { key: 'today' as const, label: 'لحد النهارده' },
  { key: 'week' as const, label: 'لحد آخر الأسبوع' },
  { key: 'month' as const, label: 'الشهر الجاي' },
]
const when = ref<'late' | 'today' | 'week' | 'month'>('week')

const statusItems = [
  { label: 'الشغّالة', value: 'active' },
  { label: 'المتأخرة', value: 'late' },
  { label: 'اللي خلصت', value: 'completed' },
  { label: 'اللي اتلغت', value: 'cancelled' },
  { label: 'الكل', value: 'all' },
]
const status = ref('active')
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
watch([status, debouncedQ], () => {
  page.value = 1
})

const { data: dueData, status: dueStatus, refresh: refreshDue } = await useAsyncData('installments-due', () => api<{ data: InstallmentDue[], meta: { summary: InstallmentSummary } }>('/installments/due', { query: { when: when.value } }), { watch: [when] })
const planQuery = computed(() => ({ status: status.value, q: debouncedQ.value || undefined, page: page.value }))
const { data: plansData, status: plansStatus, refresh: refreshPlans } = await useAsyncData(
  'installments-plans',
  () => api<Paginated<InstallmentPlan> & { meta: { summary: InstallmentSummary } }>('/installments', { query: planQuery.value }),
  { watch: [planQuery] },
)
const dueItems = computed(() => dueData.value?.data ?? [])
const plans = computed(() => plansData.value?.data ?? [])

const stats = computed(() => {
  const s = dueData.value?.meta.summary ?? plansData.value?.meta.summary
  return [
    { label: 'الباقي عند العملاء', value: formatMoney(s?.outstanding), hint: s ? `${s.active} تقسيط شغّال` : null, class: '' },
    { label: 'متأخر', value: formatMoney(s?.late_amount), hint: s?.late_plans ? `على ${s.late_plans} تقسيط` : null, class: s?.late_amount ? 'text-(--ui-error)' : '' },
    { label: 'عليه الدور النهارده', value: formatMoney(s?.due_today), hint: null, class: '' },
    { label: 'اتحصّل الشهر ده', value: formatMoney(s?.collected_month), hint: null, class: '' },
  ]
})

const collectOpen = ref(false)
const collectTarget = ref<{ id: string, reference: string, customer_name: string, remaining: number, amount: number } | null>(null)
function collect(target: NonNullable<typeof collectTarget.value>) {
  collectTarget.value = target
  collectOpen.value = true
}

function remind(d: InstallmentDue) {
  messages.sendInstallmentReminder({ id: d.plan_id, reference: d.plan_reference, customer_name: d.customer_name, customer_phone: d.customer_phone, remaining: d.plan_remaining }, d)
}

async function reload() {
  await Promise.all([refreshDue(), refreshPlans()])
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="الضمانات" description="ضمان كل قطعة وكل مصنعية اتسلّمت من الفرع ده، ولحد إمتى." class="flex-1" />
      <UInput v-model="q" icon="i-lucide-search" placeholder="رقم التذكرة أو التليفون أو الـ IMEI" class="w-full sm:w-80" />
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          أجهزة في الضمان
        </p>
        <p class="num text-2xl font-extrabold">
          {{ summary?.active ?? '—' }}
        </p>
      </UCard>
      <UCard :class="summary?.soon ? 'ring-warning' : ''">
        <p class="text-sm text-(--ui-text-muted)">
          بتخلص خلال أسبوع
        </p>
        <p class="num text-2xl font-extrabold" :class="summary?.soon ? 'text-warning' : ''">
          {{ summary?.soon ?? '—' }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          رجعت في الضمان (الشهر ده)
        </p>
        <p class="num text-2xl font-extrabold">
          {{ summary?.claims_month ?? '—' }}
        </p>
      </UCard>
      <UCard v-if="store.can('products.view_cost')">
        <p class="text-sm text-(--ui-text-muted)">
          تكلفة الرجوع (الشهر ده)
        </p>
        <p class="num text-2xl font-extrabold">
          {{ summary ? formatMoney(summary.claims_cost_month) : '—' }}
        </p>
      </UCard>
    </div>

    <div class="flex flex-wrap gap-2">
      <UButton v-for="s in statuses" :key="s.value" size="sm" :color="status === s.value ? 'primary' : 'neutral'" :variant="status === s.value ? 'solid' : 'outline'" :label="s.label" @click="status = s.value" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                التذكرة
              </th>
              <th class="p-3 text-start font-bold">
                العميل
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                الجهاز
              </th>
              <th class="p-3 text-start font-bold">
                على إيه
              </th>
              <th class="p-3 text-start font-bold">
                لحد
              </th>
              <th class="p-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-(--ui-border)">
            <tr v-for="w in rows" :key="w.id">
              <td class="p-3">
                <NuxtLink :to="`/repairs/${w.ticket_id}`" class="num font-bold text-primary">{{ w.reference }}</NuxtLink>
              </td>
              <td class="p-3">
                {{ w.customer_name }}
                <span v-if="w.customer_phone" class="num block text-xs text-(--ui-text-muted)" dir="ltr">{{ localPhone(w.customer_phone) }}</span>
              </td>
              <td class="hidden p-3 md:table-cell">
                {{ w.device_name }}
              </td>
              <td class="p-3">
                {{ w.name }} <span class="text-xs text-(--ui-text-muted)">· {{ dayCount(w.days) }}</span>
                <span v-if="w.void_reason" class="block text-xs text-error">اتلغى: {{ w.void_reason }}</span>
              </td>
              <td class="p-3">
                <span class="num">{{ formatDate(w.ends_at) }}</span>
                <UBadge v-if="w.came_back_ticket_id" color="info" variant="subtle" class="ms-1">
                  رجع
                </UBadge>
                <UBadge v-else-if="w.state === 'active' && daysLeft(w.ends_at) <= 7" color="warning" variant="subtle" class="ms-1">
                  باقي {{ dayCount(daysLeft(w.ends_at)) }}
                </UBadge>
              </td>
              <td class="p-3 text-end">
                <UButton v-if="w.state === 'active' && w.customer_phone && canSend" size="xs" color="neutral" variant="ghost" icon="i-lucide-message-circle" :aria-label="`واتساب لـ ${w.customer_name}`" @click="messages.sendWarrantyEnding(w)" />
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="6" class="p-6 text-center text-(--ui-text-muted)">
                {{ pending ? 'بنحمّل…' : 'مفيش ضمانات هنا.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
    <div v-if="lastPage > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="total" :items-per-page="30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RepairWarrantyRow } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.view', feature: 'repairs.warranty' })

const api = useApi()
const store = useSessionStore()
const messages = useMessages()
const canSend = computed(() => store.can('messages.send') && store.hasFeature('repairs.warranty'))

const statuses = [
  { value: 'active', label: 'شغالة' },
  { value: 'soon', label: 'بتخلص قريب' },
  { value: 'expired', label: 'خلصت' },
  { value: 'claimed', label: 'رجعت في الضمان' },
  { value: 'voided', label: 'اتلغت' },
]
const status = ref('active')
const q = ref('')
const page = ref(1)
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
watch([status, debouncedQ], () => {
  page.value = 1
})

interface WarrantySummary { active: number, soon: number, claims_month: number, claims_cost_month: number }
const { data, pending } = await useAsyncData('repair-warranties', () => api<{ data: RepairWarrantyRow[], meta: { page: number, last_page: number, total: number, summary: WarrantySummary } }>('/repairs/warranties', {
  query: { status: status.value, q: debouncedQ.value || undefined, page: page.value },
}), { watch: [status, debouncedQ, page] })
const rows = computed(() => data.value?.data ?? [])
const summary = computed(() => data.value?.meta.summary)
const lastPage = computed(() => data.value?.meta.last_page ?? 1)
const total = computed(() => data.value?.meta.total ?? 0)

const daysLeft = (iso: string) => Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000))
</script>

<template>
  <div class="space-y-3">
    <p v-if="pending && !devices.length" class="py-6 text-center text-sm text-(--ui-text-muted)">
      بنجيب الأجهزة…
    </p>
    <p v-else-if="!devices.length" class="py-6 text-center text-sm text-(--ui-text-muted)">
      لسه مفيش أجهزة دخلت الصيانة للعميل ده.
    </p>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <UCard v-for="d in devices" :key="d.id" :ui="{ body: 'space-y-3' }">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-bold">
              {{ d.name }}<span v-if="d.color" class="font-normal text-(--ui-text-muted)"> — {{ d.color }}</span>
            </p>
            <p v-if="d.imei" class="num text-xs text-(--ui-text-muted)" dir="ltr">
              IMEI {{ d.imei }}
            </p>
          </div>
          <UBadge v-if="d.open_ticket_id" color="warning" variant="subtle">
            جوه المحل
          </UBadge>
          <UBadge v-else-if="d.warranty_until" color="success" variant="subtle">
            ضمان لحد {{ formatDate(d.warranty_until) }}
          </UBadge>
        </div>
        <p v-if="d.notes" class="text-sm text-(--ui-text-muted)">
          {{ d.notes }}
        </p>
        <ul class="space-y-1.5">
          <li v-for="t in d.tickets.slice(0, open[d.id] ? undefined : 3)" :key="t.id">
            <component
              :is="t.branch_id === store.currentBranch?.id ? NuxtLink : 'div'"
              :to="`/repairs/${t.id}`"
              class="flex items-center justify-between gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2 text-sm"
              :class="t.branch_id === store.currentBranch?.id ? 'hover:bg-(--ui-bg-accented)' : ''"
            >
              <span class="min-w-0">
                <span class="num font-bold">{{ t.reference }}</span>
                · {{ t.faults.join('، ') || t.note || '—' }}
                <span class="block text-xs text-(--ui-text-muted)">
                  {{ formatDate(t.received_at) }}<template v-if="t.technician_name"> · {{ t.technician_name }}</template><template v-if="t.total"> · <span class="num">{{ formatMoney(t.total) }}</span></template><template v-if="t.branch_id !== store.currentBranch?.id && t.branch_name"> · فرع {{ t.branch_name }}</template>
                </span>
              </span>
              <span class="shrink-0 text-xs text-(--ui-text-muted)">{{ t.warranty_return ? 'رجوع ضمان' : t.status_label }}</span>
            </component>
          </li>
        </ul>
        <UButton v-if="d.tickets.length > 3 && !open[d.id]" size="xs" color="neutral" variant="ghost" :label="`كل الزيارات (${d.tickets.length})`" @click="open[d.id] = true" />
        <div v-if="canReceive" class="flex flex-wrap gap-2">
          <UButton v-if="d.warranty_ticket_id && warrantyOn && !d.open_ticket_id" size="sm" icon="i-lucide-shield-check" label="رجوع في الضمان" :loading="returning === d.id" @click="warrantyReturn(d)" />
          <UButton v-if="!d.open_ticket_id" size="sm" color="neutral" variant="outline" icon="i-lucide-wrench" label="استلم الجهاز ده تاني" :to="`/repairs/quick?customer=${customerId}&device=${d.id}`" />
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { NuxtLink } from '#components'
import type { RepairDevice, RepairTicket } from '~/types/api'

/** The customer's devices with their tickets: the customer page's «الأجهزة والصيانة». */
const props = defineProps<{ customerId: string }>()

const store = useSessionStore()
const api = useApi()
const toast = useToast()
const canReceive = computed(() => store.can('repairs.create'))
const warrantyOn = computed(() => store.hasFeature('repairs.warranty'))
const open = reactive<Record<string, boolean>>({})

const { data, pending } = await useAsyncData(`customer-devices-${props.customerId}`, () => api<{ data: RepairDevice[] }>('/repairs/devices', { query: { customer_id: props.customerId } }))
const devices = computed(() => data.value?.data ?? [])

const returning = ref<string | null>(null)
async function warrantyReturn(d: RepairDevice) {
  returning.value = d.id
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${d.warranty_ticket_id}/warranty`, { method: 'POST' })
    await navigateTo(`/repairs/${res.data.id}`)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    returning.value = null
  }
}
</script>

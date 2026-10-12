<template>
  <div class="space-y-6">
    <PageHeader title="طلبات القطع" description="الفنيين بيطلبوا القطع من هنا. اصرف القطعة فتتخصم من مخزون الفرع وتتسجل على تذكرة الجهاز بسعر الفني، أو ارفضها وقول السبب." />

    <div class="flex flex-wrap gap-1.5">
      <UButton
        v-for="t in tabs"
        :key="t.value"
        size="sm"
        :color="tab === t.value ? 'primary' : 'neutral'"
        :variant="tab === t.value ? 'soft' : 'ghost'"
        @click="tab = t.value"
      >
        {{ t.label }}
        <span v-if="t.value === 'requested' && waiting" class="num text-xs opacity-70">{{ waiting }}</span>
      </UButton>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <USkeleton v-if="rows === null" class="m-4 h-40" />
      <div v-else-if="!rows.length" class="space-y-2 p-8 text-center">
        <UIcon name="i-lucide-package-search" class="size-8 text-(--ui-text-dimmed)" />
        <p class="text-sm text-(--ui-text-muted)">
          {{ tab === 'requested' ? 'مفيش طلبات مستنية.' : 'لسه مفيش طلبات اتقفلت.' }}
        </p>
      </div>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="r in rows" :key="r.id" class="space-y-3 px-4 py-3">
          <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1 basis-64">
              <p class="font-bold">
                <span class="num">{{ r.qty }}</span> × {{ r.name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                <ULink v-if="r.ticket && store.can('repairs.view')" :to="`/repairs/${r.ticket.id}`" class="num underline">{{ r.ticket.reference }}</ULink>
                <span v-else-if="r.ticket" class="num">{{ r.ticket.reference }}</span>
                · {{ r.ticket?.device_name }} · طلبها {{ r.requested_by_name }}
                · <span class="num">{{ formatDate(r.created_at, true) }}</span>
              </p>
              <p v-if="r.note" class="mt-1 text-sm">
                «{{ r.note }}»
              </p>
              <p v-if="r.status !== 'requested'" class="mt-1 text-xs text-(--ui-text-muted)">
                {{ r.status_label }}<template v-if="r.handled_by_name"> · {{ r.handled_by_name }}</template><template v-if="r.reason"> · {{ r.reason }}</template>
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              <UBadge
                v-if="r.stock !== undefined && r.status === 'requested'"
                :color="r.stock < r.qty ? 'error' : 'neutral'"
                variant="subtle"
              >
                في الفرع <span class="num">{{ r.stock }}</span>
              </UBadge>
              <template v-if="r.status === 'requested'">
                <UButton size="sm" icon="i-lucide-package-check" label="اصرف" :loading="busy === r.id" @click="hand(r)" />
                <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-x" label="ارفض" @click="openReject(r)" />
              </template>
              <UBadge v-else :color="r.status === 'handed' ? 'success' : r.status === 'rejected' ? 'error' : 'neutral'" variant="subtle" :label="r.status_label" />
            </div>
          </div>
          <div v-if="serialFor === r.id" class="space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <p class="text-sm font-bold">
              IMEI / سيريال القطع اللي هتصرفها
            </p>
            <InventorySerialsInput v-model="serials" :required="r.qty" size="sm" />
            <div class="flex justify-end gap-2">
              <UButton size="sm" color="neutral" variant="ghost" label="إلغاء" @click="serialFor = null" />
              <UButton size="sm" icon="i-lucide-package-check" label="اصرف" :disabled="serials.length !== r.qty" :loading="busy === r.id" @click="hand(r, serials)" />
            </div>
          </div>
        </li>
      </ul>
    </UCard>

    <UModal v-model:open="rejectOpen" :title="rejecting ? `ترفض «${rejecting.name}»؟` : 'رفض الطلب'">
      <template #body>
        <div class="space-y-3">
          <div class="flex flex-wrap gap-1.5">
            <UButton v-for="c in reasons" :key="c" size="xs" color="neutral" variant="outline" :label="c" @click="reason = c" />
          </div>
          <UFormField label="السبب (الفني هيشوفه على التذكرة)">
            <UInput v-model="reason" maxlength="190" class="w-full" autofocus />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2">
          <UButton color="error" label="ارفض" :disabled="!reason.trim()" :loading="busy === rejecting?.id" @click="reject" />
          <UButton color="neutral" variant="ghost" label="رجوع" @click="rejectOpen = false" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { RepairPartRequest } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.hand_parts', feature: 'repairs.part_requests' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()

const tabs = [
  { value: 'requested', label: 'مستنية' },
  { value: 'done', label: 'اتقفلت' },
] as const
const tab = ref<'requested' | 'done'>('requested')
const rows = ref<RepairPartRequest[] | null>(null)
const waiting = ref(0)

async function load() {
  const res = await api<{ data: RepairPartRequest[], meta: { waiting: number } }>('/repairs/part-requests', { query: { status: tab.value } })
  rows.value = res.data
  waiting.value = res.meta.waiting
}
watch(tab, () => {
  rows.value = null
  load()
})

// New requests show up on their own while the screen is open.
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  load()
  timer = setInterval(() => {
    if (document.visibilityState === 'visible' && busy.value === null && serialFor.value === null) load().catch(() => {})
  }, 20000)
})
onBeforeUnmount(() => clearInterval(timer))

const busy = ref<string | null>(null)
const serialFor = ref<string | null>(null)
const serials = ref<string[]>([])

async function hand(r: RepairPartRequest, given?: string[]) {
  if (r.track_serial && !given) {
    serialFor.value = r.id
    serials.value = []
    return
  }
  busy.value = r.id
  try {
    await api(`/repairs/part-requests/${r.id}/hand`, { method: 'POST', body: { serials: given } })
    serialFor.value = null
    toast.add({ color: 'success', title: 'اتصرفت', description: `${r.qty} × ${r.name} على ${r.ticket?.reference ?? 'التذكرة'}` })
    await load()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

const reasons = ['خلصانة من المخزن', 'مش موجودة في الفرع ده', 'هتيجي بكرة', 'اتطلبت غلط']
const rejectOpen = ref(false)
const rejecting = ref<RepairPartRequest | null>(null)
const reason = ref('')

function openReject(r: RepairPartRequest) {
  rejecting.value = r
  reason.value = ''
  rejectOpen.value = true
}

async function reject() {
  if (!rejecting.value) return
  busy.value = rejecting.value.id
  try {
    await api(`/repairs/part-requests/${rejecting.value.id}/reject`, { method: 'POST', body: { reason: reason.value.trim() } })
    rejectOpen.value = false
    await load()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

<template>
  <UModal :open="!!pending" :dismissible="false" :title="title" :ui="{ content: 'sm:max-w-md' }" @update:open="(v: boolean) => !v && cancel()">
    <template #body>
      <div v-if="pending" class="space-y-4">
        <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
          <p class="text-xs text-(--ui-text-muted)">
            {{ pending.needed.kind_label }}
          </p>
          <p class="font-bold">
            {{ pending.needed.summary }}
          </p>
        </div>

        <!-- Choose how. -->
        <template v-if="mode === 'choose'">
          <p class="text-sm text-(--ui-text-muted)">
            العملية دي أكبر من الحد اللي حاطه صاحب المحل. محتاجة موافقته من موبايله، أو PIN مدير موجود في المحل.
          </p>
          <div class="grid gap-2">
            <UButton size="lg" icon="i-lucide-send" label="ابعت لصاحب المحل" :loading="sending" block @click="send" />
            <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-key-round" label="PIN مدير" block @click="mode = 'pin'" />
          </div>
        </template>

        <!-- Waiting for the answer (live, or polled while the WebSocket is down). -->
        <div v-else-if="mode === 'waiting' && request" class="space-y-3 text-center">
          <template v-if="request.status === 'pending'">
            <UIcon name="i-lucide-loader-circle" class="size-10 animate-spin text-primary" />
            <p class="font-bold">
              مستنيين الرد…
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              الطلب وصل لصاحب المحل والمديرين. سيب الشاشة مفتوحة، أول ما يوافقوا العملية هتكمل لوحدها.
            </p>
            <UButton color="neutral" variant="link" size="sm" label="أو PIN مدير" @click="mode = 'pin'" />
          </template>
          <template v-else-if="request.status === 'denied'">
            <UIcon name="i-lucide-circle-x" class="size-10 text-(--ui-error)" />
            <p class="font-bold">
              {{ request.decided_by_name ? `${request.decided_by_name} رفض` : 'اترفض' }}
            </p>
            <p v-if="request.reason" class="text-sm">
              «{{ request.reason }}»
            </p>
          </template>
          <template v-else-if="request.status === 'expired'">
            <UIcon name="i-lucide-clock-alert" class="size-10 text-(--ui-warning)" />
            <p class="font-bold">
              محدش رد في الوقت
            </p>
            <UButton label="ابعت تاني" :loading="sending" @click="send" />
          </template>
        </div>

        <!-- A manager at the counter. -->
        <form v-else-if="mode === 'pin'" class="space-y-3" @submit.prevent="usePin">
          <UFormField label="PIN المدير" hint="4 لـ 6 أرقام">
            <UInput v-model="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" dir="ltr" class="w-full" size="xl" autofocus />
          </UFormField>
          <UButton type="submit" block size="lg" icon="i-lucide-check" label="موافقة" :loading="sending" :disabled="pin.length < 4" />
          <UButton v-if="!request" color="neutral" variant="link" size="sm" label="رجوع" @click="mode = 'choose'" />
        </form>

        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </div>
    </template>
    <template #footer>
      <div class="flex w-full justify-end">
        <UButton color="neutral" variant="ghost" :label="request?.status === 'denied' ? 'تمام' : 'إلغاء'" @click="cancel" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '~/types/api'

const api = useApi()
const store = useSessionStore()
const realtime = useRealtime()
const { pending, finish } = useApproval()

const mode = ref<'choose' | 'waiting' | 'pin'>('choose')
const request = ref<ApprovalRequest | null>(null)
const pin = ref('')
const sending = ref(false)
const error = ref<string | null>(null)
const title = computed(() => mode.value === 'pin' ? 'موافقة بالـ PIN' : 'محتاج موافقة')

let stopListening: (() => void) | null = null
let poll: ReturnType<typeof setInterval> | undefined

function reset() {
  mode.value = 'choose'
  request.value = null
  pin.value = ''
  error.value = null
  stopWaiting()
}

function stopWaiting() {
  stopListening?.()
  stopListening = null
  clearInterval(poll)
}

watch(pending, (p) => {
  if (p) {
    reset()
  }
})

function settle(r: ApprovalRequest) {
  request.value = r
  if (r.status === 'approved') {
    stopWaiting()
    finish(r.id)
  }
  else if (r.status !== 'pending') {
    stopWaiting()
  }
}

async function send() {
  if (!pending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const res = await api<{ data: ApprovalRequest }>('/approvals', { method: 'POST', body: { token: pending.value.needed.token } })
    mode.value = 'waiting'
    settle(res.data)
    const id = res.data.id
    stopWaiting()
    stopListening = await realtime.listen<{ approval: ApprovalRequest }>(`users.${store.session?.user.id}`, 'approval.decided', (e) => {
      if (e.approval.id === id) {
        settle(e.approval)
      }
    })
    // The WebSocket is the fast path; this keeps it working without one (every 4 s, or 16 s when live).
    let tick = 0
    poll = setInterval(async () => {
      if (realtime.connected.value && ++tick % 4 !== 0) {
        return
      }
      const r = await api<{ data: ApprovalRequest }>(`/approvals/${id}`).catch(() => null)
      if (r && request.value?.id === id) {
        settle(r.data)
      }
    }, 4000)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    sending.value = false
  }
}

async function usePin() {
  if (!pending.value) {
    return
  }
  sending.value = true
  error.value = null
  try {
    const res = await api<{ data: ApprovalRequest }>('/approvals/pin', { method: 'POST', body: { token: pending.value.needed.token, pin: pin.value } })
    stopWaiting()
    finish(res.data.id)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    pin.value = ''
  }
  finally {
    sending.value = false
  }
}

function cancel() {
  stopWaiting()
  finish(null)
}

onBeforeUnmount(stopWaiting)
</script>

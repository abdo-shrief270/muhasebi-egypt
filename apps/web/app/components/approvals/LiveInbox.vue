<template>
  <span class="hidden" />
</template>

<script setup lang="ts">
import type { ApprovalRequest } from '~/types/api'

/**
 * For whoever may approve (owner, managers): a request arriving while the app is open pops a
 * toast with «وافق» / «ارفض» right away (WebSocket). The push notification covers a closed app.
 */
const store = useSessionStore()
const toast = useToast()
const realtime = useRealtime()
const { decide } = useApprovalDecision()

let stop: (() => void) | null = null
onMounted(async () => {
  if (!store.can('owner_app.approve') || !store.session) {
    return
  }
  stop = await realtime.listen<{ approval: ApprovalRequest }>(`tenants.${store.session.tenant.id}.approvals`, 'approval.requested', ({ approval: a }) => {
    toast.add({
      id: `approval-${a.id}`,
      color: 'warning',
      icon: 'i-lucide-shield-question',
      title: `${a.requested_by_name} محتاج موافقة`,
      description: a.summary,
      duration: 60_000,
      actions: [
        { label: 'وافق', color: 'primary', onClick: () => decide(a, 'approve') },
        { label: 'ارفض', color: 'neutral', variant: 'outline', onClick: () => decide(a, 'deny') },
      ],
    })
  })
})
onBeforeUnmount(() => stop?.())
</script>

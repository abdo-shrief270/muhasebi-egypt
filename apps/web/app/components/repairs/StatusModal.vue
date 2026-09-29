<template>
  <UModal v-model:open="open" title="تغيير الحالة" :description="ticket ? `${ticket.reference} · دلوقتي «${ticket.status_label}»` : undefined">
    <template #body>
      <div v-if="ticket" class="space-y-4">
        <div class="grid gap-2 sm:grid-cols-2">
          <UButton
            v-for="s in ticket.next_statuses"
            :key="s.value"
            :color="to === s.value ? ticketStatusColor(s.value) : 'neutral'"
            :variant="to === s.value ? 'soft' : 'outline'"
            class="justify-center"
            :label="s.label"
            @click="to = s.value"
          />
        </div>
        <UFormField label="ملاحظة" :hint="to === 'rejected' ? 'مثلاً: العميل رفض السعر' : 'اختياري'">
          <UTextarea v-model="note" :rows="2" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </div>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton label="حفظ" :disabled="!to" :loading="saving" @click="save" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { RepairTicket, TicketStatus } from '~/types/api'

const props = defineProps<{ ticket: RepairTicket | null, preset?: TicketStatus | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ changed: [ticket: RepairTicket] }>()

const api = useApi()
const to = ref<TicketStatus | null>(null)
const note = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    to.value = props.preset ?? props.ticket?.next_statuses[0]?.value ?? null
    note.value = ''
    error.value = null
  }
})

async function save() {
  if (!props.ticket || !to.value) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${props.ticket.id}/status`, { method: 'POST', body: { status: to.value, note: note.value || null } })
    open.value = false
    emit('changed', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

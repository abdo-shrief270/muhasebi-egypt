<template>
  <UModal v-model:open="open" title="إلغاء العملية" :description="t ? `${t.type_label} ${formatMoney(t.amount)} — ${t.account_name ?? ''} (${serviceReference(t.number)})` : undefined">
    <template #body>
      <form id="reverse-form" class="space-y-4" @submit.prevent="save">
        <p class="text-sm text-(--ui-text-muted)">
          العملية مش بتتمسح: بيتسجّل إلغاء بيرجّع رصيد المحفظة والفلوس في درجك زي ما كانوا.
          <template v-if="t && t.cash !== 0">
            <br>
            <span class="font-bold text-(--ui-text)">{{ t.cash > 0 ? 'هيطلع من الدرج' : 'هيرجع للدرج' }} <span class="num">{{ formatMoney(Math.abs(t.cash)) }}</span>.</span>
          </template>
        </p>
        <UFormField label="السبب" required>
          <UInput v-model="reason" class="w-full" placeholder="رقم غلط، المبلغ غلط، التحويل ما وصلش…" autofocus />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="رجوع" @click="open = false" />
        <UButton type="submit" form="reverse-form" color="error" icon="i-lucide-undo-2" label="ألغي العملية" :loading="saving" :disabled="!reason.trim()" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ServiceTransaction } from '~/types/api'

const props = defineProps<{ t: ServiceTransaction | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ reversed: [ServiceTransaction] }>()

const api = useApi()
const toast = useToast()
const reason = ref('')
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    reason.value = ''
    error.value = null
    needsShift.value = false
  }
})

async function save() {
  if (!props.t || !reason.value.trim()) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: ServiceTransaction }>(`/services/transactions/${props.t.id}/reverse`, { method: 'POST', body: { reason: reason.value.trim() } })
    toast.add({ color: 'success', title: 'اتلغت العملية' })
    open.value = false
    emit('reversed', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
  }
  finally {
    saving.value = false
  }
}
</script>

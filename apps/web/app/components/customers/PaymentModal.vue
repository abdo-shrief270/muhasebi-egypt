<template>
  <UModal v-model:open="open" :title="`تحصيل من «${customer?.name ?? ''}»`" :description="customer && customer.balance > 0 ? `عليه ${formatMoney(customer.balance)}` : undefined">
    <template #body>
      <form id="collect-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="المبلغ" hint="بالجنيه" required>
            <UInput v-model="form.amount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField label="طريقة الدفع" required>
            <USelect v-model="form.method" :items="CASH_METHODS" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="ملاحظة">
          <UInput v-model="form.note" class="w-full" />
        </UFormField>
        <p class="text-xs text-(--ui-text-muted)">
          الفلوس بتتسجل في درج ورديتك.
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="collect-form" icon="i-lucide-banknote" label="تسجيل التحصيل" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CashMethod, Customer } from '~/types/api'

const props = defineProps<{ customer: Customer | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const toast = useToast()
const form = reactive<{ amount: string, method: CashMethod, note: string }>({ amount: '', method: 'cash', note: '' })
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { amount: props.customer && props.customer.balance > 0 ? String(props.customer.balance / 100) : '', method: 'cash', note: '' })
    error.value = null
    needsShift.value = false
  }
})

async function save() {
  if (!props.customer) {
    return
  }
  saving.value = true
  error.value = null
  try {
    await api(`/customers/${props.customer.id}/payments`, {
      method: 'POST',
      body: { amount: toPiasters(form.amount), payment_method: form.method, note: form.note || null },
    })
    toast.add({ color: 'success', title: 'اتسجّل التحصيل' })
    open.value = false
    emit('saved')
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

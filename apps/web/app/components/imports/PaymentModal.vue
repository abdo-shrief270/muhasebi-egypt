<template>
  <UModal v-model:open="open" :title="`دفعة لـ ${contactName}`">
    <template #body>
      <form id="import-payment-form" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save">
        <UFormField label="المبلغ (ج)" required :error="errors.amount">
          <UInput v-model="form.amount" type="number" min="0" step="any" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="التاريخ" required>
          <UInput v-model="form.paid_on" type="date" class="w-full" />
        </UFormField>
        <UFormField label="الطريقة" class="sm:col-span-2">
          <USelect v-model="form.method" :items="[...IMPORT_PAYMENT_METHODS]" class="w-full" />
        </UFormField>
        <UFormField v-if="shipments.length" label="للشحنة" hint="اختياري" class="sm:col-span-2">
          <USelect v-model="form.shipment_id" :items="[{ value: 'none', label: 'دفعة على الحساب' }, ...shipments.map(s => ({ value: s.id, label: s.reference }))]" class="w-full" />
        </UFormField>
        <UFormField label="مين استلم">
          <UInput v-model="form.received_by" class="w-full" maxlength="120" />
        </UFormField>
        <UFormField label="رقم العملية">
          <UInput v-model="form.reference" dir="ltr" class="w-full" maxlength="120" />
        </UFormField>
        <UFormField label="صورة الإيصال" class="sm:col-span-2">
          <input type="file" accept="image/*" class="text-sm" @change="pick">
        </UFormField>
        <UFormField label="ملاحظات" class="sm:col-span-2">
          <UInput v-model="form.note" class="w-full" maxlength="500" />
        </UFormField>
      </form>
      <UAlert v-if="error" class="mt-3" color="error" variant="subtle" :title="error" />
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="import-payment-form" label="سجّل الدفعة" :loading="saving" :disabled="!(toPiasters(form.amount) ?? 0)" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ImportPayment } from '~/types/api'

/** Money sent to an import contact (optionally for one of their shipments), with the receipt photo. */
const props = withDefaults(defineProps<{ contactId: string, contactName: string, shipments?: { id: string, reference: string }[], shipmentId?: string | null }>(), { shipments: () => [], shipmentId: null })
const emit = defineEmits<{ saved: [payment: ImportPayment] }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' })
const form = reactive({ amount: '', paid_on: today(), method: 'bank', shipment_id: 'none', received_by: '', reference: '', note: '' })
const proof = ref<Blob | null>(null)
watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { amount: '', paid_on: today(), method: 'bank', shipment_id: props.shipmentId ?? 'none', received_by: '', reference: '', note: '' })
    proof.value = null
    error.value = null
    errors.value = {}
  }
})

async function pick(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  proof.value = file ? await shrinkPhoto(file) : null
}

const saving = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
async function save() {
  saving.value = true
  error.value = null
  const body = new FormData()
  body.append('contact_id', props.contactId)
  body.append('amount', String(toPiasters(form.amount) ?? 0))
  body.append('paid_on', form.paid_on)
  body.append('method', form.method)
  if (form.shipment_id !== 'none') {
    body.append('shipment_id', form.shipment_id)
  }
  for (const key of ['received_by', 'reference', 'note'] as const) {
    if (form[key].trim()) {
      body.append(key, form[key].trim())
    }
  }
  if (proof.value) {
    body.append('proof', proof.value, 'receipt.jpg')
  }
  try {
    const res = await api<{ data: ImportPayment }>('/imports/payments', { method: 'POST', body })
    emit('saved', res.data)
    open.value = false
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    error.value = Object.keys(errors.value).length ? null : apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

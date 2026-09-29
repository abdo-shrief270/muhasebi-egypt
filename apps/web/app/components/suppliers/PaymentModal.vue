<template>
  <UModal v-model:open="open" :title="`دفعة لـ «${supplier?.name ?? ''}»`" :description="supplier ? `عليك له ${formatMoney(supplier.balance)}` : undefined">
    <template #body>
      <form id="payment-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="المبلغ" hint="بالجنيه" required>
            <UInput v-model="form.amount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField label="طريقة الدفع" required>
            <USelect v-model="form.method" :items="methods" class="w-full" />
          </UFormField>
        </div>
        <UCheckbox
          v-if="form.method !== 'bank_transfer'"
          v-model="form.fromDrawer"
          label="من درج الوردية"
          description="اتدفعت من فلوس الدرج — شيلها لو دفعت من الخزنة أو من جيبك"
        />
        <UFormField label="ملاحظة">
          <UInput v-model="form.note" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="payment-form" icon="i-lucide-banknote" label="تسجيل الدفعة" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Supplier } from '~/types/api'

const props = defineProps<{ supplier: Supplier | null, methods: { label: string, value: string }[] }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const toast = useToast()
const form = reactive({ amount: '', method: 'cash', note: '', fromDrawer: true })
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { amount: props.supplier && props.supplier.balance > 0 ? String(props.supplier.balance / 100) : '', method: 'cash', note: '', fromDrawer: true })
    error.value = null
    needsShift.value = false
  }
})

async function save() {
  if (!props.supplier) {
    return
  }
  saving.value = true
  error.value = null
  try {
    await api(`/suppliers/${props.supplier.id}/payments`, {
      method: 'POST',
      body: { amount: toPiasters(form.amount), payment_method: form.method, note: form.note || null, from_drawer: form.fromDrawer },
    })
    toast.add({ color: 'success', title: 'اتسجّلت الدفعة' })
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

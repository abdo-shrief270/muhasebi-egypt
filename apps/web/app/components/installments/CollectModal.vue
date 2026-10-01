<template>
  <UModal v-model:open="open" :title="target ? `تحصيل قسط — ${target.reference}` : ''" :description="target ? `${target.customer_name} · الباقي ${formatMoney(target.remaining)}` : undefined">
    <template #body>
      <form id="installment-collect-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="المبلغ" hint="بالجنيه" required>
            <UInput v-model="form.amount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField label="طريقة الدفع" required>
            <USelect v-model="form.method" :items="CASH_METHODS" class="w-full" />
          </UFormField>
        </div>
        <div v-if="target && suggested !== target.remaining" class="flex flex-wrap gap-2">
          <UButton size="xs" color="neutral" variant="outline" :label="`القسط ${formatMoney(suggested)}`" @click="form.amount = String(suggested / 100)" />
          <UButton size="xs" color="neutral" variant="outline" :label="`الباقي كله ${formatMoney(target.remaining)}`" @click="form.amount = String(target.remaining / 100)" />
        </div>
        <p class="text-xs text-(--ui-text-muted)">
          بيتسجل تحصيل على حساب العميل وبيدخل درج ورديتك، وبيتوزع على الأقساط من الأقدم.
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="installment-collect-form" icon="i-lucide-banknote" label="تسجيل التحصيل" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CashMethod, InstallmentPlan } from '~/types/api'

/** The plan to collect on; `amount` = the installment to suggest (the next / late one). */
const props = defineProps<{ target: { id: string, reference: string, customer_name: string, remaining: number, amount: number } | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [plan: InstallmentPlan] }>()

const api = useApi()
const toast = useToast()
const form = reactive<{ amount: string, method: CashMethod }>({ amount: '', method: 'cash' })
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)
const suggested = computed(() => Math.min(props.target?.amount ?? 0, props.target?.remaining ?? 0))

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { amount: suggested.value ? String(suggested.value / 100) : '', method: 'cash' })
    error.value = null
    needsShift.value = false
  }
})

async function save() {
  if (!props.target) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: InstallmentPlan }>(`/installments/${props.target.id}/payments`, {
      method: 'POST',
      body: { amount: toPiasters(form.amount), method: form.method },
    })
    toast.add({ color: 'success', title: res.data.status === 'completed' ? 'اتسجّل — والتقسيط خلص 🎉' : 'اتسجّل التحصيل' })
    open.value = false
    emit('saved', res.data)
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

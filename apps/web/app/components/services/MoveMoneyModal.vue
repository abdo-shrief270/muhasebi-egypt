<template>
  <UModal v-model:open="open" :title="title" :description="account ? `الرصيد دلوقتي ${formatMoney(account.balance)}` : undefined">
    <template #body>
      <form id="move-money-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4" :class="showPaid ? 'sm:grid-cols-2' : ''">
          <UFormField :label="mode === 'fund' ? 'الرصيد اللي هيدخل' : 'المبلغ'" hint="بالجنيه" required>
            <UInput v-model="amount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField v-if="showPaid" label="اتدفع للموزع" hint="بالجنيه">
            <UInput v-model="paid" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" :placeholder="amount || 'نفس الرصيد'" />
          </UFormField>
        </div>
        <p v-if="showPaid && discount > 0" class="text-sm text-(--ui-text-muted)">
          خصم الموزع <span class="font-bold text-success num">{{ formatMoney(discount) }}</span>
          (<span class="num">{{ discountPct }}%</span>) — ده مكسبك وإنت بتشحن.
        </p>
        <UFormField :label="mode === 'fund' ? 'الفلوس طالعة منين' : 'الفلوس رايحة فين'">
          <URadioGroup v-model="source" :items="sources" orientation="horizontal" />
        </UFormField>
        <UFormField label="ملاحظة">
          <UInput v-model="note" class="w-full" :placeholder="mode === 'fund' ? 'اسم الموزع…' : ''" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="move-money-form" :icon="mode === 'fund' ? 'i-lucide-arrow-down-to-line' : 'i-lucide-arrow-up-from-line'" :label="mode === 'fund' ? 'تمويل' : 'تسييل'" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ServiceAccount } from '~/types/api'

const props = defineProps<{ account: ServiceAccount | null, mode: 'fund' | 'cash_out' }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const toast = useToast()
const amount = ref('')
const paid = ref('')
const source = ref<'drawer' | 'safe'>('drawer')
const note = ref('')
const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)

const title = computed(() => `${props.mode === 'fund' ? 'تمويل' : 'تسييل'} «${props.account?.name ?? ''}»`)
const showPaid = computed(() => props.mode === 'fund' && props.account?.kind === 'airtime')
const sources = computed(() => [
  { value: 'drawer', label: 'درج الوردية', description: props.mode === 'fund' ? 'بيطلع من درجك' : 'بيدخل درجك' },
  { value: 'safe', label: 'الخزنة / برّه الدرج', description: 'مش بيأثر على الوردية' },
])
const discount = computed(() => {
  const a = toPiasters(amount.value) ?? 0
  const p = toPiasters(paid.value)
  return p === null ? 0 : Math.max(0, a - p)
})
const discountPct = computed(() => {
  const a = toPiasters(amount.value) ?? 0
  return a > 0 ? Math.round(discount.value / a * 1000) / 10 : 0
})

watch(open, (isOpen) => {
  if (isOpen) {
    amount.value = ''
    paid.value = ''
    source.value = 'drawer'
    note.value = ''
    error.value = null
    needsShift.value = false
  }
})

async function save() {
  if (!props.account) {
    return
  }
  saving.value = true
  error.value = null
  try {
    await api(`/services/accounts/${props.account.id}/${props.mode === 'fund' ? 'fund' : 'cash-out'}`, {
      method: 'POST',
      body: { amount: toPiasters(amount.value), paid: showPaid.value ? toPiasters(paid.value) : null, source: source.value, note: note.value.trim() || null },
    })
    toast.add({ color: 'success', title: props.mode === 'fund' ? 'اتسجّل التمويل' : 'اتسجّل التسييل' })
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

<template>
  <UModal v-model:open="open" :title="`قفل الوردية ${shift?.reference ?? ''}`" description="اعد الفلوس اللي في الدرج واكتبها. الفيزا والمحافظ قارنها بتقرير الماكينة / التطبيق.">
    <template #body>
      <form v-if="shift?.expected" id="close-form" class="space-y-3" @submit.prevent="save">
        <div v-for="m in methods" :key="m.value" class="grid grid-cols-[1fr_8rem] items-center gap-3 rounded-(--ui-radius) border border-(--ui-border) p-3">
          <div>
            <p class="font-bold">
              {{ m.value === 'cash' ? 'الكاش في الدرج' : m.label }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              المفروض <span class="num">{{ formatMoney(shift.expected[m.value]) }}</span>
              <template v-if="diff(m.value) !== 0">
                · <span class="font-bold" :class="diff(m.value) < 0 ? 'text-error' : 'text-success'">
                  {{ diff(m.value) < 0 ? 'عجز' : 'زيادة' }} <span class="num">{{ formatMoney(Math.abs(diff(m.value))) }}</span>
                </span>
              </template>
            </p>
          </div>
          <UInput
            v-model="counted[m.value]"
            type="number"
            min="0"
            step="any"
            inputmode="decimal"
            dir="ltr"
            :aria-label="`${m.label} اللي اتعد`"
            :autofocus="m.value === 'cash'"
          />
        </div>
        <UFormField label="ملاحظة">
          <UInput v-model="note" placeholder="مثلاً: سبب العجز" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="close-form" icon="i-lucide-lock" label="قفل الوردية" :loading="saving" :disabled="counted.cash === ''" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CashMethod, CashShift } from '~/types/api'

const props = defineProps<{ shift: CashShift | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ closed: [shift: CashShift] }>()

const api = useApi()
const counted = reactive<Record<CashMethod, string>>({ cash: '', card: '', wallet: '', instapay: '' })
const note = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

// Cash always; the others only when something came in through them.
const methods = computed(() => CASH_METHODS.filter(m => m.value === 'cash' || (props.shift?.expected?.[m.value] ?? 0) !== 0))

watch(open, (isOpen) => {
  if (isOpen && props.shift?.expected) {
    for (const m of CASH_METHODS) {
      counted[m.value] = m.value === 'cash' ? '' : String(props.shift.expected[m.value] / 100)
    }
    note.value = ''
    error.value = null
  }
})

function diff(method: CashMethod): number {
  const value = counted[method]
  if (value === '' || !props.shift?.expected) {
    return 0
  }
  return (toPiasters(value) ?? 0) - props.shift.expected[method]
}

async function save() {
  if (!props.shift) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const body: Record<string, number | null> = {}
    for (const m of methods.value) {
      body[m.value] = toPiasters(counted[m.value])
    }
    const res = await api<{ data: CashShift }>(`/cash/shifts/${props.shift.id}/close`, { method: 'POST', body: { counted: body, note: note.value || null } })
    open.value = false
    emit('closed', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

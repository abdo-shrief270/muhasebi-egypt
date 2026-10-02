<template>
  <UModal v-model:open="open" :title="titles[type]" :description="`في الدرج دلوقتي ${formatMoney(inDrawer)}`">
    <template #body>
      <form id="movement-form" class="space-y-4" @submit.prevent="save()">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="المبلغ" hint="بالجنيه" required>
            <UInput v-model="form.amount" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField v-if="type === 'expense'" label="نوع المصروف" required>
            <USelect v-model="form.category" :items="categories" class="w-full" />
          </UFormField>
        </div>
        <UFormField :label="type === 'expense' ? 'تفاصيل' : 'السبب'" :required="type !== 'expense'">
          <UInput v-model="form.note" :placeholder="placeholders[type]" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="movement-form" :label="titles[type]" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
type ManualType = 'expense' | 'deposit' | 'withdrawal'

const props = defineProps<{ type: ManualType, inDrawer: number, categories: { value: string, label: string }[] }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const titles: Record<ManualType, string> = { expense: 'مصروف من الدرج', deposit: 'إيداع في الدرج', withdrawal: 'سحب من الدرج' }
const placeholders: Record<ManualType, string> = { expense: 'مثلاً: شاي وقهوة', deposit: 'مثلاً: فكّة من البنك', withdrawal: 'مثلاً: للمالك / إيداع في البنك' }

const api = useApi()
const toast = useToast()
const form = reactive({ amount: '', category: 'other', note: '' })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { amount: '', category: 'other', note: '' })
    error.value = null
  }
})

const approval = useApproval()

async function save(approvalId: string | null = null) {
  saving.value = true
  error.value = null
  try {
    await api('/cash/movements', {
      method: 'POST',
      headers: approvalId ? { 'X-Approval-Id': approvalId } : undefined,
      body: { type: props.type, amount: toPiasters(form.amount), category: props.type === 'expense' ? form.category : undefined, note: form.note || null },
    })
    toast.add({ color: 'success', title: 'اتسجّلت' })
    open.value = false
    emit('saved')
  }
  catch (e) {
    // Past the owner's limit: the owner's / a manager's OK, then the same movement again.
    if (approvalNeeded(e)) {
      saving.value = false
      const id = await approval.ask(e)
      if (id) {
        return save(id)
      }
    }
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

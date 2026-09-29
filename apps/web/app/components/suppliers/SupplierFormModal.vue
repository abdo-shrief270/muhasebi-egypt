<template>
  <UModal v-model:open="open" :title="supplier ? `تعديل «${supplier.name}»` : 'مورد جديد'">
    <template #body>
      <form id="supplier-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="اسم المورد" required>
            <UInput v-model="form.name" class="w-full" autofocus />
          </UFormField>
          <UFormField label="الموبايل">
            <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
          </UFormField>
        </div>
        <UFormField v-if="!supplier" label="رصيد قبل السيستم" hint="بالجنيه — اللي عليك للمورد (بالسالب لو هو اللي عليه)">
          <UInput v-model="form.opening" type="number" step="any" inputmode="decimal" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="ملاحظات">
          <UTextarea v-model="form.notes" :rows="2" class="w-full" />
        </UFormField>
        <USwitch v-if="supplier" v-model="form.is_active" label="المورد شغال" description="الموقوف مش بيظهر في فواتير الشراء الجديدة." />
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="supplier-form" label="حفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Supplier } from '~/types/api'

const props = defineProps<{ supplier?: Supplier | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [supplier: Supplier] }>()

const api = useApi()
const form = reactive({ name: '', phone: '', notes: '', opening: '', is_active: true })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, {
      name: props.supplier?.name ?? '',
      phone: props.supplier?.phone ?? '',
      notes: props.supplier?.notes ?? '',
      opening: '',
      is_active: props.supplier?.is_active ?? true,
    })
    error.value = null
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const body = {
      name: form.name,
      phone: form.phone || null,
      notes: form.notes || null,
      ...(props.supplier ? { is_active: form.is_active } : { opening_balance: toPiasters(form.opening) }),
    }
    const res = props.supplier
      ? await api<{ data: Supplier }>(`/suppliers/${props.supplier.id}`, { method: 'PATCH', body })
      : await api<{ data: Supplier }>('/suppliers', { method: 'POST', body })
    open.value = false
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

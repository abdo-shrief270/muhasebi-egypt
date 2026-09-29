<template>
  <UModal v-model:open="open" :title="customer ? `تعديل «${customer.name}»` : 'عميل جديد'">
    <template #body>
      <form id="customer-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="اسم العميل" required>
            <UInput v-model="form.name" class="w-full" autofocus />
          </UFormField>
          <UFormField label="الموبايل">
            <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
          </UFormField>
        </div>
        <div v-if="canCredit" class="grid gap-4 sm:grid-cols-2">
          <UFormField label="حد الآجل" hint="بالجنيه — فاضي = من غير حد">
            <UInput v-model="form.limit" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField v-if="!customer" label="عليه من قبل السيستم" hint="بالجنيه">
            <UInput v-model="form.opening" type="number" step="any" inputmode="decimal" dir="ltr" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="ملاحظات">
          <UTextarea v-model="form.notes" :rows="2" class="w-full" />
        </UFormField>
        <USwitch v-if="customer" v-model="form.is_active" label="الحساب شغال" description="الموقوف مينفعش يشتري آجل." />
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="customer-form" label="حفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Customer } from '~/types/api'

const props = defineProps<{ customer?: Customer | null, initialName?: string }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [customer: Customer] }>()

const api = useApi()
const store = useSessionStore()
const canCredit = computed(() => store.can('customers.credit'))
const form = reactive({ name: '', phone: '', notes: '', limit: '', opening: '', is_active: true })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    // Typed a phone number in the search box? It's the phone, not the name.
    const typed = props.initialName?.trim() ?? ''
    const typedPhone = /^[+\d\s-]{6,}$/.test(typed)
    Object.assign(form, {
      name: props.customer?.name ?? (typedPhone ? '' : typed),
      phone: props.customer?.phone?.replace(/^\+20/, '0') ?? (typedPhone ? typed : ''),
      notes: props.customer?.notes ?? '',
      limit: props.customer?.credit_limit != null ? String(props.customer.credit_limit / 100) : '',
      opening: '',
      is_active: props.customer?.is_active ?? true,
    })
    error.value = null
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const body: Record<string, unknown> = { name: form.name, phone: form.phone || null, notes: form.notes || null }
    if (canCredit.value) {
      body.credit_limit = toPiasters(form.limit)
      if (!props.customer && form.opening) {
        body.opening_balance = toPiasters(form.opening)
      }
    }
    if (props.customer) {
      body.is_active = form.is_active
    }
    const res = props.customer
      ? await api<{ data: Customer }>(`/customers/${props.customer.id}`, { method: 'PATCH', body })
      : await api<{ data: Customer }>('/customers', { method: 'POST', body })
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

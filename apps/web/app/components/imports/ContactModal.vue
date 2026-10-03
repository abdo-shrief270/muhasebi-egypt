<template>
  <UModal v-model:open="open" :title="contact ? `تعديل ${contact.name}` : 'جهة جديدة'">
    <template #body>
      <form id="import-contact-form" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save">
        <UFormField label="النوع" class="sm:col-span-2">
          <URadioGroup v-model="form.type" orientation="horizontal" :items="[...IMPORT_CONTACT_TYPES]" />
        </UFormField>
        <UFormField label="الاسم" required class="sm:col-span-2" :error="errors.name">
          <UInput v-model="form.name" class="w-full" maxlength="120" />
        </UFormField>
        <UFormField label="البلد">
          <UInput v-model="form.country" class="w-full" maxlength="60" placeholder="الصين" />
        </UFormField>
        <UFormField label="المدينة">
          <UInput v-model="form.city" class="w-full" maxlength="60" placeholder="شنزن" />
        </UFormField>
        <UFormField label="الموبايل">
          <UInput v-model="form.phone" dir="ltr" class="w-full" maxlength="30" />
        </UFormField>
        <UFormField label="WhatsApp">
          <UInput v-model="form.whatsapp" dir="ltr" class="w-full" maxlength="30" />
        </UFormField>
        <UFormField label="WeChat" class="sm:col-span-2">
          <UInput v-model="form.wechat" dir="ltr" class="w-full" maxlength="60" />
        </UFormField>
        <UFormField label="ملاحظات" class="sm:col-span-2">
          <UTextarea v-model="form.notes" :rows="2" autoresize class="w-full" maxlength="1000" />
        </UFormField>
        <USwitch v-if="contact" v-model="form.is_active" label="شغالة" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="import-contact-form" label="احفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ImportContact, ImportContactType } from '~/types/api'

/** Add or edit an import contact. */
const props = defineProps<{ contact?: ImportContact | null }>()
const emit = defineEmits<{ saved: [contact: ImportContact] }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const blank = () => ({ type: 'supplier' as ImportContactType, name: '', country: '', city: '', phone: '', whatsapp: '', wechat: '', notes: '', is_active: true })
const form = reactive(blank())
watch(open, (isOpen) => {
  if (isOpen) {
    const c = props.contact
    Object.assign(form, c
      ? { type: c.type, name: c.name, country: c.country ?? '', city: c.city ?? '', phone: c.phone ?? '', whatsapp: c.whatsapp ?? '', wechat: c.wechat ?? '', notes: c.notes ?? '', is_active: c.is_active }
      : blank())
    errors.value = {}
  }
})

const saving = ref(false)
const errors = ref<Record<string, string>>({})
async function save() {
  saving.value = true
  errors.value = {}
  const nullable = (v: string) => v.trim() || null
  try {
    const body = { ...form, country: nullable(form.country), city: nullable(form.city), phone: nullable(form.phone), whatsapp: nullable(form.whatsapp), wechat: nullable(form.wechat), notes: nullable(form.notes) }
    const res = props.contact
      ? await api<{ data: ImportContact }>(`/imports/contacts/${props.contact.id}`, { method: 'PATCH', body })
      : await api<{ data: ImportContact }>('/imports/contacts', { method: 'POST', body })
    emit('saved', res.data)
    open.value = false
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
  }
  finally {
    saving.value = false
  }
}
</script>

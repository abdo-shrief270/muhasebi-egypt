<template>
  <UCard>
    <template #header>
      <h2 class="text-lg font-bold">
        كلمة السر
      </h2>
      <p class="text-sm text-(--ui-text-muted)">
        لما تغيّرها، أي جهاز تاني داخل بحسابك هيخرج.
      </p>
    </template>
    <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="save">
      <UFormField label="الحالية">
        <UInput v-model="form.current_password" type="password" autocomplete="current-password" class="w-full" />
      </UFormField>
      <UFormField label="الجديدة" hint="8 حروف على الأقل">
        <UInput v-model="form.password" type="password" autocomplete="new-password" class="w-full" />
      </UFormField>
      <UFormField label="تأكيد الجديدة">
        <UInput v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full" />
      </UFormField>
      <UAlert v-if="error" color="error" variant="subtle" :title="error" class="sm:col-span-3" />
      <div class="sm:col-span-3">
        <UButton type="submit" icon="i-lucide-key-round" label="غيّر كلمة السر" :disabled="!form.current_password || !form.password" :loading="saving" />
      </div>
    </form>
  </UCard>
</template>

<script setup lang="ts">
/** Change your own password (needs the current one); other devices are signed out. */
const api = useApi()
const toast = useToast()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false)
const error = ref<string | null>(null)

async function save() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: { signed_out_devices: number } }>('/account/password', { method: 'PUT', body: form })
    toast.add({ color: 'success', title: 'اتغيّرت كلمة السر', description: res.data.signed_out_devices ? `خرجنا من ${res.data.signed_out_devices} جهاز تاني.` : undefined })
    Object.assign(form, { current_password: '', password: '', password_confirmation: '' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

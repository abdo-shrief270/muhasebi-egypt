<template>
  <UCard>
    <template #header>
      <div>
        <h2 class="text-lg font-bold">
          PIN الموافقات
        </h2>
        <p class="text-sm text-(--ui-text-muted)">
          لما كاشير يحتاج موافقة (خصم كبير، مرتجع، سحب من الدرج) وإنت في المحل، اكتب الـ PIN ده على شاشته بدل الموبايل.
        </p>
      </div>
    </template>
    <form class="space-y-3" @submit.prevent="save">
      <p class="text-sm">
        <UBadge :color="hasPin ? 'success' : 'neutral'" variant="subtle">
          {{ hasPin ? 'متظبط' : 'مش متظبط' }}
        </UBadge>
      </p>
      <div class="grid gap-3 sm:grid-cols-2">
        <UFormField :label="hasPin ? 'PIN جديد' : 'الـ PIN'" hint="4 لـ 6 أرقام">
          <UInput v-model="pin" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="كلمة السر بتاعتك">
          <UInput v-model="password" type="password" autocomplete="current-password" class="w-full" />
        </UFormField>
      </div>
      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <div class="flex flex-wrap gap-2">
        <UButton type="submit" :label="hasPin ? 'غيّر الـ PIN' : 'احفظ الـ PIN'" :loading="saving" :disabled="!/^\d{4,6}$/.test(pin) || !password" />
        <UButton v-if="hasPin" color="neutral" variant="ghost" label="شيل الـ PIN" :loading="removing" :disabled="!password" @click="remove" />
      </div>
    </form>
  </UCard>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { data } = await useAsyncData('account-pin', () => api<{ data: { has_pin: boolean } }>('/account/pin'))
const hasPin = computed(() => data.value?.data.has_pin ?? false)
const pin = ref('')
const password = ref('')
const saving = ref(false)
const removing = ref(false)
const error = ref<string | null>(null)

async function save() {
  saving.value = true
  error.value = null
  try {
    data.value = await api<{ data: { has_pin: boolean } }>('/account/pin', { method: 'PUT', body: { pin: pin.value, password: password.value } })
    pin.value = ''
    password.value = ''
    toast.add({ color: 'success', title: 'اتحفظ الـ PIN' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}

async function remove() {
  removing.value = true
  error.value = null
  try {
    data.value = await api<{ data: { has_pin: boolean } }>('/account/pin', { method: 'DELETE', body: { password: password.value } })
    password.value = ''
    toast.add({ color: 'success', title: 'اتشال الـ PIN' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    removing.value = false
  }
}
</script>

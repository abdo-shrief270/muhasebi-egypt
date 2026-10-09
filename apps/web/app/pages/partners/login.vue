<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        دخول الشركاء
      </h1>
    </template>
    <form class="space-y-4" @submit.prevent="submit">
      <UFormField label="رقم الموبايل">
        <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" autofocus autocomplete="tel" />
      </UFormField>
      <UFormField label="كلمة السر">
        <UInput v-model="password" type="password" class="w-full" autocomplete="current-password" />
      </UFormField>
      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <UButton type="submit" block size="lg" :loading="loading" label="دخول" />
      <p class="text-center text-sm text-(--ui-text-muted)">
        لسه مش شريك؟ <ULink to="/partners/register" class="text-(--ui-primary) underline">اعمل حساب</ULink>
      </p>
      <p class="text-center text-xs text-(--ui-text-dimmed)">
        ده دخول برنامج الشركاء. لو عندك محل، ادخل من <ULink to="/login" class="underline">دخول المحلات</ULink>.
      </p>
    </form>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ public: true, layout: 'auth' })
useHead({ title: 'دخول الشركاء — محاسبي' })

const api = usePartnerApi()
const auth = usePartnerToken()
const phone = ref('')
const password = ref('')
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = null
  try {
    const res = await api<{ token: string }>('/affiliates/login', { method: 'POST', body: { phone: phone.value, password: password.value } })
    auth.set(res.token)
    await navigateTo('/partners')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

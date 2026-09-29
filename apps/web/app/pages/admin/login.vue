<template>
  <div class="grid min-h-[70dvh] place-items-center">
    <UCard class="w-full max-w-sm">
      <template #header>
        <h1 class="text-xl font-bold">
          دخول الإدارة
        </h1>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField label="الإيميل">
          <UInput v-model="email" type="email" dir="ltr" class="w-full" autofocus />
        </UFormField>
        <UFormField label="كلمة السر">
          <UInput v-model="password" type="password" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" block :loading="loading" label="دخول" />
      </form>
    </UCard>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ public: true, layout: 'admin' })

const config = useRuntimeConfig()
const { set } = useAdminToken()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    const res = await $fetch<{ token: string }>(`${config.public.apiBase}/admin/auth/login`, { method: 'POST', body: { email: email.value, password: password.value }, headers: { Accept: 'application/json' } })
    set(res.token)
    await navigateTo('/admin')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

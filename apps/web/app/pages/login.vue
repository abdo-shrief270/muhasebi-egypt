<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        تسجيل الدخول
      </h1>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <UFormField label="رقم الموبايل">
        <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" autofocus />
      </UFormField>
      <UFormField label="كلمة السر">
        <UInput v-model="password" type="password" class="w-full" />
      </UFormField>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton type="submit" block size="lg" :loading="loading" label="دخول" />
    </form>

    <template #footer>
      <p class="text-sm text-center text-(--ui-text-muted)">
        محل جديد؟
        <NuxtLink to="/register" class="text-primary font-semibold">
          سجّل محلك
        </NuxtLink>
      </p>
    </template>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth', guest: true })

const store = useSessionStore()
const route = useRoute()
const phone = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    await store.login(phone.value, password.value)
    await navigateTo(typeof route.query.redirect === 'string' ? route.query.redirect : '/')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

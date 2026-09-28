<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        سجّل محلك
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        14 يوم تجربة مجانية، من غير كارت.
      </p>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <UFormField label="اسم المحل">
        <UInput v-model="form.shop_name" class="w-full" />
      </UFormField>

      <UFormField label="نوع المحل" description="بنفعّلك الأقسام المناسبة، وتقدر تغيّرها بعدين.">
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="type in shopTypes"
            :key="type.value"
            type="button"
            class="rounded-lg border px-3 py-2 text-sm text-start transition"
            :class="form.shop_type === type.value ? 'border-primary bg-primary/10 text-primary font-semibold' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
            @click="form.shop_type = type.value"
          >
            {{ type.label }}
          </button>
        </div>
      </UFormField>

      <UFormField label="اسمك">
        <UInput v-model="form.owner_name" class="w-full" />
      </UFormField>
      <UFormField label="رقم الموبايل">
        <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
      </UFormField>
      <div class="grid grid-cols-2 gap-3">
        <UFormField label="كلمة السر">
          <UInput v-model="form.password" type="password" class="w-full" />
        </UFormField>
        <UFormField label="تأكيدها">
          <UInput v-model="form.password_confirmation" type="password" class="w-full" />
        </UFormField>
      </div>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton type="submit" block size="lg" :loading="loading" label="ابدأ التجربة" />
    </form>

    <template #footer>
      <p class="text-sm text-center text-(--ui-text-muted)">
        عندك حساب؟
        <NuxtLink to="/login" class="text-primary font-semibold">
          سجّل دخول
        </NuxtLink>
      </p>
    </template>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth', guest: true })

const store = useSessionStore()
const shopTypes = [
  { value: 'accessories', label: 'إكسسوارات' },
  { value: 'repair', label: 'صيانة' },
  { value: 'accessories_repair', label: 'إكسسوارات + صيانة' },
  { value: 'importer', label: 'مستورد' },
  { value: 'wholesale', label: 'جملة' },
]

const form = reactive({
  shop_name: '',
  shop_type: 'accessories_repair',
  owner_name: '',
  phone: '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    await store.register({ ...form })
    await navigateTo('/')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

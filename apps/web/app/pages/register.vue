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

      <UFormField label="محلك بيعمل إيه؟" description="اختار كل اللي ينطبق. هتشوف الأقسام اللي تناسبك بس، وتقدر تغيّر ده بعدين.">
        <ShopTypePicker v-model="form.shop_types" />
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

      <UFormField label="كود الدعوة" hint="اختياري" :description="form.referral_code ? 'هتاخد خصم على أول شهور من اشتراكك.' : 'لو محل صاحبك بعتلك كود.'">
        <UInput v-model="form.referral_code" dir="ltr" class="w-full" maxlength="12" placeholder="ABC123" @update:model-value="v => form.referral_code = String(v).toUpperCase()" />
      </UFormField>

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
      <p class="mt-2 text-xs text-center text-(--ui-text-muted)">
        بياناتك وبيانات عملائك في أمان —
        <NuxtLink to="/privacy" target="_blank" class="text-primary hover:underline">سياسة الخصوصية</NuxtLink>
      </p>
    </template>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth', guest: true })

const store = useSessionStore()
const route = useRoute()

const form = reactive({
  shop_name: '',
  shop_types: ['accessories'] as string[],
  owner_name: '',
  phone: '',
  password: '',
  password_confirmation: '',
  // From an invite link: /register?ref=CODE
  referral_code: typeof route.query.ref === 'string' ? route.query.ref.trim().toUpperCase().slice(0, 12) : '',
})
const loading = ref(false)
const error = ref<string | null>(null)

async function submit() {
  if (!form.shop_types.length) {
    error.value = 'اختار نوع المحل (نوع واحد على الأقل).'
    return
  }
  loading.value = true
  error.value = null
  try {
    await store.register({ ...form, referral_code: form.referral_code.trim() || undefined })
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

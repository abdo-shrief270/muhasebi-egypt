<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        اشترك في برنامج شركاء محاسبي
      </h1>
      <p class="mt-1 text-sm text-(--ui-text-muted)">
        رشّح محاسبي لمحلات الموبايلات، وخد نسبة من اشتراك كل محل يشترك عن طريقك.
      </p>
    </template>

    <form class="space-y-4" @submit.prevent="submit">
      <UFormField label="اسمك" :error="errors.name">
        <UInput v-model="form.name" class="w-full" autofocus autocomplete="name" />
      </UFormField>
      <UFormField label="رقم الموبايل" :error="errors.phone">
        <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" autocomplete="tel" />
      </UFormField>
      <UFormField label="الإيميل" hint="اختياري" :error="errors.email">
        <UInput v-model="form.email" type="email" dir="ltr" class="w-full" autocomplete="email" />
      </UFormField>
      <UFormField label="الكود اللي في لينكك" hint="اختياري" description="حروف إنجليزي وأرقام، زي KARIM أو TECHZONE. لو مش متاح هنديك كود تاني." :error="errors.code">
        <UInput v-model="form.code" dir="ltr" class="w-full" maxlength="20" placeholder="KARIM" @update:model-value="v => form.code = String(v).toUpperCase().replace(/[^A-Z0-9]/g, '')" />
      </UFormField>
      <UFormField label="هتسوّق فين؟" hint="اختياري" :error="errors.channel">
        <UInput v-model="form.channel" class="w-full" maxlength="120" placeholder="صفحة فيسبوك، قناة يوتيوب، معارفي في سوق الموبايلات…" />
      </UFormField>
      <UFormField label="كلمة السر" :error="errors.password">
        <UInput v-model="form.password" type="password" class="w-full" autocomplete="new-password" />
      </UFormField>
      <UFormField label="تأكيد كلمة السر">
        <UInput v-model="form.password_confirmation" type="password" class="w-full" autocomplete="new-password" />
      </UFormField>
      <UCheckbox v-model="form.terms" :class="errors.terms ? 'text-(--ui-error)' : ''">
        <template #label>
          موافق على <ULink to="/partners/terms" target="_blank" class="text-(--ui-primary) underline">شروط برنامج الشركاء</ULink>
        </template>
      </UCheckbox>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <UButton type="submit" block size="lg" :loading="loading" label="اعمل حسابي" />
      <p class="text-center text-sm text-(--ui-text-muted)">
        عندك حساب؟ <ULink to="/partners/login" class="text-(--ui-primary) underline">ادخل</ULink>
      </p>
    </form>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ public: true, layout: 'auth' })
useHead({ title: 'برنامج الشركاء — محاسبي' })

const api = usePartnerApi()
const auth = usePartnerToken()
const form = reactive({ name: '', phone: '', email: '', code: '', channel: '', password: '', password_confirmation: '', terms: false })
const errors = ref<Record<string, string>>({})
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  error.value = null
  try {
    const res = await api<{ token: string }>('/affiliates/register', {
      method: 'POST',
      body: { ...form, email: form.email || null, code: form.code || null, channel: form.channel || null },
    })
    auth.set(res.token)
    await navigateTo('/partners')
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    if (!Object.keys(errors.value).length) error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        {{ challenge ? 'التحقق بخطوتين' : 'تسجيل الدخول' }}
      </h1>
    </template>

    <form v-if="!challenge" class="space-y-4" @submit.prevent="submit">
      <UFormField label="رقم الموبايل">
        <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" autofocus />
      </UFormField>
      <UFormField label="كلمة السر">
        <UInput v-model="password" type="password" class="w-full" />
      </UFormField>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton type="submit" block size="lg" :loading="loading" label="دخول" />
    </form>

    <form v-else class="space-y-4" @submit.prevent="submitCode">
      <p class="text-sm text-(--ui-text-muted)">
        {{ useRecovery
          ? 'اكتب واحد من أكواد الاسترجاع اللي حفظتها لما فعّلت التحقق بخطوتين. كل كود بيشتغل مرة واحدة.'
          : 'افتح تطبيق المصادقة على موبايلك (Google Authenticator أو غيره) واكتب الكود اللي ظاهر لحساب محاسبي.' }}
      </p>
      <UFormField :label="useRecovery ? 'كود الاسترجاع' : 'الكود (6 أرقام)'">
        <UInput
          :key="useRecovery ? 'recovery' : 'totp'"
          v-model="code"
          dir="ltr"
          class="w-full"
          size="xl"
          :inputmode="useRecovery ? 'text' : 'numeric'"
          :autocomplete="useRecovery ? 'off' : 'one-time-code'"
          :maxlength="useRecovery ? 11 : 6"
          :placeholder="useRecovery ? 'xxxxx-xxxxx' : '123456'"
          :ui="{ base: 'num text-center tracking-[0.3em] text-lg' }"
          autofocus
        />
      </UFormField>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton type="submit" block size="lg" :loading="loading" label="تأكيد" :disabled="!code.trim()" />

      <div class="flex items-center justify-between text-sm">
        <UButton variant="link" color="neutral" :label="useRecovery ? 'استخدم كود التطبيق' : 'مش معاك الموبايل؟ استخدم كود استرجاع'" class="px-0" @click="toggleRecovery" />
        <UButton variant="link" color="neutral" label="رجوع" class="px-0" @click="reset" />
      </div>
    </form>

    <template v-if="!challenge" #footer>
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
const toast = useToast()
const phone = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const challenge = ref<string | null>(null)
const code = ref('')
const useRecovery = ref(false)

async function enter() {
  await navigateTo(typeof route.query.redirect === 'string' ? route.query.redirect : '/')
}

async function submit() {
  loading.value = true
  error.value = null
  try {
    const pending = await store.login(phone.value, password.value)
    if (pending) {
      challenge.value = pending.challenge
      code.value = ''
      return
    }
    await enter()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}

async function submitCode() {
  if (!challenge.value) {
    return
  }
  loading.value = true
  error.value = null
  try {
    const { recoveryCodesLeft } = await store.completeTwoFactor(challenge.value, code.value)
    if (recoveryCodesLeft !== null) {
      toast.add({
        color: recoveryCodesLeft <= 2 ? 'warning' : 'info',
        title: `دخلت بكود استرجاع. فاضل ${recoveryCodesLeft} أكواد`,
        description: 'اعمل أكواد جديدة من «الأمان وتسجيل الدخول» لو قربوا يخلصوا.',
      })
    }
    await enter()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    code.value = ''
    // The challenge expired or ran out of tries: start over from the password.
    if ((e as { data?: { errors?: Record<string, string[]> } })?.data?.errors?.challenge) {
      challenge.value = null
    }
  }
  finally {
    loading.value = false
  }
}

function toggleRecovery() {
  useRecovery.value = !useRecovery.value
  code.value = ''
  error.value = null
}

function reset() {
  challenge.value = null
  code.value = ''
  password.value = ''
  useRecovery.value = false
  error.value = null
}
</script>

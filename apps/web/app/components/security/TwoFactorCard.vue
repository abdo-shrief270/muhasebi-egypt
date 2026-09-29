<template>
  <UCard>
    <div class="flex flex-wrap items-start gap-4">
      <span class="grid size-12 shrink-0 place-items-center rounded-lg" :class="status?.enabled ? 'bg-(--app-primary-soft) text-(--app-primary-strong)' : 'bg-(--ui-bg-elevated) text-(--ui-text-muted)'">
        <UIcon :name="status?.enabled ? 'i-lucide-shield-check' : 'i-lucide-shield'" class="size-6" />
      </span>
      <div class="min-w-0 flex-1 space-y-1">
        <h2 class="flex flex-wrap items-center gap-2 text-lg font-bold">
          التحقق بخطوتين
          <UBadge :color="status?.enabled ? 'success' : 'neutral'" variant="subtle">
            {{ status?.enabled ? 'شغال' : 'مقفول' }}
          </UBadge>
        </h2>
        <p class="text-sm text-(--ui-text-muted)">
          بعد كلمة السر، الدخول بيطلب كود من تطبيق على موبايلك (Google Authenticator أو Microsoft Authenticator). حتى لو حد عرف كلمة السر مش هيقدر يدخل.
        </p>
        <p v-if="status?.enabled" class="text-sm text-(--ui-text-muted)">
          اتفعّل <span class="num">{{ formatDate(status.confirmed_at) }}</span> · فاضل <span class="num">{{ status.recovery_codes_left }}</span> كود استرجاع
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <template v-if="status?.enabled">
          <UButton color="neutral" variant="soft" icon="i-lucide-key-round" label="أكواد استرجاع جديدة" @click="openCodes" />
          <UButton color="error" variant="soft" label="إيقاف" @click="openDisable" />
        </template>
        <UButton v-else icon="i-lucide-shield-check" label="فعّل التحقق بخطوتين" @click="openEnable" />
      </div>
    </div>

    <!-- Enable: password → scan → code → recovery codes -->
    <UModal v-model:open="enableOpen" title="تفعيل التحقق بخطوتين" :dismissible="step !== 'codes'">
      <template #body>
        <form v-if="step === 'password'" id="tf-form" class="space-y-4" @submit.prevent="start">
          <p class="text-sm text-(--ui-text-muted)">
            اكتب كلمة السر بتاعتك الأول.
          </p>
          <UFormField label="كلمة السر">
            <UInput v-model="password" type="password" class="w-full" autofocus />
          </UFormField>
        </form>

        <form v-else-if="step === 'scan' && setup" id="tf-form" class="space-y-4" @submit.prevent="confirm">
          <ol class="list-decimal space-y-1 ps-5 text-sm">
            <li>نزّل تطبيق مصادقة على موبايلك (Google Authenticator أو Microsoft Authenticator).</li>
            <li>من التطبيق اختار «إضافة حساب» وصوّر الكود ده.</li>
            <li>اكتب الـ 6 أرقام اللي هتظهر لك.</li>
          </ol>
          <div class="flex justify-center">
            <div class="rounded-(--ui-radius) bg-white p-3">
              <PrintQrCode :value="setup.otpauth_url" :size="176" />
            </div>
          </div>
          <details class="text-sm">
            <summary class="cursor-pointer text-(--ui-text-muted)">
              مش عارف تصوّر؟ اكتب المفتاح ده بإيدك
            </summary>
            <p class="mt-2 break-all rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2 text-center font-mono num" dir="ltr">
              {{ groupedSecret }}
            </p>
          </details>
          <UFormField label="الكود من التطبيق">
            <UInput
              v-model="code"
              dir="ltr"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              placeholder="123456"
              size="xl"
              class="w-full"
              :ui="{ base: 'num text-center tracking-[0.3em] text-lg' }"
            />
          </UFormField>
        </form>

        <SecurityRecoveryCodes v-else-if="step === 'codes'" :codes="recoveryCodes" />

        <UAlert v-if="error" class="mt-4" color="error" variant="subtle" :title="error" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <template v-if="step !== 'codes'">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="enableOpen = false" />
            <UButton type="submit" form="tf-form" :loading="busy" :label="step === 'password' ? 'التالي' : 'تفعيل'" />
          </template>
          <UButton v-else label="حفظت الأكواد" @click="enableOpen = false" />
        </div>
      </template>
    </UModal>

    <!-- Disable: password + code -->
    <UModal v-model:open="disableOpen" title="إيقاف التحقق بخطوتين">
      <template #body>
        <form id="tf-disable" class="space-y-4" @submit.prevent="disable">
          <UAlert color="warning" variant="subtle" title="الحساب هيبقى محمي بكلمة السر بس." />
          <UFormField label="كلمة السر">
            <UInput v-model="password" type="password" class="w-full" />
          </UFormField>
          <UFormField label="كود من التطبيق أو كود استرجاع">
            <UInput v-model="code" dir="ltr" autocomplete="one-time-code" class="w-full" :ui="{ base: 'num' }" />
          </UFormField>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="disableOpen = false" />
          <UButton type="submit" form="tf-disable" color="error" :loading="busy" label="إيقاف" />
        </div>
      </template>
    </UModal>

    <!-- New recovery codes -->
    <UModal v-model:open="codesOpen" title="أكواد استرجاع جديدة" :dismissible="!recoveryCodes.length">
      <template #body>
        <SecurityRecoveryCodes v-if="recoveryCodes.length" :codes="recoveryCodes" />
        <form v-else id="tf-codes" class="space-y-4" @submit.prevent="regenerate">
          <p class="text-sm text-(--ui-text-muted)">
            الأكواد القديمة هتبطل تشتغل. اكتب كلمة السر عشان تكمّل.
          </p>
          <UFormField label="كلمة السر">
            <UInput v-model="password" type="password" class="w-full" autofocus />
          </UFormField>
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <template v-if="!recoveryCodes.length">
            <UButton color="neutral" variant="ghost" label="إلغاء" @click="codesOpen = false" />
            <UButton type="submit" form="tf-codes" :loading="busy" label="اعمل أكواد جديدة" />
          </template>
          <UButton v-else label="حفظت الأكواد" @click="codesOpen = false" />
        </div>
      </template>
    </UModal>
  </UCard>
</template>

<script setup lang="ts">
import type { TwoFactorSetup, TwoFactorStatus } from '~/types/api'

const api = useApi()
const store = useSessionStore()
const toast = useToast()

const { data, refresh } = await useAsyncData('two-factor', () => api<{ data: TwoFactorStatus }>('/account/two-factor'))
const status = computed(() => data.value?.data ?? null)

const busy = ref(false)
const error = ref<string | null>(null)
const password = ref('')
const code = ref('')
const setup = ref<TwoFactorSetup | null>(null)
const recoveryCodes = ref<string[]>([])
const step = ref<'password' | 'scan' | 'codes'>('password')
const groupedSecret = computed(() => setup.value?.secret.match(/.{1,4}/g)?.join(' ') ?? '')

const enableOpen = ref(false)
const disableOpen = ref(false)
const codesOpen = ref(false)

function resetForm() {
  error.value = null
  password.value = ''
  code.value = ''
  recoveryCodes.value = []
}

function openEnable() {
  resetForm()
  setup.value = null
  step.value = 'password'
  enableOpen.value = true
}

function openDisable() {
  resetForm()
  disableOpen.value = true
}

function openCodes() {
  resetForm()
  codesOpen.value = true
}

async function run(fn: () => Promise<void>) {
  busy.value = true
  error.value = null
  try {
    await fn()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    busy.value = false
  }
}

const start = () => run(async () => {
  setup.value = (await api<{ data: TwoFactorSetup }>('/account/two-factor/setup', { method: 'POST', body: { password: password.value } })).data
  password.value = ''
  step.value = 'scan'
})

const confirm = () => run(async () => {
  const res = await api<{ data: { recovery_codes: string[] } }>('/account/two-factor/confirm', { method: 'POST', body: { code: code.value } })
  recoveryCodes.value = res.data.recovery_codes
  setup.value = null
  step.value = 'codes'
  await Promise.all([refresh(), store.load()])
})

const disable = () => run(async () => {
  await api('/account/two-factor', { method: 'DELETE', body: { password: password.value, code: code.value } })
  disableOpen.value = false
  toast.add({ color: 'success', title: 'التحقق بخطوتين اتقفل' })
  await Promise.all([refresh(), store.load()])
})

const regenerate = () => run(async () => {
  const res = await api<{ data: { recovery_codes: string[] } }>('/account/two-factor/recovery-codes', { method: 'POST', body: { password: password.value } })
  recoveryCodes.value = res.data.recovery_codes
  password.value = ''
  await refresh()
})
</script>

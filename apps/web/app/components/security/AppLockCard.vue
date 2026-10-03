<template>
  <UCard>
    <template #header>
      <div>
        <h2 class="text-lg font-bold">
          قفل التطبيق
        </h2>
        <p class="text-sm text-(--ui-text-muted)">
          على الجهاز ده: التطبيق يفتح مقفول ويتقفل تاني لو فضل في الخلفية 5 دقايق، ويتفتح بالبصمة أو الـ PIN. مفيد على موبايلك لو حد مسكه.
        </p>
      </div>
    </template>

    <div class="space-y-5">
      <USwitch
        :model-value="lock.prefs.value.enabled"
        label="اقفل التطبيق على الجهاز ده"
        :description="canLock ? undefined : 'حط PIN أو سجّل بصمة الجهاز الأول.'"
        :disabled="!canLock"
        @update:model-value="toggle"
      />

      <div class="space-y-3">
        <h3 class="font-semibold">
          البصمة
        </h3>
        <p v-if="!platform" class="text-sm text-(--ui-text-muted)">
          الجهاز أو المتصفح ده مش بيدعم الفتح بالبصمة؛ استخدم الـ PIN.
        </p>
        <form v-else-if="!lock.prefs.value.passkey" class="flex flex-wrap items-end gap-2" @submit.prevent="register">
          <UFormField label="كلمة السر بتاعتك" class="min-w-56 flex-1">
            <UInput v-model="password" type="password" autocomplete="current-password" class="w-full" />
          </UFormField>
          <UButton type="submit" icon="i-lucide-fingerprint" label="سجّل بصمة الجهاز ده" :loading="registering" :disabled="!password" />
        </form>
        <p v-else class="flex items-center gap-2 text-sm">
          <UIcon name="i-lucide-fingerprint" class="size-5 text-(--ui-success)" />
          الجهاز ده بيفتح بالبصمة.
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />

        <ul v-if="keys.length" class="divide-y divide-(--ui-border) rounded-md border border-(--ui-border)">
          <li v-for="k in keys" :key="k.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
            <div>
              <p class="font-medium">
                {{ k.name }}
                <UBadge v-if="k.credential_id === lock.prefs.value.passkey" size="sm" variant="subtle" label="الجهاز ده" />
              </p>
              <p class="text-(--ui-text-muted)">
                اتسجلت {{ formatDate(k.created_at) }}<template v-if="k.last_used_at">
                  · آخر استخدام {{ timeAgo(k.last_used_at) }}
                </template>
              </p>
            </div>
            <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${k.name}`" @click="remove(k)" />
          </li>
        </ul>
      </div>
    </div>
  </UCard>
</template>

<script setup lang="ts">
import type { Passkey } from '~/types/api'
import type { PasskeyCreateOptions } from '~/utils/webauthn'

const api = useApi()
const toast = useToast()
const lock = useAppLock()

const { data: pinData } = await useAsyncData('account-pin', () => api<{ data: { has_pin: boolean } }>('/account/pin'))
const { data: keyData, refresh } = await useAsyncData('account-passkeys', () => api<{ data: Passkey[] }>('/account/passkeys'))
const keys = computed(() => keyData.value?.data ?? [])
const platform = ref(false)
const password = ref('')
const registering = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  lock.loadPrefs()
  platform.value = await platformPasskeyAvailable()
  // This device's key was removed (here or from another device): forget it.
  if (lock.prefs.value.passkey && !keys.value.some(k => k.credential_id === lock.prefs.value.passkey)) {
    lock.savePrefs({ passkey: null })
  }
})

const canLock = computed(() => (pinData.value?.data.has_pin ?? false) || lock.prefs.value.passkey !== null)

function toggle(on: boolean) {
  lock.savePrefs({ enabled: on && canLock.value })
  toast.add({ color: 'success', title: on ? 'التطبيق هيتقفل على الجهاز ده' : 'قفل التطبيق اتشال من الجهاز ده' })
}

function deviceName(): string {
  const ua = navigator.userAgent
  if (/iPhone/.test(ua)) return 'آيفون'
  if (/iPad/.test(ua)) return 'آيباد'
  if (/Android/.test(ua)) return 'موبايل أندرويد'
  if (/Mac/.test(ua)) return 'ماك'
  if (/Windows/.test(ua)) return 'كمبيوتر ويندوز'
  return 'جهاز'
}

async function register() {
  registering.value = true
  error.value = null
  try {
    const options = await api<{ data: PasskeyCreateOptions }>('/account/passkeys/options', { method: 'POST', body: { password: password.value } })
    const made = await createPasskey(options.data)
    if (!made) {
      return
    }
    await api('/account/passkeys', { method: 'POST', body: { ...made, name: deviceName() } })
    lock.savePrefs({ passkey: made.id })
    password.value = ''
    await refresh()
    toast.add({ color: 'success', title: 'البصمة اتسجلت على الجهاز ده' })
  }
  catch (e) {
    error.value = e instanceof Error && !('data' in e) ? e.message : apiErrorMessage(e)
  }
  finally {
    registering.value = false
  }
}

async function remove(k: Passkey) {
  try {
    await api(`/account/passkeys/${k.id}`, { method: 'DELETE' })
    if (k.credential_id === lock.prefs.value.passkey) {
      lock.savePrefs({ passkey: null, enabled: lock.prefs.value.enabled && (pinData.value?.data.has_pin ?? false) })
    }
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

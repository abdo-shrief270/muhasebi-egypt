<template>
  <div
    v-if="lock.locked.value"
    class="app-lock fixed inset-0 z-[100] flex items-center justify-center bg-(--ui-bg) p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="title"
  >
    <div class="w-full max-w-xs space-y-6 text-center">
      <div class="space-y-2">
        <UIcon :name="lock.mode.value === 'lock' ? 'i-lucide-lock-keyhole' : 'i-lucide-shield-check'" class="size-10 text-(--ui-primary)" />
        <h1 class="text-xl font-bold">
          {{ title }}
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          {{ store.session?.user.name }}
        </p>
      </div>

      <UButton
        v-if="lock.prefs.value.passkey"
        block
        size="xl"
        icon="i-lucide-fingerprint"
        label="افتح بالبصمة"
        :loading="busy === 'passkey'"
        @click="withPasskey"
      />

      <form class="space-y-3" @submit.prevent="withPin">
        <UInput
          ref="pinInput"
          v-model="pin"
          type="password"
          inputmode="numeric"
          autocomplete="off"
          maxlength="6"
          dir="ltr"
          size="xl"
          placeholder="الـ PIN"
          class="w-full"
          :ui="{ base: 'text-center tracking-[0.5em] placeholder:tracking-normal num' }"
          aria-label="الـ PIN"
        />
        <UButton type="submit" block :variant="lock.prefs.value.passkey ? 'outline' : 'solid'" label="افتح" :loading="busy === 'pin'" :disabled="!/^\d{4,6}$/.test(pin)" />
      </form>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <UButton
        v-if="lock.mode.value === 'confirm'"
        color="neutral"
        variant="ghost"
        label="رجوع"
        @click="lock.cancel()"
      />
      <UButton
        v-else
        color="neutral"
        variant="ghost"
        icon="i-lucide-log-out"
        label="مش إنت؟ اخرج"
        @click="signOut"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
/** «قفل التطبيق»: covers the app until the fingerprint / face or the PIN opens it (useAppLock). */
const lock = useAppLock()
const store = useSessionStore()
const pin = ref('')
const busy = ref<'pin' | 'passkey' | null>(null)
const error = ref<string | null>(null)
const pinInput = useTemplateRef<{ inputRef?: HTMLInputElement }>('pinInput')

const title = computed(() => lock.mode.value === 'lock' ? 'التطبيق مقفول' : 'أكّد إنك إنت')

watch(lock.locked, async (isLocked) => {
  pin.value = ''
  error.value = null
  if (isLocked) {
    await nextTick()
    pinInput.value?.inputRef?.focus()
  }
})

async function withPin() {
  busy.value = 'pin'
  error.value = null
  try {
    await lock.unlockWithPin(pin.value)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    pin.value = ''
  }
  finally {
    busy.value = null
  }
}

async function withPasskey() {
  busy.value = 'passkey'
  error.value = null
  try {
    await lock.unlockWithPasskey()
  }
  catch (e) {
    error.value = e instanceof Error && !('data' in e) ? e.message : apiErrorMessage(e)
  }
  finally {
    busy.value = null
  }
}

async function signOut() {
  lock.cancel()
  await store.logout()
  await navigateTo('/login')
}

let stop: () => void = () => {}
onMounted(() => {
  stop = lock.watchDevice()
})
onBeforeUnmount(() => stop())
</script>

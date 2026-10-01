<template>
  <div v-if="showCard" class="app-card flex flex-wrap items-center gap-3 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 sm:flex-nowrap">
    <img src="/icons/icon-192.png?v=1" alt="" class="size-12 shrink-0 rounded-xl">
    <div class="min-w-0 flex-1">
      <p class="font-bold">
        نزّل محاسبي على {{ deviceName }}
      </p>
      <p class="text-sm text-(--ui-text-muted)">
        {{ mobile ? 'يفتح بدوسة من الشاشة الرئيسية بملء الشاشة' : 'يفتح بدوسة من سطح المكتب في شباك لوحده' }}، والكاشير شغال حتى لو النت فصل.
      </p>
    </div>
    <div class="flex shrink-0 gap-2">
      <UButton icon="i-lucide-download" :label="state.canPrompt ? 'نزّل التطبيق' : 'إزاي أنزّله؟'" @click="install()" />
      <UButton color="neutral" variant="ghost" label="مش دلوقتي" @click="dismiss" />
    </div>
  </div>
</template>

<script setup lang="ts">
/** Home page: offer the app once per device (dismiss is remembered on this device). */
const { state, showCard, install, dismiss } = useInstallApp()

const mobile = computed(() => state.value.platform === 'ios' || state.value.platform === 'android')
const deviceName = computed(() => ({ ios: 'جهازك', android: 'الموبايل', desktop: 'الكمبيوتر', 'mac-safari': 'الماك' })[state.value.platform])
</script>

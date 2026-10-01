<template>
  <UModal v-model:open="state.helpOpen" title="نزّل محاسبي على الجهاز" :description="description">
    <template #body>
      <InstallSteps :platform="state.platform" />
    </template>
    <template #footer>
      <div class="flex w-full flex-wrap items-center justify-between gap-2">
        <UButton to="/settings/app" color="neutral" variant="link" icon="i-lucide-monitor-smartphone" label="الطريقة على كل الأجهزة" @click="state.helpOpen = false" />
        <UButton label="تمام" @click="state.helpOpen = false" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
/** The install steps for this device, when the browser has no install prompt (iPhone / iPad, Safari, Firefox). */
const { state } = useInstallApp()

const description = computed(() => state.value.platform === 'ios'
  ? 'يفتح من الشاشة الرئيسية زي أي تطبيق، بملء الشاشة، والكاشير شغال من غير نت.'
  : 'يفتح في شباك لوحده زي أي برنامج، والكاشير شغال من غير نت.')
</script>

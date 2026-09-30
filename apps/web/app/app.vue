<template>
  <UApp :locale="ar">
    <NuxtLoadingIndicator color="var(--ui-primary)" :height="2" :throttle="150" />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>

<script setup lang="ts">
import { ar } from '@nuxt/ui/locale'
</script>

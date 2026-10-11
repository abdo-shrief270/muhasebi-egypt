<template>
  <div class="flex min-h-dvh flex-col">
    <MarketHeader />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:py-10">
      <NuxtPage />
    </main>
    <MarketFooter />
  </div>
</template>

<script setup lang="ts">
const { load } = usePlace()
onMounted(load)

const base = String(useRuntimeConfig().public.marketUrl).replace(/\/$/, '')
const route = useRoute()
useHead(() => ({ link: [{ rel: 'canonical', href: `${base}${route.path}` }] }))
useSeoMeta({ ogType: 'website', twitterCard: 'summary_large_image' })
</script>

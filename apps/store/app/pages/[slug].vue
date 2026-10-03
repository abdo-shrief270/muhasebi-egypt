<template>
  <div :style="{ '--brand': store.color }" class="flex min-h-dvh flex-col">
    <StoreHeader :store="store" />
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-5">
      <NuxtPage />
    </main>
    <StoreFooter :store="store" />
  </div>
</template>

<script setup lang="ts">
/** Every page of one shop's store: its colour, header, footer. */
const home = await useStoreHome()
const store = computed(() => home.value.store)
const config = useRuntimeConfig()

useHead(() => ({
  titleTemplate: (title?: string) => (title && title !== store.value.name ? `${title} | ${store.value.name}` : store.value.name),
  meta: [{ name: 'theme-color', content: store.value.color }],
  link: [
    ...(store.value.logo ? [{ rel: 'icon' as const, type: 'image/webp', href: store.value.logo['128'] ?? '' }] : []),
    { rel: 'sitemap' as const, type: 'application/xml', href: `/${store.value.slug}/sitemap.xml` },
  ],
}))
useSeoMeta({
  ogSiteName: () => store.value.name,
  ogImage: () => (store.value.cover ? `${config.public.storeUrl}${store.value.cover['1600']}` : store.value.logo ? `${config.public.storeUrl}${store.value.logo['512']}` : undefined),
})
</script>

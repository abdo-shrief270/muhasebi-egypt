<template>
  <div :style="{ '--brand': store.color }" class="flex min-h-dvh flex-col">
    <p v-if="store.announcement" class="bg-brand px-4 py-2 text-center text-sm font-bold text-white">
      {{ store.announcement }}
    </p>
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
const place = useStorePlace()

useHead(() => ({
  titleTemplate: (title?: string) => (title && title !== store.value.name ? `${title} | ${store.value.name}` : store.value.name),
  meta: [{ name: 'theme-color', content: store.value.color }],
  link: [
    ...(store.value.logo ? [{ rel: 'icon' as const, type: 'image/webp', href: store.value.logo['128'] ?? '' }] : []),
    { rel: 'sitemap' as const, type: 'application/xml', href: place.onSubdomain ? '/sitemap.xml' : `/${store.value.slug}/sitemap.xml` },
  ],
}))
useSeoMeta({
  ogSiteName: () => store.value.name,
  ogImage: () => (store.value.cover ? place.media(store.value.cover['1600'] ?? '') : store.value.logo ? place.media(store.value.logo['512'] ?? '') : undefined),
})
</script>

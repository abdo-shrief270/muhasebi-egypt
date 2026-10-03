<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-extrabold">
      {{ category.name }}
    </h1>
    <ProductList :filters="{ category: category.id }" />
  </div>
</template>

<script setup lang="ts">
const home = await useStoreHome()
const route = useRoute()
const config = useRuntimeConfig()
const id = Number(route.params.id)
const found = home.value.categories.find(c => c.id === id)
if (!found) {
  throw createError({ statusCode: 404, fatal: true })
}
const category = found
const url = `${config.public.storeUrl}/${home.value.store.slug}/c/${id}`

useSeoMeta({
  title: category.name,
  description: `${category.name} في ${home.value.store.name}: ${category.products} صنف بالأسعار والتوفر.`,
  ogTitle: `${category.name} — ${home.value.store.name}`,
  ogUrl: url,
})
useHead({ link: [{ rel: 'canonical', href: url }] })
</script>

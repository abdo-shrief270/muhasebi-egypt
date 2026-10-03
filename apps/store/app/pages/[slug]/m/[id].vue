<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-extrabold">
      كل حاجة لـ <span dir="ltr">{{ title }}</span>
    </h1>
    <ProductList :filters="{ model: id }" empty="مفيش أصناف للموبايل ده دلوقتي." />
  </div>
</template>

<script setup lang="ts">
/** «كل حاجة لـ iPhone 13»: everything compatible with one phone model. */
const home = await useStoreHome()
const route = useRoute()
const config = useRuntimeConfig()
const id = Number(route.params.id)
const brand = home.value.device_brands.find(b => b.models.some(m => m.id === id))
const model = brand?.models.find(m => m.id === id)
if (!brand || !model) {
  throw createError({ statusCode: 404, fatal: true })
}
const title = brand.id === null ? model.name : `${brand.name} ${model.name}`
const url = `${config.public.storeUrl}/${home.value.store.slug}/m/${id}`

useSeoMeta({
  title: `إكسسوارات وقطع غيار ${title}`,
  description: `جرابات وسكرينات وشواحن وقطع غيار ${title} من ${home.value.store.name}، بالأسعار والتوفر.`,
  ogTitle: `كل حاجة لـ ${title} — ${home.value.store.name}`,
  ogUrl: url,
})
useHead({ link: [{ rel: 'canonical', href: url }] })
</script>

<template>
  <div class="space-y-8">
    <section class="card overflow-hidden">
      <img
        v-if="store.cover"
        :src="store.cover['800']"
        :srcset="srcset(store.cover)"
        sizes="(min-width: 1152px) 1152px, 100vw"
        :alt="store.name"
        width="1600"
        height="600"
        fetchpriority="high"
        class="aspect-[8/3] w-full object-cover"
      >
      <div class="space-y-1 p-4 sm:p-6">
        <h1 class="text-2xl font-extrabold sm:text-3xl">
          {{ store.name }}
        </h1>
        <p v-if="store.tagline" class="text-muted">
          {{ store.tagline }}
        </p>
      </div>
    </section>

    <NuxtLink v-if="store.repair_booking" :to="place.path('/repair')" class="card flex items-center gap-3 p-4 hover:border-brand">
      <span class="flex size-11 items-center justify-center rounded-xl bg-brand/10 text-brand"><StoreIcon name="wrench" /></span>
      <span class="min-w-0 flex-1">
        <span class="block font-extrabold">موبايلك محتاج صيانة؟ احجز دلوقتي</span>
        <span class="block truncate text-sm text-muted">{{ store.repair_booking.note || 'اكتب جهازك والمشكلة، والمحل هيكلّمك يحدد ميعاد.' }}</span>
      </span>
      <StoreIcon name="next" class="text-muted" />
    </NuxtLink>

    <section v-if="home.device_brands.length" class="space-y-3" aria-labelledby="pick-phone">
      <h2 id="pick-phone" class="flex items-center gap-2 text-lg font-extrabold">
        <StoreIcon name="smartphone" /> اختار موبايلك
      </h2>
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="b in home.device_brands"
          :key="b.name"
          type="button"
          class="chip"
          :class="brand === b.name ? 'chip-on' : ''"
          @click="brand = brand === b.name ? null : b.name"
        >
          {{ b.name }}
        </button>
      </div>
      <div v-if="models.length" class="flex flex-wrap gap-2">
        <NuxtLink v-for="m in models" :key="m.id" :to="place.path(`/m/${m.id}`)" class="chip">
          {{ m.name }} <span class="num ms-1 text-xs text-muted">({{ m.products }})</span>
        </NuxtLink>
      </div>
    </section>

    <section v-if="home.categories.length" class="space-y-3" aria-labelledby="categories">
      <h2 id="categories" class="text-lg font-extrabold">
        الأقسام
      </h2>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <NuxtLink v-for="c in home.categories" :key="c.id" :to="place.path(`/c/${c.id}`)" class="card flex items-center justify-between px-4 py-3 font-semibold hover:ring-brand">
          {{ c.name }}
          <span class="num text-xs text-muted">{{ c.products }}</span>
        </NuxtLink>
      </div>
    </section>

    <section v-if="store.show_latest" class="space-y-3" aria-labelledby="latest">
      <h2 id="latest" class="text-lg font-extrabold">
        وصل جديد
      </h2>
      <ProductGrid :products="home.latest" :slug="store.slug" empty="لسه مفيش أصناف في المتجر." />
    </section>
  </div>
</template>

<script setup lang="ts">
const home = await useStoreHome()
const store = computed(() => home.value.store)
const place = useStorePlace()
const brand = ref<string | null>(home.value.device_brands[0]?.name ?? null)
const models = computed(() => home.value.device_brands.find(b => b.name === brand.value)?.models ?? [])

const description = computed(() => store.value.tagline
  ?? `${store.value.name}: إكسسوارات وقطع غيار موبايلات بأسعار المحل ومخزونه الحقيقي. اختار موبايلك واطلب على واتساب.`)
useSeoMeta({
  title: () => store.value.name,
  description,
  ogTitle: () => store.value.name,
  ogDescription: description,
  ogType: 'website',
  ogUrl: () => place.url(),
})
useHead(() => ({
  link: [{ rel: 'canonical', href: place.url() }],
  script: [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Store',
      'name': store.value.name,
      'url': place.url(),
      ...(store.value.logo ? { image: place.media(store.value.logo['512'] ?? '') } : {}),
      ...(store.value.phone ? { telephone: store.value.phone } : {}),
      ...(store.value.address ? { address: { '@type': 'PostalAddress', 'streetAddress': store.value.address, 'addressCountry': 'EG' } } : {}),
      ...(store.value.hours ? { openingHours: store.value.hours } : {}),
    }),
  }],
}))
</script>

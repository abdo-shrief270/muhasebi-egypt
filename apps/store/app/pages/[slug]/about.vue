<template>
  <article class="card mx-auto max-w-2xl space-y-5 p-5 sm:p-8">
    <h1 class="text-2xl font-extrabold">
      عن {{ store.name }}
    </h1>
    <p v-if="store.about" class="whitespace-pre-line leading-relaxed">
      {{ store.about }}
    </p>
    <ul class="space-y-3">
      <li v-if="store.address" class="flex items-start gap-2">
        <StoreIcon name="map" class="mt-0.5 text-muted" />
        <span>{{ store.address }} <a v-if="store.map_url" :href="store.map_url" target="_blank" rel="noopener" class="ms-1 font-semibold text-brand">على الخريطة</a></span>
      </li>
      <li v-if="store.hours" class="flex items-start gap-2">
        <StoreIcon name="clock" class="mt-0.5 text-muted" /> {{ store.hours }}
      </li>
      <li v-if="store.phone" class="flex items-center gap-2">
        <StoreIcon name="phone" class="text-muted" />
        <a :href="`tel:${store.phone}`" class="num" dir="ltr">{{ localPhone(store.phone) }}</a>
      </li>
    </ul>
    <section v-if="store.policy" class="space-y-2">
      <h2 class="text-lg font-extrabold">
        الاستبدال والاسترجاع
      </h2>
      <p class="whitespace-pre-line leading-relaxed">
        {{ store.policy }}
      </p>
    </section>
    <a v-if="store.whatsapp" :href="whatsappLink(store.whatsapp, `السلام عليكم، جاي من متجر ${store.name}`)" target="_blank" rel="noopener" class="btn-wa">
      <StoreIcon name="whatsapp" :size="18" /> كلّمنا واتساب
    </a>
  </article>
</template>

<script setup lang="ts">
const home = await useStoreHome()
const store = computed(() => home.value.store)
useSeoMeta({ title: 'عن المحل', description: () => store.value.about?.slice(0, 160) ?? `عنوان ومواعيد ${store.value.name}.` })
</script>

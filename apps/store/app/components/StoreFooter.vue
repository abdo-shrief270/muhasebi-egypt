<template>
  <footer class="mt-12 border-t border-line bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm sm:grid-cols-3">
      <div class="space-y-2">
        <p class="text-base font-extrabold">
          {{ store.name }}
        </p>
        <p v-if="store.tagline" class="text-muted">
          {{ store.tagline }}
        </p>
        <NuxtLink :to="`/${store.slug}/about`" class="font-semibold text-brand">
          عن المحل وسياسة الاستبدال
        </NuxtLink>
      </div>
      <ul class="space-y-2">
        <li v-if="store.address" class="flex items-start gap-2">
          <StoreIcon name="map" :size="18" class="mt-0.5 text-muted" />
          <a v-if="store.map_url" :href="store.map_url" target="_blank" rel="noopener" class="hover:text-brand">{{ store.address }}</a>
          <span v-else>{{ store.address }}</span>
        </li>
        <li v-if="store.hours" class="flex items-start gap-2">
          <StoreIcon name="clock" :size="18" class="mt-0.5 text-muted" />
          {{ store.hours }}
        </li>
        <li v-if="store.phone" class="flex items-center gap-2">
          <StoreIcon name="phone" :size="18" class="text-muted" />
          <a :href="`tel:${store.phone}`" class="num hover:text-brand" dir="ltr">{{ localPhone(store.phone) }}</a>
        </li>
      </ul>
      <div class="flex flex-wrap items-start gap-2 sm:justify-end">
        <a v-if="store.whatsapp" :href="whatsappLink(store.whatsapp, `السلام عليكم، جاي من متجر ${store.name}`)" target="_blank" rel="noopener" class="btn-wa">
          <StoreIcon name="whatsapp" :size="18" /> كلّمنا واتساب
        </a>
        <a v-if="store.facebook" :href="store.facebook" target="_blank" rel="noopener" class="btn-line">فيسبوك</a>
        <a v-if="store.instagram" :href="store.instagram" target="_blank" rel="noopener" class="btn-line">إنستجرام</a>
      </div>
    </div>
    <p class="border-t border-line py-3 text-center text-xs text-muted">
      المتجر ده
      <a :href="`${config.public.siteUrl}/?utm_source=store&utm_medium=footer&utm_campaign=${store.slug}`" target="_blank" rel="noopener" class="font-semibold text-ink hover:text-brand">اتعمل بمحاسبي</a>
      — برنامج محلات الموبايلات
    </p>
  </footer>
</template>

<script setup lang="ts">
import type { PublicStore } from '~/types'

defineProps<{ store: PublicStore }>()
const config = useRuntimeConfig()
</script>

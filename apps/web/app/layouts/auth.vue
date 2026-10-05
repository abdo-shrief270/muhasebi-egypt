<template>
  <div class="min-h-dvh flex flex-col bg-(--ui-bg-muted)">
    <!-- Sign-up / sign-in are reached from the website: keep its bar so visitors can go back. -->
    <header v-if="siteNav" class="border-b border-(--ui-border) bg-(--ui-bg)">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4">
        <a :href="site" class="flex items-center gap-2 text-xl font-extrabold" aria-label="محاسبي — الموقع">
          <BrandMark class="size-8" />
          محاسبي
        </a>
        <nav class="ms-6 hidden items-center gap-1 md:flex" aria-label="الموقع">
          <UButton v-for="l in links" :key="l.to" :to="`${site}${l.to}`" color="neutral" variant="ghost" :label="l.label" />
        </nav>
        <div class="ms-auto flex items-center gap-2">
          <UButton v-if="route.path !== '/login'" to="/login" color="neutral" variant="outline" label="دخول" />
          <UButton v-if="route.path !== '/register'" to="/register" label="جرّب ببلاش" />
          <UButton :to="site" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الموقع" class="md:hidden" />
        </div>
      </div>
    </header>
    <div class="flex-1 grid place-items-center p-4">
      <div class="w-full max-w-md">
        <div v-if="!siteNav" class="flex items-center justify-center gap-2 mb-6">
          <BrandMark class="size-10" />
          <span class="text-2xl font-extrabold">محاسبي</span>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
// Receipts, repair tracking and the customers' privacy notice belong to the shop: no website bar there.
const siteNav = computed(() => route.meta.marketing === true)
const site = String(useRuntimeConfig().public.siteUrl).replace(/\/$/, '')
const links = [
  { to: '/#features', label: 'المميزات' },
  { to: '/for', label: 'لمين؟' },
  { to: '/pricing', label: 'الأسعار' },
  { to: '/docs', label: 'شرح البرنامج' },
]
</script>

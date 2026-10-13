<template>
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-40 border-b border-(--ui-border) bg-(--ui-bg)/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <span class="flex items-center gap-2 text-xl font-extrabold">
          <BrandMark class="size-8" />
          محاسبي
        </span>
        <div class="ms-auto flex items-center gap-2">
          <UButton :to="links.login" color="neutral" variant="outline" label="دخول" class="hidden sm:inline-flex" />
          <UButton :to="links.register" label="جرّب ببلاش" />
        </div>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t border-(--ui-border) bg-(--ui-bg-muted)">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-(--ui-text-muted)">
        <span>© <span class="num">{{ year }}</span> محاسبي</span>
        <NuxtLink to="/privacy" class="hover:text-primary">سياسة الخصوصية</NuxtLink>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
/**
 * Ad landing pages: the brand, sign-up / sign-in and the privacy notice only — no menu into the
 * rest of the site, so the page an ad points at says just what the ad is about.
 */
const links = useAppLinks()
const year = new Date().getFullYear()
</script>

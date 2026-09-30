<template>
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-40 border-b border-(--ui-border) bg-(--ui-bg)/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <NuxtLink to="/" class="flex items-center gap-2 text-xl font-extrabold" aria-label="محاسبي — الرئيسية">
          <UIcon name="i-lucide-smartphone" class="size-6 text-primary" />
          محاسبي
        </NuxtLink>

        <nav class="ms-6 hidden items-center gap-1 md:flex" aria-label="القائمة">
          <UButton v-for="l in nav" :key="l.to" :to="l.to" color="neutral" variant="ghost" :label="l.label" />
        </nav>

        <div class="ms-auto flex items-center gap-2">
          <ClientOnly>
            <UButton
              color="neutral"
              variant="ghost"
              square
              :icon="isDark ? 'i-lucide-sun' : 'i-lucide-moon'"
              :aria-label="isDark ? 'الوضع الفاتح' : 'الوضع الغامق'"
              @click="isDark = !isDark"
            />
          </ClientOnly>
          <UButton :to="links.login" color="neutral" variant="outline" label="دخول" class="hidden sm:inline-flex" />
          <UButton :to="links.register" label="جرّب ببلاش" />
          <UButton class="md:hidden" color="neutral" variant="ghost" square icon="i-lucide-menu" aria-label="القائمة" @click="menuOpen = true" />
        </div>
      </div>
    </header>

    <USlideover v-model:open="menuOpen" side="right" title="محاسبي">
      <template #body>
        <nav class="flex flex-col gap-1" aria-label="القائمة">
          <UButton v-for="l in nav" :key="l.to" :to="l.to" color="neutral" variant="ghost" size="lg" :label="l.label" class="justify-start" @click="menuOpen = false" />
          <USeparator class="my-2" />
          <UButton :to="links.login" color="neutral" variant="outline" size="lg" label="دخول" class="justify-center" />
          <UButton :to="links.register" size="lg" label="جرّب 14 يوم ببلاش" class="justify-center" />
        </nav>
      </template>
    </USlideover>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t border-(--ui-border) bg-(--ui-bg-muted)">
      <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-2">
          <p class="flex items-center gap-2 text-lg font-extrabold">
            <UIcon name="i-lucide-smartphone" class="size-5 text-primary" />
            محاسبي
          </p>
          <p class="text-sm text-(--ui-text-muted)">
            برنامج حسابات ومخزون وصيانة لمحلات الموبايلات والإكسسوارات في مصر.
          </p>
        </div>
        <div>
          <p class="mb-3 font-bold">
            المنتج
          </p>
          <ul class="space-y-2 text-sm text-(--ui-text-muted)">
            <li><NuxtLink to="/#features" class="hover:text-primary">المميزات</NuxtLink></li>
            <li><NuxtLink to="/pricing" class="hover:text-primary">الأسعار</NuxtLink></li>
            <li><NuxtLink to="/docs" class="hover:text-primary">شرح البرنامج</NuxtLink></li>
          </ul>
        </div>
        <div>
          <p class="mb-3 font-bold">
            الشرح
          </p>
          <ul class="space-y-2 text-sm text-(--ui-text-muted)">
            <li v-for="d in footerDocs" :key="d.slug">
              <NuxtLink :to="`/docs/${d.slug}`" class="hover:text-primary">{{ d.title }}</NuxtLink>
            </li>
          </ul>
        </div>
        <div>
          <p class="mb-3 font-bold">
            حسابك
          </p>
          <ul class="space-y-2 text-sm text-(--ui-text-muted)">
            <li><a :href="links.register" class="hover:text-primary">سجّل محلك</a></li>
            <li><a :href="links.login" class="hover:text-primary">دخول</a></li>
            <li><a :href="links.privacy" class="hover:text-primary">سياسة الخصوصية</a></li>
          </ul>
        </div>
      </div>
      <p class="border-t border-(--ui-border) py-4 text-center text-xs text-(--ui-text-muted)">
        © <span class="num">{{ year }}</span> محاسبي. كل الحقوق محفوظة.
      </p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { docs } from '~/data/docs'

const links = useAppLinks()
const menuOpen = ref(false)
const year = new Date().getFullYear()
const nav = [
  { to: '/#features', label: 'المميزات' },
  { to: '/pricing', label: 'الأسعار' },
  { to: '/docs', label: 'شرح البرنامج' },
]
const footerDocs = docs.filter(d => ['getting-started', 'pos', 'offline', 'repairs', 'billing'].includes(d.slug))

const colorMode = useColorMode()
const isDark = computed({
  get: () => colorMode.value === 'dark',
  set: v => (colorMode.preference = v ? 'dark' : 'light'),
})
</script>

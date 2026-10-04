<template>
  <div class="min-h-screen flex flex-col">
    <div v-if="offer" class="bg-primary px-4 py-2 text-center text-sm font-bold text-white">
      {{ offer.text }}
      <template v-if="offer.code">
        — كود <span class="num rounded bg-white/20 px-1.5 py-0.5" dir="ltr">{{ offer.code }}</span> في صفحة الاشتراك
      </template>
      <a :href="links.register" class="ms-2 underline underline-offset-4">سجّل دلوقتي</a>
    </div>
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
          <div v-if="socials.length" class="flex gap-1 pt-2">
            <UButton v-for="s in socials" :key="s.href" :to="s.href" target="_blank" rel="noopener me" color="neutral" variant="ghost" square :icon="s.icon" :aria-label="s.label" />
          </div>
        </div>
        <div>
          <p class="mb-3 font-bold">
            المنتج
          </p>
          <ul class="space-y-2 text-sm text-(--ui-text-muted)">
            <li><NuxtLink to="/#features" class="hover:text-primary">المميزات</NuxtLink></li>
            <li><NuxtLink to="/pricing" class="hover:text-primary">الأسعار</NuxtLink></li>
            <li><NuxtLink to="/docs" class="hover:text-primary">شرح البرنامج</NuxtLink></li>
            <li><NuxtLink to="/blog" class="hover:text-primary">مقالات لأصحاب المحلات</NuxtLink></li>
            <li v-for="s in solutions" :key="s.slug">
              <NuxtLink :to="`/for/${s.slug}`" class="hover:text-primary">برنامج {{ s.label }}</NuxtLink>
            </li>
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
      <!-- «كلّمنا»: always within reach on phones -->
      <a
        v-if="whatsappHref"
        :href="whatsappHref"
        target="_blank"
        rel="noopener"
        class="fixed bottom-4 start-4 z-40 grid size-12 sm:size-14 place-items-center rounded-full bg-(--ui-success) text-white shadow-lg transition hover:scale-105"
        aria-label="كلّمنا واتساب"
        @click="track('whatsapp_click', { place: 'floating' })"
      >
        <UIcon name="i-lucide-message-circle" class="size-6 sm:size-7" />
      </a>
      <p class="border-t border-(--ui-border) py-4 text-center text-xs text-(--ui-text-muted)">
        © <span class="num">{{ year }}</span> محاسبي. كل الحقوق محفوظة.
      </p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { docs } from '~/data/docs'
import { solutions } from '~/data/solutions'

const links = useAppLinks()
const menuOpen = ref(false)
const year = new Date().getFullYear()
const nav = [
  { to: '/#features', label: 'المميزات' },
  { to: '/for', label: 'لمين؟' },
  { to: '/pricing', label: 'الأسعار' },
  { to: '/docs', label: 'شرح البرنامج' },
  { to: '/blog', label: 'مقالات' },
]
const { track } = useTracking()
// The campaign bar; past its last day it disappears without a rebuild (checked in the browser too).
const offerConfig = useRuntimeConfig().public
const offerLive = (until: string) => !until || new Date().toISOString().slice(0, 10) <= until
const offer = ref(offerConfig.offerText && offerLive(String(offerConfig.offerUntil))
  ? { text: String(offerConfig.offerText), code: String(offerConfig.offerCode ?? '') }
  : null)
onMounted(() => {
  if (offer.value && !offerLive(String(offerConfig.offerUntil))) offer.value = null
})
const contact = useRuntimeConfig().public
const whatsappHref = contact.whatsapp ? `https://wa.me/${String(contact.whatsapp).replace(/\D/g, '')}?text=${encodeURIComponent('السلام عليكم، عايز أعرف أكتر عن برنامج محاسبي')}` : null
const socials = [
  { href: contact.facebookUrl, icon: 'i-lucide-facebook', label: 'صفحتنا على فيسبوك' },
  { href: contact.instagramUrl, icon: 'i-lucide-instagram', label: 'إنستجرام' },
  { href: contact.tiktokUrl, icon: 'i-lucide-music-2', label: 'تيك توك' },
  { href: contact.youtubeUrl, icon: 'i-lucide-youtube', label: 'يوتيوب' },
].filter(s => s.href)
const footerDocs = docs.filter(d => ['getting-started', 'pos', 'offline', 'repairs', 'billing'].includes(d.slug))

const colorMode = useColorMode()
const isDark = computed({
  get: () => colorMode.value === 'dark',
  set: v => (colorMode.preference = v ? 'dark' : 'light'),
})
</script>

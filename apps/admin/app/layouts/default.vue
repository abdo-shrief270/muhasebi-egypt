<template>
  <div class="min-h-dvh bg-(--ui-bg-muted)">
    <header class="sticky top-0 z-20 border-b border-(--ui-border) bg-(--ui-bg)">
      <div class="flex items-center gap-2 px-4 py-2.5">
        <UButton v-if="token" class="lg:hidden" color="neutral" variant="ghost" icon="i-lucide-menu" square aria-label="القائمة" @click="menuOpen = true" />
        <NuxtLink to="/" class="flex items-center gap-2">
          <UIcon name="i-lucide-shield" class="size-6 text-primary" />
          <span class="text-lg font-extrabold">محاسبي — الإدارة</span>
        </NuxtLink>
        <span v-if="token && current" class="hidden truncate text-sm text-(--ui-text-muted) sm:inline lg:hidden">· {{ current.label }}</span>
        <div class="ms-auto flex items-center gap-2">
          <UButton color="neutral" variant="ghost" :icon="colorMode.value === 'dark' ? 'i-lucide-sun' : 'i-lucide-moon'" square aria-label="الوضع" @click="colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'" />
          <UButton v-if="token" color="neutral" variant="outline" icon="i-lucide-log-out" label="خروج" class="hidden sm:inline-flex" @click="logout" />
        </div>
      </div>
    </header>

    <div class="flex">
      <aside v-if="token" class="sticky top-[57px] hidden h-[calc(100dvh-57px)] w-60 shrink-0 overflow-y-auto border-e border-(--ui-border) bg-(--ui-bg) p-3 lg:block">
        <AdminNav :groups="groups" :badges="badges" :is-active="isActive" />
      </aside>
      <main class="mx-auto w-full min-w-0 max-w-7xl p-4 lg:p-8">
        <slot />
      </main>
    </div>

    <USlideover v-if="token" v-model:open="menuOpen" side="right" title="القائمة" :ui="{ content: 'max-w-72' }">
      <template #body>
        <AdminNav :groups="groups" :badges="badges" :is-active="isActive" @navigate="menuOpen = false" />
        <UButton class="mt-4 w-full justify-center" color="neutral" variant="outline" icon="i-lucide-log-out" label="خروج" @click="logout" />
      </template>
    </USlideover>
  </div>
</template>

<script setup lang="ts">
import type { NavGroup } from '~/components/AdminNav.vue'

const route = useRoute()
const colorMode = useColorMode()
const { token, set } = useAdminToken()
const api = useAdminApi()
const menuOpen = ref(false)

const groups: NavGroup[] = [
  { items: [{ to: '/', label: 'نظرة عامة', icon: 'i-lucide-layout-dashboard' }] },
  {
    title: 'الفلوس',
    items: [
      { to: '/payments', label: 'المدفوعات', icon: 'i-lucide-banknote', badge: 'payments' },
      { to: '/pricing', label: 'الأسعار ومدى الحياة', icon: 'i-lucide-tags' },
      { to: '/coupons', label: 'الكوبونات', icon: 'i-lucide-ticket-percent' },
      { to: '/partners', label: 'الشركاء', icon: 'i-lucide-handshake', badge: 'payouts' },
    ],
  },
  {
    title: 'المحلات',
    items: [
      { to: '/shops', label: 'المحلات', icon: 'i-lucide-store' },
      { to: '/modules', label: 'الأقسام والمميزات', icon: 'i-lucide-blocks' },
      { to: '/phones', label: 'كتالوج الموبايلات', icon: 'i-lucide-smartphone', badge: 'phones' },
    ],
  },
  {
    title: 'الدعم',
    items: [
      { to: '/feedback', label: 'الملاحظات', icon: 'i-lucide-message-square-heart', badge: 'feedback' },
      { to: '/errors', label: 'أخطاء الواجهة', icon: 'i-lucide-bug', badge: 'errors' },
    ],
  },
  {
    title: 'النظام',
    items: [
      { to: '/server', label: 'السيرفر', icon: 'i-lucide-server', badge: 'server' },
      { to: '/activity', label: 'السجل', icon: 'i-lucide-history' },
    ],
  },
]
const isActive = (to: string) => (to === '/' ? route.path === '/' : route.path.startsWith(to))
const current = computed(() => groups.flatMap(g => g.items).find(i => isActive(i.to)))

// What's waiting: next to the menu entries, refreshed every minute and on each page change.
const badges = ref<Record<string, number>>({})
async function loadBadges() {
  if (!token.value) {
    return
  }
  try {
    badges.value = (await api<{ data: Record<string, number> }>('/badges')).data
  }
  catch {
    // The menu works without them.
  }
}
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  loadBadges()
  timer = setInterval(loadBadges, 60_000)
})
onBeforeUnmount(() => clearInterval(timer))
watch(() => route.path, () => {
  menuOpen.value = false
  loadBadges()
})
watch(token, loadBadges)

async function logout() {
  menuOpen.value = false
  await api('/auth/logout', { method: 'POST' }).catch(() => {})
  set(null)
  await navigateTo('/login')
}
</script>

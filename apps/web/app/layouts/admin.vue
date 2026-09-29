<template>
  <div class="min-h-dvh bg-(--ui-bg-muted)">
    <header class="sticky top-0 z-10 border-b border-(--ui-border) bg-(--ui-bg)">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 px-4 py-3">
        <UIcon name="i-lucide-shield" class="size-6 text-primary" />
        <span class="me-4 text-lg font-extrabold">محاسبي — الإدارة</span>
        <nav v-if="token" class="flex flex-wrap gap-1">
          <UButton v-for="item in nav" :key="item.to" :to="item.to" :icon="item.icon" :label="item.label" color="neutral" :variant="isActive(item.to) ? 'soft' : 'ghost'" />
        </nav>
        <div class="ms-auto flex items-center gap-2">
          <UButton color="neutral" variant="ghost" :icon="colorMode.value === 'dark' ? 'i-lucide-sun' : 'i-lucide-moon'" square aria-label="الوضع" @click="colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'" />
          <UButton v-if="token" color="neutral" variant="outline" icon="i-lucide-log-out" label="خروج" @click="logout" />
        </div>
      </div>
    </header>
    <main class="mx-auto max-w-7xl p-4 lg:p-8">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const colorMode = useColorMode()
const { token, set } = useAdminToken()
const api = useAdminApi()

const nav = [
  { to: '/admin', label: 'نظرة عامة', icon: 'i-lucide-layout-dashboard' },
  { to: '/admin/payments', label: 'المدفوعات', icon: 'i-lucide-banknote' },
  { to: '/admin/shops', label: 'المحلات', icon: 'i-lucide-store' },
]
const isActive = (to: string) => (to === '/admin' ? route.path === '/admin' : route.path.startsWith(to))

async function logout() {
  await api('/auth/logout', { method: 'POST' }).catch(() => {})
  set(null)
  await navigateTo('/admin/login')
}
</script>

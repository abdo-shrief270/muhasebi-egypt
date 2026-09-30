<template>
  <nav class="flex gap-1 overflow-x-auto rounded-(--ui-radius) bg-(--ui-bg-elevated) p-1" aria-label="الشحن والتحويلات">
    <UButton
      v-for="tab in tabs"
      :key="tab.to"
      :to="tab.to"
      size="sm"
      :icon="tab.icon"
      :label="tab.label"
      :color="route.path === tab.to ? 'primary' : 'neutral'"
      :variant="route.path === tab.to ? 'solid' : 'ghost'"
      :aria-current="route.path === tab.to ? 'page' : undefined"
      class="shrink-0"
    />
  </nav>
</template>

<script setup lang="ts">
const route = useRoute()
const store = useSessionStore()
const tabs = computed(() => [
  { to: '/services', label: 'الخدمة', icon: 'i-lucide-arrow-left-right' },
  { to: '/services/transactions', label: 'الحركات', icon: 'i-lucide-list' },
  { to: '/services/accounts', label: store.can('services.settings') ? 'المحافظ والإعدادات' : 'المحافظ والأرصدة', icon: 'i-lucide-wallet-cards' },
])
</script>

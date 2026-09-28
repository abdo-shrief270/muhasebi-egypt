<template>
  <div class="min-h-dvh flex bg-(--ui-bg-muted)">
    <aside class="hidden lg:flex w-64 shrink-0 flex-col border-e border-(--ui-border) bg-(--ui-bg)">
      <div class="h-16 flex items-center gap-2 px-5 border-b border-(--ui-border)">
        <UIcon name="i-lucide-smartphone" class="size-6 text-primary" />
        <span class="text-lg font-extrabold">محاسبي</span>
      </div>

      <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        <NuxtLink
          v-for="item in navigation"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-(--ui-text-muted) hover:bg-(--ui-bg-elevated) hover:text-(--ui-text)"
          active-class="!bg-primary/10 !text-primary"
        >
          <UIcon :name="item.icon" class="size-5" />
          {{ item.label }}
        </NuxtLink>
      </nav>

      <div class="p-3 border-t border-(--ui-border)">
        <UButton
          block
          color="neutral"
          variant="ghost"
          icon="i-lucide-log-out"
          label="تسجيل الخروج"
          @click="store.logout()"
        />
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-16 flex items-center justify-between gap-4 px-4 lg:px-8 border-b border-(--ui-border) bg-(--ui-bg)">
        <div class="min-w-0">
          <p class="font-bold truncate">
            {{ store.session?.tenant.name }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ store.session?.branches[0]?.name }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <UBadge v-if="store.session" color="neutral" variant="subtle">
            {{ store.session.tenant.shop_type_label }}
          </UBadge>
          <UAvatar :alt="store.session?.user.name" size="sm" />
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
const store = useSessionStore()

const navigation = computed(() => [
  { to: '/', label: 'الرئيسية', icon: 'i-lucide-layout-dashboard' },
  ...store.menu,
  { to: '/settings/modules', label: 'الأقسام', icon: 'i-lucide-blocks' },
])
</script>

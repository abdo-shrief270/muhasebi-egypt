<template>
  <div class="min-h-dvh flex bg-(--ui-bg-muted)">
    <aside class="hidden lg:flex w-60 shrink-0 flex-col gap-4 border-e border-(--ui-border) bg-(--ui-bg) px-3 py-4">
      <div class="flex items-center gap-2.5 px-2.5 text-primary">
        <UIcon name="i-lucide-smartphone" class="size-6" />
        <span class="text-xl font-extrabold text-(--ui-text)">محاسبي</span>
      </div>

      <div class="rounded-(--ui-radius) border border-(--ui-border) px-3 py-2">
        <p class="text-xs text-(--ui-text-muted)">
          الفرع
        </p>
        <div class="flex items-center justify-between font-bold">
          <span class="truncate">{{ store.session?.branches[0]?.name }}</span>
          <UIcon name="i-lucide-chevron-down" class="size-4 text-(--ui-text-muted)" />
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto space-y-0.5">
        <NuxtLink
          v-for="item in navigation"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-[calc(var(--ui-radius)*1.5)] px-3 py-2 font-semibold text-(--ui-text-muted) transition hover:bg-(--ui-bg-elevated) hover:text-(--ui-text)"
          :class="{ 'app-soft': isActive(item.to) }"
        >
          <UIcon :name="item.icon" class="size-5" />
          {{ item.label }}
        </NuxtLink>
      </nav>

      <UButton
        block
        color="neutral"
        variant="ghost"
        icon="i-lucide-log-out"
        label="تسجيل الخروج"
        @click="store.logout()"
      />
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <header class="h-16 flex items-center justify-between gap-4 px-4 lg:px-8 border-b border-(--ui-border) bg-(--ui-bg)">
        <div class="min-w-0">
          <p class="font-bold truncate">
            {{ store.session?.tenant.name }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ store.session?.tenant.shop_type_label }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <UTooltip text="كود محلك — ادّيه لأي محل عايز يبقى شريكك">
            <UBadge color="neutral" variant="subtle" size="lg" class="num">
              <UIcon name="i-lucide-hash" class="size-4" />{{ store.session?.tenant.code }}
            </UBadge>
          </UTooltip>
          <UButton color="neutral" variant="outline" icon="i-lucide-bell" square />
          <UAvatar :alt="store.session?.user.name" size="md" />
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
const route = useRoute()

const navigation = computed(() => [
  { to: '/', label: 'الرئيسية', icon: 'i-lucide-layout-dashboard' },
  ...store.menu,
  { to: '/settings/modules', label: 'الأقسام', icon: 'i-lucide-blocks' },
])

function isActive(to: string): boolean {
  return to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(`${to}/`)
}
</script>

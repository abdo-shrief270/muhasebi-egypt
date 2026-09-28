<template>
  <div class="min-h-dvh flex bg-(--ui-bg-muted)">
    <aside class="hidden lg:flex w-60 shrink-0 flex-col gap-4 border-e border-(--ui-border) bg-(--ui-bg) px-3 py-4">
      <div class="flex items-center gap-2.5 px-2.5 text-primary">
        <UIcon name="i-lucide-smartphone" class="size-6" />
        <span class="text-xl font-extrabold text-(--ui-text)">محاسبي</span>
      </div>

      <UDropdownMenu :items="branchItems" :disabled="(store.session?.branches.length ?? 0) < 2" :content="{ align: 'start' }">
        <button type="button" class="w-full rounded-(--ui-radius) border border-(--ui-border) px-3 py-2 text-start transition enabled:hover:bg-(--ui-bg-elevated)">
          <p class="text-xs text-(--ui-text-muted)">
            الفرع
          </p>
          <div class="flex items-center justify-between font-bold">
            <span class="truncate">{{ store.currentBranch?.name ?? '—' }}</span>
            <UIcon v-if="(store.session?.branches.length ?? 0) > 1" name="i-lucide-chevrons-up-down" class="size-4 text-(--ui-text-muted)" />
          </div>
        </button>
      </UDropdownMenu>

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

        <template v-if="settings.length">
          <p class="px-3 pt-4 pb-1 text-xs font-bold text-(--ui-text-muted)">
            الإعدادات
          </p>
          <NuxtLink
            v-for="item in settings"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 rounded-[calc(var(--ui-radius)*1.5)] px-3 py-2 font-semibold text-(--ui-text-muted) transition hover:bg-(--ui-bg-elevated) hover:text-(--ui-text)"
            :class="{ 'app-soft': isActive(item.to) }"
          >
            <UIcon :name="item.icon" class="size-5" />
            {{ item.label }}
          </NuxtLink>
        </template>
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
])

const settings = computed(() => [
  { to: '/settings/users', label: 'الموظفين', icon: 'i-lucide-users-round', show: store.can('users.manage') },
  { to: '/settings/roles', label: 'الأدوار والصلاحيات', icon: 'i-lucide-shield-check', show: store.can('roles.manage') },
  { to: '/settings/branches', label: 'الفروع', icon: 'i-lucide-store', show: store.can('branches.manage') },
  { to: '/settings/modules', label: 'الأقسام', icon: 'i-lucide-blocks', show: store.isOwner },
  { to: '/settings/audit', label: 'سجل العمليات', icon: 'i-lucide-history', show: store.can('audit.view') },
].filter(item => item.show))

const branchItems = computed(() => (store.session?.branches ?? []).map(branch => ({
  label: branch.name,
  icon: branch.id === store.session?.current_branch_id ? 'i-lucide-check' : 'i-lucide-store',
  onSelect: () => store.switchBranch(branch.id),
})))

function isActive(to: string): boolean {
  return to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(`${to}/`)
}
</script>

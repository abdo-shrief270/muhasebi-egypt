<template>
  <div class="flex h-full flex-col gap-4">
    <div class="flex items-center gap-2.5 px-2.5 text-primary">
      <BrandMark class="size-8" />
      <span class="text-xl font-extrabold text-(--ui-text-highlighted)">محاسبي</span>
    </div>

    <UDropdownMenu :items="branchItems" :disabled="branchCount < 2" :content="{ align: 'start' }">
      <button type="button" class="w-full rounded-(--ui-radius) border border-(--ui-border) px-3 py-2 text-start transition enabled:hover:bg-(--ui-bg-elevated)">
        <p class="text-xs text-(--ui-text-muted)">
          الفرع
        </p>
        <div class="flex items-center justify-between font-bold">
          <span class="truncate">{{ store.currentBranch?.name ?? '—' }}</span>
          <UIcon v-if="branchCount > 1" name="i-lucide-chevrons-up-down" class="size-4 text-(--ui-text-muted)" />
        </div>
      </button>
    </UDropdownMenu>

    <nav class="-mx-1 flex-1 space-y-3 overflow-y-auto px-1" aria-label="القائمة الرئيسية">
      <NuxtLink :to="home.to" :class="linkClass(home.to)" @click="emit('navigate')">
        <UIcon :name="home.icon" class="size-5 shrink-0" />
        {{ home.label }}
      </NuxtLink>

      <section v-for="group in groups" :key="group.key">
        <!-- A one-entry section (e.g. التقارير) needs no header of its own. -->
        <button
          v-if="group.items.length > 1"
          type="button"
          class="flex w-full items-center justify-between rounded-(--ui-radius) px-3 py-1 text-xs font-bold text-(--ui-text-dimmed) transition hover:text-(--ui-text)"
          :aria-expanded="isOpen(group)"
          @click="toggle(group.key)"
        >
          {{ group.title }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5 transition-transform" :class="{ 'rotate-90': !isOpen(group) }" />
        </button>
        <div v-show="isOpen(group)" class="mt-0.5 space-y-0.5">
          <NuxtLink v-for="item in group.items" :key="item.to" :to="item.to" :class="linkClass(item.to)" @click="emit('navigate')">
            <UIcon :name="item.icon" class="size-5 shrink-0" />
            <span class="truncate">{{ item.label }}</span>
          </NuxtLink>
        </div>
      </section>
    </nav>

    <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2">
      <p class="truncate text-sm font-bold">
        {{ store.session?.tenant.name }}
      </p>
      <p class="truncate text-xs text-(--ui-text-muted)">
        {{ store.session?.tenant.shop_type_label }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { MenuGroup } from '~/types/api'

const emit = defineEmits<{ navigate: [] }>()

const store = useSessionStore()
const route = useRoute()
const { home, groups } = useNavigation()

const branchCount = computed(() => store.session?.branches.length ?? 0)
const branchItems = computed(() => (store.session?.branches ?? []).map(branch => ({
  label: branch.name,
  icon: branch.id === store.session?.current_branch_id ? 'i-lucide-check' : 'i-lucide-store',
  onSelect: () => store.switchBranch(branch.id),
})))

// Sections the user folded away, remembered across visits.
const collapsed = useCookie<MenuGroup[]>('muhasebi_nav_collapsed', { default: () => [], maxAge: 60 * 60 * 24 * 365, sameSite: 'lax' })

function toggle(key: MenuGroup) {
  collapsed.value = collapsed.value.includes(key) ? collapsed.value.filter(k => k !== key) : [...collapsed.value, key]
}

/** A folded section still opens while you are on one of its pages. */
function isOpen(group: NavGroup): boolean {
  return group.items.length === 1 || !collapsed.value.includes(group.key) || group.items.some(item => isActivePath(route.path, item.to))
}

function linkClass(to: string) {
  return [
    'flex items-center gap-3 rounded-[calc(var(--ui-radius)*1.5)] px-3 py-2 font-semibold transition',
    isActivePath(route.path, to) ? 'app-soft' : 'text-(--ui-text-muted) hover:bg-(--ui-bg-elevated) hover:text-(--ui-text)',
  ]
}
</script>

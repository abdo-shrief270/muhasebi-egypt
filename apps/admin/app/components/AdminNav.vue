<template>
  <nav class="space-y-4" aria-label="القائمة">
    <div v-for="(group, i) in groups" :key="i">
      <p v-if="group.title" class="mb-1 px-2 text-xs font-bold text-(--ui-text-dimmed)">
        {{ group.title }}
      </p>
      <ul class="space-y-0.5">
        <li v-for="item in group.items" :key="item.to">
          <NuxtLink
            :to="item.to"
            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors"
            :class="isActive(item.to) ? 'bg-(--ui-bg-elevated) font-bold text-(--ui-text-highlighted)' : 'text-(--ui-text-muted) hover:bg-(--ui-bg-elevated)/60 hover:text-(--ui-text)'"
            :aria-current="isActive(item.to) ? 'page' : undefined"
            @click="emit('navigate')"
          >
            <UIcon :name="item.icon" class="size-4 shrink-0" :class="isActive(item.to) ? 'text-primary' : ''" />
            <span class="flex-1 truncate">{{ item.label }}</span>
            <UBadge v-if="item.badge && badges[item.badge]" :label="String(badges[item.badge])" size="sm" :color="item.badge === 'payments' || item.badge === 'payouts' ? 'primary' : 'neutral'" variant="soft" class="num" />
          </NuxtLink>
        </li>
      </ul>
    </div>
  </nav>
</template>

<script setup lang="ts">
export interface NavItem { to: string, label: string, icon: string, badge?: string }
export interface NavGroup { title?: string, items: NavItem[] }

defineProps<{ groups: NavGroup[], badges: Record<string, number>, isActive: (to: string) => boolean }>()
const emit = defineEmits<{ navigate: [] }>()
</script>

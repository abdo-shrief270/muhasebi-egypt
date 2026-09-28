<template>
  <div class="flex items-center gap-3 min-w-0">
    <div class="size-10 shrink-0 grid place-items-center rounded-(--ui-radius) app-soft">
      <UIcon name="i-lucide-store" class="size-5" />
    </div>
    <div class="min-w-0">
      <p class="font-bold truncate">
        {{ shop?.name ?? '—' }}
      </p>
      <p class="text-xs text-(--ui-text-muted)">
        <span class="num">{{ shop?.code }}</span> · <span class="num">{{ shop?.phone }}</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ShopRef } from '~/types/api'

defineProps<{ shop: ShopRef | null }>()
</script>

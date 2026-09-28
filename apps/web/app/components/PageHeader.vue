<template>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-extrabold">
        {{ title }}
      </h1>
      <p v-if="description" class="text-(--ui-text-muted)">
        {{ description }}
      </p>
    </div>
    <div class="flex gap-2">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string, description?: string }>()
</script>

<template>
  <Teleport to="body">
    <div class="print-root" dir="rtl">
      <component :is="'style'">
        @page { size: {{ pageSize }}; margin: {{ margin }}; }
      </component>
      <slot />
    </div>
  </Teleport>
</template>

<script setup lang="ts">
/**
 * What goes to the printer. Only mounted while printing: the parent renders it, calls print(),
 * and unmounts it. pageSize is a CSS @page size ("80mm 200mm", "A4"…).
 */
withDefaults(defineProps<{ pageSize?: string, margin?: string }>(), { pageSize: 'auto', margin: '0' })
</script>

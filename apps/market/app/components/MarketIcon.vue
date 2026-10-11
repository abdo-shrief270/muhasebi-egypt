<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="stroke" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name === 'pin'">
      <path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" /><circle cx="12" cy="10" r="2.5" />
    </template>
    <template v-else-if="name === 'phone'">
      <rect x="6" y="2.5" width="12" height="19" rx="2.5" /><path d="M11 18h2" />
    </template>
    <template v-else-if="name === 'search'">
      <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
    </template>
    <template v-else-if="name === 'locate'">
      <circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3" /><circle cx="12" cy="12" r="8" />
    </template>
    <template v-else-if="name === 'whatsapp'">
      <path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3z" /><path d="M9 9.5c.5 2 2 3.6 4 4.5l1.2-1.2 2 .8-.4 1.6c-3.5.3-7-3.2-6.8-6.8l1.6-.4.8 2z" />
    </template>
    <template v-else-if="name === 'call'">
      <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />
    </template>
    <template v-else-if="name === 'route'">
      <circle cx="6" cy="19" r="2" /><circle cx="18" cy="5" r="2" /><path d="M8 19h7a3.5 3.5 0 0 0 0-7H9a3.5 3.5 0 0 1 0-7h7" />
    </template>
    <template v-else-if="name === 'store'">
      <path d="M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6" />
    </template>
    <template v-else-if="name === 'x'">
      <path d="M18 6 6 18M6 6l12 12" />
    </template>
    <template v-else-if="name === 'filter'">
      <path d="M4 5h16M7 12h10M10 19h4" />
    </template>
  </svg>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ name: string, size?: number, stroke?: number }>(), { size: 18, stroke: 2 })
</script>

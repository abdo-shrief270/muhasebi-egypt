<template>
  <NuxtLink to="/cart" class="relative rounded-lg bg-ink px-3 py-2 text-paper hover:text-paper hover:opacity-90" :aria-label="`السلة (${count})`">
    السلة<ClientOnly><span v-if="count" class="num ms-1">({{ count }})</span></ClientOnly>
  </NuxtLink>
</template>

<script setup lang="ts">
const { load, count } = useCart()
onMounted(load)
</script>

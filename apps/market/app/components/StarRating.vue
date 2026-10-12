<template>
  <span class="inline-flex items-center gap-1" :aria-label="`التقييم ${rating} من 5 (${reviews} تقييم)`">
    <span aria-hidden="true" class="tracking-tight text-[#d97706]">{{ '★'.repeat(full) }}<span class="text-line-strong">{{ '★'.repeat(5 - full) }}</span></span>
    <span class="num font-semibold">{{ rating.toFixed(1) }}</span>
    <span v-if="reviews" class="text-muted">(<span class="num">{{ reviews }}</span>)</span>
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ rating: number, reviews?: number }>()
const full = computed(() => Math.round(props.rating))
</script>

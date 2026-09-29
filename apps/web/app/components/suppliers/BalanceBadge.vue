<template>
  <span class="font-bold num" :class="balance > 0 ? 'text-warning' : balance < 0 ? 'text-success' : 'text-(--ui-text-muted)'">
    {{ formatMoney(Math.abs(balance)) }}
    <span class="text-xs font-normal">{{ balance > 0 ? 'عليك' : balance < 0 ? 'ليك' : '' }}</span>
  </span>
</template>

<script setup lang="ts">
/** Supplier balance: > 0 the shop owes the supplier ("عليك"), < 0 the supplier owes the shop ("ليك"). */
defineProps<{ balance: number }>()
</script>

<template>
  <span class="font-bold num" :class="balance > 0 ? 'text-warning' : balance < 0 ? 'text-success' : 'text-(--ui-text-muted)'">
    {{ formatMoney(Math.abs(balance)) }}
    <span class="text-xs font-normal">{{ balance > 0 ? 'عليه' : balance < 0 ? 'ليه' : '' }}</span>
  </span>
</template>

<script setup lang="ts">
/** Customer balance: > 0 the customer owes the shop ("عليه"), < 0 store credit ("ليه"). */
defineProps<{ balance: number }>()
</script>

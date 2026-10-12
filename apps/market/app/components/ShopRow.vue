<template>
  <NuxtLink :to="`/s/${shop.slug}`" class="card flex items-center gap-3.5 px-4 py-3.5 text-ink hover:border-line-strong hover:text-ink">
    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-mint font-bold text-teal">{{ initial(shop.name) }}</span>
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
      <span class="truncate text-base font-bold">{{ shop.name }}</span>
      <span class="truncate text-[13px] text-muted">{{ [shop.area, shop.governorate_label].filter(Boolean).join('، ') || 'مصر' }}</span>
      <span class="flex flex-wrap items-center gap-x-2 text-[13px]">
        <span class="font-semibold text-teal"><span class="num">{{ shop.offers }}</span> صنف متاح</span>
        <StarRating v-if="shop.rating" :rating="shop.rating" :reviews="shop.reviews" />
      </span>
    </span>
    <span v-if="shop.distance_km !== null" class="flex flex-col items-end gap-0.5 text-end">
      <span class="num text-lg font-bold">{{ formatKm(shop.distance_km) }}</span>
      <span class="text-xs text-muted">{{ tripTime(shop.distance_km) }}</span>
    </span>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { ShopRow } from '~/types'

defineProps<{ shop: ShopRow }>()
</script>

<template>
  <NuxtLink :to="`/p/${encodeURIComponent(offer.id)}`" class="card group flex flex-col overflow-hidden text-ink hover:text-ink">
    <span class="relative block aspect-[4/5] overflow-hidden bg-sand">
      <img v-if="offer.image" :src="offer.image" :alt="offer.title" loading="lazy" class="size-full object-cover transition-transform group-hover:scale-[1.02]">
      <MarketIcon v-else name="phone" :size="48" :stroke="1" class="absolute inset-0 m-auto text-teal" />
      <span v-if="offer.grade" class="absolute top-2 start-2 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold shadow-sm">
        {{ GRADE_LABELS[offer.grade] ?? `درجة ${offer.grade}` }}
      </span>
    </span>
    <span class="flex flex-1 flex-col gap-1 p-3">
      <span class="line-clamp-2 text-[15px] leading-snug font-semibold">{{ offer.title }}</span>
      <span class="flex flex-wrap gap-x-2 text-xs text-muted">
        <span v-if="offer.storage_gb" class="num">{{ storageLabel(offer.storage_gb) }}</span>
        <span v-if="offer.battery_health" :class="offer.battery_health >= 85 ? 'text-ok' : ''">بطارية <bdi dir="ltr" class="num">{{ offer.battery_health }}%</bdi></span>
        <span v-if="offer.color">{{ offer.color }}</span>
      </span>
      <span class="mt-auto flex items-end justify-between gap-2 pt-1">
        <span class="num text-lg font-bold">{{ formatPrice(offer.price) }}</span>
        <span class="truncate text-xs text-muted">
          {{ offer.shop.name }}<template v-if="offer.distance_km !== null"> · <span class="num">{{ formatKm(offer.distance_km) }}</span></template>
        </span>
      </span>
    </span>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { Offer } from '~/types'

defineProps<{ offer: Offer }>()
</script>

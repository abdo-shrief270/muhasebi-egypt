<template>
  <NuxtLink :to="`/p/${encodeURIComponent(offer.id)}`" class="flex items-center gap-3 border-b border-line bg-white px-4 py-3 text-ink last:border-b-0 hover:bg-paper hover:text-ink sm:grid sm:grid-cols-[64px_minmax(0,1fr)_150px_120px_110px] sm:gap-4">
    <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-sand">
      <img v-if="offer.image" :src="offer.image" :alt="offer.title" loading="lazy" class="size-full object-cover">
      <MarketIcon v-else name="phone" :size="26" :stroke="1.4" class="text-teal" />
    </span>
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
      <span class="truncate text-[15px] font-semibold">{{ offer.title }}</span>
      <span class="truncate text-[13px] text-muted">
        {{ offer.shop.name }}<template v-if="offer.condition === 'used'"> · {{ offer.grade ? `مستعمل ${offer.grade}` : 'مستعمل' }}</template>
        <span class="sm:hidden"><template v-if="offer.distance_km !== null"> · <span class="num">{{ formatKm(offer.distance_km) }}</span></template></span>
      </span>
      <span class="text-xs font-semibold sm:hidden" :class="AVAILABILITY[offer.availability]?.class">{{ AVAILABILITY[offer.availability]?.label }}</span>
    </span>
    <span class="hidden text-[13px] text-muted sm:block">
      <template v-if="offer.distance_km !== null"><span class="num">{{ formatKm(offer.distance_km) }}</span> · {{ tripTime(offer.distance_km) }}</template>
      <template v-else>{{ [offer.place.area, offer.place.governorate_label].filter(Boolean).join('، ') }}</template>
    </span>
    <span class="hidden text-[13px] font-semibold sm:block" :class="AVAILABILITY[offer.availability]?.class">{{ AVAILABILITY[offer.availability]?.label }}</span>
    <span class="num shrink-0 text-end text-lg font-bold">{{ formatPrice(offer.price) }}</span>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { Offer } from '~/types'

defineProps<{ offer: Offer }>()
</script>

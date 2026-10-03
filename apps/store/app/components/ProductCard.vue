<template>
  <NuxtLink :to="`/${slug}/p/${product.id}`" class="card group flex flex-col overflow-hidden">
    <div class="relative aspect-square bg-soft">
      <img
        v-if="product.image"
        :src="product.image.urls['320']"
        :srcset="srcset(product.image.urls)"
        sizes="(min-width: 1024px) 240px, (min-width: 640px) 33vw, 50vw"
        :alt="product.name"
        loading="lazy"
        decoding="async"
        width="320"
        height="320"
        class="size-full object-contain p-2 transition-transform group-hover:scale-[1.03]"
      >
      <div v-else class="flex size-full items-center justify-center text-line">
        <StoreIcon name="image" :size="40" />
      </div>
      <span v-if="product.availability !== 'in'" class="absolute top-2 start-2 rounded-full bg-white/95 px-2 py-0.5 text-xs font-bold" :class="AVAILABILITY[product.availability]!.class">
        {{ AVAILABILITY[product.availability]!.label }}
      </span>
    </div>
    <div class="flex flex-1 flex-col gap-1 p-3">
      <p class="line-clamp-2 text-sm font-semibold leading-snug">
        {{ product.name }}
      </p>
      <p class="mt-auto pt-1 text-base font-extrabold text-brand">
        <span v-if="product.price_max > product.price" class="text-xs font-semibold text-muted">من </span>
        <span class="num">{{ formatPrice(product.price) }}</span>
      </p>
    </div>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { ProductCard } from '~/types'

defineProps<{ product: ProductCard, slug: string }>()
</script>

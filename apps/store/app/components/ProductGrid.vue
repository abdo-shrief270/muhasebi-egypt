<template>
  <div>
    <div v-if="products.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <ProductCard v-for="p in products" :key="p.id" :product="p" :slug="slug" />
    </div>
    <div v-else class="card px-4 py-12 text-center text-muted">
      {{ empty }}
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProductCard } from '~/types'

withDefaults(defineProps<{ products: ProductCard[], slug: string, empty?: string }>(), { empty: 'مفيش أصناف هنا دلوقتي.' })
</script>

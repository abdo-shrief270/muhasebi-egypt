<template>
  <div class="space-y-6 max-w-5xl">
    <div class="flex items-center gap-3">
      <UButton to="/products" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <h1 class="text-2xl font-extrabold">
        صنف جديد
      </h1>
    </div>
    <ProductForm @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import type { Product } from '~/types/api'

definePageMeta({ permission: 'products.manage' })

const toast = useToast()

async function onSaved(product: Product) {
  toast.add({ color: 'success', title: `اتضاف «${product.name}»` })
  await navigateTo('/products')
}
</script>

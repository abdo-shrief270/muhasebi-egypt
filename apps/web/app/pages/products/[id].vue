<template>
  <div class="space-y-6 max-w-5xl">
    <div class="flex items-center gap-3">
      <UButton to="/products" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <h1 class="flex-1 text-2xl font-extrabold">
        {{ product ? `تعديل «${product.name}»` : 'الصنف' }}
      </h1>
      <UButton
        v-if="product"
        :to="{ path: '/products/labels', query: { 'ids[]': product.variants.map(v => v.id) } }"
        color="neutral"
        variant="outline"
        icon="i-lucide-tag"
        label="اطبع ليبلات"
      />
    </div>
    <ProductForm v-if="product" :key="product.id" :product="product" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import type { Product } from '~/types/api'

definePageMeta({ permission: 'products.manage' })

const api = useApi()
const route = useRoute()
const toast = useToast()

const { data } = await useAsyncData(`product-${route.params.id}`, () => api<{ data: Product }>(`/products/${route.params.id}`))
const product = computed(() => data.value?.data)

async function onSaved(saved: Product) {
  toast.add({ color: 'success', title: `اتحفظ «${saved.name}»` })
  await navigateTo('/products')
}
</script>

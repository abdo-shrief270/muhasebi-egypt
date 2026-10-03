<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-extrabold">
      نتايج «{{ q }}»
    </h1>
    <ProductList :key="q" :filters="{ q }" empty="مالقيناش حاجة بالاسم ده. جرّب كلمة تانية أو اسم الموبايل." />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const q = computed(() => String(route.query.q ?? '').slice(0, 100))
useSeoMeta({ title: () => `بحث: ${q.value}`, robots: 'noindex, follow' })
</script>

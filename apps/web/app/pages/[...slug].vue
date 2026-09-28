<template>
  <div class="grid place-items-center py-24 text-center">
    <div class="space-y-3">
      <UIcon name="i-lucide-hammer" class="size-12 text-(--ui-text-muted)" />
      <h1 class="text-xl font-bold">
        الصفحة دي لسه تحت الإنشاء
      </h1>
      <p class="text-(--ui-text-muted)">
        {{ route.path }}
      </p>
      <UButton to="/" label="الرجوع للرئيسية" variant="outline" />
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
</script>

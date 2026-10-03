<template>
  <main class="flex min-h-dvh flex-col items-center justify-center gap-4 p-6 text-center">
    <h1 class="text-2xl font-extrabold">
      متاجر محاسبي
    </h1>
    <p class="max-w-md text-muted">
      كل محل موبايلات بيستخدم محاسبي يقدر يفتح متجره هنا بأصنافه وأسعاره ومخزونه الحقيقي. افتح لينك المحل اللي معاك.
    </p>
    <a :href="config.public.siteUrl" class="btn-brand">اعرف أكتر عن محاسبي</a>
  </main>
</template>

<script setup lang="ts">
const config = useRuntimeConfig()
useSeoMeta({ title: 'متاجر محاسبي', description: 'متاجر محلات الموبايلات والإكسسوارات على محاسبي.' })
</script>

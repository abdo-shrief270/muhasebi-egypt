<template>
  <footer class="mt-16 border-t border-line">
    <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-3 px-4 py-6 text-sm text-muted">
      <span>سوق محاسبي · المحلات بتبيع بنفسها، والدفع للمحل.</span>
      <a :href="`${siteUrl}/?utm_source=souq&utm_medium=footer`">عندك محل؟ اظهر هنا</a>
    </div>
  </footer>
</template>

<script setup lang="ts">
const siteUrl = String(useRuntimeConfig().public.siteUrl).replace(/\/$/, '')
</script>

<template>
  <main class="flex min-h-dvh flex-col items-center justify-center gap-4 p-6 text-center">
    <p class="text-6xl font-extrabold text-line">
      {{ error.statusCode }}
    </p>
    <h1 class="text-xl font-bold">
      {{ error.statusCode === 404 ? 'الصفحة دي مش موجودة' : 'حصلت مشكلة، جرّب تاني بعد شوية' }}
    </h1>
    <p class="text-muted">
      ممكن يكون المتجر مقفول دلوقتي أو الصنف اتشال.
    </p>
    <button type="button" class="btn-line" @click="clearError({ redirect: home })">
      ارجع للمتجر
    </button>
  </main>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const place = useStorePlace()
const home = place.slug && props.error.statusCode !== 404 ? place.path() : '/'
useHead({ title: 'مش موجود' })
</script>

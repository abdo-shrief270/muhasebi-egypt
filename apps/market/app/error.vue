<template>
  <div class="flex min-h-dvh flex-col">
    <MarketHeader />
    <main class="mx-auto grid w-full max-w-xl flex-1 place-items-center px-4 py-16 text-center">
      <div class="space-y-4">
        <p class="text-2xl font-bold">
          {{ error?.statusCode === 404 ? 'الصفحة دي مش موجودة' : error?.statusCode === 503 ? 'السوق واقف دقيقة' : 'حصلت مشكلة' }}
        </p>
        <p class="text-muted">
          {{ error?.statusCode === 404 ? 'يمكن الصنف خلص أو المحل شاله.' : 'جرّب كمان شوية.' }}
        </p>
        <button type="button" class="btn-teal" @click="clearError({ redirect: '/' })">
          ارجع للرئيسية
        </button>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app'

defineProps<{ error: NuxtError | null }>()
</script>

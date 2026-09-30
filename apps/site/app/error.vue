<template>
  <UApp :locale="ar">
    <NuxtLayout>
      <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center">
        <p class="num text-6xl font-extrabold text-primary">
          {{ error?.statusCode ?? 404 }}
        </p>
        <h1 class="mt-4 text-2xl font-extrabold">
          الصفحة دي مش موجودة
        </h1>
        <p class="mt-2 text-(--ui-text-muted)">
          يمكن اللينك اتغيّر. ارجع للرئيسية أو شوف الشرح.
        </p>
        <div class="mt-8 flex gap-3">
          <UButton label="الرئيسية" @click="clearError({ redirect: '/' })" />
          <UButton color="neutral" variant="outline" label="شرح البرنامج" @click="clearError({ redirect: '/docs' })" />
        </div>
      </div>
    </NuxtLayout>
  </UApp>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app'
import { ar } from '@nuxt/ui/locale'

defineProps<{ error: NuxtError | null }>()
useSeoMeta({ title: 'مش موجودة', robots: 'noindex' })
</script>

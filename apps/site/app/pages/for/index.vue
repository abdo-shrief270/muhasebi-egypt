<template>
  <div class="mx-auto max-w-6xl px-4 py-14">
    <div class="mx-auto mb-10 max-w-2xl text-center">
      <h1 class="text-3xl font-extrabold sm:text-4xl">
        محاسبي لكل نوع محل موبايلات
      </h1>
      <p class="mt-3 text-lg text-(--ui-text-muted)">
        نفس البرنامج، وكل محل بيفتح الأقسام اللي تناسب شغله.
      </p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink v-for="s in solutions" :key="s.slug" :to="`/for/${s.slug}`" class="group rounded-2xl border border-(--ui-border) p-6 transition hover:-translate-y-0.5 hover:shadow-[var(--app-shadow)]">
        <span class="mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-(--app-primary-soft)">
          <UIcon :name="s.icon" class="size-5 text-primary" />
        </span>
        <p class="text-lg font-extrabold group-hover:text-primary">
          {{ s.label }}
        </p>
        <p class="mt-2 text-sm leading-relaxed text-(--ui-text-muted)">
          {{ s.description }}
        </p>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { solutions } from '~/data/solutions'

usePageSeo({
  title: 'برنامج لمحلات الموبايلات: صيانة، إكسسوارات، بيع موبايلات، جملة، استيراد',
  description: 'محاسبي لكل نوع محل موبايلات في مصر: محلات الصيانة، الإكسسوارات، بيع الموبايلات الجديدة والمستعملة، تجار الجملة والمستوردين. اختار شغلك واعرف إيه اللي هيتغيّر.',
  path: '/for',
  jsonLd: [breadcrumbs([{ name: 'محاسبي لكل نوع محل', path: '/for' }])],
})
</script>

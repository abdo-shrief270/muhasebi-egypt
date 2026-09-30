<template>
  <div class="mx-auto max-w-6xl px-4 py-16">
    <div class="mx-auto mb-12 max-w-2xl text-center">
      <UIcon name="i-lucide-book-open" class="mb-3 size-8 text-primary" />
      <h1 class="text-4xl font-extrabold">
        شرح البرنامج
      </h1>
      <p class="mt-3 text-lg text-(--ui-text-muted)">
        كل حاجة في محاسبي خطوة بخطوة، بصور من البرنامج نفسه.
      </p>
    </div>

    <div class="space-y-12">
      <section v-for="(title, key) in docGroups" :key="key">
        <h2 class="mb-4 text-xl font-extrabold">
          {{ title }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="d in docs.filter(x => x.group === key)"
            :key="d.slug"
            :to="`/docs/${d.slug}`"
            class="group flex gap-4 rounded-2xl border border-(--ui-border) p-5 transition hover:border-primary hover:shadow-[var(--app-shadow)]"
          >
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-(--app-primary-soft)">
              <UIcon :name="d.icon" class="size-5 text-primary" />
            </span>
            <span>
              <span class="block font-extrabold group-hover:text-primary">{{ d.title }}</span>
              <span class="mt-1 block text-sm text-(--ui-text-muted)">{{ d.summary }}</span>
            </span>
          </NuxtLink>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { docGroups, docs } from '~/data/docs'

useSeoMeta({
  title: 'شرح البرنامج',
  description: 'شرح محاسبي خطوة بخطوة: الكاشير، البيع من غير نت، المخزون وIMEI، الصيانة، الآجل، الخزنة، التقارير والاشتراك.',
  ogImage: '/og.png',
})
</script>

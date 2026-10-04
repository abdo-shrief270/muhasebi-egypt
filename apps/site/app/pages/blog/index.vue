<template>
  <div class="mx-auto max-w-6xl px-4 py-14">
    <div class="mx-auto mb-10 max-w-2xl text-center">
      <h1 class="text-3xl font-extrabold sm:text-4xl">
        مقالات لأصحاب محلات الموبايلات
      </h1>
      <p class="mt-3 text-lg text-(--ui-text-muted)">
        إزاي تحسب مكسبك، تجرد محلك، تنظّم الصيانة، وتبيع آجل من غير ما فلوسك تضيع.
      </p>
    </div>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink v-for="a in sorted" :key="a.slug" :to="`/blog/${a.slug}`" class="group overflow-hidden rounded-2xl border border-(--ui-border) transition hover:-translate-y-0.5 hover:shadow-[var(--app-shadow)]">
        <img :src="a.image" :alt="a.title" width="1440" height="900" loading="lazy" class="aspect-[16/10] w-full object-cover object-top">
        <div class="p-5">
          <p class="text-lg font-extrabold leading-relaxed group-hover:text-primary">
            {{ a.title }}
          </p>
          <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-(--ui-text-muted)">
            {{ a.description }}
          </p>
          <p class="mt-3 text-xs text-(--ui-text-dimmed)">
            <span class="num">{{ a.minutes }}</span> دقايق قراية
          </p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { articles } from '~/data/articles'

const sorted = [...articles].sort((a, b) => b.published.localeCompare(a.published))
const { siteUrl } = useSiteUrls()
usePageSeo({
  title: 'مقالات لأصحاب محلات الموبايلات والإكسسوارات والصيانة',
  description: 'نصايح عملية لأصحاب محلات الموبايلات في مصر: حساب المكسب الحقيقي، جرد الإكسسوارات، تنظيم الصيانة، شراء المستعمل بأمان، الآجل والتقسيط، والشحن والتحويلات.',
  path: '/blog',
  jsonLd: [
    breadcrumbs([{ name: 'مقالات', path: '/blog' }]),
    {
      '@type': 'Blog',
      'name': 'مقالات محاسبي',
      'url': `${siteUrl}/blog`,
      'inLanguage': 'ar-EG',
      'publisher': { '@id': `${siteUrl}/#org` },
      'blogPost': sorted.map(a => ({ '@type': 'BlogPosting', 'headline': a.title, 'url': `${siteUrl}/blog/${a.slug}`, 'datePublished': a.published })),
    },
  ],
})
</script>

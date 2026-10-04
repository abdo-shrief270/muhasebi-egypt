<template>
  <div class="mx-auto max-w-3xl px-4 py-12">
    <NuxtLink to="/blog" class="text-sm text-(--ui-text-muted) hover:text-primary">
      مقالات
    </NuxtLink>
    <h1 class="mt-2 text-3xl font-extrabold leading-[1.45] sm:text-4xl">
      {{ page.title }}
    </h1>
    <p class="mt-3 text-sm text-(--ui-text-muted)">
      <time :datetime="page.updated ?? page.published">{{ formatDate(page.updated ?? page.published) }}</time>
      · <span class="num">{{ page.minutes }}</span> دقايق قراية
    </p>
    <div class="shot mt-6">
      <img :src="page.image" :alt="page.title" width="1440" height="900" fetchpriority="high">
    </div>

    <DocBlocks class="mt-8" :blocks="page.blocks" />

    <div class="mt-12 rounded-2xl bg-(--ui-bg-muted) p-6 text-center">
      <p class="text-lg font-extrabold">
        خلّي البرنامج يعملها بدالك
      </p>
      <p class="mt-1 text-sm text-(--ui-text-muted)">
        محاسبي معمول لمحلات الموبايلات في مصر. جرّبه 14 يوم ببلاش من غير كارت.
      </p>
      <div class="mt-4 flex flex-wrap justify-center gap-2">
        <UButton :to="links.register" label="سجّل محلك" />
        <UButton v-if="audience" :to="`/for/${audience.slug}`" color="neutral" variant="outline" :label="`محاسبي لـ${audience.label}`" />
      </div>
    </div>

    <nav v-if="more.length" class="mt-12" aria-label="مقالات تانية">
      <p class="mb-3 font-bold">
        اقرا كمان
      </p>
      <ul class="space-y-2">
        <li v-for="a in more" :key="a.slug">
          <NuxtLink :to="`/blog/${a.slug}`" class="text-primary hover:underline">
            {{ a.title }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { articleBySlug, articles } from '~/data/articles'
import { solutionBySlug } from '~/data/solutions'

const route = useRoute()
const page = articleBySlug(String(route.params.slug))
if (!page) {
  throw createError({ statusCode: 404, statusMessage: 'المقال مش موجود', fatal: true })
}
const links = useAppLinks()
const audience = page.forSlug ? solutionBySlug(page.forSlug) : undefined
const more = articles.filter(a => a.slug !== page.slug).slice(0, 3)
const formatDate = (d: string) => new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(d))

const { siteUrl } = useSiteUrls()
usePageSeo({
  title: page.seoTitle,
  description: page.description,
  path: `/blog/${page.slug}`,
  type: 'article',
  image: page.image,
  jsonLd: [
    breadcrumbs([{ name: 'مقالات', path: '/blog' }, { name: page.title, path: `/blog/${page.slug}` }]),
    {
      '@type': 'BlogPosting',
      'headline': page.title,
      'description': page.description,
      'image': `${siteUrl}${page.image}`,
      'datePublished': page.published,
      'dateModified': page.updated ?? page.published,
      'inLanguage': 'ar-EG',
      'mainEntityOfPage': `${siteUrl}/blog/${page.slug}`,
      'author': { '@id': `${siteUrl}/#org` },
      'publisher': { '@id': `${siteUrl}/#org` },
    },
  ],
})
</script>

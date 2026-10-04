<template>
  <div>
    <section :style="{ background: 'var(--app-hero)' }">
      <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 lg:grid-cols-[1fr_1.1fr]">
        <div class="space-y-5">
          <NuxtLink to="/for" class="text-sm text-(--ui-text-muted) hover:text-primary">
            محاسبي لكل نوع محل
          </NuxtLink>
          <UBadge color="primary" variant="subtle" size="lg" :icon="page.icon" :label="`لـ${page.label}`" />
          <h1 class="text-3xl font-extrabold leading-[1.4] sm:text-4xl">
            {{ page.h1 }}
          </h1>
          <p class="text-lg leading-loose text-(--ui-text-muted)">
            {{ page.intro }}
          </p>
          <div class="flex flex-wrap gap-3">
            <UButton :to="links.register" size="xl" icon="i-lucide-rocket" label="جرّب 14 يوم ببلاش" />
            <ContactWhatsapp :text="`عايز أعرف أكتر عن محاسبي لـ${page.label}`" place="for" />
          </div>
        </div>
        <div class="shot">
          <img :src="page.shots[0]!.src" :alt="page.shots[0]!.alt" width="1440" height="900" fetchpriority="high">
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16">
      <h2 class="mb-8 text-center text-3xl font-extrabold">
        مشاكل كل يوم في {{ page.label }}
      </h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="p in page.pains" :key="p.title" class="rounded-2xl border border-(--ui-border) p-5">
          <p class="flex items-center gap-2 font-extrabold">
            <UIcon name="i-lucide-circle-alert" class="size-5 text-warning" />{{ p.title }}
          </p>
          <p class="mt-2 text-sm leading-relaxed text-(--ui-text-muted)">
            {{ p.text }}
          </p>
        </div>
      </div>
    </section>

    <section class="bg-(--ui-bg-muted) py-16">
      <div class="mx-auto max-w-6xl px-4">
        <h2 class="mb-8 text-center text-3xl font-extrabold">
          ومع محاسبي
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <component
            :is="g.doc ? NuxtLink : 'div'"
            v-for="g in page.gains"
            :key="g.title"
            :to="g.doc ? `/docs/${g.doc}` : undefined"
            class="rounded-2xl border border-(--ui-border) bg-(--ui-bg) p-5 transition hover:shadow-[var(--app-shadow)]"
          >
            <p class="flex items-center gap-2 font-extrabold">
              <UIcon name="i-lucide-check" class="size-5 text-primary" />{{ g.title }}
            </p>
            <p class="mt-2 text-sm leading-relaxed text-(--ui-text-muted)">
              {{ g.text }}
            </p>
          </component>
        </div>
        <div v-if="page.shots.length > 1" class="mt-10 grid items-end gap-6" :class="page.shots.length > 2 ? 'md:grid-cols-[2fr_1fr]' : ''">
          <div v-for="s in page.shots.slice(1)" :key="s.src" :class="s.phone ? 'phone mx-auto w-52' : 'shot'">
            <img :src="s.src" :alt="s.alt" :width="s.phone ? 390 : 1440" :height="s.phone ? 844 : 900" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 py-16 text-center">
      <h2 class="text-2xl font-extrabold">
        الباقة المناسبة: {{ plan?.name ?? planNames[page.plan] }}
      </h2>
      <p class="mt-3 text-(--ui-text-muted)">
        {{ page.planWhy }}
      </p>
      <p v-if="plan" class="mt-4 text-3xl font-extrabold">
        <span class="num">{{ pounds(plan.monthly) }}</span> ج <span class="text-base font-medium text-(--ui-text-muted)">في الشهر شامل الضريبة</span>
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <UButton :to="links.register" size="xl" label="ابدأ التجربة المجانية" />
        <UButton to="/pricing" size="xl" color="neutral" variant="outline" label="كل الأسعار" />
      </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-16">
      <h2 class="mb-6 text-center text-2xl font-extrabold">
        أسئلة {{ page.label }}
      </h2>
      <UAccordion :items="page.faqs.map(f => ({ label: f.q, content: f.a }))" type="multiple" :ui="{ trigger: 'text-base font-bold py-4', body: 'text-(--ui-text-muted) leading-loose' }" />
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-20">
      <p class="mb-4 text-center font-bold text-(--ui-text-muted)">
        محاسبي كمان لـ
      </p>
      <div class="flex flex-wrap justify-center gap-2">
        <UButton v-for="o in others" :key="o.slug" :to="`/for/${o.slug}`" :icon="o.icon" :label="o.label" color="neutral" variant="outline" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { NuxtLink } from '#components'
import { solutionBySlug, solutions } from '~/data/solutions'

const route = useRoute()
const page = solutionBySlug(String(route.params.slug))
if (!page) {
  throw createError({ statusCode: 404, statusMessage: 'الصفحة مش موجودة', fatal: true })
}
const links = useAppLinks()
const others = solutions.filter(s => s.slug !== page.slug)
const { data: plans } = await usePlans()
const plan = computed(() => plans.value?.data.plans.find(p => p.key === page.plan))
// Until the live prices load (or when the API isn't reachable at build time).
const planNames = { accessories: 'إكسسوارات', repair: 'صيانة', pro: 'برو', business: 'بيزنس' } as const

const { siteUrl } = useSiteUrls()
usePageSeo({
  title: page.seoTitle,
  description: page.description,
  path: `/for/${page.slug}`,
  image: page.shots[0]!.src,
  jsonLd: [
    breadcrumbs([{ name: 'محاسبي لكل نوع محل', path: '/for' }, { name: page.label, path: `/for/${page.slug}` }]),
    {
      '@type': 'WebPage',
      'name': page.seoTitle,
      'description': page.description,
      'url': `${siteUrl}/for/${page.slug}`,
      'inLanguage': 'ar-EG',
      'about': { '@type': 'SoftwareApplication', 'name': 'محاسبي', 'applicationCategory': 'BusinessApplication' },
      'audience': { '@type': 'BusinessAudience', 'audienceType': page.label },
      'isPartOf': { '@id': `${siteUrl}/#website` },
    },
    { '@type': 'FAQPage', 'mainEntity': page.faqs.map(f => ({ '@type': 'Question', 'name': f.q, 'acceptedAnswer': { '@type': 'Answer', 'text': f.a } })) },
  ],
})
</script>

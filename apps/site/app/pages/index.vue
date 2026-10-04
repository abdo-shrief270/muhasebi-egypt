<template>
  <div>
    <!-- Hero -->
    <section class="relative overflow-hidden" :style="{ background: 'var(--app-hero)' }">
      <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-16 pt-14 lg:grid-cols-[1fr_1.15fr] lg:pt-20">
        <div class="space-y-6">
          <UBadge color="primary" variant="subtle" size="lg" icon="i-lucide-sparkles" label="معمول مخصوص لمحلات الموبايلات" />
          <h1 class="text-4xl font-extrabold leading-[1.35] sm:text-5xl">
            محلك كله في برنامج واحد:
            <span class="text-primary">الكاشير والمخزون والصيانة</span>
          </h1>
          <p class="text-lg leading-loose text-(--ui-text-muted)">
            محاسبي بيعرّفك بعت كام وكسبت كام النهارده، البضاعة فين، والفلوس عند مين. سريع على الباركود، بيحفظ IMEI كل جهاز، وبيكمّل بيع حتى لو النت فصل.
          </p>
          <div class="flex flex-wrap gap-3">
            <UButton :to="links.register" size="xl" icon="i-lucide-rocket" label="جرّب 14 يوم ببلاش" />
            <UButton to="/docs" size="xl" color="neutral" variant="outline" icon="i-lucide-book-open" label="اتفرج على الشرح" />
          </div>
          <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-(--ui-text-muted)">
            <span v-for="r in reassurance" :key="r" class="flex items-center gap-1"><UIcon name="i-lucide-shield-check" class="size-4 text-primary" />{{ r }}</span>
          </p>
          <ul class="grid grid-cols-2 gap-2 text-sm font-bold">
            <li v-for="p in heroPoints" :key="p" class="flex items-center gap-2">
              <UIcon name="i-lucide-check" class="size-4 text-primary" />{{ p }}
            </li>
          </ul>
        </div>
        <div class="relative">
          <div class="shot">
            <img src="/screens/dashboard.webp" alt="الشاشة الرئيسية في محاسبي: مبيعات ومكسب النهارده ورسم المبيعات" width="1440" height="900" fetchpriority="high">
          </div>
          <div class="phone absolute -bottom-10 -start-4 hidden w-40 sm:block lg:-start-10 lg:w-44">
            <img src="/screens/m-pos.webp" alt="الكاشير على الموبايل" width="390" height="844" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- Who it's for -->
    <section class="mx-auto max-w-6xl px-4 pt-20">
      <div class="mx-auto mb-8 max-w-2xl text-center">
        <h2 class="text-3xl font-extrabold">
          محلك بيشتغل في إيه؟
        </h2>
        <p class="mt-3 text-(--ui-text-muted)">
          نفس البرنامج، وكل محل بيفتح الأقسام اللي تناسب شغله.
        </p>
      </div>
      <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <NuxtLink v-for="s in solutions" :key="s.slug" :to="`/for/${s.slug}`" class="group rounded-2xl border border-(--ui-border) p-4 text-center transition hover:-translate-y-0.5 hover:border-primary">
          <span class="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-xl bg-(--app-primary-soft)">
            <UIcon :name="s.icon" class="size-6 text-primary" />
          </span>
          <p class="font-extrabold group-hover:text-primary">
            {{ s.label }}
          </p>
        </NuxtLink>
      </div>
    </section>

    <!-- Showcase -->
    <section class="mx-auto max-w-6xl px-4 py-20">
      <div class="mx-auto mb-10 max-w-2xl text-center">
        <h2 class="text-3xl font-extrabold">
          كل اللي بيحصل في المحل، قدامك
        </h2>
        <p class="mt-3 text-(--ui-text-muted)">
          دي شاشات حقيقية من البرنامج لمحل تجريبي.
        </p>
      </div>
      <div class="mb-8 flex flex-wrap justify-center gap-2" role="tablist" aria-label="أقسام البرنامج">
        <UButton
          v-for="s in showcases"
          :key="s.key"
          role="tab"
          :aria-selected="active === s.key"
          :icon="s.icon"
          :label="s.label"
          :color="active === s.key ? 'primary' : 'neutral'"
          :variant="active === s.key ? 'solid' : 'outline'"
          size="lg"
          @click="active = s.key"
        />
      </div>
      <div v-for="s in showcases" v-show="active === s.key" :key="s.key" class="grid items-center gap-10 lg:grid-cols-[1fr_1.6fr]" role="tabpanel">
        <div class="space-y-4">
          <h3 class="text-2xl font-extrabold">
            {{ s.title }}
          </h3>
          <p class="leading-loose text-(--ui-text-muted)">
            {{ s.text }}
          </p>
          <ul class="space-y-2">
            <li v-for="p in s.points" :key="p" class="flex items-start gap-2">
              <UIcon name="i-lucide-check" class="mt-1.5 size-4 shrink-0 text-primary" />{{ p }}
            </li>
          </ul>
          <UButton :to="`/docs/${s.doc}`" variant="link" trailing-icon="i-lucide-arrow-left" label="اعرف أكتر" class="px-0" />
        </div>
        <div class="shot">
          <img :src="s.image" :alt="s.title" width="1440" height="900" loading="lazy">
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="scroll-mt-16 bg-(--ui-bg-muted) py-20">
      <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto mb-12 max-w-2xl text-center">
          <h2 class="text-3xl font-extrabold">
            كل اللي محلك محتاجه، ومفيش حاجة زيادة
          </h2>
          <p class="mt-3 text-(--ui-text-muted)">
            افتح الأقسام اللي تناسب شغلك، واقفل المميزات اللي مش محتاجها.
          </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="f in features"
            :key="f.title"
            :to="f.doc ? `/docs/${f.doc}` : undefined"
            class="group rounded-2xl border border-(--ui-border) bg-(--ui-bg) p-6 transition hover:-translate-y-0.5 hover:shadow-[var(--app-shadow)]"
          >
            <span class="mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-(--app-primary-soft)">
              <UIcon :name="f.icon" class="size-5 text-primary" />
            </span>
            <p class="text-lg font-extrabold group-hover:text-primary">
              {{ f.title }}
            </p>
            <p class="mt-2 text-sm leading-relaxed text-(--ui-text-muted)">
              {{ f.text }}
            </p>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- Offline -->
    <section class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 lg:grid-cols-2">
      <div class="shot order-last lg:order-first">
        <img src="/screens/offline-pos.webp" alt="الكاشير بيبيع والنت فاصل" width="1440" height="900" loading="lazy">
      </div>
      <div class="space-y-4">
        <UBadge color="warning" variant="subtle" icon="i-lucide-wifi-off" label="النت فصل؟ عادي" />
        <h2 class="text-3xl font-extrabold">
          الزبون مش هيستنى النت
        </h2>
        <p class="leading-loose text-(--ui-text-muted)">
          الكاشير بيكمّل بيع من الأصناف والأسعار المحفوظة على الجهاز، والفواتير بتتحفظ وتتبعت لوحدها بالترتيب أول ما النت يرجع، بنفس وقت البيع وبالسعر اللي العميل دفعه.
        </p>
        <UButton to="/docs/offline" variant="link" trailing-icon="i-lucide-arrow-left" label="إزاي بيشتغل" class="px-0" />
      </div>
    </section>

    <!-- Everywhere -->
    <section class="bg-(--ui-bg-muted) py-20">
      <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto mb-12 max-w-2xl text-center">
          <h2 class="text-3xl font-extrabold">
            على كمبيوتر المحل، وعلى موبايلك وإنت برا
          </h2>
          <p class="mt-3 text-(--ui-text-muted)">
            من أي متصفح، فاتح أو غامق زي ما تحب.
          </p>
        </div>
        <div class="grid items-end gap-6 md:grid-cols-[2fr_1fr_1fr]">
          <div class="shot">
            <img src="/screens/pos-dark.webp" alt="الكاشير في الوضع الغامق" width="1440" height="900" loading="lazy">
          </div>
          <div class="phone mx-auto w-44 md:w-full">
            <img src="/screens/m-dashboard.webp" alt="الشاشة الرئيسية على الموبايل" width="390" height="844" loading="lazy">
          </div>
          <div class="phone mx-auto w-44 md:w-full">
            <img src="/screens/m-repairs.webp" alt="الصيانة على الموبايل" width="390" height="844" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- Before / after -->
    <section class="mx-auto max-w-5xl px-4 py-20">
      <h2 class="mb-3 text-center text-3xl font-extrabold">
        الكشكول والإكسل، ولا محاسبي؟
      </h2>
      <p class="mb-10 text-center text-(--ui-text-muted)">
        نفس الشغل اللي بتعمله كل يوم، من غير الحسابات اللي في دماغك.
      </p>
      <div class="overflow-hidden rounded-2xl border border-(--ui-border)">
        <table class="w-full text-sm sm:text-base">
          <thead class="bg-(--ui-bg-muted)">
            <tr>
              <th class="p-3 text-start font-bold" />
              <th class="p-3 text-start font-bold text-(--ui-text-muted)">
                الكشكول / الإكسل
              </th>
              <th class="p-3 text-start font-extrabold text-primary">
                محاسبي
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in comparison" :key="row.what" class="border-t border-(--ui-border)">
              <td class="p-3 font-bold">
                {{ row.what }}
              </td>
              <td class="p-3 text-(--ui-text-muted)">
                <UIcon name="i-lucide-x" class="me-1 size-4 align-[-3px] text-error" />{{ row.before }}
              </td>
              <td class="p-3">
                <UIcon name="i-lucide-check" class="me-1 size-4 align-[-3px] text-primary" />{{ row.after }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- How -->
    <section class="mx-auto max-w-6xl px-4 py-20">
      <h2 class="mb-12 text-center text-3xl font-extrabold">
        تبدأ في 3 خطوات
      </h2>
      <ol class="grid gap-6 md:grid-cols-3">
        <li v-for="(s, i) in steps" :key="s.title" class="rounded-2xl border border-(--ui-border) p-6">
          <span class="mb-4 flex items-center gap-3">
            <span class="num inline-flex size-9 items-center justify-center rounded-full bg-primary font-extrabold text-white">{{ i + 1 }}</span>
            <UIcon :name="s.icon" class="size-5 text-primary" />
          </span>
          <p class="text-lg font-extrabold">
            {{ s.title }}
          </p>
          <p class="mt-2 text-sm text-(--ui-text-muted)">
            {{ s.text }}
          </p>
        </li>
      </ol>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="scroll-mt-16 bg-(--ui-bg-muted) py-20">
      <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto mb-10 max-w-2xl text-center">
          <h2 class="text-3xl font-extrabold">
            أسعار واضحة، وتدفع بإنستاباي
          </h2>
          <p class="mt-3 text-(--ui-text-muted)">
            اختار الباقة اللي تناسب شغلك، وضيف أقسام بعدين لو احتجت.
          </p>
        </div>
        <PlanCards />
        <div class="mt-6 text-center">
          <UButton to="/pricing" variant="link" trailing-icon="i-lucide-arrow-left" label="تفاصيل الأسعار والأقسام الإضافية" />
        </div>
        <div class="mx-auto mt-8 flex max-w-3xl flex-wrap items-center justify-center gap-3 rounded-2xl border border-dashed border-primary/40 bg-(--ui-bg) p-5 text-center">
          <UIcon name="i-lucide-gift" class="size-6 text-primary" />
          <p class="text-sm sm:text-base">
            <b>جيب محل صاحبك:</b> هو ياخد خصم 20% أول 3 شهور، وانت تاخد 500 نقطة (= 50 ج من اشتراكك) أول ما يدفع.
          </p>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="mx-auto max-w-3xl px-4 py-20">
      <h2 class="mb-8 text-center text-3xl font-extrabold">
        أسئلة بتتسأل كتير
      </h2>
      <UAccordion :items="faqItems" type="multiple" :ui="{ trigger: 'text-base font-bold py-4', body: 'text-(--ui-text-muted) leading-loose' }" />
    </section>

    <!-- CTA -->
    <section class="px-4 pb-20">
      <div class="mx-auto max-w-5xl rounded-3xl bg-primary px-6 py-14 text-center text-white">
        <h2 class="text-3xl font-extrabold">
          جرّب محاسبي في محلك النهارده
        </h2>
        <p class="mx-auto mt-3 max-w-xl text-white/85">
          14 يوم ببلاش بكل الأقسام المناسبة لشغلك. من غير كارت ومن غير أي التزام.
        </p>
        <UButton :to="links.register" size="xl" color="neutral" variant="solid" class="mt-8 bg-white text-primary hover:bg-white/90" label="سجّل محلك دلوقتي" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { comparison, faqs, features, heroPoints, reassurance, showcases, steps } from '~/data/features'
import { solutions } from '~/data/solutions'

const links = useAppLinks()
const active = ref(showcases[0]!.key)
const faqItems = faqs.map(f => ({ label: f.q, content: f.a }))

const { siteUrl, appUrl } = useSiteUrls()
usePageSeo({
  title: 'محاسبي | برنامج حسابات ومخزون وصيانة لمحلات الموبايلات',
  description: 'محاسبي: برنامج كاشير بالباركود وIMEI، مخزون وجرد، صيانة بتذاكر ورسايل واتساب، آجل العملاء والموردين، شحن وتحويلات ومستعمل، وتقارير المكسب لمحلات الموبايلات في مصر. بيشتغل من غير نت. جرّب 14 يوم ببلاش.',
  path: '/',
  jsonLd: [
    {
      '@type': 'SoftwareApplication',
      'name': 'محاسبي',
      'alternateName': 'Muhasebi',
      'applicationCategory': 'BusinessApplication',
      'applicationSubCategory': 'Point of Sale',
      'operatingSystem': 'Web, Android, iOS, Windows, macOS',
      'inLanguage': 'ar-EG',
      'url': siteUrl,
      'installUrl': `${appUrl}/register`,
      'image': `${siteUrl}/og.png`,
      'screenshot': [`${siteUrl}/screens/dashboard.webp`, `${siteUrl}/screens/pos.webp`, `${siteUrl}/screens/repairs.webp`],
      'description': 'برنامج حسابات ومخزون وصيانة لمحلات الموبايلات والإكسسوارات في مصر: كاشير بالباركود وIMEI، صيانة، آجل، شحن وتحويلات، مستعمل، وتقارير، وبيشتغل من غير نت.',
      'featureList': features.map(f => f.title).join('، '),
      'offers': { '@type': 'Offer', 'price': '0', 'priceCurrency': 'EGP', 'description': 'تجربة 14 يوم ببلاش، وبعدها اشتراك شهري أو سنوي', 'url': `${siteUrl}/pricing` },
      'publisher': { '@id': `${siteUrl}/#org` },
    },
    {
      '@type': 'FAQPage',
      'mainEntity': faqs.map(f => ({ '@type': 'Question', 'name': f.q, 'acceptedAnswer': { '@type': 'Answer', 'text': f.a } })),
    },
  ],
})
</script>

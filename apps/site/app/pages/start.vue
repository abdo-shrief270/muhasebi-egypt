<template>
  <div>
    <!-- Hero + the founders' offer -->
    <section class="relative overflow-hidden" :style="{ background: 'var(--app-hero)' }">
      <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-16 pt-12 lg:grid-cols-[1fr_1.15fr] lg:pt-16">
        <div class="space-y-6">
          <UBadge color="primary" variant="subtle" size="lg" icon="i-lucide-smartphone" label="معمول مخصوص لمحلات الموبايلات" />
          <h1 class="text-4xl font-extrabold leading-[1.35] sm:text-5xl">
            نظام إدارة متكامل
            <span class="text-primary">لمحلات الموبايلات</span>
          </h1>
          <p class="text-lg leading-loose text-(--ui-text-muted)">
            البيع بالباركود و IMEI، الصيانة من الاستلام للتسليم، والمخزون لكل فرع، في برنامج واحد بالعربي. وبيكمّل شغل حتى لو الإنترنت فصل.
          </p>

          <div class="rounded-2xl border-2 border-dashed border-amber-400 bg-amber-400/10 p-5">
            <p class="text-sm font-bold text-amber-700 dark:text-amber-300">
              عرض المؤسسين
            </p>
            <p class="mt-1 text-2xl font-extrabold">
              خصم <span class="num">50%</span> على الاشتراك لمدة 3 شهور
            </p>
            <p class="mt-2 text-(--ui-text-muted)">
              استخدم الكود
              <button type="button" class="num mx-1 rounded-lg bg-amber-400 px-3 py-1 font-extrabold tracking-wider text-neutral-900" dir="ltr" :aria-label="`انسخ الكود ${CODE}`" @click="copyCode">
                {{ CODE }}
              </button>
              وقت الاشتراك.
              <span v-if="copied" class="text-sm font-bold text-primary">اتنسخ</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <UButton :to="links.register" size="xl" icon="i-lucide-rocket" label="جرّب 7 أيام ببلاش" />
            <ContactWhatsapp place="start-hero" text="السلام عليكم، عايز أعرف أكتر عن محاسبي وعرض المؤسسين" />
          </div>
          <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-(--ui-text-muted)">
            <span v-for="r in reassurance" :key="r" class="flex items-center gap-1"><UIcon name="i-lucide-shield-check" class="size-4 text-primary" />{{ r }}</span>
          </p>
        </div>
        <div class="relative">
          <div class="shot">
            <img src="/screens/pos.webp" alt="شاشة الكاشير في محاسبي" width="1600" height="1000" fetchpriority="high">
          </div>
          <div class="phone absolute -bottom-10 -start-4 hidden w-40 sm:block lg:-start-10 lg:w-44">
            <img src="/screens/m-repairs.webp" alt="الصيانة على الموبايل" width="390" height="844" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- What it does, screen by screen -->
    <section class="mx-auto max-w-6xl space-y-20 px-4 py-20">
      <div v-for="(f, i) in features" :key="f.title" class="grid items-center gap-10 lg:grid-cols-2">
        <div class="space-y-4" :class="i % 2 ? 'lg:order-2' : ''">
          <span class="inline-flex size-12 items-center justify-center rounded-xl bg-(--app-primary-soft)">
            <UIcon :name="f.icon" class="size-6 text-primary" />
          </span>
          <h2 class="text-3xl font-extrabold">
            {{ f.title }}
          </h2>
          <p class="text-lg leading-loose text-(--ui-text-muted)">
            {{ f.text }}
          </p>
          <ul class="space-y-2">
            <li v-for="p in f.points" :key="p" class="flex items-start gap-2 font-bold">
              <UIcon name="i-lucide-check" class="mt-1 size-4 shrink-0 text-primary" />{{ p }}
            </li>
          </ul>
        </div>
        <div class="shot">
          <img :src="f.image" :alt="f.title" width="1600" height="1000" loading="lazy">
        </div>
      </div>
    </section>

    <!-- More in short -->
    <section class="bg-(--ui-bg-muted) py-20">
      <div class="mx-auto max-w-6xl px-4">
        <h2 class="mb-10 text-center text-3xl font-extrabold">
          وكمان
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="m in more" :key="m.title" class="rounded-2xl border border-(--ui-border) bg-(--ui-bg) p-6">
            <UIcon :name="m.icon" class="size-7 text-primary" />
            <p class="mt-3 text-lg font-extrabold">
              {{ m.title }}
            </p>
            <p class="mt-1 text-(--ui-text-muted)">
              {{ m.text }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Getting started -->
    <section class="mx-auto max-w-4xl px-4 py-20">
      <h2 class="mb-10 text-center text-3xl font-extrabold">
        تبدأ إزاي؟
      </h2>
      <ol class="grid gap-4 sm:grid-cols-3">
        <li v-for="(s, i) in steps" :key="s.title" class="rounded-2xl border border-(--ui-border) p-6 text-center">
          <span class="num mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-primary text-lg font-extrabold text-white">{{ i + 1 }}</span>
          <p class="text-lg font-extrabold">
            {{ s.title }}
          </p>
          <p class="mt-1 text-(--ui-text-muted)">
            {{ s.text }}
          </p>
        </li>
      </ol>
    </section>

    <!-- Questions -->
    <section class="mx-auto max-w-3xl px-4 pb-20">
      <h2 class="mb-6 text-center text-3xl font-extrabold">
        أسئلة بتتسأل كتير
      </h2>
      <UAccordion :items="faq" />
    </section>

    <!-- Last call -->
    <section class="px-4 pb-20">
      <div class="mx-auto max-w-4xl rounded-3xl bg-primary px-6 py-12 text-center text-white">
        <h2 class="text-3xl font-extrabold">
          جرّب محاسبي على محلك
        </h2>
        <p class="mt-3 text-lg text-white/85">
          7 أيام ببلاش، وبعدها خصم <span class="num">50%</span> لمدة 3 شهور بالكود <span class="num font-extrabold" dir="ltr">{{ CODE }}</span>.
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <UButton :to="links.register" size="xl" color="neutral" variant="solid" icon="i-lucide-rocket" label="سجّل محلك دلوقتي" />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * The page the ads point at (`/start`): the product for phone shops and the founders' offer, in
 * its own short layout. Kept to selling, repairs, stock and running the shop — no money-lending
 * wording (customer credit, instalments), which ad platforms read as a financial service — and
 * out of the sitemap / search (noindex).
 */
definePageMeta({ layout: 'ad' })

const CODE = 'FOUNDERS'
const links = useAppLinks()

usePageSeo({
  title: 'محاسبي — نظام إدارة محلات الموبايلات | عرض المؤسسين',
  description: 'نظام متكامل لمحلات الموبايلات في مصر: البيع بالباركود و IMEI، الصيانة، والمخزون لكل فرع. جرّبه 7 أيام ببلاش وخصم 50% لمدة 3 شهور بكود FOUNDERS.',
  path: '/start',
  noindex: true,
})

const copied = ref(false)
async function copyCode() {
  try {
    await navigator.clipboard.writeText(CODE)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  }
  catch {
    // No clipboard (an old browser): the code is on the page to type.
  }
}

const reassurance = ['من غير كارت', 'تلغي في أي وقت', 'بالعربي المصري']

const features = [
  {
    icon: 'i-lucide-scan-barcode',
    title: 'كاشير سريع، حتى من غير إنترنت',
    text: 'امسح الباركود أو IMEI الجهاز والفاتورة تطلع في ثواني. ولو النت فصل، الكاشير بيكمّل بيع عادي والفواتير بتتسجل لوحدها أول ما يرجع.',
    points: ['البيع بالباركود أو رقم IMEI', 'إيصال حراري عليه QR يفتح الفاتورة من الموبايل', 'مرتجعات واستبدال بسهولة'],
    image: '/screens/offline-pos.webp',
  },
  {
    icon: 'i-lucide-wrench',
    title: 'الصيانة من الاستلام للتسليم',
    text: 'استلام الجهاز في دقيقة، وستيكر بيتطبع ويتلزق عليه، والعميل يتابع جهازه من لينك من غير ما يتصل كل شوية.',
    points: ['ستيكر على كل جهاز: العميل، العطل، الفني، والسعر المتفق عليه', 'رسالة واتساب للعميل في كل مرحلة', 'الفني يطلب قطع الغيار من المخزن، وعمولته بتتحسب لوحدها'],
    image: '/screens/repairs.webp',
  },
  {
    icon: 'i-lucide-boxes',
    title: 'المخزون تحت إيدك',
    text: 'مخزون لكل فرع لوحده، وكل جهاز معروف هو فين برقمه التسلسلي: اتباع، في الصيانة، ولا رجع للمورد.',
    points: ['تتبع IMEI والسيريال لكل جهاز', 'تحويلات بين الفروع', 'تنبيه لما صنف يقرب يخلص'],
    image: '/screens/inventory.webp',
  },
]

const more = [
  { icon: 'i-lucide-smartphone', title: 'الأجهزة المستعملة', text: 'تسجيل بيانات البائع ورقم IMEI وصور الجهاز، وإقرار بيع جاهز للطباعة.' },
  { icon: 'i-lucide-chart-column', title: 'المحل على موبايلك', text: 'مبيعات النهارده مقارنة بإمبارح، وتوافق على الخصومات الكبيرة والمرتجعات وانت بره المحل.' },
  { icon: 'i-lucide-store', title: 'متجر أونلاين', text: 'متجر باسم محلك على لينك خاص بيك، وظهور في «سوق محاسبي» للزباين القريبين منك.' },
  { icon: 'i-lucide-truck', title: 'الموردين والمشتريات', text: 'فواتير الشرا، ومرتجعات الموردين، والقطع البايظة بتتجمع عشان ترجعها.' },
  { icon: 'i-lucide-users', title: 'فريق المحل', text: 'كل موظف بحسابه وصلاحياته، وسجل بكل عملية حصلت ومين عملها.' },
  { icon: 'i-lucide-printer', title: 'طباعة جاهزة', text: 'إيصالات حرارية وليبلات باركود للأصناف، على الطابعة اللي عندك.' },
]

const steps = [
  { title: 'سجّل محلك', text: 'رقم موبايلك واسم المحل، ومن غير كارت.' },
  { title: 'ضيف أصنافك', text: 'من شيت إكسيل أو صنف صنف، وابدأ تبيع.' },
  { title: 'اشترك بالخصم', text: `بعد التجربة استخدم الكود ${CODE} وخد 50% لمدة 3 شهور.` },
]

const faq = [
  { label: 'محتاج أسطب حاجة؟', content: 'لا. البرنامج بيفتح من المتصفح على الكمبيوتر أو الموبايل، وتقدر تنزّله كتطبيق على الجهاز.' },
  { label: 'لو النت فصل؟', content: 'الكاشير بيكمّل بيع عادي، والفواتير بتتسجل لوحدها أول ما النت يرجع.' },
  { label: 'ينفع لأكتر من فرع؟', content: 'أيوه، كل فرع بمخزونه، وتقدر تحوّل بضاعة بين الفروع وتشوف كل الفروع مع بعض.' },
  { label: 'الكود بيتحط فين؟', content: `في صفحة الاشتراك جوه البرنامج، قبل ما تدفع. الخصم 50% على الاشتراك لمدة 3 شهور.` },
  { label: 'بياناتي في أمان؟', content: 'كل محل بياناته منفصلة، وفيه قفل بكود أو بصمة، وتحقق بخطوتين لصاحب المحل، ونسخة احتياطية يومية.' },
]
</script>

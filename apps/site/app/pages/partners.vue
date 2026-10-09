<template>
  <div>
    <section class="border-b border-(--ui-border) bg-(--ui-bg-muted)">
      <div class="mx-auto max-w-4xl px-4 py-16 text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-(--app-primary-soft) px-3 py-1 text-sm font-bold text-primary">
          <UIcon name="i-lucide-handshake" class="size-4" />
          برنامج شركاء محاسبي
        </span>
        <h1 class="mt-5 text-3xl font-extrabold leading-[1.35] sm:text-5xl sm:leading-[1.3]">
          رشّح محاسبي لمحلات الموبايلات،<br>
          وخد <span class="num text-primary">{{ program.rate_percent }}%</span> من اشتراكاتهم
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-(--ui-text-muted)">
          اعمل حسابك، خد لينكك، وابعته لأصحاب المحلات اللي تعرفهم أو انشره على صفحتك. كل محل يشترك من لينكك، بتاخد نسبة من كل دفعة لمدة <span class="num">{{ program.months }}</span> شهر.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <UButton :to="`${appBase}/partners/register`" size="xl" icon="i-lucide-user-plus" label="اعمل حساب شريك" />
          <UButton :to="`${appBase}/partners/login`" size="xl" color="neutral" variant="outline" label="عندي حساب" />
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 py-14">
      <h2 class="text-center text-2xl font-extrabold">
        بيشتغل إزاي
      </h2>
      <ol class="mt-8 grid gap-4 md:grid-cols-3">
        <li v-for="(s, i) in steps" :key="s.title" class="rounded-2xl border border-(--ui-border) p-6">
          <span class="num inline-grid size-9 place-items-center rounded-full bg-primary font-extrabold text-white">{{ i + 1 }}</span>
          <p class="mt-4 text-lg font-extrabold">
            {{ s.title }}
          </p>
          <p class="mt-2 leading-relaxed text-(--ui-text-muted)">
            {{ s.text }}
          </p>
        </li>
      </ol>
    </section>

    <section class="border-y border-(--ui-border) bg-(--ui-bg-muted)">
      <div class="mx-auto grid max-w-5xl gap-4 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="n in numbers" :key="n.label" class="rounded-2xl bg-(--ui-bg) p-6 text-center shadow-[var(--app-shadow)]">
          <p class="num text-3xl font-extrabold text-primary">
            {{ n.value }}
          </p>
          <p class="mt-2 font-bold">
            {{ n.label }}
          </p>
          <p class="mt-1 text-sm text-(--ui-text-muted)">
            {{ n.hint }}
          </p>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 py-14">
      <h2 class="text-2xl font-extrabold">
        مثال
      </h2>
      <p class="mt-3 max-w-3xl leading-relaxed text-(--ui-text-muted)">
        رشّحت <b>10 محلات</b> اشتركوا في باقة <b>{{ example.plan }}</b> (<span class="num">{{ example.price }}</span> ج في الشهر). هتاخد حوالي
        <b class="num text-primary">{{ example.monthly }} ج</b> كل شهر، يعني حوالي <b class="num text-primary">{{ example.yearly }} ج</b> في السنة، من غير ما تعمل حاجة تانية.
      </p>
      <p class="mt-2 text-sm text-(--ui-text-dimmed)">
        النسبة من اللي المحل دفعه فعلاً من غير ضريبة القيمة المضافة.
      </p>
    </section>

    <section class="mx-auto max-w-5xl px-4 pb-14">
      <h2 class="text-2xl font-extrabold">
        مناسب لمين
      </h2>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="w in who" :key="w.title" class="rounded-2xl border border-(--ui-border) p-6">
          <UIcon :name="w.icon" class="size-6 text-primary" />
          <p class="mt-3 font-extrabold">
            {{ w.title }}
          </p>
          <p class="mt-1 text-sm leading-relaxed text-(--ui-text-muted)">
            {{ w.text }}
          </p>
        </div>
      </div>
    </section>

    <section class="border-t border-(--ui-border) bg-(--ui-bg-muted)">
      <div class="mx-auto max-w-3xl px-4 py-14">
        <h2 class="text-2xl font-extrabold">
          أسئلة
        </h2>
        <div class="mt-6 space-y-3">
          <details v-for="f in faqs" :key="f.q" class="rounded-xl border border-(--ui-border) bg-(--ui-bg) p-4">
            <summary class="cursor-pointer font-bold">
              {{ f.q }}
            </summary>
            <p class="mt-2 leading-relaxed text-(--ui-text-muted)">
              {{ f.a }}
            </p>
          </details>
        </div>
        <div class="mt-10 text-center">
          <UButton :to="`${appBase}/partners/register`" size="xl" icon="i-lucide-user-plus" label="ابدأ دلوقتي" />
          <p class="mt-3 text-sm text-(--ui-text-muted)">
            <a :href="`${appBase}/partners/terms`" class="underline">شروط البرنامج</a>
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
interface Program { rate_percent: number, months: number, hold_days: number, min_payout: number, welcome_discount: { percent: number, months: number } | null }

const { apiBase, appUrl } = useRuntimeConfig().public
const appBase = String(appUrl).replace(/\/$/, '')

// The program's numbers from the API (in the static HTML from the build, then refreshed).
const fallback: Program = { rate_percent: 20, months: 12, hold_days: 14, min_payout: 20000, welcome_discount: { percent: 20, months: 3 } }
const { data } = await useAsyncData('affiliate-program', () => (import.meta.server
  ? $fetch<{ data: Program }>(`${appBase}/api/v1/public/affiliates/program`, { timeout: 5000 })
  : $fetch<{ data: Program }>(`${apiBase}/public/affiliates/program`)).then(r => r.data).catch(() => fallback))
const program = computed<Program>(() => data.value ?? fallback)

const { data: plans } = usePlans()
const example = computed(() => {
  const plan = plans.value?.data.plans.find(p => p.featured) ?? plans.value?.data.plans[0]
  const monthly = plan?.monthly ?? 44900
  const vat = 1 + (plans.value?.data.vat_percent ?? 14) / 100
  const perShop = (monthly / vat) * (program.value.rate_percent / 100) / 100
  return {
    plan: plan?.name ?? 'الصيانة',
    price: pounds(monthly),
    monthly: Math.round(perShop * 10).toLocaleString('en-US'),
    yearly: Math.round(perShop * 10 * Math.min(12, program.value.months)).toLocaleString('en-US'),
  }
})

const steps = computed(() => [
  { title: 'اعمل حسابك', text: 'بموبايلك وكلمة سر، في دقيقة. بتاخد لينك وكود باسمك.' },
  { title: 'ابعت لينكك', text: `لأصحاب المحلات اللي تعرفهم، أو في جروب أو صفحة أو فيديو.${program.value.welcome_discount ? ` المحل اللي يسجّل منه بياخد خصم ${program.value.welcome_discount.percent}% على أول ${program.value.welcome_discount.months} شهور.` : ''}` },
  { title: 'اكسب كل شهر', text: `كل ما محل من عندك يدفع اشتراكه، بتاخد ${program.value.rate_percent}% منه لمدة ${program.value.months} شهر، وتسحبها على InstaPay أو محفظتك.` },
])
const numbers = computed(() => [
  { value: `${program.value.rate_percent}%`, label: 'من كل دفعة', hint: 'من غير ضريبة القيمة المضافة' },
  { value: `${program.value.months} شهر`, label: 'من أول دفعة للمحل', hint: 'شهري أو سنوي، كله بيتحسب' },
  { value: `${pounds(program.value.min_payout)} ج`, label: 'أقل سحب', hint: 'InstaPay أو محفظة أو بنك' },
  { value: `${program.value.hold_days} يوم`, label: 'وتبقى متاحة', hint: 'بعد ما المحل يدفع' },
])
const who = [
  { icon: 'i-lucide-wrench', title: 'الفنيين', text: 'بتتعامل مع محلات كتير كل يوم وعارف مين محتاج يرتّب شغله.' },
  { icon: 'i-lucide-truck', title: 'المندوبين والموزعين', text: 'بتلف على المحلات أصلاً: رشّح وانت بتسلّم البضاعة.' },
  { icon: 'i-lucide-video', title: 'صناع المحتوى', text: 'صفحة أو قناة عن الموبايلات والصيانة؟ لينكك في الوصف بيكسب.' },
  { icon: 'i-lucide-calculator', title: 'المحاسبين', text: 'بتمسك حسابات محلات؟ رشّحلهم برنامج يسهّل عليك وعليهم.' },
]
const faqs = computed(() => [
  { q: 'هل لازم أكون صاحب محل؟', a: 'لأ. البرنامج لأي حد: فني، مندوب، صاحب صفحة، أو حتى صاحبك اللي يعرف ناس في السوق.' },
  { q: 'المحل بيتحسب ليّا إمتى؟', a: 'لما يفتح لينكك أو يكتب الكود بتاعك ويسجّل. اللينك بيفتكره الجهاز 60 يوم، فلو المحل سجّل بعدها بكام يوم برضه بيتحسب ليك.' },
  { q: 'بقبض إزاي؟', a: `العمولة بتفضل متعلّقة ${program.value.hold_days} يوم بعد ما المحل يدفع، وبعدين تقدر تطلب سحب لما المتاح يوصل ${pounds(program.value.min_payout)} ج. بنحوّلك على InstaPay أو المحفظة أو البنك، وبتشوف رقم العملية في حسابك.` },
  { q: 'أقدر أشوف المحلات اللي جت من عندي؟', a: 'أيوه: في حسابك بتشوف الزيارات من لينكك، والمحلات اللي سجّلت، واللي دفعت، وعمولة كل دفعة.' },
  { q: 'فيه حاجة ممنوعة؟', a: 'الإعلانات على اسم «محاسبي» في جوجل، أو إنك تتكلم كأنك موظف في محاسبي، أو تسجيل محلات وهمية. ساعتها الحساب بيتوقف.' },
])

usePageSeo({
  title: `برنامج شركاء محاسبي: اكسب ${program.value.rate_percent}% من اشتراكات محلات الموبايلات`,
  description: `اعمل حساب شريك في محاسبي ورشّح البرنامج لمحلات الموبايلات. خد ${program.value.rate_percent}% من كل اشتراك يدفعه المحل لمدة ${program.value.months} شهر، واسحب على InstaPay أو محفظتك.`,
  path: '/partners',
  jsonLd: [
    breadcrumbs([{ name: 'برنامج الشركاء', path: '/partners' }]),
    { '@type': 'FAQPage', 'mainEntity': faqs.value.map(f => ({ '@type': 'Question', 'name': f.q, 'acceptedAnswer': { '@type': 'Answer', 'text': f.a } })) },
  ],
})
</script>

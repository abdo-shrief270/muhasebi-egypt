<template>
  <div class="mx-auto max-w-3xl px-4 py-14">
    <h1 class="text-3xl font-extrabold sm:text-4xl">
      سياسة الخصوصية
    </h1>
    <p class="mt-3 text-lg text-(--ui-text-muted)">
      إيه اللي بنعرفه عنك وإنت بتزور موقع محاسبي أو بتسجّل محلك، وبنعمل بيه إيه.
    </p>

    <section v-for="s in sections" :key="s.title" class="mt-10">
      <h2 class="flex items-center gap-2 text-xl font-extrabold">
        <UIcon :name="s.icon" class="size-5 text-primary" />
        {{ s.title }}
      </h2>
      <ul class="mt-3 list-disc space-y-2 ps-6 leading-relaxed text-(--ui-text-muted)">
        <li v-for="line in s.lines" :key="line">
          {{ line }}
        </li>
      </ul>
    </section>

    <div class="mt-12 rounded-2xl border border-(--ui-border) bg-(--ui-bg-muted) p-6">
      <p class="font-bold">
        إنت عميل لمحل بيستخدم محاسبي؟
      </p>
      <p class="mt-1 text-(--ui-text-muted)">
        بياناتك اللي المحل بيسجّلها (فواتيرك، أجهزتك في الصيانة، حسابك الآجل) ليها صفحة لوحدها بتشرح حقوقك.
      </p>
      <UButton :to="links.privacy" color="neutral" variant="outline" class="mt-4" label="خصوصية عملاء المحلات" trailing-icon="i-lucide-arrow-left" />
    </div>

    <p class="mt-10 text-sm text-(--ui-text-muted)">
      آخر تحديث: <span class="num">{{ UPDATED }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
const UPDATED = '2026-10-05'
const links = useAppLinks()

usePageSeo({
  title: 'سياسة الخصوصية',
  description: 'إزاي موقع محاسبي بيتعامل مع بياناتك: الزيارات وأدوات القياس (Google Analytics و Meta Pixel)، بيانات تسجيل المحل، وحقوقك طبقاً لقانون حماية البيانات 151 لسنة 2020.',
  path: '/privacy',
  jsonLd: [breadcrumbs([{ name: 'سياسة الخصوصية', path: '/privacy' }])],
})

const sections = [
  {
    icon: 'i-lucide-chart-line',
    title: 'وإنت بتتصفح الموقع',
    lines: [
      'بنستخدم Google Analytics و Meta Pixel (فيسبوك) عشان نعرف عدد الزوار، وجايين منين، وإيه الصفحات والإعلانات اللي بتجيب محلات تسجّل.',
      'الأدوات دي بتحط ملفات صغيرة (cookies) على جهازك، وبتسجّل الصفحات اللي فتحتها والزراير اللي دوست عليها، من غير اسمك أو رقمك.',
      'لو جيت من إعلان أو لينك فيه بيانات حملة (utm)، بنحفظ اسم الحملة مع المحل لما يسجّل، عشان نعرف أنهي إعلان نافع.',
    ],
  },
  {
    icon: 'i-lucide-store',
    title: 'لما تسجّل محلك',
    lines: [
      'بنسجّل اسم المحل ونوعه، واسمك ورقم موبايلك، وكلمة السر (متشفّرة، ومحدش يقدر يشوفها حتى إحنا).',
      'بنستخدمهم عشان حسابك، والاشتراك والفواتير، وعشان نكلّمك لو احتجت مساعدة.',
      'بنقول لفيسبوك إن محل جديد سجّل (من غير أي بيانات عنه) عشان الإعلانات توصل لناس شبهك.',
    ],
  },
  {
    icon: 'i-lucide-database',
    title: 'بيانات محلك جوه البرنامج',
    lines: [
      'أصنافك ومبيعاتك وعملاءك ومورّدينك بتاعتك إنت. مش بنبيعها، ومش بنستخدمها في إعلانات، ومش بنشاركها مع حد.',
      'صفحتي التسجيل والدخول بيستخدموا نفس أدوات القياس بتاعة الموقع، لكن بعد ما تدخل البرنامج مفيش أي قياس ولا إعلانات.',
      'البيانات على سيرفرات مؤمّنة، بتتبعت مشفّرة (HTTPS)، وبيتعمل منها نسخة احتياطية كل يوم.',
    ],
  },
  {
    icon: 'i-lucide-user-check',
    title: 'حقوقك',
    lines: [
      'تعرف إيه اللي متسجل عنك، وتصحّحه، وتطلب مسحه، طبقاً لقانون حماية البيانات الشخصية رقم 151 لسنة 2020.',
      'تقدر تمنع أدوات القياس من إعدادات المتصفح (حظر cookies الطرف التالت) أو بإضافة حجب الإعلانات، والموقع هيشتغل عادي.',
      'لأي طلب كلّمنا على الواتساب أو من صفحتنا على فيسبوك.',
    ],
  },
]
</script>

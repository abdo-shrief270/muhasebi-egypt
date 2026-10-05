<template>
  <div class="space-y-4 pb-6">
    <UCard>
      <h1 class="text-xl font-extrabold">
        الخصوصية وبياناتك
      </h1>
      <p class="mt-2 text-sm text-(--ui-text-muted)">
        محاسبي برنامج بتستخدمه محلات الموبايلات والصيانة. المحل اللي اتعاملت معاه هو المسؤول عن بياناتك، ومحاسبي بيحفظهاله،
        طبقاً لقانون حماية البيانات الشخصية رقم 151 لسنة 2020.
      </p>
    </UCard>

    <UCard v-for="section in sections" :key="section.title">
      <div class="flex items-start gap-3">
        <UIcon :name="section.icon" class="mt-0.5 size-5 shrink-0 text-primary" />
        <div class="min-w-0 space-y-1.5">
          <h2 class="font-bold">
            {{ section.title }}
          </h2>
          <ul class="list-disc space-y-1 ps-4 text-sm text-(--ui-text-muted)">
            <li v-for="line in section.lines" :key="line">
              {{ line }}
            </li>
          </ul>
        </div>
      </div>
    </UCard>

    <p class="text-center text-xs text-(--ui-text-muted)">
      آخر تحديث: <span class="num">{{ formatDate(UPDATED) }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
// Linked from receipts, repair tracking and registration: readable without an account.
definePageMeta({ layout: 'auth', public: true })
useHead({ title: 'الخصوصية وبياناتك — محاسبي' })

const UPDATED = '2026-10-05'

const sections = [
  {
    icon: 'i-lucide-database',
    title: 'إيه اللي بيتسجل',
    lines: [
      'اسمك ورقم موبايلك.',
      'فواتيرك والمرتجعات، وحسابك الآجل لو بتشتري بالآجل.',
      'لو سبت جهاز للصيانة: نوعه والـ IMEI وحالته وأعطاله، وكود فتح الشاشة لو اديته للمحل (متشفّر، والفني بس اللي يشوفه).',
      'إمتى المحل بعتلك رسالة واتساب.',
    ],
  },
  {
    icon: 'i-lucide-target',
    title: 'ليه',
    lines: [
      'عشان الفواتير والضمان والمرتجع.',
      'عشان تتابع جهازك في الصيانة والمحل يبلّغك لما يخلص.',
      'عشان حسابك الآجل مع المحل.',
      'مش بنبيع بياناتك، ومش بنستخدمها في إعلانات.',
    ],
  },
  {
    icon: 'i-lucide-eye',
    title: 'مين بيشوفها',
    lines: [
      'المحل وموظفينه، كل واحد على قد شغله.',
      'لينك الإيصال ولينك متابعة الجهاز مفيهمش رقم موبايلك.',
    ],
  },
  {
    icon: 'i-lucide-clock',
    title: 'قد إيه بنحتفظ بيها',
    lines: [
      'طول ما إنت بتتعامل مع المحل.',
      'المحل يقدر يختار مدة (من سنة لـ 10 سنين) لو عدّت من غير أي حركة، اسمك ورقمك بيتمسحوا تلقائياً.',
      'الفواتير والمبالغ نفسها بتفضل من غير اسمك، لأن المحل لازم يحتفظ بحساباته.',
    ],
  },
  {
    icon: 'i-lucide-user-check',
    title: 'حقوقك',
    lines: [
      'تعرف إيه اللي متسجل عنك وتاخد نسخة منه.',
      'تصحّح بياناتك لو فيها غلط.',
      'تطلب مسحها: اسمك ورقمك بيتشالوا من كل مكان وبيتكتب مكانهم «عميل محذوف». ده بيحصل طالما مفيش فلوس لسه بينك وبين المحل ومفيش جهاز ليك في الصيانة.',
    ],
  },
  {
    icon: 'i-lucide-phone',
    title: 'تطلب إزاي',
    lines: [
      'كلّم المحل اللي اتعاملت معاه أو روحله؛ رقمه على الإيصال وفي صفحة متابعة الجهاز.',
      'ممكن المحل يتأكد الأول إن الرقم رقمك.',
    ],
  },
  {
    icon: 'i-lucide-chart-line',
    title: 'موقع محاسبي وصفحة التسجيل',
    lines: [
      'موقع muhasebi.com بيستخدم Google Analytics و Meta Pixel (فيسبوك) عشان نعرف عدد الزوار وجايين منين، وإيه الإعلانات اللي بتجيب محلات تسجّل.',
      'الأدوات دي بتحط ملفات صغيرة (cookies) على جهازك، وبتسجّل الصفحات اللي فتحتها والزراير اللي دوست عليها، من غير اسمك أو رقمك.',
      'جوه البرنامج نفسه، الحاجة الوحيدة اللي بتتبعت لفيسبوك هي إن محل جديد خلّص التسجيل، من غير أي بيانات عن المحل أو عملائه.',
      'تقدر تمنع ده من إعدادات المتصفح (حظر cookies الطرف التالت) أو بإضافة حجب الإعلانات.',
    ],
  },
  {
    icon: 'i-lucide-lock',
    title: 'الأمان',
    lines: [
      'البيانات محفوظة على سيرفرات مؤمّنة، وبتتبعت مشفّرة (HTTPS).',
      'كل عملية حساسة (زي المسح أو تنزيل البيانات) بتتسجل باسم اللي عملها.',
    ],
  },
]
</script>

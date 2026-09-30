<template>
  <div class="mx-auto max-w-6xl px-4 py-16">
    <div class="mx-auto mb-10 max-w-2xl text-center">
      <h1 class="text-4xl font-extrabold">
        الأسعار
      </h1>
      <p class="mt-3 text-lg text-(--ui-text-muted)">
        ابدأ بتجربة ببلاش، وبعدها ادفع شهري أو سنوي بإنستاباي. من غير عقود ومن غير رسوم مخفية.
      </p>
    </div>

    <PlanCards />

    <section v-if="data?.data.modules.length" class="mt-16">
      <h2 class="mb-2 text-2xl font-extrabold">
        أقسام إضافية
      </h2>
      <p class="mb-6 text-(--ui-text-muted)">
        محتاج قسم مش في باقتك؟ ضيفه لوحده على أي باقة.
      </p>
      <div class="overflow-hidden rounded-2xl border border-(--ui-border)">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-muted) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                القسم
              </th>
              <th class="p-3 text-start font-bold">
                في الشهر
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in data.data.modules" :key="m.key" class="border-t border-(--ui-border)">
              <td class="p-3 font-bold">
                {{ m.name }}
                <UBadge v-if="!m.available" size="sm" color="neutral" variant="subtle" label="قريباً" class="ms-2" />
              </td>
              <td class="p-3">
                <span class="num">{{ pounds(m.monthly) }}</span> ج
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-16 grid gap-6 md:grid-cols-3">
      <div v-for="i in info" :key="i.title" class="rounded-2xl border border-(--ui-border) p-6">
        <UIcon :name="i.icon" class="mb-3 size-6 text-primary" />
        <p class="font-extrabold">
          {{ i.title }}
        </p>
        <p class="mt-2 text-sm leading-relaxed text-(--ui-text-muted)">
          {{ i.text }}
        </p>
      </div>
    </section>

    <div class="mt-12 text-center">
      <UButton to="/docs/billing" variant="link" trailing-icon="i-lucide-arrow-left" label="إزاي الاشتراك والدفع بيشتغلوا" />
    </div>
  </div>
</template>

<script setup lang="ts">
const { data } = usePlans()

const info = [
  { icon: 'i-lucide-clock', title: 'تجربة ببلاش', text: 'أول 14 يوم ببلاش بكل الأقسام المناسبة لنوع محلك. مش محتاج كارت.' },
  { icon: 'i-lucide-credit-card', title: 'الدفع بإنستاباي', text: 'حوّل من التطبيق، واكتب رقم العملية وارفع صورة التحويل من صفحة الاشتراك، وبنفعّل بعد المراجعة.' },
  { icon: 'i-lucide-receipt-text', title: 'فاتورة ضريبية', text: 'كل دفعة ليها فاتورة بالضريبة تقدر تطبعها من جوه البرنامج.' },
]

useSeoMeta({
  title: 'الأسعار',
  description: 'أسعار محاسبي لمحلات الموبايلات: باقات شهرية وسنوية شاملة الضريبة، وتجربة 14 يوم ببلاش، والدفع بإنستاباي.',
  ogImage: '/og.png',
})
</script>

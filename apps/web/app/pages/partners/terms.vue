<template>
  <UCard>
    <template #header>
      <h1 class="text-xl font-bold">
        شروط برنامج شركاء محاسبي
      </h1>
    </template>
    <div class="space-y-3 text-sm leading-7">
      <p><b>الفكرة:</b> بتنشر لينكك أو الكود بتاعك. أي محل يسجّل في محاسبي من خلاله ويدفع اشتراك، بتاخد نسبة من اللي دفعه.</p>
      <p><b>النسبة:</b> {{ rate }}% من قيمة الاشتراك اللي المحل دفعها فعلاً (من غير ضريبة القيمة المضافة، ومن غير اللي اتدفع من رصيد أو نقاط)، لمدة {{ months }} شهر من أول دفعة للمحل.</p>
      <p><b>إمتى المحل يتحسب ليك:</b> لما يسجّل من لينكك أو بالكود بتاعك. المحل بيتحسب لشريك واحد بس، وبيفضل ليك. المحل اللي بنفس رقم موبايلك مش بيتحسب.</p>
      <p><b>الخصم للمحل:</b> المحل اللي جاي من لينكك بياخد خصم ترحيب على أول شهور اشتراكه.</p>
      <p><b>إمتى تقدر تسحب:</b> كل عمولة بتفضل متعلّقة {{ holdDays }} يوم بعد الدفع، وبعدين تبقى متاحة. أقل سحب {{ minPayout }}. الفلوس بتتحوّل على InstaPay أو المحفظة أو الحساب البنكي اللي كاتبه.</p>
      <p><b>ممنوع:</b> الإعلانات على اسم «محاسبي» في جوجل، أو الكلام باسم محاسبي كأنك موظف، أو تسجيل محلات وهمية، أو أي طريقة فيها خداع. ساعتها الحساب بيتوقف والعمولات اللي ما اتدفعتش بتتلغي.</p>
      <p><b>الإلغاء:</b> لو المحل استرجع فلوسه، العمولة بتاعته بتتلغي. ومحاسبي ممكن يغيّر النسبة للاشتراكات الجديدة بعد ما يبلّغك.</p>
    </div>
    <template #footer>
      <UButton to="/partners/register" label="اشترك في البرنامج" icon="i-lucide-handshake" />
    </template>
  </UCard>
</template>

<script setup lang="ts">
definePageMeta({ public: true, layout: 'auth' })
useHead({ title: 'شروط برنامج الشركاء — محاسبي' })

// The program's numbers come from the API (billing.affiliates).
const api = usePartnerApi()
const { data } = await useAsyncData('partner-program', () => api<{ data: { rate_percent: number, months: number, hold_days: number, min_payout: number } }>('/public/affiliates/program'))
const rate = computed(() => data.value?.data.rate_percent ?? 20)
const months = computed(() => data.value?.data.months ?? 12)
const holdDays = computed(() => data.value?.data.hold_days ?? 14)
const minPayout = computed(() => formatMoney(data.value?.data.min_payout ?? 20000))
</script>

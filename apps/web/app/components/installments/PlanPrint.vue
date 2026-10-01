<template>
  <div class="plan-print bg-white text-black">
    <div class="flex items-start justify-between border-b-2 border-black pb-3">
      <div>
        <p class="text-xl font-extrabold">
          {{ shop?.name }}
        </p>
        <p v-if="shop?.address" class="text-sm">
          {{ shop.address }}
        </p>
        <p v-if="shop?.phone" class="text-sm num" dir="ltr">
          {{ localPhone(shop.phone) }}
        </p>
      </div>
      <div class="text-end text-sm">
        <p class="font-bold num">
          {{ plan.reference }}
        </p>
        <p class="num">
          {{ formatDate(plan.created_at) }}
        </p>
      </div>
    </div>

    <h1 class="my-5 text-center text-2xl font-extrabold">
      اتفاق تقسيط وجدول الأقساط
    </h1>

    <p class="text-justify text-base leading-9">
      أقر أنا / <b>{{ plan.customer_name }}</b><template v-if="plan.customer_phone">، موبايل <b class="num" dir="ltr">{{ localPhone(plan.customer_phone) }}</b></template>،
      الرقم القومي ...................................... ، بإن عليّ لمحل «{{ shop?.name }}» مبلغ
      <b class="num">{{ formatMoney(plan.total) }}</b><template v-if="plan.sale_reference"> (عن الفاتورة <b class="num">{{ plan.sale_reference }}</b>)</template>،
      وإني ملتزم أدفعه على <b class="num">{{ plan.count }}</b> قسط حسب الجدول اللي تحت، في المواعيد المكتوبة.
    </p>

    <table class="mt-4 w-full border-collapse text-sm">
      <tbody>
        <tr v-for="row in rows" :key="row.label">
          <th class="w-44 border border-black/60 bg-black/5 p-2 text-start">
            {{ row.label }}
          </th>
          <td class="border border-black/60 p-2 num">
            {{ row.value }}
          </td>
        </tr>
      </tbody>
    </table>

    <table class="mt-4 w-full border-collapse text-sm">
      <thead>
        <tr class="bg-black/5">
          <th class="border border-black/60 p-1.5">
            #
          </th>
          <th class="border border-black/60 p-1.5 text-start">
            الميعاد
          </th>
          <th class="border border-black/60 p-1.5 text-end">
            القسط
          </th>
          <th class="border border-black/60 p-1.5 text-start">
            اتدفع / توقيع المحصّل
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="i in plan.items ?? []" :key="i.id">
          <td class="border border-black/60 p-1.5 text-center num">
            {{ i.seq }}
          </td>
          <td class="border border-black/60 p-1.5 num">
            {{ formatDate(i.due_on) }}
          </td>
          <td class="border border-black/60 p-1.5 text-end num">
            {{ formatMoney(i.amount) }}
          </td>
          <td class="border border-black/60 p-1.5 num">
            {{ i.remaining === 0 ? `اتدفع ${formatDate(i.paid_at)}` : i.paid ? `اتدفع ${formatMoney(i.paid)}` : '' }}
          </td>
        </tr>
      </tbody>
    </table>

    <p v-if="plan.notes" class="mt-3 text-sm">
      ملاحظات: {{ plan.notes }}
    </p>

    <div class="mt-12 grid gap-8 text-sm" :class="plan.guarantor_name ? 'grid-cols-3' : 'grid-cols-2'">
      <div>
        <p class="font-bold">
          العميل
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          الاسم: {{ plan.customer_name }}
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          التوقيع
        </p>
      </div>
      <div v-if="plan.guarantor_name">
        <p class="font-bold">
          الضامن
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          الاسم: {{ plan.guarantor_name }}
          <span v-if="plan.guarantor_phone" class="num" dir="ltr"> · {{ localPhone(plan.guarantor_phone) }}</span>
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          التوقيع
        </p>
      </div>
      <div>
        <p class="font-bold">
          عن المحل
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          الاسم: {{ plan.created_by_name ?? '' }}
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          التوقيع والختم
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { InstallmentPlan } from '~/types/api'

/** The plan on A4: what the customer owes, the schedule (with a column to sign each payment), signatures. */
const props = defineProps<{ plan: InstallmentPlan }>()
const shop = useReceiptShop()

const rows = computed(() => [
  { label: 'المبلغ المقسّط', value: formatMoney(props.plan.principal) },
  ...(props.plan.markup ? [{ label: 'الزيادة (فوايد التقسيط)', value: formatMoney(props.plan.markup) }] : []),
  { label: 'الإجمالي', value: formatMoney(props.plan.total) },
  ...(props.plan.paid ? [{ label: 'اتدفع لحد دلوقتي', value: formatMoney(props.plan.paid) }] : []),
  { label: 'عدد الأقساط', value: `${props.plan.count} قسط${props.plan.interval_months > 1 ? ` — كل ${props.plan.interval_months} شهور` : ' — كل شهر'}` },
  { label: 'أول قسط', value: formatDate(props.plan.first_due_on) },
])
</script>

<style scoped>
.plan-print {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.5;
}
</style>

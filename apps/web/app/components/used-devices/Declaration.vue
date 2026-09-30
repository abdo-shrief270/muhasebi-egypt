<template>
  <div class="declaration bg-white text-black">
    <div class="flex items-start justify-between border-b-2 border-black pb-3">
      <div>
        <p class="text-xl font-extrabold">
          {{ shop.name }}
        </p>
        <p v-if="shop.branch" class="text-sm">
          {{ shop.branch }}
        </p>
        <p v-if="shop.address" class="text-sm">
          {{ shop.address }}
        </p>
        <p v-if="shop.phone" class="text-sm num" dir="ltr">
          {{ localPhone(shop.phone) }}
        </p>
      </div>
      <div class="text-end text-sm">
        <p class="font-bold num">
          {{ device.reference }}
        </p>
        <p class="num">
          {{ formatDate(device.bought_at, true) }}
        </p>
      </div>
    </div>

    <h1 class="my-6 text-center text-2xl font-extrabold">
      إقرار بيع جهاز محمول مستعمل
    </h1>

    <p class="text-justify text-base leading-9">
      أقر أنا / <b>{{ seller?.name ?? '................................' }}</b>، الرقم القومي <b class="num" dir="ltr">{{ seller?.national_id ?? '..............................' }}</b><template v-if="seller?.phone">، موبايل <b class="num" dir="ltr">{{ localPhone(seller.phone) }}</b></template>، والمقيم في ........................................................................
      بإني بعت لمحل «{{ shop.name }}» الجهاز الموضّح بياناته تحت، وإنه ملكي الخاص، ومش مسروق ولا ضايع ولا متبلّغ عنه،
      وخالي من أي نزاع أو رهن أو أقساط، وإني شلت منه كل حساباتي (iCloud / Google) وبياناتي الشخصية،
      وإني استلمت تمنه كامل وقدره <b class="num">{{ formatMoney(device.purchase_price ?? pricePaid) }}</b>
      ({{ paidBy }})، وأتحمّل المسئولية القانونية كاملة لو ظهر عكس كده.
    </p>

    <table class="mt-6 w-full border-collapse text-sm">
      <tbody>
        <tr v-for="row in rows" :key="row.label">
          <th class="w-40 border border-black/60 bg-black/5 p-2 text-start">
            {{ row.label }}
          </th>
          <td class="border border-black/60 p-2" :class="row.ltr ? 'num' : ''" :dir="row.ltr ? 'ltr' : undefined" :style="row.ltr ? 'text-align: right' : undefined">
            {{ row.value }}
          </td>
        </tr>
      </tbody>
    </table>

    <p class="mt-4 text-xs">
      اتسجّل الرقم القومي وصورة البطاقة عند المحل كإثبات لملكية الجهاز، ومحفوظين بشكل آمن حسب قانون حماية البيانات الشخصية 151 لسنة 2020.
    </p>

    <div class="mt-14 grid grid-cols-2 gap-10 text-sm">
      <div>
        <p class="font-bold">
          البايع
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          الاسم: {{ seller?.name ?? '' }}
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          التوقيع
        </p>
        <div class="mt-4 grid size-24 place-items-center border border-black/60 text-xs">
          البصمة
        </div>
      </div>
      <div>
        <p class="font-bold">
          عن المحل
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          الاسم: {{ device.bought_by_name ?? '' }}
        </p>
        <p class="mt-8 border-t border-dotted border-black pt-1">
          التوقيع والختم
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { UsedDevice } from '~/types/api'

/** «إقرار بيع جهاز»: the seller declares the device is theirs; printed on A4 and signed by both sides. */
const props = defineProps<{
  device: UsedDevice
  shop: { name: string, phone?: string | null, branch?: string | null, address?: string | null }
  /** What was paid, when the viewer can't see costs (right after buying). */
  pricePaid?: number | null
}>()

const seller = computed(() => props.device.seller)
const paidBy = computed(() => ({ cash: 'كاش', wallet: 'على محفظة', instapay: 'InstaPay', bank: 'تحويل بنكي' })[props.device.payment_method])
const rows = computed(() => [
  { label: 'الجهاز', value: props.device.model_name },
  { label: 'السعة / اللون', value: [props.device.storage, props.device.color].filter(Boolean).join(' · ') || '—' },
  { label: 'IMEI', value: props.device.imei, ltr: true },
  ...(props.device.imei2 ? [{ label: 'IMEI التاني', value: props.device.imei2, ltr: true }] : []),
  { label: 'الحالة', value: `فئة ${props.device.grade} — ${props.device.grade_label}${props.device.battery_health ? ` · البطارية ${props.device.battery_health}%` : ''}` },
  { label: 'تاريخ البيع', value: formatDate(props.device.bought_at, true) },
])
</script>

<style scoped>
.declaration {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.5;
}
</style>

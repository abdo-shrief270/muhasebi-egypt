<template>
  <div class="intake mx-auto bg-white text-black" :style="{ width }">
    <div class="text-center">
      <p class="text-lg font-extrabold">
        {{ shop?.name }}
      </p>
      <p v-if="shop?.address" class="text-xs">
        {{ shop.address }}
      </p>
      <p v-if="shop?.phone" class="text-xs num">
        {{ localPhone(shop.phone) }}
      </p>
      <p class="mt-1 text-sm font-bold">
        إيصال استلام جهاز
      </p>
    </div>

    <div class="my-2 space-y-0.5 border-y border-dashed border-black py-1 text-xs">
      <div class="flex justify-between">
        <span>رقم التذكرة</span><span class="text-base font-extrabold num">{{ ticket.reference }}</span>
      </div>
      <div class="flex justify-between">
        <span>الاستلام</span><span class="num">{{ formatDate(ticket.received_at, true) }}</span>
      </div>
      <div v-if="ticket.expected_at" class="flex justify-between">
        <span>التسليم المتوقع</span><span class="font-bold num">{{ formatDate(ticket.expected_at, true) }}</span>
      </div>
      <div class="flex justify-between">
        <span>العميل</span><span>{{ ticket.customer_name }}</span>
      </div>
      <div v-if="ticket.customer_phone" class="flex justify-between">
        <span>الموبايل</span><span class="num" dir="ltr">{{ localPhone(ticket.customer_phone) }}</span>
      </div>
    </div>

    <div class="space-y-0.5 text-xs">
      <p class="font-bold">
        {{ ticket.device_name }}<span v-if="ticket.color"> · {{ ticket.color }}</span>
      </p>
      <p v-if="ticket.imei" class="num">
        IMEI: {{ ticket.imei }}
      </p>
      <p v-if="ticket.accessories_labels.length">
        مع الجهاز: {{ ticket.accessories_labels.join('، ') }}
      </p>
      <p v-if="ticket.condition_labels.length">
        حالته: {{ ticket.condition_labels.join('، ') }}
      </p>
      <p>
        العطل: {{ [...ticket.reported_faults.map(f => f.name), ticket.reported_note].filter(Boolean).join('، ') }}
      </p>
    </div>

    <div v-if="ticket.parts?.length" class="mt-2 space-y-0.5 border-t border-dashed border-black pt-1 text-xs">
      <p class="font-bold">
        قطع الغيار
      </p>
      <div v-for="p in ticket.parts" :key="p.id">
        <div class="flex justify-between gap-2">
          <span><span class="num">{{ p.qty }}</span> × {{ p.name }}</span><span class="num">{{ formatMoney(p.line_total) }}</span>
        </div>
        <p v-if="p.serials?.length" class="num" dir="ltr">
          {{ p.serials.join(' · ') }}
        </p>
      </div>
    </div>

    <div v-if="ticket.estimate || ticket.paid"class="mt-2 space-y-0.5 border-t border-dashed border-black pt-1 text-xs">
      <div v-if="ticket.estimate" class="flex justify-between">
        <span>التكلفة المبدئية</span><span class="num">{{ formatMoney(ticket.estimate) }}</span>
      </div>
      <div v-if="ticket.paid" class="flex justify-between font-bold">
        <span>عربون</span><span class="num">{{ formatMoney(ticket.paid) }}</span>
      </div>
    </div>

    <div v-if="tracking" class="mt-3 flex flex-col items-center gap-1">
      <PrintQrCode :value="ticketUrl(ticket.public_token)" :size="92" />
      <p class="text-[10px]">
        امسح الكود عشان تتابع حالة جهازك
      </p>
    </div>
    <p class="mt-2 text-center text-[10px]">
      برجاء الاحتفاظ بالإيصال لاستلام الجهاز.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ReceiptShop, RepairTicket } from '~/types/api'

/** What the customer takes home when leaving a device: 80mm thermal. */
withDefaults(defineProps<{ ticket: RepairTicket, shop: ReceiptShop | null, width?: string }>(), { width: '72mm' })

const tracking = computed(() => useSessionStore().hasFeature('repairs.public_tracking'))
</script>

<style scoped>
.intake {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.5;
  padding: 2mm;
}
</style>

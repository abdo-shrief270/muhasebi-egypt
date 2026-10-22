<template>
  <div class="warranty mx-auto bg-white text-black" :style="{ width: paper.width.value }">
    <div class="text-center">
      <p class="text-lg font-extrabold">
        {{ shop?.name }}
      </p>
      <p v-if="shop?.phone" class="text-xs num">
        {{ localPhone(shop.phone) }}
      </p>
      <p class="mt-1 text-sm font-bold">
        كارت ضمان صيانة
      </p>
    </div>
    <div class="my-2 space-y-0.5 border-y border-dashed border-black py-1 text-xs">
      <div class="flex justify-between">
        <span>رقم التذكرة</span><span class="font-extrabold num">{{ ticket.reference }}</span>
      </div>
      <div class="flex justify-between">
        <span>العميل</span><span>{{ ticket.customer_name }}</span>
      </div>
      <div class="flex justify-between">
        <span>الجهاز</span><span>{{ ticket.device_name }}</span>
      </div>
      <div v-if="ticket.imei" class="flex justify-between">
        <span>IMEI</span><span class="num" dir="ltr">{{ ticket.imei }}</span>
      </div>
      <div class="flex justify-between">
        <span>التسليم</span><span class="num">{{ formatDate(ticket.delivered_at) }}</span>
      </div>
    </div>
    <table class="w-full text-xs">
      <thead>
        <tr class="border-b border-black">
          <th class="py-0.5 text-start">
            على إيه
          </th>
          <th class="py-0.5 text-start">
            المدة
          </th>
          <th class="py-0.5 text-start">
            لحد
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="w in standing" :key="w.id">
          <td class="py-0.5">
            {{ w.name }}
          </td>
          <td class="py-0.5 num">
            {{ dayCount(w.days) }}
          </td>
          <td class="py-0.5 font-bold num">
            {{ formatDate(w.ends_at) }}
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="ticket.warranty_defaults?.warranty_terms" class="mt-2 border-t border-dashed border-black pt-1 text-[10px] leading-snug">
      {{ ticket.warranty_defaults.warranty_terms }}
    </p>
    <div v-if="trackUrl" class="mt-2 flex flex-col items-center gap-1">
      <PrintQrCode :value="trackUrl" :size="96" />
      <p class="text-[10px]">
        تابع ضمانك من هنا
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RepairTicket } from '~/types/api'

/** The warranty card handed over with a repaired device (thermal roll): each item and until when. */
const props = defineProps<{ ticket: RepairTicket, shop: { name: string, phone?: string | null } | null }>()
const paper = useThermalPaper()
const standing = computed(() => (props.ticket.warranties ?? []).filter(w => w.state !== 'voided'))
const trackUrl = computed(() => (props.ticket.public_token ? ticketUrl(props.ticket.public_token) : null))
</script>

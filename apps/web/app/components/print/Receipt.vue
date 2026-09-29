<template>
  <div class="receipt mx-auto bg-white text-black" :style="{ width }">
    <div class="text-center">
      <p class="text-lg font-extrabold">
        {{ data.shop?.name }}
      </p>
      <p v-if="data.branch" class="text-xs">
        {{ data.branch }}
      </p>
      <p v-if="data.shop?.phone" class="text-xs num">
        {{ data.shop.phone }}
      </p>
    </div>

    <div class="my-2 border-y border-dashed border-black py-1 text-xs">
      <div class="flex justify-between">
        <span>فاتورة</span><span class="font-bold num">{{ data.reference }}</span>
      </div>
      <div class="flex justify-between">
        <span>التاريخ</span><span class="num">{{ formatDate(data.completed_at, true) }}</span>
      </div>
      <div v-if="data.cashier_name" class="flex justify-between">
        <span>الكاشير</span><span>{{ data.cashier_name }}</span>
      </div>
      <div v-if="data.customer_name" class="flex justify-between">
        <span>العميل</span><span>{{ data.customer_name }}</span>
      </div>
    </div>

    <div class="space-y-1 text-xs">
      <div v-for="(item, i) in data.items" :key="i">
        <p class="font-bold">
          {{ item.name }}
        </p>
        <div class="flex items-baseline justify-between gap-2">
          <span class="num">{{ item.qty }} × {{ money(item.unit_price) }}<template v-if="item.discount"> − {{ money(item.discount) }}</template></span>
          <span class="font-bold num">{{ money(item.line_total) }}</span>
        </div>
      </div>
    </div>

    <div class="mt-2 space-y-0.5 border-t border-dashed border-black pt-1 text-xs">
      <div v-if="data.discount" class="flex justify-between">
        <span>الإجمالي</span><span class="num">{{ money(data.subtotal) }}</span>
      </div>
      <div v-if="data.discount" class="flex justify-between">
        <span>خصم</span><span class="num">−{{ money(data.discount) }}</span>
      </div>
      <div class="flex justify-between text-base font-extrabold">
        <span>المطلوب</span><span class="num">{{ money(data.total) }} ج</span>
      </div>
      <div v-for="(p, i) in data.payments" :key="i" class="flex justify-between">
        <span>{{ p.method_label }}</span><span class="num">{{ money(p.amount) }}</span>
      </div>
      <div v-if="data.change" class="flex justify-between font-bold">
        <span>الباقي للعميل</span><span class="num">{{ money(data.change) }}</span>
      </div>
      <div v-if="data.refunded" class="flex justify-between">
        <span>مرتجع</span><span class="num">−{{ money(data.refunded) }}</span>
      </div>
    </div>

    <div v-if="qrUrl" class="mt-3 flex flex-col items-center gap-1">
      <PrintQrCode :value="qrUrl" :size="92" />
      <p class="text-[10px]">
        امسح الكود عشان تشوف الفاتورة على موبايلك
      </p>
    </div>
    <p class="mt-2 text-center text-xs font-bold">
      شكراً لزيارتك 🌷
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ReceiptData } from '~/types/api'

/** The customer's receipt: thermal 80mm / 58mm, also shown on the public receipt page. */
withDefaults(defineProps<{ data: ReceiptData, qrUrl?: string | null, width?: string }>(), { qrUrl: null, width: '72mm' })

/** "1,250" or "1,250.50" — the currency is printed once, on the total. */
function money(piasters: number): string {
  return (piasters / 100).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.receipt {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.5;
  padding: 2mm;
}
</style>

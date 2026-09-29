<template>
  <div class="space-y-6 text-sm text-black">
    <div class="flex items-start justify-between border-b border-black pb-4">
      <div>
        <p class="text-2xl font-extrabold">
          محاسبي
        </p>
        <p>فاتورة اشتراك</p>
      </div>
      <div class="text-end">
        <p class="num text-lg font-bold" dir="ltr">
          {{ invoice.reference }}
        </p>
        <p>التاريخ: <span class="num">{{ formatDate(invoice.paid_at) }}</span></p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <p class="font-bold">
          العميل
        </p>
        <p>{{ shop.name }}</p>
        <p v-if="shop.phone" class="num" dir="ltr">
          {{ localPhone(shop.phone) }}
        </p>
      </div>
      <div class="text-end">
        <p class="font-bold">
          فترة الاشتراك
        </p>
        <p class="num">
          {{ formatDate(invoice.period_start) }} ← {{ formatDate(invoice.period_end) }}
        </p>
        <p>{{ invoice.method_label }}<span v-if="invoice.payment_reference" class="num"> — {{ invoice.payment_reference }}</span></p>
      </div>
    </div>

    <table class="w-full border-collapse">
      <thead>
        <tr class="border-b border-black">
          <th class="py-2 text-start">
            البيان
          </th>
          <th class="py-2 text-end">
            المبلغ
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(line, i) in invoice.lines" :key="i" class="border-b border-gray-300">
          <td class="py-2">
            {{ line.description }}
          </td>
          <td class="num py-2 text-end">
            {{ formatMoney(line.amount) }}
          </td>
        </tr>
      </tbody>
    </table>

    <div class="ms-auto w-64 space-y-1">
      <div class="flex justify-between">
        <span>قبل الضريبة</span><span class="num">{{ formatMoney(invoice.net) }}</span>
      </div>
      <div class="flex justify-between">
        <span>ضريبة القيمة المضافة 14%</span><span class="num">{{ formatMoney(invoice.vat) }}</span>
      </div>
      <div class="flex justify-between border-t border-black pt-1 text-base font-extrabold">
        <span>الإجمالي</span><span class="num">{{ formatMoney(invoice.total) }}</span>
      </div>
    </div>

    <p v-if="invoice.note" class="text-xs">
      ملاحظة: {{ invoice.note }}
    </p>
    <p class="text-xs">
      الأسعار شاملة ضريبة القيمة المضافة. تم الدفع — شكراً لاشتراكك في محاسبي.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { BillingInvoiceInfo } from '~/types/api'

/** A subscription invoice on A4 (wrap in <PrintSheet page-size="A4">). */
defineProps<{ invoice: BillingInvoiceInfo, shop: { name: string, phone: string | null } }>()
</script>

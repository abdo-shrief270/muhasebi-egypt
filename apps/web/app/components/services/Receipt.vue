<template>
  <div class="receipt mx-auto bg-white text-black" :style="{ width: width ?? paper.width.value }">
    <div class="text-center">
      <p class="text-lg font-extrabold">
        {{ shopName }}
      </p>
      <p v-if="branchName" class="text-xs">
        {{ branchName }}
      </p>
    </div>

    <p class="mt-2 border border-black p-1 text-center text-sm font-bold">
      {{ t.reverses_id ? `إلغاء ${t.type_label}` : t.type_label }} · {{ t.provider_label ?? t.account_name }}
    </p>

    <div class="my-2 space-y-0.5 border-y border-dashed border-black py-1 text-xs">
      <div class="flex justify-between">
        <span>رقم العملية</span><span class="font-bold num">{{ serviceReference(t.number) }}</span>
      </div>
      <div class="flex justify-between">
        <span>التاريخ</span><span class="num">{{ formatDate(t.created_at, true) }}</span>
      </div>
      <div v-if="t.user_name" class="flex justify-between">
        <span>الموظف</span><span>{{ t.user_name }}</span>
      </div>
      <div v-if="t.customer_phone" class="flex justify-between">
        <span>{{ t.type === 'topup' ? 'الخط' : 'رقم العميل' }}</span><span class="num">{{ localPhone(t.customer_phone) }}</span>
      </div>
      <div v-if="t.customer_name" class="flex justify-between">
        <span>العميل</span><span>{{ t.customer_name }}</span>
      </div>
      <div v-if="t.reference" class="flex justify-between">
        <span>مرجع التحويل</span><span class="num">{{ t.reference }}</span>
      </div>
    </div>

    <div class="space-y-0.5 text-xs">
      <div class="flex justify-between">
        <span>{{ t.type === 'topup' ? 'قيمة الشحن' : 'المبلغ' }}</span><span class="num">{{ money(t.amount) }}</span>
      </div>
      <div v-if="t.fee" class="flex justify-between">
        <span>العمولة</span><span class="num">{{ money(t.fee) }}</span>
      </div>
      <div class="flex justify-between border-t border-dashed border-black pt-1 text-base font-extrabold">
        <span>{{ t.cash >= 0 ? 'المدفوع' : 'اتسلّم للعميل' }}</span><span class="num">{{ money(Math.abs(t.cash)) }} ج</span>
      </div>
    </div>

    <p class="mt-2 text-center text-xs font-bold">
      شكراً لزيارتك 🌷
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ServiceTransaction } from '~/types/api'

/** The customer's slip for a wallet transfer or a top-up: thermal 80mm. */
withDefaults(defineProps<{ t: ServiceTransaction, shopName: string, branchName?: string | null, width?: string }>(), { branchName: null })
const paper = useThermalPaper()

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

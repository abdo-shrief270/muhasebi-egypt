<template>
  <UModal v-model:open="open" :title="sale ? `اتباعت — ${sale.reference}` : ''" :ui="{ content: 'sm:max-w-md' }">
    <template #body>
      <div v-if="sale" class="space-y-4">
        <UAlert v-if="sale.offline" color="warning" variant="subtle" icon="i-lucide-wifi-off" :title="OFFLINE_NOTE" description="الفاتورة محفوظة على الجهاز ده وهتاخد رقمها لما تتسجل." />
        <div class="flex items-center justify-between rounded-[calc(var(--ui-radius)*1.5)] app-soft p-4">
          <span class="font-bold">{{ sale.change ? 'الباقي للعميل' : 'اتدفع' }}</span>
          <span class="text-3xl font-extrabold num">{{ formatMoney(sale.change || sale.paid) }}</span>
        </div>
        <UButton
          v-if="canPlan"
          :to="`/installments/new?customer=${sale.customer_id}&sale=${sale.id}`"
          block
          color="neutral"
          variant="outline"
          icon="i-lucide-calendar-clock"
          :label="`قسّط الآجل (${formatMoney(sale.credit)})`"
        />
        <div class="max-h-72 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-white py-2">
          <PrintReceipt :data="receipt" :qr-url="qrUrl" :note="sale.offline ? OFFLINE_NOTE : null" width="100%" />
        </div>
      </div>
    </template>
    <template #footer>
      <div class="grid w-full gap-2" :class="canShare ? 'grid-cols-3' : 'grid-cols-2'">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة" class="justify-center" @click="print" />
        <UButton v-if="canShare" color="neutral" variant="outline" icon="i-lucide-message-circle" label="WhatsApp" class="justify-center" :disabled="sale?.offline" @click="shareWhatsapp" />
        <UButton icon="i-lucide-plus" label="بيع جديد" class="justify-center" @click="open = false" />
      </div>
    </template>
  </UModal>

  <PrintSheet v-if="printing && sale" :page-size="paper.page.value">
    <PrintReceipt :data="receipt" :qr-url="qrUrl" :note="sale.offline ? OFFLINE_NOTE : null" />
  </PrintSheet>
</template>

<script setup lang="ts">
import type { Sale } from '~/types/api'

const paper = useThermalPaper()
const props = defineProps<{ sale: Sale | null }>()
const open = defineModel<boolean>('open', { default: false })

const store = useSessionStore()
// A credit sale can be split into installments right away (not one still waiting to reach the server).
const canPlan = computed(() => !!props.sale && props.sale.credit > 0 && !!props.sale.customer_id && !props.sale.offline
  && store.hasModule('installments') && store.can('installments.manage'))
const { printing, print } = usePrint()

const shop = useReceiptShop()
const receipt = computed(() => props.sale ? receiptFromSale(props.sale, shop.value, store.currentBranch?.name ?? null) : receiptFromSale({ items: [], payments: [] } as unknown as Sale, null, null))
// A sale made offline has no public link until it reaches the server.
const qrUrl = computed(() => props.sale && !props.sale.offline && store.hasFeature('sales.receipt_link') ? receiptUrl(props.sale.public_token) : null)

const messages = useMessages()
// The receipt link + WhatsApp share are one owner switch («لينك وQR الفاتورة للعميل»).
const canShare = computed(() => store.hasFeature('sales.receipt_link'))

function shareWhatsapp() {
  if (!props.sale || props.sale.offline) {
    return
  }
  messages.sendSale(props.sale)
}

defineExpose({ print })
</script>

<template>
  <UModal v-model:open="open" :title="sale ? `اتباعت — ${sale.reference}` : ''" :ui="{ content: 'sm:max-w-md' }">
    <template #body>
      <div v-if="sale" class="space-y-4">
        <UAlert v-if="sale.offline" color="warning" variant="subtle" icon="i-lucide-wifi-off" :title="OFFLINE_NOTE" description="الفاتورة محفوظة على الجهاز ده وهتاخد رقمها لما تتسجل." />
        <div class="flex items-center justify-between rounded-[calc(var(--ui-radius)*1.5)] app-soft p-4">
          <span class="font-bold">{{ sale.change ? 'الباقي للعميل' : 'اتدفع' }}</span>
          <span class="text-3xl font-extrabold num">{{ formatMoney(sale.change || sale.paid) }}</span>
        </div>
        <div class="max-h-72 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-white py-2">
          <PrintReceipt :data="receipt" :qr-url="qrUrl" :note="sale.offline ? OFFLINE_NOTE : null" width="100%" />
        </div>
      </div>
    </template>
    <template #footer>
      <div class="grid w-full grid-cols-3 gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة" class="justify-center" @click="print" />
        <UButton color="neutral" variant="outline" icon="i-lucide-message-circle" label="WhatsApp" class="justify-center" :disabled="sale?.offline" @click="shareWhatsapp" />
        <UButton icon="i-lucide-plus" label="بيع جديد" class="justify-center" @click="open = false" />
      </div>
    </template>
  </UModal>

  <PrintSheet v-if="printing && sale" page-size="80mm auto">
    <PrintReceipt :data="receipt" :qr-url="qrUrl" :note="sale.offline ? OFFLINE_NOTE : null" />
  </PrintSheet>
</template>

<script setup lang="ts">
import type { Sale } from '~/types/api'

const props = defineProps<{ sale: Sale | null }>()
const open = defineModel<boolean>('open', { default: false })

const store = useSessionStore()
const { printing, print } = usePrint()

const shop = computed(() => store.session ? { name: store.session.tenant.name, phone: store.session.tenant.phone } : null)
const receipt = computed(() => props.sale ? receiptFromSale(props.sale, shop.value, store.currentBranch?.name ?? null) : receiptFromSale({ items: [], payments: [] } as unknown as Sale, null, null))
// A sale made offline has no public link until it reaches the server.
const qrUrl = computed(() => props.sale && !props.sale.offline && store.hasFeature('sales.receipt_link') ? receiptUrl(props.sale.public_token) : null)

const messages = useMessages()

function shareWhatsapp() {
  if (!props.sale || props.sale.offline) {
    return
  }
  messages.sendSale(props.sale)
}

defineExpose({ print })
</script>

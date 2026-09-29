<template>
  <UModal v-model:open="open" :title="sale ? `اتباعت — ${sale.reference}` : ''" :ui="{ content: 'sm:max-w-md' }">
    <template #body>
      <div v-if="sale" class="space-y-4">
        <div class="flex items-center justify-between rounded-[calc(var(--ui-radius)*1.5)] app-soft p-4">
          <span class="font-bold">{{ sale.change ? 'الباقي للعميل' : 'اتدفع' }}</span>
          <span class="text-3xl font-extrabold num">{{ formatMoney(sale.change || sale.paid) }}</span>
        </div>
        <div class="max-h-72 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-white py-2">
          <PrintReceipt :data="receipt" :qr-url="qrUrl" width="100%" />
        </div>
      </div>
    </template>
    <template #footer>
      <div class="grid w-full grid-cols-3 gap-2">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="طباعة" class="justify-center" @click="print" />
        <UButton color="neutral" variant="outline" icon="i-lucide-message-circle" label="WhatsApp" class="justify-center" @click="shareWhatsapp" />
        <UButton icon="i-lucide-plus" label="بيع جديد" class="justify-center" @click="open = false" />
      </div>
    </template>
  </UModal>

  <PrintSheet v-if="printing && sale" page-size="80mm auto">
    <PrintReceipt :data="receipt" :qr-url="qrUrl" />
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
const qrUrl = computed(() => props.sale ? receiptUrl(props.sale.public_token) : null)

function shareWhatsapp() {
  if (!props.sale) {
    return
  }
  const text = receiptWhatsappText(props.sale, shop.value?.name ?? '')
  const phone = props.sale.customer_phone
  window.open(phone ? whatsappLink(phone, text) : `https://wa.me/?text=${encodeURIComponent(text)}`, '_blank')
}

defineExpose({ print })
</script>

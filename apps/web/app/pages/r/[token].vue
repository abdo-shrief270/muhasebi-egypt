<template>
  <div class="space-y-4">
    <UAlert v-if="error" color="error" variant="subtle" icon="i-lucide-receipt" title="الإيصال ده مش موجود" description="اتأكد من اللينك أو اسأل المحل." />
    <template v-else-if="data">
      <div class="app-card overflow-hidden rounded-[calc(var(--ui-radius)*2)] bg-white py-3">
        <PrintReceipt :data="data" width="100%" />
      </div>
      <p v-if="data.refunded" class="text-center text-sm text-(--ui-text-muted)">
        فيه مرتجع من الفاتورة دي بـ <span class="num">{{ formatMoney(data.refunded) }}</span>.
      </p>
      <p class="text-center text-xs text-(--ui-text-muted)">
        إيصال إلكتروني من محاسبي ·
        <NuxtLink to="/privacy" class="hover:text-primary hover:underline">الخصوصية وبياناتك</NuxtLink>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ReceiptData } from '~/types/api'

// Opened from the QR code on a paper receipt or a WhatsApp link: no login.
definePageMeta({ layout: 'auth', public: true })

const route = useRoute()
const config = useRuntimeConfig()

const { data: res, error } = await useAsyncData(`receipt-${route.params.token}`, () =>
  $fetch<{ data: ReceiptData }>(`${config.public.apiBase}/public/receipts/${route.params.token}`, { headers: { Accept: 'application/json' } }))
const data = computed(() => res.value?.data)

useHead({ title: computed(() => data.value ? `${data.value.reference} — ${data.value.shop?.name ?? ''}` : 'إيصال') })
</script>

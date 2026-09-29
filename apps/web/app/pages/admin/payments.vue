<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-extrabold">
        المدفوعات
      </h1>
      <UFieldGroup>
        <UButton v-for="t in tabs" :key="t.value" :label="t.label" :color="status === t.value ? 'primary' : 'neutral'" :variant="status === t.value ? 'solid' : 'outline'" @click="status = t.value" />
      </UFieldGroup>
    </div>
    <AdminPaymentCard v-for="p in payments" :key="p.id" :payment="p" @changed="refresh" />
    <p v-if="!payments.length" class="py-10 text-center text-(--ui-text-muted)">
      مفيش حاجة هنا.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { AdminPayment } from '~/types/api'

definePageMeta({ public: true, layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const tabs = [
  { value: 'pending', label: 'مستنية' },
  { value: 'approved', label: 'اتقبلت' },
  { value: 'rejected', label: 'اترفضت' },
  { value: 'all', label: 'الكل' },
]
const status = ref('pending')
const { data, refresh } = await useAsyncData('admin-payments', () => api<{ data: AdminPayment[] }>('/payments', { query: { status: status.value } }), { watch: [status] })
const payments = computed(() => data.value?.data ?? [])
</script>

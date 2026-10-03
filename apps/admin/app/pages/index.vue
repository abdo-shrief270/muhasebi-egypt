<template>
  <div v-if="overview" class="space-y-6">
    <h1 class="text-2xl font-extrabold">
      نظرة عامة
    </h1>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
      <NuxtLink v-for="card in cards" :key="card.label" :to="card.to" class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 hover:ring-1 hover:ring-primary">
        <p class="text-sm text-(--ui-text-muted)">
          {{ card.label }}
        </p>
        <p class="num text-2xl font-extrabold" :class="card.tone">
          {{ card.value }}
        </p>
      </NuxtLink>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold">
          تحويلات مستنية مراجعة
        </h2>
        <UButton to="/payments" color="neutral" variant="ghost" label="الكل" trailing-icon="i-lucide-arrow-left" />
      </div>
      <PaymentCard v-for="p in payments" :key="p.id" :payment="p" @changed="reload" />
      <p v-if="!payments.length" class="py-6 text-center text-(--ui-text-muted)">
        مفيش تحويلات مستنية.
      </p>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { AdminOverview, AdminPayment } from '~/types/api'


const api = useAdminApi()
const { data, refresh } = await useAsyncData('admin-overview', () => api<{ data: AdminOverview }>('/overview'))
const { data: paymentsData, refresh: refreshPayments } = await useAsyncData('admin-pending', () => api<{ data: AdminPayment[] }>('/payments', { query: { status: 'pending' } }))
const overview = computed(() => data.value?.data)
const payments = computed(() => (paymentsData.value?.data ?? []).slice(0, 10))
const reload = () => Promise.all([refresh(), refreshPayments()])

const cards = computed(() => {
  const o = overview.value!
  return [
    { label: 'تحويلات مستنية', value: o.pending_payments, to: '/payments', tone: o.pending_payments ? 'text-(--ui-warning)' : '' },
    { label: 'الإيراد الشهري المتكرر', value: formatMoney(o.mrr), to: '/shops?status=active', tone: 'text-(--ui-success)' },
    { label: 'اتحصّل الشهر ده', value: formatMoney(o.collected_this_month), to: '/shops', tone: '' },
    { label: 'بيخلص خلال أسبوع', value: o.expiring_soon, to: '/shops?status=expiring', tone: '' },
    { label: 'كل المحلات', value: o.shops, to: '/shops', tone: '' },
    { label: 'مشتركين', value: o.counts.active, to: '/shops?status=active', tone: 'text-(--ui-success)' },
    { label: 'تجربة', value: o.counts.trialing, to: '/shops?status=trialing', tone: 'text-(--ui-info)' },
    { label: 'متأخرين / محدود / موقوف', value: `${o.counts.past_due} / ${o.counts.restricted} / ${o.counts.suspended}`, to: '/shops?status=past_due', tone: 'text-(--ui-error)' },
    { label: 'في فترة Beta', value: o.beta, to: '/shops?status=beta', tone: 'text-(--ui-info)' },
    { label: 'ملاحظات جديدة', value: o.new_feedback, to: '/feedback', tone: o.new_feedback ? 'text-(--ui-warning)' : '' },
    { label: 'أخطاء واجهة مفتوحة', value: o.open_errors, to: '/errors', tone: o.open_errors ? 'text-(--ui-error)' : '' },
    { label: 'أخطاء آخر 24 ساعة', value: o.errors_24h, to: '/errors', tone: '' },
  ]
})
</script>

<template>
  <UAlert
    v-if="message"
    :color="message.color"
    variant="subtle"
    :icon="message.icon"
    :title="message.title"
    :description="message.description"
    :actions="store.isOwner ? [{ label: 'الاشتراك', to: '/settings/billing', color: message.color, variant: 'solid' }] : []"
    class="mb-4"
  />
</template>

<script setup lang="ts">
/** Trial ending, unpaid, restricted or suspended: shown on every screen (the owner can act on it). */
const store = useSessionStore()
const route = useRoute()
const { status, refresh } = useSubscription()
onMounted(refresh)
watch(() => store.session?.tenant.id, refresh)

const message = computed(() => {
  const s = status.value
  if (!s || route.path === '/settings/billing') {
    return null
  }
  const until = formatDate(s.paid_until)
  switch (s.status) {
    case 'trialing':
      return store.isOwner && s.days_left <= 5
        ? { color: 'info' as const, icon: 'i-lucide-hourglass', title: `التجربة المجانية فاضل فيها ${Math.max(0, s.days_left)} يوم`, description: 'اشترك دلوقتي عشان الشغل يكمل من غير توقف.' }
        : null
    case 'active':
      return store.isOwner && s.days_left <= 5
        ? { color: 'info' as const, icon: 'i-lucide-calendar-clock', title: `الاشتراك بيخلص ${until}`, description: 'جدّد قبلها عشان متتقفلش أي حاجة.' }
        : null
    case 'past_due':
      return { color: 'warning' as const, icon: 'i-lucide-triangle-alert', title: 'الاشتراك خلص', description: `كل حاجة شغالة لحد دلوقتي، بس جدّد في أقرب وقت (خلص ${until}).` }
    case 'restricted':
      return { color: 'warning' as const, icon: 'i-lucide-lock', title: 'الاشتراك متأخر: البيع شغال بس', description: 'مينفعش تضيف أصناف أو موظفين أو فروع لحد ما الاشتراك يتجدد.' }
    case 'suspended':
      return { color: 'error' as const, icon: 'i-lucide-ban', title: 'الاشتراك موقوف', description: s.suspended_reason ?? 'تقدر تتفرج وتصدّر بس. جدّد الاشتراك عشان ترجع تشتغل.' }
    default:
      return null
  }
})
</script>

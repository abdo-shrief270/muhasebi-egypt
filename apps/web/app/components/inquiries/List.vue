<template>
  <div class="space-y-3">
    <p v-if="!items.length" class="py-6 text-center text-sm text-(--ui-text-muted)">
      {{ pending ? 'بنحمّل…' : 'مفيش استعلامات هنا.' }}
    </p>
    <UCard v-for="i in items" :key="i.id" :ui="{ body: 'space-y-2' }">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <NuxtLink :to="`/customers/${i.customer_id}`" class="font-bold hover:text-primary">{{ i.customer_name }}</NuxtLink>
          <a v-if="i.customer_phone" :href="`tel:${i.customer_phone}`" class="num ms-2 text-sm text-(--ui-text-muted)" dir="ltr">{{ localPhone(i.customer_phone) }}</a>
        </div>
        <span class="text-xs text-(--ui-text-muted)">{{ formatDate(i.created_at, true) }}<template v-if="i.created_by_name"> · {{ i.created_by_name }}</template></span>
      </div>
      <p>{{ i.text }}</p>
      <div class="flex flex-wrap items-center gap-2">
        <UBadge color="neutral" variant="subtle">
          {{ i.kind_label }}
        </UBadge>
        <UBadge v-if="i.status === 'open' && i.follow_up_at" :color="i.overdue ? 'error' : 'warning'" variant="subtle" icon="i-lucide-phone-call">
          {{ i.overdue ? 'اتأخرت في الرد' : 'كلّمه' }} {{ formatDate(i.follow_up_at, true) }}
        </UBadge>
        <UBadge v-if="i.status !== 'open'" color="success" variant="subtle">
          {{ i.status_label }}<template v-if="i.outcome">: {{ i.outcome }}</template>
        </UBadge>
        <span class="flex-1" />
        <template v-if="i.status === 'open' && canManage">
          <UButton v-if="i.customer_phone" size="xs" color="neutral" variant="outline" icon="i-lucide-message-circle" label="واتساب" @click="whatsapp(i)" />
          <UButton v-if="store.can('sales.sell') && i.kind !== 'repair'" size="xs" color="neutral" variant="outline" icon="i-lucide-shopping-cart" label="بيع" @click="convert(i, 'sale')" />
          <UButton v-if="store.can('repairs.create')" size="xs" color="neutral" variant="outline" icon="i-lucide-wrench" label="استلام جهاز" @click="convert(i, 'repair')" />
          <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-check" label="اتردّ عليه" @click="close(i, 'answered')" />
          <UButton size="xs" color="neutral" variant="ghost" label="اقفل" @click="close(i, 'closed')" />
        </template>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { Inquiry } from '~/types/api'

/** Inquiries as cards with what to do next: call, sell, take the device, or close. */
defineProps<{ items: Inquiry[], pending?: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const canManage = computed(() => store.can('inquiries.manage'))

async function patch(i: Inquiry, body: Record<string, unknown>) {
  try {
    await api(`/inquiries/${i.id}`, { method: 'PATCH', body })
    emit('changed')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function close(i: Inquiry, status: 'answered' | 'closed') {
  const outcome = status === 'answered' ? window.prompt('قلت له إيه؟ (اختياري)') : window.prompt('ليه اتقفل؟ (اختياري)')
  if (outcome === null) return
  await patch(i, { status, outcome: outcome.trim() || null })
}

async function convert(i: Inquiry, to: 'sale' | 'repair') {
  await patch(i, { status: 'converted', converted_to: to })
  await navigateTo(to === 'sale' ? '/pos' : `/repairs/quick?customer=${i.customer_id}`)
}

function whatsapp(i: Inquiry) {
  const shop = store.session?.tenant.name ?? ''
  const message = `أهلاً ${i.customer_name} 👋\nبخصوص سؤالك عن «${i.text}» عند ${shop}:\n`
  window.open(whatsappLink(i.customer_phone as string, message), '_blank')
}
</script>

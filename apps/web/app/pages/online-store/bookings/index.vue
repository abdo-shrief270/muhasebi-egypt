<template>
  <div class="space-y-6">
    <PageHeader title="حجوزات الصيانة" description="الزباين اللي حجزوا صيانة من المتجر الأونلاين. كلّم الزبون واتفق على ميعاد، ولما الجهاز يوصل اعمله تذكرة من هنا.">
      <UButton to="/online-store/orders" color="neutral" variant="outline" icon="i-lucide-shopping-bag" label="طلبات المتجر" />
    </PageHeader>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="h-10 rounded-full px-4 font-semibold transition"
        :class="filter === tab.value ? 'bg-primary text-white' : 'bg-(--ui-bg-elevated) hover:bg-(--ui-border)'"
        @click="filter = tab.value"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="!bookings.length" class="space-y-2 rounded-(--ui-radius) border border-dashed border-(--ui-border) py-16 text-center">
      <UIcon name="i-lucide-wrench" class="size-10 text-(--ui-text-muted)" />
      <p class="font-semibold">
        مفيش حجوزات هنا
      </p>
      <p v-if="filter === 'open' && store.can('online_store.manage')" class="text-sm text-(--ui-text-muted)">
        «احجز صيانة» بيتفعّل من <ULink to="/online-store" class="font-bold text-primary">إعدادات المتجر</ULink>.
      </p>
    </div>
    <div v-else class="grid gap-3 lg:grid-cols-2">
      <UCard v-for="b in bookings" :id="`booking-${b.id}`" :key="b.id" :class="b.id === route.query.open ? 'ring-2 ring-(--ui-primary)' : ''">
        <div class="flex flex-wrap items-start gap-2">
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              {{ b.device }} <span class="num text-sm font-normal text-(--ui-text-muted)" dir="ltr">{{ b.reference }}</span>
            </p>
            <p class="text-sm">
              {{ b.customer_name }} · <a :href="`tel:${b.customer_phone}`" class="num" dir="ltr">{{ localPhone(b.customer_phone) }}</a>
            </p>
          </div>
          <UBadge :color="statusColors[b.status]" variant="subtle" :label="b.status_label" />
        </div>
        <p class="mt-2 whitespace-pre-line text-sm">
          {{ b.problem }}
        </p>
        <p class="mt-2 text-xs text-(--ui-text-muted)">
          اتحجز <span class="num">{{ formatDate(b.created_at, true) }}</span>
          <template v-if="b.preferred_on">
            · جاي يوم <span class="num font-bold text-(--ui-text)">{{ formatDate(b.preferred_on) }}</span>
          </template>
          <template v-if="b.handled_by_name">
            · {{ b.handled_by_name }}
          </template>
        </p>
        <p v-if="b.cancel_reason" class="mt-1 text-sm text-(--ui-error)">
          السبب: {{ b.cancel_reason }}
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
          <UButton size="sm" color="success" variant="soft" icon="i-lucide-message-circle" label="واتساب" :href="whatsappLink(b.customer_phone, `أهلاً ${b.customer_name}، معاك ${shopName} بخصوص حجز صيانة ${b.device} (${b.reference}).`)" target="_blank" />
          <template v-if="b.status === 'new' || b.status === 'contacted'">
            <UButton v-if="b.status === 'new'" size="sm" color="neutral" variant="outline" icon="i-lucide-phone-call" label="كلّمته" :loading="busy === b.id" @click="move(b, 'contacted')" />
            <UButton v-if="store.can('repairs.create')" size="sm" icon="i-lucide-clipboard-plus" label="الجهاز وصل — اعمل تذكرة" :to="`/repairs/new?booking=${b.id}`" />
            <UButton size="sm" color="error" variant="ghost" icon="i-lucide-x" label="الغي" @click="cancelling = b" />
          </template>
          <UButton v-if="b.ticket_id" size="sm" color="neutral" variant="outline" icon="i-lucide-wrench" :label="`التذكرة ${b.ticket_reference}`" :to="`/repairs/${b.ticket_id}`" />
        </div>
      </UCard>
    </div>
    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="30" />
    </div>

    <UModal :open="!!cancelling" title="إلغاء الحجز" @update:open="v => !v && (cancelling = null)">
      <template #body>
        <UFormField label="السبب">
          <UInput v-model="reason" class="w-full" maxlength="255" placeholder="مثلاً الزبون مش جاي" />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelling = null" />
          <UButton color="error" label="الغي الحجز" :disabled="!reason.trim()" :loading="busy === cancelling?.id" @click="cancelling && move(cancelling, 'cancelled', reason.trim())" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { RepairBooking } from '~/types/api'

definePageMeta({ permission: 'online_store.orders', module: 'online_store' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const shopName = computed(() => store.session?.tenant.name ?? '')

const tabs = [
  { value: 'open', label: 'مستنيين' },
  { value: 'converted', label: 'اتعملهم تذكرة' },
  { value: 'cancelled', label: 'اتلغوا' },
]
const statusColors: Record<RepairBooking['status'], 'warning' | 'info' | 'success' | 'neutral'> = { new: 'warning', contacted: 'info', converted: 'success', cancelled: 'neutral' }
const filter = ref('open')
const page = ref(1)
watch(filter, () => {
  page.value = 1
})

const { data, refresh } = await useAsyncData('online-store-bookings', () => api<{ data: RepairBooking[], meta: { total: number, last_page: number, new: number } }>('/online-store/bookings', {
  query: { status: filter.value, page: page.value },
}), { watch: [filter, page] })
const bookings = computed(() => data.value?.data ?? [])

const busy = ref<string | null>(null)
const cancelling = ref<RepairBooking | null>(null)
const reason = ref('')
async function move(b: RepairBooking, status: 'contacted' | 'cancelled', why?: string) {
  busy.value = b.id
  try {
    await api(`/online-store/bookings/${b.id}/status`, { method: 'POST', body: { status, reason: why ?? null } })
    cancelling.value = null
    reason.value = ''
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

onMounted(() => {
  if (typeof route.query.open === 'string') {
    nextTick(() => document.getElementById(`booking-${route.query.open}`)?.scrollIntoView({ block: 'center' }))
  }
})
</script>

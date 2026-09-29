<template>
  <div class="max-w-3xl space-y-6">
    <PageHeader title="بحث بالـ IMEI" description="امسح أو اكتب الـ IMEI أو السيريال (أو آخر أرقامه): الجهاز ده إيه، فين دلوقتي، واتشرى من مين واتباع لمين." />

    <UInput
      v-model="q"
      size="xl"
      icon="i-lucide-scan-line"
      placeholder="IMEI / سيريال…"
      dir="ltr"
      class="w-full"
      autofocus
    />

    <p v-if="status === 'pending'" class="text-center text-sm text-(--ui-text-muted)">
      بندوّر…
    </p>
    <p v-else-if="term.length >= 4 && !results.length" class="py-6 text-center text-(--ui-text-muted)">
      الرقم ده مش متسجّل عندنا.
    </p>

    <UCard v-for="r in results" :key="r.serial">
      <template #header>
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="num text-lg font-extrabold" dir="ltr">
              {{ r.serial }}
            </p>
            <p class="font-bold">
              {{ r.variant?.display_name ?? '—' }}
            </p>
          </div>
          <div class="text-end">
            <UBadge :color="r.status === 'in_stock' ? 'success' : r.status === 'damaged' ? 'warning' : 'neutral'" variant="subtle">
              {{ r.status_label }}
            </UBadge>
            <p v-if="r.status === 'in_stock' && r.branch_name" class="mt-1 text-xs text-(--ui-text-muted)">
              {{ r.branch_name }}
            </p>
          </div>
        </div>
      </template>
      <ol class="space-y-3">
        <li v-for="(e, i) in r.events" :key="i" class="flex gap-3">
          <UIcon :name="eventIcon(e.type)" class="mt-0.5 size-5 shrink-0 text-(--ui-text-muted)" />
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              {{ e.type_label }}
              <ULink v-if="link(e)" :to="link(e)!" class="ms-1 text-sm font-normal text-primary">
                {{ e.note }}
              </ULink>
              <span v-else-if="e.note" class="ms-1 text-sm font-normal text-(--ui-text-muted)">{{ e.note }}</span>
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              <span class="num">{{ formatDate(e.created_at, true) }}</span>
              <span v-if="e.user_name"> · {{ e.user_name }}</span>
              <span v-if="e.branch_name"> · {{ e.branch_name }}</span>
            </p>
          </div>
        </li>
      </ol>
    </UCard>
  </div>
</template>

<script setup lang="ts">
interface SerialEvent {
  type: string
  type_label: string
  ref_type: string | null
  ref_id: string | null
  note: string | null
  user_name: string | null
  branch_name: string | null
  created_at: string
}
interface SerialResult {
  serial: string
  status: 'in_stock' | 'out' | 'damaged'
  status_label: string
  variant: { id: string, display_name: string } | null
  branch_name: string | null
  events: SerialEvent[]
}

definePageMeta({ permission: 'inventory.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const term = ref(q.value.trim())

let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    term.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const { data, status } = await useAsyncData('serial-lookup', () => term.value.length >= 4
  ? api<{ data: SerialResult[] }>('/inventory/serials', { query: { q: term.value } })
  : Promise.resolve({ data: [] as SerialResult[] }), { watch: [term] })
const results = computed(() => data.value?.data ?? [])

function link(e: SerialEvent): string | null {
  if (e.ref_type === 'sale' && e.ref_id && store.can('sales.view')) {
    return `/sales/${e.ref_id}`
  }
  if (e.ref_type === 'purchase' && e.ref_id && store.can('suppliers.view')) {
    return `/purchases/${e.ref_id}`
  }
  return null
}

function eventIcon(type: string): string {
  return ({
    purchase: 'i-lucide-package-plus',
    sale: 'i-lucide-shopping-cart',
    sale_return: 'i-lucide-undo-2',
    supplier_return: 'i-lucide-truck',
  } as Record<string, string>)[type] ?? 'i-lucide-circle-dot'
}
</script>

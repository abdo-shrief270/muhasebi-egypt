<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <PageHeader title="الاستعلامات" description="عميل سأل على صنف أو تصليح أو موبايل: سجّله وفكّر نفسك تكلّمه." class="flex-1" />
      <UInput v-model="q" icon="i-lucide-search" placeholder="الاسم أو الرقم أو اللي سأل عليه" class="w-full sm:w-72" />
      <UButton v-if="store.can('inquiries.manage')" icon="i-lucide-plus" label="استعلام جديد" @click="formOpen = true" />
    </div>

    <div class="flex flex-wrap gap-2">
      <UButton v-for="s in statuses" :key="s.value" size="sm" :color="status === s.value ? 'primary' : 'neutral'" :variant="status === s.value ? 'solid' : 'outline'" @click="status = s.value">
        {{ s.label }}<span v-if="counts[s.value]" class="num ms-1 opacity-75">({{ counts[s.value] }})</span>
      </UButton>
    </div>

    <InquiriesList :items="items" :pending="pending" @changed="() => refresh()" />
    <div v-if="lastPage > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="total" :items-per-page="30" />
    </div>

    <InquiriesFormModal v-model:open="formOpen" @saved="() => refresh()" />
  </div>
</template>

<script setup lang="ts">
import type { Inquiry } from '~/types/api'

definePageMeta({ permission: 'inquiries.view' })

const api = useApi()
const store = useSessionStore()
const route = useRoute()

const statuses = [
  { value: 'open', label: 'مفتوحة' },
  { value: 'due', label: 'جه ميعاد الرد' },
  { value: 'answered', label: 'اتردّ عليها' },
  { value: 'converted', label: 'اتحوّلت لبيع / تذكرة' },
  { value: 'closed', label: 'اتقفلت' },
]
const status = ref(typeof route.query.status === 'string' ? route.query.status : 'open')
const formOpen = ref(route.query.new === '1')
const q = ref('')
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
const page = ref(1)
watch([status, debouncedQ], () => {
  page.value = 1
})

const { data, pending, refresh } = await useAsyncData('inquiries', () => api<{ data: Inquiry[], meta: { last_page: number, total: number, counts: Record<string, number> } }>('/inquiries', {
  query: { status: status.value, q: debouncedQ.value || undefined, page: page.value },
}), { watch: [status, debouncedQ, page] })
const items = computed(() => data.value?.data ?? [])
const counts = computed(() => data.value?.meta.counts ?? {})
const lastPage = computed(() => data.value?.meta.last_page ?? 1)
const total = computed(() => data.value?.meta.total ?? 0)
</script>

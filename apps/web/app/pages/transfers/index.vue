<template>
  <div class="space-y-6">
    <PageHeader title="التحويلات بين الفروع" description="فرع يطلب بضاعة أو يبعتها لفرع تاني، والتاني يستلمها. المخزون والسيريالات والتكلفة بتتنقل معاها.">
      <UButton to="/transfers/new" icon="i-lucide-plus" label="تحويل جديد" />
    </PageHeader>

    <div v-if="meta && (meta.to_receive || meta.to_ship)" class="grid gap-3 sm:grid-cols-2">
      <UAlert v-if="meta.to_receive" color="info" variant="subtle" icon="i-lucide-truck" :title="`${meta.to_receive} تحويل في الطريق ليك — استلمه لما يوصل`" />
      <UAlert v-if="meta.to_ship" color="warning" variant="subtle" icon="i-lucide-package" :title="`${meta.to_ship} طلب تحويل مستني تبعته`" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="h-10 rounded-full px-4 font-semibold transition"
        :class="box === tab.value ? 'bg-primary text-white' : 'bg-(--ui-bg-elevated) hover:bg-(--ui-border)'"
        @click="box = tab.value"
      >
        {{ tab.label }}
      </button>
      <USelect v-model="status" :items="statuses" class="ms-auto w-40" aria-label="الحالة" />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="pending && !transfers.length" class="space-y-3 p-6">
        <USkeleton v-for="i in 3" :key="i" class="h-12 w-full" />
      </div>
      <div v-else-if="!transfers.length" class="space-y-3 py-16 text-center">
        <UIcon name="i-lucide-git-compare-arrows" class="size-10 text-(--ui-text-muted)" />
        <p class="font-semibold">
          مفيش تحويلات هنا
        </p>
      </div>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="t in transfers" :key="t.id">
          <NuxtLink :to="`/transfers/${t.id}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-(--ui-bg-muted)">
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                <span class="num" dir="ltr">{{ t.reference }}</span> · {{ t.from.name }} ← {{ t.to.name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                <span class="num">{{ t.status === 'requested' ? t.units_requested : t.units_shipped }}</span> قطعة · {{ formatDate(t.created_at, true) }}
                <template v-if="t.requested_by_name">
                  · {{ t.requested_by_name }}
                </template>
              </p>
            </div>
            <UBadge v-if="t.can_receive" color="info" variant="soft" label="استلم" />
            <UBadge v-else-if="t.can_ship" color="warning" variant="soft" label="ابعت" />
            <UBadge :color="TRANSFER_COLORS[t.status]" variant="subtle" :label="t.status_label" />
          </NuxtLink>
        </li>
      </ul>
    </UCard>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { StockTransfer, TransferStatus } from '~/types/api'

definePageMeta({ permission: 'transfers.manage', module: 'multi_branch' })

const api = useApi()
const tabs = [
  { value: 'all', label: 'الكل' },
  { value: 'incoming', label: 'واردة لفروعي' },
  { value: 'outgoing', label: 'صادرة من فروعي' },
] as const
const box = ref<(typeof tabs)[number]['value']>('all')
const statuses = [
  { value: 'any', label: 'كل الحالات' },
  { value: 'requested', label: 'مطلوب' },
  { value: 'shipped', label: 'في الطريق' },
  { value: 'received', label: 'اتستلم' },
  { value: 'cancelled', label: 'اتلغى' },
]
const status = ref<'any' | TransferStatus>('any')
const page = ref(1)
watch([box, status], () => {
  page.value = 1
})

type Page = { data: StockTransfer[], meta: { total: number, last_page: number, to_receive: number, to_ship: number } }
const { data, pending } = await useAsyncData('transfers', () => api<Page>('/transfers', {
  query: { box: box.value, status: status.value === 'any' ? undefined : status.value, page: page.value },
}), { watch: [box, status, page] })
const transfers = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
</script>

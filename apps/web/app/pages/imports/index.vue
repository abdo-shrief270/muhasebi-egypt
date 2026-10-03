<template>
  <div class="space-y-6">
    <PageHeader title="الاستيراد" description="الشحنات من الطلب لحد ما تدخل المخزن بتكلفتها النهائية، وحساب كل مصنع ووسيط وشركة شحن ومخلّص.">
      <UButton to="/imports/contacts" color="neutral" variant="outline" icon="i-lucide-users" label="جهات الاستيراد" />
      <UButton v-if="canManage" to="/imports/shipments/new" icon="i-lucide-plus" label="شحنة جديدة" />
    </PageHeader>

    <div v-if="summary" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          عليك للجهات
        </p>
        <p class="num text-2xl font-extrabold">
          {{ formatMoney(summary.owed) }}
        </p>
        <p v-if="summary.credit" class="text-xs text-(--ui-success)">
          وليك عندهم {{ formatMoney(summary.credit) }}
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          شحنات في الطريق
        </p>
        <p class="num text-2xl font-extrabold">
          {{ summary.on_the_way }}
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          بضاعة بـ <span class="num">{{ formatMoney(summary.on_the_way_value) }}</span>
        </p>
      </UCard>
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          متأخرة عن ميعادها
        </p>
        <p class="num text-2xl font-extrabold" :class="summary.late ? 'text-(--ui-error)' : ''">
          {{ summary.late }}
        </p>
      </UCard>
      <UCard>
        <p class="mb-1 text-sm text-(--ui-text-muted)">
          أكتر جهات ليها فلوس
        </p>
        <ul class="space-y-0.5 text-sm">
          <li v-for="c in summary.top_owed" :key="c.id" class="flex justify-between gap-2">
            <NuxtLink :to="`/imports/contacts/${c.id}`" class="truncate hover:underline">{{ c.name }}</NuxtLink>
            <span class="num">{{ formatMoney(c.balance) }}</span>
          </li>
          <li v-if="!summary.top_owed.length" class="text-(--ui-text-muted)">
            مفيش
          </li>
        </ul>
      </UCard>
    </div>

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

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="!shipments.length" class="space-y-3 py-16 text-center">
        <UIcon name="i-lucide-ship" class="size-10 text-(--ui-text-muted)" />
        <p class="font-semibold">
          مفيش شحنات هنا
        </p>
        <UButton v-if="canManage && filter === 'open'" to="/imports/shipments/new" variant="soft" label="سجّل أول شحنة" />
      </div>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="s in shipments" :key="s.id">
          <NuxtLink :to="`/imports/shipments/${s.id}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-(--ui-bg-muted)">
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                <span class="num" dir="ltr">{{ s.reference }}</span> · {{ s.contact.name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                اتطلبت {{ formatDate(s.ordered_on) }}
                <template v-if="s.expected_on">
                  · متوقعة {{ formatDate(s.expected_on) }}
                </template>
                · {{ s.branch.name }}
              </p>
            </div>
            <UBadge v-if="s.late" color="error" variant="soft" label="متأخرة" />
            <span class="num font-bold">{{ formatMoney(s.total) }}</span>
            <UBadge :color="IMPORT_STATUS_COLORS[s.status]" variant="subtle" :label="s.status_label" />
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
import type { ImportContact, ImportShipment } from '~/types/api'

definePageMeta({ permission: 'imports.view', module: 'imports' })

const api = useApi()
const store = useSessionStore()
const canManage = computed(() => store.can('imports.manage'))

const tabs = [
  { value: 'open', label: 'في الطريق' },
  { value: 'late', label: 'متأخرة' },
  { value: 'received', label: 'اتستلمت' },
  { value: 'cancelled', label: 'اتلغت' },
] as const
const filter = ref<(typeof tabs)[number]['value']>('open')
const page = ref(1)
watch(filter, () => {
  page.value = 1
})

type Summary = { owed: number, credit: number, on_the_way: number, on_the_way_value: number, late: number, top_owed: ImportContact[] }
const { data: summaryData } = await useAsyncData('imports-summary', () => api<{ data: Summary }>('/imports/summary'))
const summary = computed(() => summaryData.value?.data ?? null)
const { data } = await useAsyncData('imports-shipments', () => api<{ data: ImportShipment[], meta: { total: number, last_page: number } }>('/imports/shipments', {
  query: { status: filter.value, page: page.value },
}), { watch: [filter, page] })
const shipments = computed(() => data.value?.data ?? [])
</script>

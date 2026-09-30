<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-extrabold">
      المحلات
    </h1>
    <div class="grid gap-3 md:grid-cols-[1fr_220px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم المحل، الكود، أو الموبايل…" class="w-full" />
      <USelect v-model="status" :items="statusItems" class="w-full" />
    </div>
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                المحل
              </th>
              <th class="p-3 text-start font-bold">
                صاحب المحل
              </th>
              <th class="p-3 text-start font-bold">
                الاشتراك
              </th>
              <th class="p-3 text-start font-bold">
                لحد
              </th>
              <th class="p-3 text-start font-bold">
                النشاط
              </th>
              <th class="p-3 text-start font-bold">
                ابدأ من هنا
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.shop?.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/shops/${row.shop?.id}`)">
              <td class="p-3">
                <p class="font-bold">
                  {{ row.shop?.name }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num" dir="ltr">{{ row.shop?.code }}</span> · {{ row.shop?.types }}
                </p>
              </td>
              <td class="p-3">
                <p>{{ row.shop?.owner_name }}</p>
                <p class="num text-xs text-(--ui-text-muted)" dir="ltr">
                  {{ localPhone(row.shop?.owner_phone) }}
                </p>
              </td>
              <td class="p-3">
                <UBadge :color="subscriptionStatusColor(row.subscription.status)" variant="subtle">
                  {{ row.subscription.status_label }}
                </UBadge>
                <UBadge v-if="row.subscription.beta" color="info" variant="outline" class="ms-1">
                  Beta
                </UBadge>
                <span v-if="row.subscription.plan_name" class="ms-2">{{ row.subscription.plan_name }}</span>
              </td>
              <td class="num p-3">
                {{ formatDate(row.subscription.paid_until) }}
              </td>
              <td class="p-3 whitespace-nowrap">
                <p :class="staleTone(row.shop?.last_seen_at)">
                  آخر دخول {{ timeAgo(row.shop?.last_seen_at) }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num">{{ row.activity?.sales_7d ?? 0 }}</span> فاتورة ·
                  <span class="num">{{ row.activity?.repairs_7d ?? 0 }}</span> صيانة <span class="text-(--ui-text-dimmed)">(7 أيام)</span>
                </p>
              </td>
              <td class="p-3">
                <div v-if="row.setup" class="w-28" :title="row.setup.missing.join('، ')">
                  <p class="num text-xs font-bold">
                    {{ row.setup.done }} / {{ row.setup.total }}
                  </p>
                  <div class="mt-1 h-1.5 rounded-full bg-(--ui-bg-elevated)">
                    <div class="h-1.5 rounded-full" :class="row.setup.done === row.setup.total ? 'bg-(--ui-success)' : 'bg-(--ui-primary)'" :style="{ width: `${Math.round(row.setup.done / row.setup.total * 100)}%` }" />
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!rows.length" class="py-8 text-center text-(--ui-text-muted)">
        مفيش محلات.
      </p>
    </UCard>
    <div v-if="meta && meta.last_page > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="meta.total" :items-per-page="30" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { AdminShop, SetupProgress, ShopActivity, SubscriptionInfo } from '~/types/api'


const api = useAdminApi()
const route = useRoute()
const ALL = 'all'
const statusItems = [
  { label: 'كل الحالات', value: ALL },
  { label: 'تجربة', value: 'trialing' },
  { label: 'مشترك', value: 'active' },
  { label: 'الاشتراك خلص', value: 'past_due' },
  { label: 'محدود', value: 'restricted' },
  { label: 'موقوف', value: 'suspended' },
  { label: 'في فترة Beta', value: 'beta' },
]
const q = ref('')
const debounced = ref('')
const status = ref(typeof route.query.status === 'string' ? route.query.status : ALL)
const page = ref(1)
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debounced.value = v.trim()
    page.value = 1
  }, 300)
})
watch(status, () => {
  page.value = 1
})
onBeforeUnmount(() => clearTimeout(timer))

const { data } = await useAsyncData('admin-shops', () => api<{ data: { shop: AdminShop | null, subscription: SubscriptionInfo, activity: ShopActivity | null, setup: SetupProgress | null }[], meta: { total: number, last_page: number } }>('/shops', {
  query: { q: debounced.value || undefined, status: status.value === ALL ? undefined : status.value, page: page.value },
}), { watch: [debounced, status, page] })
const rows = computed(() => data.value?.data ?? [])

const meta = computed(() => data.value?.meta)
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="العملاء" description="حسابات الآجل، واللي عليه فلوس لمحلك.">
      <UButton v-if="store.isOwner" color="neutral" variant="ghost" icon="i-lucide-shield" label="الخصوصية" @click="privacyOpen = true" />
      <UButton v-if="canManage" icon="i-lucide-plus" label="عميل جديد" @click="formOpen = true" />
    </PageHeader>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر بالاسم أو الموبايل…" class="w-full" />
      <UButton
        :color="owing ? 'primary' : 'neutral'"
        :variant="owing ? 'soft' : 'outline'"
        icon="i-lucide-hand-coins"
        :label="`عليهم فلوس (${data?.meta.owing_count ?? 0})`"
        :aria-pressed="owing"
        @click="owing = !owing"
      />
      <div class="app-card flex items-center gap-3 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) px-4 py-2">
        <span class="text-sm text-(--ui-text-muted)">إجمالي الآجل</span>
        <span class="text-lg font-extrabold num">{{ formatMoney(data?.meta.receivable ?? 0) }}</span>
      </div>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start font-bold">
                العميل
              </th>
              <th class="hidden p-3 text-start font-bold md:table-cell">
                آخر تعامل
              </th>
              <th class="hidden p-3 text-start font-bold sm:table-cell">
                حد الآجل
              </th>
              <th class="p-3 text-start font-bold">
                الرصيد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in customers"
              :key="c.id"
              class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)"
              :class="{ 'opacity-60': !c.is_active }"
              @click="navigateTo(`/customers/${c.id}`)"
            >
              <td class="p-3">
                <p class="font-bold">
                  {{ c.name }}
                </p>
                <p v-if="c.phone" class="text-xs text-(--ui-text-muted) num" dir="ltr">
                  {{ localPhone(c.phone) }}
                </p>
              </td>
              <td class="hidden p-3 text-(--ui-text-muted) md:table-cell">
                {{ c.last_activity_at ? formatDate(c.last_activity_at) : '—' }}
              </td>
              <td class="hidden p-3 num sm:table-cell">
                {{ c.credit_limit === null ? '—' : formatMoney(c.credit_limit) }}
              </td>
              <td class="p-3">
                <CustomersBalanceBadge :balance="c.balance" />
              </td>
            </tr>
            <tr v-if="!customers.length && status !== 'pending'">
              <td colspan="4" class="p-10 text-center text-(--ui-text-muted)">
                {{ q ? 'مفيش عميل بالاسم أو الرقم ده.' : owing ? 'مفيش حد عليه فلوس 🎉' : 'لسه مفيش عملاء. العملاء بيتضافوا من هنا أو من الكاشير.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="30" />
    </div>

    <CustomersPrivacySettingsModal v-if="store.isOwner" v-model:open="privacyOpen" />
    <CustomersCustomerFormModal v-model:open="formOpen" :initial-name="q" @saved="c => navigateTo(`/customers/${c.id}`)" />
  </div>
</template>

<script setup lang="ts">
import type { Customer } from '~/types/api'

definePageMeta({ permission: 'customers.view' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const canManage = computed(() => store.can('customers.manage'))

const q = ref('')
const owing = ref(route.query.owing === '1')
const page = ref(1)
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))
watch([debouncedQ, owing], () => {
  page.value = 1
})

const { data, status } = await useAsyncData('customers', () => api<{ data: Customer[], meta: { current_page: number, last_page: number, total: number, receivable: number, owing_count: number } }>('/customers', {
  query: { q: debouncedQ.value || undefined, owing: owing.value ? 1 : undefined, page: page.value },
}), { watch: [debouncedQ, owing, page] })
const customers = computed(() => data.value?.data ?? [])

const formOpen = ref(false)
const privacyOpen = ref(false)
// Quick action «عميل جديد»: /customers?new=1
onMounted(() => {
  if (route.query.new === '1' && canManage.value) {
    formOpen.value = true
  }
})
</script>

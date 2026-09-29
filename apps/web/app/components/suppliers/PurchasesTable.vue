<template>
  <UCard :ui="{ body: 'p-0 sm:p-0' }">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              الفاتورة
            </th>
            <th v-if="!supplierId" class="p-3 text-start font-bold">
              المورد
            </th>
            <th class="p-3 text-start font-bold">
              التاريخ
            </th>
            <th class="p-3 text-start font-bold">
              الإجمالي
            </th>
            <th class="hidden p-3 text-start font-bold sm:table-cell">
              المدفوع
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in purchases"
            :key="p.id"
            class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)"
            @click="navigateTo(`/purchases/${p.id}`)"
          >
            <td class="p-3">
              <p class="font-bold num">
                {{ p.reference }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                <span v-if="p.supplier_invoice_no">فاتورة المورد <span class="num">{{ p.supplier_invoice_no }}</span> · </span>
                <span class="num">{{ p.items_count }}</span> صنف
                <span v-if="p.returned" class="text-warning"> · فيها مرتجع</span>
              </p>
            </td>
            <td v-if="!supplierId" class="p-3">
              {{ p.supplier?.name }}
            </td>
            <td class="p-3 text-(--ui-text-muted)">
              {{ formatDate(p.invoice_date) }}
            </td>
            <td class="p-3 font-bold num">
              {{ formatMoney(p.total) }}
            </td>
            <td class="hidden p-3 num sm:table-cell">
              {{ p.paid ? formatMoney(p.paid) : '—' }}
            </td>
          </tr>
          <tr v-if="!purchases.length && status !== 'pending'">
            <td :colspan="supplierId ? 4 : 5" class="p-10 text-center text-(--ui-text-muted)">
              {{ q ? 'مفيش فاتورة بالرقم ده.' : 'لسه مفيش فواتير شراء.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </UCard>
  <div v-if="(data?.meta.last_page ?? 1) > 1" class="mt-4 flex justify-center">
    <UPagination v-model:page="page" :total="data?.meta.total ?? 0" :items-per-page="data?.meta.per_page ?? 30" />
  </div>
</template>

<script setup lang="ts">
import type { Paginated, Purchase } from '~/types/api'

const props = defineProps<{ supplierId?: string, q?: string }>()

const api = useApi()
const page = ref(1)
watch(() => props.q, () => {
  page.value = 1
})

const { data, status } = await useAsyncData(`purchases-${props.supplierId ?? 'all'}`, () => api<Paginated<Purchase>>('/purchases', {
  query: { supplier_id: props.supplierId, q: props.q || undefined, page: page.value },
}), { watch: [page, () => props.q] })
const purchases = computed(() => data.value?.data ?? [])
</script>

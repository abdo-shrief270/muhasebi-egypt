<template>
  <UModal v-model:open="open" title="سجل الأسعار" description="كل تغيير في أسعار الصنف ده: مين غيّره وإمتى." :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <p v-if="status === 'pending'" class="py-6 text-center text-sm text-(--ui-text-muted)">
        بنحمّل…
      </p>
      <p v-else-if="!changes.length" class="py-6 text-center text-sm text-(--ui-text-muted)">
        الأسعار ما اتغيرتش من ساعة ما الصنف اتضاف.
      </p>
      <table v-else class="w-full text-sm">
        <thead class="text-(--ui-text-muted)">
          <tr>
            <th class="p-2 text-start font-bold">
              إمتى
            </th>
            <th class="p-2 text-start font-bold">
              السعر
            </th>
            <th class="p-2 text-start font-bold">
              من ← إلى
            </th>
            <th class="p-2 text-start font-bold">
              مين
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in changes" :key="c.id" class="border-t border-(--ui-border)">
            <td class="num p-2 text-(--ui-text-muted)">
              {{ formatDate(c.created_at, true) }}
            </td>
            <td class="p-2">
              {{ c.field_label }}
              <span v-if="c.variant" class="text-(--ui-text-muted)">· {{ c.variant }}</span>
            </td>
            <td class="num p-2">
              {{ formatMoney(c.old_price) }} ← <b>{{ formatMoney(c.new_price) }}</b>
            </td>
            <td class="p-2">
              {{ c.user_name ?? '—' }}
              <UBadge v-if="c.source === 'bulk'" color="neutral" variant="subtle" size="sm" class="ms-1">
                جماعي
              </UBadge>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </UModal>
</template>

<script setup lang="ts">
interface PriceChange {
  id: string
  variant: string | null
  field: string
  field_label: string
  old_price: number | null
  new_price: number | null
  source: 'edit' | 'bulk'
  user_name: string | null
  created_at: string
}

const props = defineProps<{ productId: string }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const { data, status, refresh } = await useAsyncData(
  `price-history-${props.productId}`,
  () => api<{ data: PriceChange[] }>(`/products/${props.productId}/prices`),
  { immediate: false },
)
const changes = computed(() => data.value?.data ?? [])

watch(open, (isOpen) => {
  if (isOpen) {
    refresh()
  }
})
</script>

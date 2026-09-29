<template>
  <USlideover v-model:open="open" side="left" :title="data?.item.display_name ?? 'حركة الصنف'" :description="subtitle" :ui="{ content: 'max-w-lg' }">
    <template #body>
      <div v-if="pending" class="flex justify-center py-10 text-(--ui-text-muted)">
        <UIcon name="i-lucide-loader-circle" class="size-6 animate-spin" />
      </div>
      <p v-else-if="!data?.movements.length" class="py-10 text-center text-(--ui-text-muted)">
        مفيش حركات للصنف ده في الفرع ده لسه.
      </p>
      <ol v-else class="space-y-2">
        <li v-for="m in data.movements" :key="m.id" class="rounded-[calc(var(--ui-radius)*1.5)] border border-(--ui-border) p-3">
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="font-bold">
                {{ m.type_label }}
                <span v-if="m.reason_label" class="font-normal text-(--ui-text-muted)">· {{ m.reason_label }}</span>
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ formatDate(m.created_at, true) }}<span v-if="m.user_name"> · {{ m.user_name }}</span>
              </p>
            </div>
            <div class="text-end">
              <p class="text-lg font-extrabold num" :class="m.qty > 0 ? 'text-success' : 'text-error'">
                {{ m.qty > 0 ? '+' : '' }}{{ m.qty }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                الرصيد <span class="num">{{ m.balance_after }}</span>
              </p>
            </div>
          </div>
          <p v-if="m.note" class="mt-2 text-sm text-(--ui-text-muted)">
            {{ m.note }}
          </p>
          <p v-if="m.unit_cost !== null" class="mt-1 text-xs text-(--ui-text-muted)">
            التكلفة <span class="num">{{ formatMoney(m.unit_cost) }}</span> للقطعة
          </p>
        </li>
      </ol>
    </template>
  </USlideover>
</template>

<script setup lang="ts">
import type { StockMovementRow, StockRow } from '~/types/api'

const props = defineProps<{ variantId: string | null }>()
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const data = ref<{ item: StockRow, movements: StockMovementRow[] } | null>(null)
const pending = ref(false)

const subtitle = computed(() => data.value
  ? `الرصيد الحالي ${data.value.item.qty}${data.value.item.avg_cost !== null ? ` · متوسط التكلفة ${formatMoney(data.value.item.avg_cost)}` : ''}`
  : undefined)

watch(() => [open.value, props.variantId] as const, async ([isOpen, id]) => {
  if (!isOpen || !id) {
    return
  }
  pending.value = true
  data.value = null
  try {
    data.value = (await api<{ data: { item: StockRow, movements: StockMovementRow[] } }>(`/inventory/variants/${id}/movements`)).data
  }
  finally {
    pending.value = false
  }
}, { immediate: true })
</script>

<template>
  <UPopover v-if="history.length">
    <button type="button" class="mt-1 flex w-full items-center gap-1.5 rounded-md text-start text-xs text-(--ui-text-muted) hover:text-(--ui-text)" :aria-label="`آخر سعر ${customerName} اشترى بيه ${itemName}`">
      <UIcon name="i-lucide-history" class="size-3.5 shrink-0" />
      <span class="min-w-0 truncate">آخر مرة اشتراه بـ <b class="num text-(--ui-text)">{{ formatMoney(last.price) }}</b> · {{ formatDate(last.at) }}</span>
      <UBadge :color="tone.color" variant="subtle" size="sm" :label="tone.label" class="ms-auto shrink-0" />
    </button>
    <template #content>
      <div class="w-72 space-y-3 p-3">
        <p class="text-sm font-bold">
          {{ customerName }} اشترى «{{ itemName }}» قبل كده
        </p>
        <ul class="divide-y divide-(--ui-border) text-sm">
          <li v-for="h in history" :key="h.sale_id" class="flex justify-between gap-2 py-1.5">
            <span class="text-(--ui-text-muted)">{{ formatDate(h.at) }} · <span class="num" dir="ltr">{{ h.reference }}</span><span v-if="h.qty > 1" class="num"> · {{ h.qty }} قطع</span></span>
            <span class="num font-bold">{{ formatMoney(h.price) }}</span>
          </li>
        </ul>
        <p v-if="diff !== 0" class="text-xs" :class="diff > 0 ? 'text-(--ui-warning)' : 'text-(--ui-success)'">
          السعر النهارده {{ formatMoney(currentPrice) }}، يعني {{ diff > 0 ? 'أغلى' : 'أرخص' }} بـ {{ formatMoney(Math.abs(diff)) }} من آخر مرة.
        </p>
        <UButton
          v-if="canMatch && diff > 0"
          block
          icon="i-lucide-badge-percent"
          :label="`بيعه بـ ${formatMoney(last.price)} زي آخر مرة`"
          @click="emit('match', last.price)"
        />
      </div>
    </template>
  </UPopover>
</template>

<script setup lang="ts">
export interface LastPriceEntry { price: number, list_price: number, qty: number, sale_id: string, reference: string, at: string }

const props = defineProps<{
  history: LastPriceEntry[]
  currentPrice: number
  itemName: string
  customerName: string
  /** may give a line discount (sales.discount + the line discounts switch) */
  canMatch: boolean
}>()
const emit = defineEmits<{ match: [price: number] }>()

const last = computed(() => props.history[0]!)
// > 0 = dearer today than last time
const diff = computed(() => props.currentPrice - last.value.price)
const tone = computed(() => diff.value > 0
  ? { color: 'warning' as const, label: `أغلى بـ ${formatMoney(diff.value)}` }
  : diff.value < 0
    ? { color: 'success' as const, label: `أرخص بـ ${formatMoney(-diff.value)}` }
    : { color: 'neutral' as const, label: 'نفس السعر' })
</script>

<template>
  <UModal v-model:open="open" title="استعلام عن سعر" description="امسح الباركود أو اكتب اسم الصنف أو الموديل." :ui="{ content: 'sm:max-w-xl' }">
    <template #body>
      <div class="space-y-4">
        <UInput
          ref="inputRef"
          v-model="term"
          size="xl"
          icon="i-lucide-scan-barcode"
          placeholder="باركود، اسم، أو موديل…"
          class="w-full"
          autofocus
          :loading="loading"
          @keydown.enter.prevent="search(true)"
        />

        <p v-if="!items.length && term.trim().length >= 2 && !loading" class="py-6 text-center text-(--ui-text-muted)">
          مفيش صنف كده.
        </p>

        <ul class="space-y-3">
          <li v-for="item in items" :key="item.id" class="rounded-[calc(var(--ui-radius)*1.5)] border p-3" :class="item.exact_barcode ? 'border-primary' : 'border-(--ui-border)'">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-bold">
                  {{ item.display_name }}
                  <UBadge v-if="!item.is_active" color="neutral" variant="subtle" size="sm" class="ms-1">
                    موقوف
                  </UBadge>
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  {{ item.category }}<span v-if="item.quality_label"> · {{ item.quality_label }}</span><span v-if="item.barcode"> · <bdi class="num">{{ item.barcode }}</bdi></span>
                </p>
              </div>
              <p class="shrink-0 text-2xl font-extrabold num">
                {{ formatMoney(item.prices.retail) }}
              </p>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
              <UBadge v-if="item.prices.wholesale !== null" color="neutral" variant="subtle">
                جملة <span class="num">{{ formatMoney(item.prices.wholesale) }}</span>
              </UBadge>
              <UBadge v-if="item.prices.technician !== null" color="neutral" variant="subtle">
                فني <span class="num">{{ formatMoney(item.prices.technician) }}</span>
              </UBadge>
              <span v-if="item.cost !== null" class="text-xs text-(--ui-text-muted)">
                التكلفة <span class="num">{{ formatMoney(item.cost) }}</span>
              </span>
            </div>

            <div v-if="item.stock" class="mt-2 flex flex-wrap gap-2">
              <UBadge
                v-for="s in item.stock"
                :key="s.branch_id"
                size="sm"
                :variant="s.current ? 'soft' : 'outline'"
                :color="s.qty <= 0 ? 'error' : s.current ? 'primary' : 'neutral'"
              >
                {{ s.name }}: <span class="num">{{ s.qty <= 0 ? 'خلصان' : s.qty }}</span>
              </UBadge>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <UButton v-if="store.can('sales.sell') && item.is_active" size="xs" icon="i-lucide-shopping-cart" label="أضف للفاتورة" @click="go(`/pos?add=${item.id}`)" />
              <UButton v-if="store.can('products.view')" size="xs" color="neutral" variant="outline" icon="i-lucide-tag" label="اطبع ليبل" @click="go(`/products/labels?ids[]=${item.id}`)" />
              <UButton v-if="store.can('products.manage')" size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" label="عدّل الصنف" @click="go(`/products/${item.product_id}`)" />
            </div>
          </li>
        </ul>
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { PriceCheckItem } from '~/types/api'

const api = useApi()
const store = useSessionStore()
const { open, term } = usePriceCheck()

const items = ref<PriceCheckItem[]>([])
const loading = ref(false)
const inputRef = ref<{ inputRef?: HTMLInputElement } | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

watch(term, () => {
  clearTimeout(timer)
  timer = setTimeout(() => search(false), 250)
})

watch(open, (isOpen) => {
  if (isOpen) {
    if (term.value) {
      search(false)
    }
    nextTick(() => inputRef.value?.inputRef?.select())
  }
  else {
    term.value = ''
    items.value = []
  }
})
onBeforeUnmount(() => clearTimeout(timer))

/** A scanner types the code and presses Enter: select the text so the next scan replaces it. */
async function search(fromEnter: boolean) {
  clearTimeout(timer)
  const q = term.value.trim()
  if (q.length < 2) {
    items.value = []
    return
  }
  const id = ++requestId
  loading.value = true
  try {
    const res = await api<{ data: PriceCheckItem[] }>('/inventory/price-check', { query: { q } })
    if (id === requestId) {
      items.value = res.data
    }
  }
  finally {
    if (id === requestId) {
      loading.value = false
    }
    if (fromEnter) {
      inputRef.value?.inputRef?.select()
    }
  }
}

function go(to: string) {
  open.value = false
  navigateTo(to)
}
</script>

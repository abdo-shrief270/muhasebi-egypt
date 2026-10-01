<template>
  <div>
    <div v-if="modelValue" class="flex items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2">
      <UIcon name="i-lucide-user" class="size-4 shrink-0 text-(--ui-text-muted)" />
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-bold">
          {{ modelValue.name }}
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          <span v-if="modelValue.phone" class="num" dir="ltr">{{ localPhone(modelValue.phone) }}</span>
          <template v-if="modelValue.balance !== 0">
            · <CustomersBalanceBadge :balance="modelValue.balance" class="text-xs" />
          </template>
        </p>
      </div>
      <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-x" square aria-label="شيل العميل" @click="modelValue = null" />
    </div>

    <div v-else class="relative">
      <UInput
        v-model="term"
        size="sm"
        icon="i-lucide-user-search"
        :placeholder="store.hasFeature('sales.require_customer') ? 'العميل (مطلوب) — دوّر بالاسم أو الموبايل' : 'العميل (اختياري) — دوّر بالاسم أو الموبايل'"
        class="w-full"
        @keydown.esc="term = ''"
      />
      <div v-if="term.trim().length >= 2" class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
        <button
          v-for="c in results"
          :key="c.id"
          type="button"
          class="flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm hover:bg-(--ui-bg-elevated)"
          @click="pick(c)"
        >
          <span class="min-w-0">
            <span class="block truncate font-bold">{{ c.name }}</span>
            <span v-if="c.phone" class="block text-xs text-(--ui-text-muted) num" dir="ltr">{{ localPhone(c.phone) }}</span>
          </span>
          <CustomersBalanceBadge v-if="c.balance" :balance="c.balance" class="shrink-0 text-xs" />
        </button>
        <p v-if="!results.length && !loading" class="px-3 py-2 text-sm text-(--ui-text-muted)">
          مفيش عميل كده.
        </p>
        <button v-if="canAdd" type="button" class="flex w-full items-center gap-2 border-t border-(--ui-border) px-3 py-2 text-sm font-bold text-primary hover:bg-(--ui-bg-elevated)" @click="formOpen = true">
          <UIcon name="i-lucide-user-plus" class="size-4" />
          عميل جديد «{{ term.trim() }}»
        </button>
      </div>
    </div>

    <CustomersCustomerFormModal v-model:open="formOpen" :initial-name="term" @saved="pick" />
  </div>
</template>

<script setup lang="ts">
import type { Customer, PosCustomer } from '~/types/api'

/** Picks (or adds) the customer a sale is for — needed to sell on credit (آجل). */
const modelValue = defineModel<PosCustomer | null>({ default: null })

const api = useApi()
const store = useSessionStore()
const canAdd = computed(() => store.can('customers.manage'))

const term = ref('')
const results = ref<Customer[]>([])
const loading = ref(false)
const formOpen = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (q.length < 2) {
    results.value = []
    return
  }
  loading.value = true
  timer = setTimeout(async () => {
    try {
      results.value = (await api<{ data: Customer[] }>('/customers', { query: { q, active: 1, per_page: 6 } })).data
    }
    finally {
      loading.value = false
    }
  }, 250)
})
onBeforeUnmount(() => clearTimeout(timer))

function pick(c: Customer) {
  modelValue.value = { id: c.id, name: c.name, phone: c.phone, balance: c.balance, credit_limit: c.credit_limit }
  term.value = ''
  results.value = []
}
</script>

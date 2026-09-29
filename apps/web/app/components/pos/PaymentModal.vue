<template>
  <UModal v-model:open="open" title="الدفع" :ui="{ content: 'sm:max-w-lg' }">
    <template #body>
      <div class="space-y-4">
        <div class="rounded-[calc(var(--ui-radius)*1.5)] bg-(--ui-bg-muted) p-4 text-center">
          <p class="text-sm text-(--ui-text-muted)">
            المطلوب
          </p>
          <p class="text-4xl font-extrabold num">
            {{ formatMoney(total) }}
          </p>
        </div>

        <div class="grid grid-cols-4 gap-2">
          <button
            v-for="m in methods"
            :key="m.value"
            type="button"
            class="flex flex-col items-center gap-1 rounded-[calc(var(--ui-radius)*1.5)] border py-2 text-sm font-bold transition"
            :class="active === m.value ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
            @click="choose(m.value)"
          >
            <UIcon :name="icons[m.value] ?? 'i-lucide-wallet'" class="size-5" />
            {{ m.label }}
          </button>
        </div>

        <div v-for="(p, i) in payments" :key="p.method" class="grid grid-cols-[100px_1fr_auto] items-center gap-2">
          <span class="font-bold">{{ labelOf(p.method) }}</span>
          <UInput
            :ref="el => setInputRef(p.method, el)"
            v-model="p.amount"
            type="number"
            min="0"
            step="any"
            inputmode="decimal"
            dir="ltr"
            size="lg"
            :aria-label="`مبلغ ${labelOf(p.method)}`"
            @keydown.enter.prevent="confirm"
          />
          <UButton v-if="payments.length > 1" color="neutral" variant="ghost" icon="i-lucide-x" square :aria-label="`شيل ${labelOf(p.method)}`" @click="payments.splice(i, 1)" />
        </div>

        <p class="text-xs text-(--ui-text-muted)">
          للدفع بأكتر من طريقة: عدّل المبلغ الأول واختار الطريقة التانية، هتاخد الباقي.
        </p>

        <div v-if="cashLine" class="flex flex-wrap gap-2">
          <UButton v-for="amount in quickCash" :key="amount" size="sm" color="neutral" variant="outline" class="num" :label="formatMoney(amount)" @click="cashLine.amount = String(amount / 100)" />
        </div>

        <div class="flex items-center justify-between rounded-[calc(var(--ui-radius)*1.5)] border border-(--ui-border) p-3">
          <span class="font-bold">{{ remaining > 0 ? 'لسه ناقص' : 'الباقي للعميل' }}</span>
          <span class="text-2xl font-extrabold num" :class="remaining > 0 ? 'text-error' : 'text-success'">
            {{ formatMoney(remaining > 0 ? remaining : change) }}
          </span>
        </div>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </div>
    </template>
    <template #footer>
      <div class="flex w-full gap-2">
        <UButton color="neutral" variant="ghost" label="رجوع" @click="open = false" />
        <UButton class="flex-1 justify-center" size="xl" icon="i-lucide-check" :label="`تأكيد الدفع (Enter)`" :loading="loading" :disabled="remaining > 0" @click="confirm" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
/** Splits the total over payment methods; change only comes out of cash. Amounts typed in pounds. */
const props = defineProps<{ total: number, methods: { value: string, label: string }[], loading?: boolean, error?: string | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ pay: [payments: { method: string, amount: number }[]] }>()

const icons: Record<string, string> = { cash: 'i-lucide-banknote', card: 'i-lucide-credit-card', wallet: 'i-lucide-smartphone', instapay: 'i-lucide-arrow-left-right' }

const payments = ref<{ method: string, amount: string }[]>([])
const active = ref('cash')
const inputs = new Map<string, { inputRef?: HTMLInputElement } | null>()

function setInputRef(method: string, el: unknown) {
  inputs.set(method, el as { inputRef?: HTMLInputElement } | null)
}

watch(open, (isOpen) => {
  if (isOpen) {
    payments.value = [{ method: 'cash', amount: String(props.total / 100) }]
    active.value = 'cash'
    focus('cash')
  }
})

const labelOf = (method: string) => props.methods.find(m => m.value === method)?.label ?? method
const cashLine = computed(() => payments.value.find(p => p.method === 'cash'))
const paid = computed(() => payments.value.reduce((sum, p) => sum + (toPiasters(p.amount) ?? 0), 0))
const remaining = computed(() => props.total - paid.value)
const change = computed(() => Math.max(0, -remaining.value))

// Round-up notes a customer is likely to hand over.
const quickCash = computed(() => {
  const t = props.total
  const options = new Set([t, ...[5000, 10000, 20000, 50000, 100000].map(n => Math.ceil(t / n) * n)])
  return [...options].filter(v => v >= t).sort((a, b) => a - b).slice(0, 5)
})

/**
 * One line holding the whole amount: another method replaces it (paid by card instead).
 * After editing the amount down, another method adds a line for what's left (split payment).
 */
function choose(method: string) {
  active.value = method
  if (!payments.value.some(p => p.method === method)) {
    const only = payments.value.length === 1 ? payments.value[0] : undefined
    payments.value = only && toPiasters(only.amount) === props.total
      ? [{ method, amount: String(props.total / 100) }]
      : [...payments.value, { method, amount: String(Math.max(0, remaining.value) / 100) }]
  }
  focus(method)
}

function focus(method: string) {
  nextTick(() => {
    const input = inputs.get(method)?.inputRef
    input?.focus()
    input?.select()
  })
}

function confirm() {
  if (remaining.value > 0 || props.loading) {
    return
  }
  emit('pay', payments.value
    .map(p => ({ method: p.method, amount: toPiasters(p.amount) ?? 0 }))
    .filter(p => p.amount > 0))
}
</script>

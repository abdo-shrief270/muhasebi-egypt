<template>
  <UModal v-model:open="open" :title="mode === 'opening' ? 'رصيد افتتاحي' : 'إضافة أو خصم'" :description="row?.display_name">
    <template #body>
      <form id="adjust-form" class="space-y-4" @submit.prevent="save">
        <template v-if="mode === 'opening'">
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="الكمية" required>
              <UInput v-model="form.qty" type="number" min="1" step="1" inputmode="numeric" dir="ltr" class="w-full" autofocus />
            </UFormField>
            <UFormField v-if="canCost" label="سعر التكلفة للقطعة" hint="بالجنيه">
              <UInput v-model="form.cost" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
          </div>
          <p class="text-sm text-(--ui-text-muted)">
            الرصيد الافتتاحي بيتسجّل مرة واحدة لكل صنف في الفرع. بعد كده أي فرق يتسجّل بالجرد.
          </p>
        </template>

        <template v-else>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="d in directions"
              :key="d.value"
              type="button"
              class="flex h-10 items-center justify-center gap-2 rounded-[calc(var(--ui-radius)*1.5)] border font-semibold transition"
              :class="form.direction === d.value ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              @click="form.direction = d.value"
            >
              <UIcon :name="d.icon" class="size-4" /> {{ d.label }}
            </button>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="الكمية" required>
              <UInput v-model="form.qty" type="number" min="1" step="1" inputmode="numeric" dir="ltr" class="w-full" autofocus />
            </UFormField>
            <UFormField label="السبب" required>
              <USelect v-model="form.reason" :items="reasons" class="w-full" />
            </UFormField>
          </div>
          <UFormField v-if="canCost && form.direction === 'in'" label="سعر التكلفة للقطعة" hint="اختياري — لو فاضي هيتحسب بمتوسط التكلفة">
            <UInput v-model="form.cost" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظة">
            <UInput v-model="form.note" placeholder="مثلاً: 2 شاشة وقعت واتكسرت" class="w-full" />
          </UFormField>
          <p v-if="row" class="text-sm text-(--ui-text-muted)">
            الرصيد هيبقى <span class="font-bold num">{{ after }}</span> بدل <span class="num">{{ row.qty }}</span>.
          </p>
        </template>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="adjust-form" label="حفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { StockRow } from '~/types/api'

const props = defineProps<{ row: StockRow | null, mode: 'adjust' | 'opening', reasons: { label: string, value: string }[] }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const canCost = computed(() => store.can('products.view_cost'))

const directions = [
  { value: 'in', label: 'إضافة', icon: 'i-lucide-plus' },
  { value: 'out', label: 'خصم', icon: 'i-lucide-minus' },
] as const

const form = reactive({ direction: 'out' as 'in' | 'out', qty: '', reason: 'damaged', cost: '', note: '' })
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, { direction: 'out', qty: '', reason: 'damaged', cost: '', note: '' })
    error.value = null
  }
})

const after = computed(() => (props.row?.qty ?? 0) + (form.direction === 'in' ? 1 : -1) * (Number(form.qty) || 0))

async function save() {
  if (!props.row) {
    return
  }
  saving.value = true
  error.value = null
  const qty = Number(form.qty)
  try {
    if (props.mode === 'opening') {
      await api('/inventory/opening', {
        method: 'POST',
        body: { items: [{ variant_id: props.row.id, qty, unit_cost: toPiasters(form.cost) ?? 0 }] },
      })
    }
    else {
      await api('/inventory/adjustments', {
        method: 'POST',
        body: {
          reason: form.reason,
          note: form.note || null,
          items: [{ variant_id: props.row.id, delta: form.direction === 'in' ? qty : -qty, unit_cost: form.direction === 'in' ? toPiasters(form.cost) : null }],
        },
      })
    }
    toast.add({ color: 'success', title: 'اتسجّل' })
    open.value = false
    emit('saved')
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

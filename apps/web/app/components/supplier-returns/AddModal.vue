<template>
  <UModal v-model:open="open" title="طلّع للمرتجعات" :description="row ? `${row.display_name} — هيخرج من المخزون ويستنى يترجع لمصدره.` : undefined">
    <template #body>
      <form id="bin-add-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="الكمية" required>
            <UInput v-model="form.qty" type="number" min="1" step="1" inputmode="numeric" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <UFormField label="السبب" required>
            <USelect v-model="form.reason" :items="RETURN_REASONS" class="w-full" />
          </UFormField>
        </div>
        <UFormField v-if="row?.track_serial" label="IMEI / سيريال القطع" required>
          <InventorySerialsInput v-model="form.serials" :required="Number(form.qty) || 0" />
        </UFormField>
        <UFormField label="ملاحظة" :required="form.reason === 'other'">
          <UInput v-model="form.note" placeholder="مثلاً: الشاشة فيها خط" class="w-full" />
        </UFormField>
        <UFormField label="المصدر" hint="تلقائي = من السيريال أو من الدفعة اللي القطعة منها">
          <USelectMenu v-model="form.source" :items="sourceItems" value-key="value" :search-input="{ placeholder: 'دوّر على مورد…' }" class="w-full" />
        </UFormField>
        <p v-if="row" class="text-sm text-(--ui-text-muted)">
          الرصيد هيبقى <span class="font-bold num">{{ row.qty - (Number(form.qty) || 0) }}</span> بدل <span class="num">{{ row.qty }}</span>.
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="bin-add-form" icon="i-lucide-undo-2" label="حطها في السلة" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { StockRow } from '~/types/api'
import type { BinItem, SourceCandidates } from '~/utils/supplierReturns'

const props = defineProps<{ row: StockRow | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [items: BinItem[]] }>()

const api = useApi()
const toast = useToast()

const AUTO = 'auto'
const form = reactive({ qty: '1', reason: 'defect', note: '', serials: [] as string[], source: AUTO })
const saving = ref(false)
const error = ref<string | null>(null)
const candidates = ref<SourceCandidates | null>(null)

watch(open, async (isOpen) => {
  if (!isOpen || !props.row) {
    return
  }
  Object.assign(form, { qty: '1', reason: 'defect', note: '', serials: [], source: AUTO })
  error.value = null
  candidates.value = null
  try {
    candidates.value = (await api<{ data: SourceCandidates }>('/supplier-returns/sources', { query: { variant_id: props.row.id } })).data
  }
  catch {
    // Automatic detection still works.
  }
})

const sourceItems = computed(() => sourceOptions(candidates.value, { label: 'تلقائي (من السيريال / الدفعة)', value: AUTO }))

async function save() {
  if (!props.row) {
    return
  }
  const qty = Number(form.qty)
  if (props.row.track_serial && form.serials.length !== qty) {
    error.value = `اكتب IMEI / سيريال لكل قطعة (${qty}).`
    return
  }
  saving.value = true
  error.value = null
  try {
    const [type, id] = form.source === AUTO ? [] : form.source.split(':')
    const res = await api<{ data: BinItem[] }>('/supplier-returns/bin', {
      method: 'POST',
      body: {
        variant_id: props.row.id,
        qty,
        reason: form.reason,
        note: form.note || null,
        serials: props.row.track_serial ? form.serials : undefined,
        source: type && id ? { type, id } : undefined,
      },
    })
    const unknown = res.data.filter(i => !i.source).length
    toast.add({
      color: unknown ? 'warning' : 'success',
      title: 'اتحطت في سلة المرتجعات',
      description: unknown ? 'مصدرها مش معروف — اختاره من شاشة الفرز.' : `المصدر: ${[...new Set(res.data.map(i => i.source?.name))].join('، ')}`,
    })
    open.value = false
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

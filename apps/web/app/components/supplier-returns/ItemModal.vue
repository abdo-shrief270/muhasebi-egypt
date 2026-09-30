<template>
  <UModal v-model:open="open" :title="item?.source ? 'تعديل القطعة' : 'اختار المصدر'" :description="item ? `${item.qty} × ${item.name}${item.serial ? ` · ${item.serial}` : ''}` : undefined">
    <template #body>
      <form id="bin-item-form" class="space-y-4" @submit.prevent="save">
        <div v-if="candidates?.suggested.length">
          <p class="mb-2 text-sm font-bold">
            الصنف ده اتشرى قريب من:
          </p>
          <div class="grid gap-2 sm:grid-cols-2">
            <button
              v-for="s in candidates.suggested"
              :key="`${s.type}:${s.id}`"
              type="button"
              class="rounded-[calc(var(--ui-radius)*1.5)] border p-2.5 text-start transition"
              :class="form.source === `${s.type}:${s.id}` ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              @click="form.source = `${s.type}:${s.id}`"
            >
              <p class="font-bold">
                {{ s.name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                <span class="num">{{ s.doc }}</span><template v-if="s.date">
                  · <span class="num">{{ formatDate(s.date) }}</span>
                </template><template v-if="s.unit_cost">
                  · <span class="num">{{ formatMoney(s.unit_cost) }}</span>
                </template>
              </p>
            </button>
          </div>
        </div>
        <UFormField :label="candidates?.suggested.length ? 'أو مصدر تاني' : 'المصدر'" :hint="item?.source ? `دلوقتي: ${item.source.name} (${detectedLabel(item.detected_by)})` : undefined">
          <USelectMenu v-model="form.source" :items="sourceItems" value-key="value" :search-input="{ placeholder: 'دوّر…' }" :loading="!candidates" class="w-full" />
        </UFormField>
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="السبب">
            <USelect v-model="form.reason" :items="RETURN_REASONS" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظة" :required="form.reason === 'other'">
            <UInput v-model="form.note" class="w-full" />
          </UFormField>
        </div>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="bin-item-form" label="حفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { BinItem, SourceCandidates } from '~/utils/supplierReturns'

const props = defineProps<{ item: BinItem | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const KEEP = 'keep'
const form = reactive({ source: KEEP, reason: 'defect', note: '' })
const candidates = ref<SourceCandidates | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, async (isOpen) => {
  if (!isOpen || !props.item) {
    return
  }
  Object.assign(form, { source: KEEP, reason: props.item.reason, note: props.item.note ?? '' })
  error.value = null
  candidates.value = null
  try {
    candidates.value = (await api<{ data: SourceCandidates }>('/supplier-returns/sources', { query: { variant_id: props.item.variant_id } })).data
  }
  catch {
    candidates.value = { suggested: [], suppliers: [], shops: [] }
  }
})

const sourceItems = computed(() => sourceOptions(candidates.value, { label: props.item?.source ? `زي ما هو (${props.item.source.name})` : '— اختار —', value: KEEP }))

async function save() {
  if (!props.item) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const [type, id] = form.source === KEEP ? [] : form.source.split(':')
    await api(`/supplier-returns/bin/${props.item.id}`, {
      method: 'PATCH',
      body: { reason: form.reason, note: form.note, source: type && id ? { type, id } : undefined },
    })
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

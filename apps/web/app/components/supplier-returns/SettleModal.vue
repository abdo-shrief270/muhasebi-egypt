<template>
  <UModal v-model:open="open" title="رد المورد" :description="note ? `${note.reference} — ${note.source.name}` : undefined" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <form v-if="note" id="settle-form" class="space-y-5" @submit.prevent="save">
        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <p class="font-bold">
              قبل كام قطعة؟
            </p>
            <div class="flex gap-1">
              <UButton size="xs" color="neutral" variant="outline" label="قبل الكل" @click="setAll(true)" />
              <UButton size="xs" color="neutral" variant="outline" label="رفض الكل" @click="setAll(false)" />
            </div>
          </div>
          <ul class="divide-y divide-(--ui-border) rounded-[calc(var(--ui-radius)*1.5)] border border-(--ui-border)">
            <li v-for="item in items" :key="item.id" class="flex flex-wrap items-center gap-3 p-2.5">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">
                  <span class="num">{{ item.qty }}</span> × {{ item.name }}
                </p>
                <p v-if="item.serial" class="num text-xs text-(--ui-text-muted)" dir="ltr">
                  {{ item.serial }}
                </p>
              </div>
              <USwitch
                v-if="item.serial"
                :model-value="accepted[item.id] === 1"
                :label="accepted[item.id] === 1 ? 'اتقبلت' : 'اترفضت'"
                @update:model-value="accepted[item.id] = $event ? 1 : 0"
              />
              <UInputNumber v-else v-model="accepted[item.id]" :min="0" :max="item.qty" class="w-28" :aria-label="`المقبول من ${item.name}`" />
              <UInput
                v-if="item.serial && resolution === 'replacement' && accepted[item.id] === 1"
                v-model="replacement[item.id]"
                dir="ltr"
                class="num w-full"
                placeholder="IMEI البديل"
                :aria-label="`IMEI البديل لـ ${item.name}`"
              />
            </li>
          </ul>
        </div>

        <div v-if="acceptedUnits > 0" class="space-y-3">
          <p class="font-bold">
            المقبول (<span class="num">{{ acceptedUnits }}</span> قطعة<template v-if="acceptedValue !== null">
              بـ <span class="num">{{ formatMoney(acceptedValue) }}</span>
            </template>) اتعوّض إزاي؟
          </p>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="r in RESOLUTIONS"
              :key="r.value"
              type="button"
              class="flex flex-col items-center justify-center gap-1 rounded-[calc(var(--ui-radius)*1.5)] border p-2 text-sm font-semibold transition"
              :class="resolution === r.value ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              @click="resolution = r.value"
            >
              <UIcon :name="r.icon" class="size-5" /> {{ r.label }}
            </button>
          </div>
          <p class="text-xs text-(--ui-text-muted)">
            {{ resolutionHint }}
          </p>
          <UFormField v-if="resolution === 'refund'" label="الفلوس رجعت إزاي">
            <USelect v-model="refundMethod" :items="REFUND_METHODS" class="w-full sm:w-60" />
          </UFormField>
        </div>

        <div v-if="rejectedUnits > 0" class="space-y-3">
          <p class="font-bold">
            المرفوض (<span class="num">{{ rejectedUnits }}</span> قطعة) يعمل إيه؟
          </p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="a in rejectedActions"
              :key="a.value"
              type="button"
              class="flex items-center justify-center gap-2 rounded-[calc(var(--ui-radius)*1.5)] border p-2 text-sm font-semibold transition"
              :class="rejectedAction === a.value ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              @click="rejectedAction = a.value"
            >
              <UIcon :name="a.icon" class="size-4" /> {{ a.label }}
            </button>
          </div>
        </div>

        <UFormField label="ملاحظة">
          <UInput v-model="settleNote" placeholder="مثلاً: قبل الشاشات ورفض واحدة مكسورة من عندنا" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="settle-form" icon="i-lucide-check" label="تسجيل الرد" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { ReturnNote } from '~/utils/supplierReturns'

const props = defineProps<{ note: ReturnNote | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [note: ReturnNote] }>()

const api = useApi()
const toast = useToast()

const accepted = reactive<Record<string, number>>({})
const replacement = reactive<Record<string, string>>({})
const resolution = ref<'credit' | 'refund' | 'replacement'>('credit')
const refundMethod = ref('cash')
const rejectedAction = ref<'restock' | 'write_off'>('restock')
const settleNote = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

const rejectedActions = [
  { value: 'restock' as const, label: 'يرجع المخزون يتباع', icon: 'i-lucide-package-plus' },
  { value: 'write_off' as const, label: 'يتعدم (تالف)', icon: 'i-lucide-trash-2' },
]

const items = computed(() => props.note?.items ?? [])

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }
  setAll(true)
  items.value.forEach((i) => {
    replacement[i.id] = i.serial ?? ''
  })
  resolution.value = props.note?.source.type === 'shop' ? 'replacement' : 'credit'
  refundMethod.value = 'cash'
  rejectedAction.value = 'restock'
  settleNote.value = ''
  error.value = null
})

function setAll(on: boolean) {
  items.value.forEach((i) => {
    accepted[i.id] = on ? i.qty : 0
  })
}

const acceptedUnits = computed(() => items.value.reduce((n, i) => n + (accepted[i.id] ?? 0), 0))
const rejectedUnits = computed(() => items.value.reduce((n, i) => n + i.qty, 0) - acceptedUnits.value)
const acceptedValue = computed(() => items.value.some(i => i.unit_cost === null) ? null : items.value.reduce((n, i) => n + (accepted[i.id] ?? 0) * (i.unit_cost ?? 0), 0))

const resolutionHint = computed(() => {
  const shop = props.note?.source.type === 'shop'
  switch (resolution.value) {
    case 'credit': return shop ? 'هيتسجّل عندك إن المحل الشريك عليه القيمة دي (حسابه عنده مش بيتغيّر من هنا).' : 'القيمة هتتخصم من اللي عليك للمورد.'
    case 'refund': return 'الفلوس هتدخل درجك (الوردية المفتوحة)، إلا التحويل البنكي.'
    default: return 'البديل هيدخل المخزون بنفس التكلفة.'
  }
})

async function save() {
  if (!props.note) {
    return
  }
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: ReturnNote }>(`/supplier-returns/notes/${props.note.id}/settle`, {
      method: 'POST',
      body: {
        items: items.value.map(i => ({
          id: i.id,
          accepted_qty: accepted[i.id] ?? 0,
          replacement_serials: i.serial && resolution.value === 'replacement' && accepted[i.id] ? [replacement[i.id] ?? ''] : undefined,
        })),
        resolution: acceptedUnits.value ? resolution.value : null,
        refund_method: acceptedUnits.value && resolution.value === 'refund' ? refundMethod.value : null,
        rejected_action: rejectedUnits.value ? rejectedAction.value : null,
        note: settleNote.value || null,
      },
    })
    toast.add({ color: 'success', title: `اتسجّل: ${res.data.status_label}` })
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

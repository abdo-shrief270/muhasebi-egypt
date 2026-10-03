<template>
  <div class="space-y-6">
    <PageHeader title="تحويل جديد" description="ابعت بضاعة لفرع تاني دلوقتي، أو اطلب من فرع تاني يبعتلك.">
      <UButton to="/transfers" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="التحويلات" />
    </PageHeader>

    <UCard>
      <div class="space-y-4">
        <URadioGroup
          v-model="mode"
          orientation="horizontal"
          :items="[{ value: 'send', label: 'هبعت بضاعة دلوقتي' }, { value: 'request', label: 'هطلب بضاعة من فرع' }]"
        />
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="من فرع">
            <USelect v-model="fromId" :items="fromChoices" class="w-full" placeholder="اختار الفرع" />
          </UFormField>
          <UFormField label="لفرع">
            <USelect v-model="toId" :items="toChoices" class="w-full" placeholder="اختار الفرع" />
          </UFormField>
        </div>
        <UFormField label="ملاحظات" hint="اختياري">
          <UInput v-model="notes" class="w-full" maxlength="500" placeholder="مثلاً: مع المندوب أحمد" />
        </UFormField>
      </div>
    </UCard>

    <UCard v-if="fromId" :ui="{ body: 'space-y-4' }">
      <div class="relative">
        <UInput
          v-model="term"
          icon="i-lucide-scan-barcode"
          placeholder="امسح الباركود أو اكتب اسم الصنف…"
          class="w-full"
          size="lg"
          @keydown.enter.prevent="addFirst"
        />
        <div v-if="results.length && term" class="absolute inset-x-0 top-full z-10 mt-1 max-h-80 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
          <button
            v-for="v in results"
            :key="v.id"
            type="button"
            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start hover:bg-(--ui-bg-elevated)"
            @click="add(v)"
          >
            <span>
              <span class="font-bold">{{ v.display_name }}</span>
              <span class="block text-xs text-(--ui-text-muted)">{{ v.category.name }}<span v-if="v.barcode"> · <span class="num">{{ v.barcode }}</span></span></span>
            </span>
            <span class="text-xs" :class="v.qty > 0 ? 'text-(--ui-text-muted)' : 'text-(--ui-error)'">في {{ fromName }}: <span class="num">{{ v.qty }}</span></span>
          </button>
        </div>
      </div>

      <ul v-if="lines.length" class="divide-y divide-(--ui-border)">
        <li v-for="(line, i) in lines" :key="line.variant.id" class="space-y-2 py-3">
          <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                {{ line.variant.display_name }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                الموجود في {{ fromName }}: <span class="num">{{ line.variant.qty }}</span>
              </p>
            </div>
            <UInput
              v-if="!(line.variant.track_serial && mode === 'send')"
              v-model="line.qty"
              type="number"
              min="1"
              dir="ltr"
              class="w-24"
              :aria-label="`كمية ${line.variant.display_name}`"
            />
            <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${line.variant.display_name}`" @click="lines.splice(i, 1)" />
          </div>
          <InventorySerialsInput v-if="line.variant.track_serial && mode === 'send'" v-model="line.serials" size="sm" />
          <p v-if="mode === 'send' && lineQty(line) > line.variant.qty" class="text-xs text-(--ui-error)">
            أكتر من الموجود في الفرع.
          </p>
        </li>
      </ul>
      <p v-else class="py-6 text-center text-sm text-(--ui-text-muted)">
        ضيف الأصناف اللي هتتنقل.
      </p>
    </UCard>

    <UAlert v-if="error" color="error" variant="subtle" :title="error" />
    <div class="flex justify-end gap-2">
      <UButton to="/transfers" color="neutral" variant="ghost" label="إلغاء" />
      <UButton
        :icon="mode === 'send' ? 'i-lucide-truck' : 'i-lucide-send'"
        :label="mode === 'send' ? 'ابعت التحويل' : 'ابعت الطلب'"
        :loading="saving"
        :disabled="!canSave"
        @click="save"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { StockTransfer, TransferVariant } from '~/types/api'

definePageMeta({ permission: 'transfers.manage', module: 'multi_branch' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()

const { data: index } = await useAsyncData('transfer-branches', () => api<{ meta: { branches: { id: string, name: string, mine: boolean }[] } }>('/transfers'))
const branches = computed(() => index.value?.meta.branches ?? [])

const mode = ref<'send' | 'request'>('send')
const fromId = ref<string | undefined>(undefined)
const toId = ref<string | undefined>(undefined)
const notes = ref('')
interface Line { variant: TransferVariant, qty: string, serials: string[] }
const lines = ref<Line[]>([])

// Sending: from one of my branches (this one first). Requesting: to one of mine, from any other.
watch(mode, (m) => {
  const current = store.session?.current_branch_id ?? undefined
  if (m === 'send') {
    fromId.value = current
    toId.value = undefined
  }
  else {
    toId.value = current
    fromId.value = undefined
  }
  lines.value = []
}, { immediate: true })
const fromChoices = computed(() => branches.value.filter(b => (mode.value === 'send' ? b.mine : true) && b.id !== toId.value).map(b => ({ value: b.id, label: b.name })))
const toChoices = computed(() => branches.value.filter(b => (mode.value === 'request' ? b.mine : true) && b.id !== fromId.value).map(b => ({ value: b.id, label: b.name })))
const fromName = computed(() => branches.value.find(b => b.id === fromId.value)?.name ?? '')

watch(fromId, () => {
  lines.value = []
})

// Search / scan in the sending branch.
const term = ref('')
const results = ref<TransferVariant[]>([])
let timer: ReturnType<typeof setTimeout> | undefined
let pendingEnter = false
watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (q.length < 2 || !fromId.value) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    const res = await api<{ data: TransferVariant[] }>('/transfers/variants', { query: { q, branch_id: fromId.value } }).catch(() => ({ data: [] as TransferVariant[] }))
    results.value = res.data
    if (pendingEnter && res.data[0]?.exact_barcode) {
      add(res.data[0])
    }
    pendingEnter = false
  }, 200)
})
onBeforeUnmount(() => clearTimeout(timer))

function addFirst() {
  if (results.value[0]) {
    add(results.value[0])
  }
  else {
    pendingEnter = true
  }
}

function add(v: TransferVariant) {
  const existing = lines.value.find(l => l.variant.id === v.id)
  if (existing) {
    existing.qty = String((Number(existing.qty) || 0) + 1)
  }
  else {
    lines.value.push({ variant: v, qty: '1', serials: [] })
  }
  term.value = ''
  results.value = []
}

const lineQty = (l: Line) => (l.variant.track_serial && mode.value === 'send' ? l.serials.length : Number(l.qty) || 0)
const canSave = computed(() => !!fromId.value && !!toId.value && lines.value.length > 0 && lines.value.every(l => lineQty(l) > 0))

const saving = ref(false)
const error = ref<string | null>(null)
async function save() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: StockTransfer }>('/transfers', {
      method: 'POST',
      body: {
        from_branch_id: fromId.value,
        to_branch_id: toId.value,
        notes: notes.value.trim() || null,
        ship_now: mode.value === 'send',
        items: lines.value.map(l => ({ variant_id: l.variant.id, qty: lineQty(l), serials: l.variant.track_serial && mode.value === 'send' ? l.serials : undefined })),
      },
    })
    toast.add({ color: 'success', title: mode.value === 'send' ? `التحويل ${res.data.reference} خرج` : `طلب التحويل ${res.data.reference} اتبعت` })
    await navigateTo(`/transfers/${res.data.id}`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

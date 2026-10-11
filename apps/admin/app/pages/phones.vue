<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold">
          كتالوج الموبايلات
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          القايمة المرجعية للموبايلات اللي «سوق محاسبي» هيربط بيها عروض المحلات. «محتاج مراجعة» = موديلات جديدة لسه هنتأكد منها.
          <span v-if="result">(<span class="num">{{ result.total }}</span> موبايل)</span>
        </p>
      </div>
      <UButton icon="i-lucide-plus" label="ضيف موبايل" @click="openForm()" />
    </div>

    <div class="space-y-3">
      <div class="flex flex-wrap gap-2">
        <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر بالاسم أو الاسم العربي (ايفون 15)" class="w-full sm:w-80" />
        <div class="flex flex-wrap gap-1.5">
          <UButton
            v-for="s in statusTabs"
            :key="s.value"
            size="sm"
            :color="status === s.value ? 'primary' : 'neutral'"
            :variant="status === s.value ? 'soft' : 'ghost'"
            @click="status = s.value"
          >
            {{ s.label }}
            <span v-if="s.value && result" class="num text-xs opacity-70">{{ result.statuses[s.value] ?? 0 }}</span>
          </UButton>
        </div>
      </div>
      <div v-if="result" class="flex flex-wrap gap-1.5">
        <UButton size="xs" :color="brand === '' ? 'primary' : 'neutral'" :variant="brand === '' ? 'soft' : 'outline'" label="كل الماركات" @click="brand = ''" />
        <UButton
          v-for="(n, b) in result.brands"
          :key="b"
          size="xs"
          :color="brand === b ? 'primary' : 'neutral'"
          :variant="brand === b ? 'soft' : 'outline'"
          @click="brand = brand === b ? '' : String(b)"
        >
          {{ b }} <span class="num opacity-70">{{ n }}</span>
        </UButton>
      </div>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="pending && !result" class="p-6 text-center text-sm text-(--ui-text-muted)">
        بيحمّل…
      </div>
      <p v-else-if="!phones.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
        مفيش موبايلات بالبحث ده.
      </p>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="p in shown" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-bold">{{ p.brand }} {{ p.model }}</span>
              <UBadge v-if="p.status !== 'published'" :color="p.status === 'review' ? 'warning' : 'neutral'" variant="subtle" size="sm" :label="statusLabel[p.status]" />
              <UBadge v-if="p.network" color="neutral" variant="outline" size="sm" :label="p.network" />
            </div>
            <p class="mt-0.5 flex flex-wrap gap-x-1.5 text-xs text-(--ui-text-muted)">
              <template v-for="(part, i) in details(p)" :key="i">
                <span v-if="i" aria-hidden="true">·</span>
                <bdi class="num">{{ part }}</bdi>
              </template>
            </p>
            <p v-if="p.notes" class="mt-0.5 text-xs text-(--ui-text-dimmed)">
              {{ p.notes }}
            </p>
          </div>
          <div class="flex gap-1">
            <UButton v-if="p.status === 'review'" size="xs" color="success" variant="soft" icon="i-lucide-check" label="تمام" :loading="busy === p.id" @click="setStatus(p, 'published')" />
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" aria-label="تعديل" @click="openForm(p)" />
          </div>
        </li>
      </ul>
      <div v-if="phones.length > shown.length" class="border-t border-(--ui-border) p-3 text-center">
        <UButton color="neutral" variant="ghost" :label="`اعرض كمان (${phones.length - shown.length})`" @click="limit += 100" />
      </div>
    </UCard>

    <UModal v-model:open="formOpen" :title="form.id ? 'تعديل موبايل' : 'موبايل جديد'">
      <template #body>
        <form id="phone-form" class="space-y-4" @submit.prevent="save">
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="الماركة" required>
              <UInputMenu v-model="form.brand" :items="brandNames" create-item placeholder="Samsung" class="w-full" @create="(b: string) => form.brand = b" />
            </UFormField>
            <UFormField label="السلسلة">
              <UInput v-model="form.series" placeholder="Galaxy A" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="الموديل" required>
            <UInput v-model="form.model" placeholder="Galaxy A56 5G" class="w-full" dir="ltr" />
          </UFormField>
          <UFormField label="أسماء تانية" help="زي ما الناس بتدوّر بيها، مثلاً «سامسونج A56». اكتب واضغط Enter.">
            <UInputTags v-model="form.aliases" placeholder="ضيف اسم" class="w-full" />
          </UFormField>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="سنة النزول">
              <UInput v-model="form.year" type="number" inputmode="numeric" placeholder="2026" class="w-full num" />
            </UFormField>
            <UFormField label="الشبكة">
              <USelect v-model="form.network" :items="networkItems" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="المساحات">
            <div class="flex flex-wrap gap-1.5">
              <UButton
                v-for="s in storageOptions"
                :key="s"
                size="xs"
                class="num"
                :color="form.storage_gb.includes(s) ? 'primary' : 'neutral'"
                :variant="form.storage_gb.includes(s) ? 'soft' : 'outline'"
                :label="storageLabel(s)"
                @click="toggleStorage(s)"
              />
            </div>
          </UFormField>
          <UFormField label="الحالة">
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="s in statuses"
                :key="s.value"
                type="button"
                class="rounded-lg border p-2 text-start text-sm transition"
                :class="form.status === s.value ? 'border-(--ui-primary) bg-(--ui-primary)/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
                @click="form.status = s.value"
              >
                <span class="font-semibold">{{ s.label }}</span>
                <span class="block text-xs text-(--ui-text-muted)">{{ s.hint }}</span>
              </button>
            </div>
          </UFormField>
          <UFormField label="ملاحظة">
            <UTextarea v-model="form.notes" :rows="2" class="w-full" />
          </UFormField>
          <p v-if="editing?.updated_by_name" class="text-xs text-(--ui-text-muted)">
            آخر تعديل: {{ editing.updated_by_name }} · {{ formatDate(editing.updated_at, true) }}
          </p>
        </form>
      </template>
      <template #footer>
        <div class="flex w-full items-center gap-2">
          <UButton type="submit" form="phone-form" :loading="busy === 'form'" label="احفظ" />
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="formOpen = false" />
          <UButton v-if="form.id" class="ms-auto" color="error" variant="ghost" icon="i-lucide-trash-2" :label="confirmDelete ? 'متأكد؟ امسح' : 'امسح'" :loading="busy === 'delete'" @click="remove" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
type Status = 'published' | 'review' | 'hidden'

interface Phone {
  id: string
  brand: string
  series: string | null
  model: string
  slug: string
  aliases: string[]
  year: number | null
  storage_gb: number[]
  network: string | null
  status: Status
  notes: string | null
  updated_by_name: string | null
  updated_at: string | null
}

interface Browse {
  phones: Phone[]
  brands: Record<string, number>
  statuses: Record<Status, number>
  total: number
}

const api = useAdminApi()
const toast = useToast()

const statuses: { value: Status, label: string, hint: string }[] = [
  { value: 'published', label: 'منشور', hint: 'في القايمة' },
  { value: 'review', label: 'محتاج مراجعة', hint: 'لسه هنتأكد' },
  { value: 'hidden', label: 'مخفي', hint: 'مش هيستخدم' },
]
const statusLabel = Object.fromEntries(statuses.map(s => [s.value, s.label])) as Record<Status, string>
const statusTabs: { value: Status | '', label: string }[] = [{ value: '', label: 'الكل' }, ...statuses]
const storageOptions = [16, 32, 64, 128, 256, 512, 1024, 2048]
const networkItems = [{ label: 'مش محدد', value: 'none' }, ...['5G', '4G', '3G', '2G'].map(n => ({ label: n, value: n }))]
const storageLabel = (gb: number) => (gb >= 1024 ? `${gb / 1024}TB` : `${gb}GB`)
const details = (p: Phone) => [
  p.series,
  p.year ? String(p.year) : null,
  p.storage_gb.length ? p.storage_gb.map(storageLabel).join(' / ') : null,
  ...p.aliases,
].filter((x): x is string => !!x)

const q = ref('')
const search = ref('')
let typing: ReturnType<typeof setTimeout> | undefined
watch(q, (v) => {
  clearTimeout(typing)
  typing = setTimeout(() => search.value = v.trim(), 300)
})
const brand = ref('')
const status = ref<Status | ''>('')
const limit = ref(100)

const { data, pending, refresh } = await useAsyncData(
  'admin-phones',
  () => api<{ data: Browse }>('/phones', { query: { q: search.value || undefined, brand: brand.value || undefined, status: status.value || undefined } }),
  { watch: [search, brand, status] },
)
const result = computed(() => data.value?.data)
const phones = computed(() => result.value?.phones ?? [])
const shown = computed(() => phones.value.slice(0, limit.value))
watch([search, brand, status], () => {
  limit.value = 100
})
const brandNames = computed(() => Object.keys(result.value?.brands ?? {}).sort())

const busy = ref<string | null>(null)

async function setStatus(p: Phone, next: Status) {
  busy.value = p.id
  try {
    await api(`/phones/${p.id}`, { method: 'PATCH', body: { status: next } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

const formOpen = ref(false)
const confirmDelete = ref(false)
const editing = ref<Phone | null>(null)
const blank = () => ({ id: '', brand: brand.value, series: '', model: '', aliases: [] as string[], year: '' as string, network: 'none', storage_gb: [] as number[], status: 'published' as Status, notes: '' })
const form = reactive(blank())

function openForm(p?: Phone) {
  editing.value = p ?? null
  confirmDelete.value = false
  Object.assign(form, p
    ? { id: p.id, brand: p.brand, series: p.series ?? '', model: p.model, aliases: [...p.aliases], year: p.year ? String(p.year) : '', network: p.network ?? 'none', storage_gb: [...p.storage_gb], status: p.status, notes: p.notes ?? '' }
    : blank())
  formOpen.value = true
}

function toggleStorage(gb: number) {
  form.storage_gb = form.storage_gb.includes(gb) ? form.storage_gb.filter(s => s !== gb) : [...form.storage_gb, gb].sort((a, b) => a - b)
}

async function save() {
  busy.value = 'form'
  const body = {
    brand: form.brand.trim(),
    series: form.series.trim() || null,
    model: form.model.trim(),
    aliases: form.aliases,
    year: form.year ? Number(form.year) : null,
    network: form.network === 'none' ? null : form.network,
    storage_gb: form.storage_gb,
    status: form.status,
    notes: form.notes.trim() || null,
  }
  try {
    await api(form.id ? `/phones/${form.id}` : '/phones', { method: form.id ? 'PATCH' : 'POST', body })
    toast.add({ color: 'success', title: `اتحفظ «${body.brand} ${body.model}»` })
    formOpen.value = false
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

async function remove() {
  if (!confirmDelete.value) {
    confirmDelete.value = true
    return
  }
  busy.value = 'delete'
  try {
    await api(`/phones/${form.id}`, { method: 'DELETE' })
    toast.add({ color: 'success', title: `اتمسح «${form.brand} ${form.model}»` })
    formOpen.value = false
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

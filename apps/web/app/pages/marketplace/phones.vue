<template>
  <div class="space-y-6">
    <PageHeader title="موبايلاتك في سوق محاسبي" description="كل موبايل بتبيعه بيتربط بموديله في كتالوج السوق، عشان يظهر في صفحة «سعره في كل المحلات» وفي جدول أسعار الموبايلات. أغلبها بيتعرف لوحده من الاسم؛ راجع اللي ما اتعرفش أو اتعرف غلط.">
      <UButton to="/marketplace" color="neutral" variant="outline" icon="i-lucide-arrow-right" label="إعدادات السوق" />
    </PageHeader>

    <div class="flex flex-wrap items-center gap-2">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر في موبايلاتك" class="w-full sm:w-72" />
      <div class="flex flex-wrap gap-1.5">
        <UButton
          v-for="t in tabs"
          :key="t.value"
          size="sm"
          :color="state === t.value ? 'primary' : 'neutral'"
          :variant="state === t.value ? 'soft' : 'ghost'"
          @click="state = t.value"
        >
          {{ t.label }}
          <span v-if="data" class="num text-xs opacity-70">{{ t.value ? data.counts[t.value] : data.total }}</span>
        </UButton>
      </div>
      <UButton
        v-if="autoIds.length"
        class="ms-auto"
        icon="i-lucide-check-check"
        :label="`أكّد اللي اتعرف لوحده (${autoIds.length})`"
        :loading="busy === 'confirm'"
        @click="confirmAll"
      />
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <USkeleton v-if="!data" class="m-4 h-40" />
      <div v-else-if="!data.total" class="space-y-2 p-8 text-center">
        <UIcon name="i-lucide-smartphone" class="size-8 text-(--ui-text-dimmed)" />
        <p class="font-medium">
          مفيش موبايلات معروضة
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          الموبايلات اللي في قسم «أجهزة» (جديدة أو مستعملة) وليها سعر بتظهر هنا.
        </p>
      </div>
      <p v-else-if="!rows.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
        مفيش حاجة هنا.
      </p>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="r in rows" :key="r.variant_id" class="flex flex-wrap items-center gap-3 px-4 py-3">
          <div class="min-w-0 flex-1 basis-60">
            <p class="font-medium">
              {{ r.title }}
              <UBadge v-if="r.used" color="neutral" variant="subtle" size="sm" label="مستعمل" class="ms-1" />
            </p>
            <p class="num text-xs text-(--ui-text-muted)">
              {{ formatMoney(r.price) }}
            </p>
          </div>

          <div class="min-w-0 flex-1 basis-60">
            <template v-if="r.phone">
              <p class="flex flex-wrap items-center gap-1.5 text-sm">
                <UIcon name="i-lucide-link" class="size-4 text-(--ui-primary)" />
                <bdi class="font-bold">{{ r.phone.name }}</bdi>
                <span v-if="r.storage_gb" class="num text-(--ui-text-muted)">{{ storageLabel(r.storage_gb) }}</span>
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ r.state === 'auto' ? 'اتعرف لوحده من الاسم' : 'انت ربطته' }}
              </p>
            </template>
            <template v-else-if="r.state === 'excluded'">
              <p class="text-sm text-(--ui-text-muted)">
                مش موبايل من الكتالوج: بيظهر في البحث بس، من غير مقارنة أسعار.
              </p>
            </template>
            <template v-else>
              <p class="text-sm text-(--ui-warning)">
                ما اتعرفش. اختار موديله:
              </p>
              <div class="mt-1 flex flex-wrap gap-1.5">
                <UButton
                  v-for="s in r.suggestions"
                  :key="s.id"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  :loading="busy === `${r.variant_id}:${s.id}`"
                  @click="link(r, s, null, `${r.variant_id}:${s.id}`)"
                >
                  <bdi>{{ s.name }}</bdi>
                </UButton>
              </div>
            </template>
          </div>

          <div class="flex flex-wrap gap-1">
            <UButton v-if="r.state === 'auto' && r.phone" size="xs" color="success" variant="soft" icon="i-lucide-check" label="تمام" :loading="busy === r.variant_id" @click="link(r, r.phone, r.storage_gb, r.variant_id)" />
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-search" :label="r.phone ? 'غيّر' : 'اختار'" @click="openPicker(r)" />
            <UDropdownMenu :items="menu(r)">
              <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-ellipsis" aria-label="كمان" />
            </UDropdownMenu>
          </div>
        </li>
      </ul>
    </UCard>

    <UModal v-model:open="pickerOpen" :title="picking ? `موديل «${picking.title}»` : 'اختار الموديل'">
      <template #body>
        <div class="space-y-4">
          <UInput v-model="catalogQuery" icon="i-lucide-search" placeholder="مثلاً: ايفون 15 برو، A55، Redmi Note 13" class="w-full" autofocus />
          <USkeleton v-if="catalogLoading" class="h-24" />
          <p v-else-if="catalogQuery.trim() && !catalogResults.length" class="text-sm text-(--ui-text-muted)">
            مش لاقيين الموديل ده في الكتالوج. لو موبايل جديد، ابعتلنا ملاحظة وهنضيفه.
          </p>
          <div v-else class="grid gap-2 sm:grid-cols-2">
            <button
              v-for="p in catalogResults"
              :key="p.id"
              type="button"
              class="rounded-(--ui-radius) border p-3 text-start transition"
              :class="chosen?.id === p.id ? 'border-(--ui-primary) bg-(--ui-primary)/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              @click="choose(p)"
            >
              <bdi class="font-bold">{{ p.name }}</bdi>
              <span v-if="p.year" class="num block text-xs text-(--ui-text-muted)">{{ p.year }}</span>
            </button>
          </div>
          <UFormField v-if="chosen && chosen.storage_gb.length" label="المساحة">
            <div class="flex flex-wrap gap-1.5">
              <UButton
                v-for="gb in chosen.storage_gb"
                :key="gb"
                size="sm"
                class="num"
                :color="storage === gb ? 'primary' : 'neutral'"
                :variant="storage === gb ? 'soft' : 'outline'"
                :label="storageLabel(gb)"
                @click="storage = storage === gb ? null : gb"
              />
            </div>
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2">
          <UButton label="اربط" :disabled="!chosen" :loading="busy === 'picker'" @click="savePicker" />
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="pickerOpen = false" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

definePageMeta({ permission: 'marketplace.manage' })

type State = 'unmatched' | 'auto' | 'shop' | 'excluded'

interface CatalogPhone { id: string, slug: string, name: string, year: number | null, storage_gb: number[] }

interface Row {
  variant_id: string
  title: string
  brand: string | null
  used: boolean
  price: number
  state: State
  phone: CatalogPhone | null
  storage_gb: number | null
  suggestions: CatalogPhone[]
}

interface Links { items: Row[], counts: Record<State, number>, total: number }

const api = useApi()
const toast = useToast()

const tabs: { value: State | '', label: string }[] = [
  { value: '', label: 'الكل' },
  { value: 'unmatched', label: 'محتاج تختار' },
  { value: 'auto', label: 'اتعرف لوحده' },
  { value: 'shop', label: 'انت ربطته' },
  { value: 'excluded', label: 'مش من الكتالوج' },
]
const storageLabel = (gb: number) => (gb >= 1024 ? `${gb / 1024}TB` : `${gb}GB`)

const data = ref<Links | null>(null)
const q = ref('')
const state = ref<State | ''>('')
const rows = computed(() => {
  const needle = normalizeSearch(q.value.trim())
  return (data.value?.items ?? []).filter(r => (!state.value || r.state === state.value)
    && (!needle || needle.split(' ').every(w => normalizeSearch(`${r.title} ${r.phone?.name ?? ''}`).includes(w))))
})
const autoIds = computed(() => (data.value?.items ?? []).filter(r => r.state === 'auto').map(r => r.variant_id))

async function load() {
  data.value = (await api<{ data: Links }>('/marketplace/phones')).data
}
onMounted(load)

const busy = ref<string | null>(null)

async function run(tag: string, fn: () => Promise<unknown>, done: string) {
  busy.value = tag
  try {
    await fn()
    await load()
    toast.add({ color: 'success', title: done })
    return true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    return false
  }
  finally {
    busy.value = null
  }
}

function link(r: Row, phone: CatalogPhone, storage: number | null, tag: string) {
  return run(tag, () => api(`/marketplace/phones/${r.variant_id}`, { method: 'PUT', body: { phone_id: phone.id, storage_gb: storage } }), `اتربط بـ «${phone.name}»`)
}

function confirmAll() {
  return run('confirm', () => api('/marketplace/phones/confirm', { method: 'POST', body: { variant_ids: autoIds.value } }), 'اتأكدوا')
}

function menu(r: Row): DropdownMenuItem[] {
  const items: DropdownMenuItem[] = []
  if (r.state !== 'excluded') {
    items.push({ label: 'مش موبايل من الكتالوج', icon: 'i-lucide-ban', onSelect: () => run(r.variant_id, () => api(`/marketplace/phones/${r.variant_id}`, { method: 'PUT', body: { phone_id: null } }), 'اتشال من مقارنة الأسعار') })
  }
  if (r.state === 'shop' || r.state === 'excluded') {
    items.push({ label: 'رجّعه للتعرف التلقائي', icon: 'i-lucide-undo-2', onSelect: () => run(r.variant_id, () => api(`/marketplace/phones/${r.variant_id}`, { method: 'DELETE' }), 'رجع تلقائي') })
  }
  return items
}

// The catalog picker.
const pickerOpen = ref(false)
const picking = ref<Row | null>(null)
const catalogQuery = ref('')
const catalogResults = ref<CatalogPhone[]>([])
const catalogLoading = ref(false)
const chosen = ref<CatalogPhone | null>(null)
const storage = ref<number | null>(null)

function openPicker(r: Row) {
  picking.value = r
  chosen.value = r.phone
  storage.value = r.storage_gb
  catalogResults.value = r.phone ? [r.phone] : r.suggestions
  catalogQuery.value = ''
  pickerOpen.value = true
}

function choose(p: CatalogPhone) {
  chosen.value = p
  storage.value = storage.value !== null && p.storage_gb.includes(storage.value) ? storage.value : null
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(catalogQuery, (value) => {
  clearTimeout(timer)
  if (!value.trim()) return
  timer = setTimeout(async () => {
    catalogLoading.value = true
    try {
      catalogResults.value = (await api<{ data: CatalogPhone[] }>('/marketplace/phones/catalog', { query: { q: value.trim() } })).data
    }
    finally {
      catalogLoading.value = false
    }
  }, 250)
})

async function savePicker() {
  if (!picking.value || !chosen.value) return
  if (await link(picking.value, chosen.value, storage.value, 'picker')) {
    pickerOpen.value = false
  }
}
</script>

<template>
  <div class="space-y-6 pb-24">
    <PageHeader title="مرتجعات الموردين" description="التالف والمرتجع متفرز حسب مصدره تلقائي. اختار القطع واعمل إذن مرتجع لكل مورد." />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          في السلة
        </p>
        <p class="text-2xl font-extrabold num">
          {{ bin?.units ?? 0 }} <span class="text-sm font-normal text-(--ui-text-muted)">قطعة</span>
        </p>
        <p v-if="bin?.value !== null && bin?.value !== undefined" class="text-xs text-(--ui-text-muted) num">
          {{ formatMoney(bin.value) }} بالتكلفة
        </p>
      </div>
      <div class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
        <p class="text-sm text-(--ui-text-muted)">
          مصدرها مش معروف
        </p>
        <p class="text-2xl font-extrabold num" :class="unknownUnits ? 'text-warning' : ''">
          {{ unknownUnits }}
        </p>
      </div>
      <button type="button" class="app-card col-span-2 rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4 text-start lg:col-span-1" @click="tab = 'notes'">
        <p class="text-sm text-(--ui-text-muted)">
          أذونات لسه متسوّتش
        </p>
        <p class="text-2xl font-extrabold num">
          {{ bin?.open_notes ?? 0 }}
        </p>
      </button>
    </div>

    <UTabs v-model="tab" :items="tabs" :content="false" class="w-full" />

    <!-- الفرز -->
    <template v-if="tab === 'sort'">
      <div v-if="!bin?.groups.length && binStatus !== 'pending'" class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-10 text-center text-(--ui-text-muted)">
        <UIcon name="i-lucide-package-check" class="mx-auto mb-2 size-10" />
        <p>السلة فاضية.</p>
        <p class="mt-1 text-sm">
          القطع بتدخلها من المخزون («طلّع للمرتجعات»)، ومن مرتجعات العملاء التالفة، ومن قطع الصيانة اللي طلعت بايظة.
        </p>
      </div>

      <UCard
        v-for="group in bin?.groups ?? []"
        :key="group.key"
        :ui="{ header: 'p-3 sm:px-4', body: 'p-0 sm:p-0' }"
        :class="!group.source ? 'ring-1 ring-warning/60' : ''"
      >
        <template #header>
          <div class="flex flex-wrap items-center gap-3">
            <UCheckbox
              v-if="canManage && group.source"
              :model-value="groupState(group)"
              :aria-label="`اختار كل قطع ${group.source.name}`"
              @update:model-value="toggleGroup(group, $event === true)"
            />
            <UIcon v-else-if="!group.source" name="i-lucide-circle-help" class="size-5 text-warning" />
            <div class="min-w-0 flex-1">
              <p class="font-extrabold">
                {{ group.source ? group.source.name : 'مصدرها مش معروف' }}
                <UBadge v-if="group.source" :color="group.source.type === 'shop' ? 'info' : 'neutral'" variant="subtle" size="sm" class="ms-1 align-middle">
                  {{ group.source.type === 'shop' ? 'محل شريك (طلبات بين المحلات)' : 'مورد' }}
                </UBadge>
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                <span class="num">{{ group.units }}</span> قطعة<template v-if="group.value !== null">
                  بـ <span class="font-bold num text-(--ui-text)">{{ formatMoney(group.value) }}</span>
                </template>
                <template v-if="!group.source">
                  · اختار مصدر كل قطعة عشان تقدر ترجّعها
                </template>
              </p>
            </div>
            <UButton
              v-if="canManage && group.source"
              size="sm"
              icon="i-lucide-file-output"
              :label="selectedIn(group).length && selectedIn(group).length < group.items.length ? `إذن مرتجع (${selectedIn(group).length})` : 'إذن مرتجع'"
              :loading="creating === group.key"
              @click="createNotes(selectedIn(group).length ? selectedIn(group) : group.items.map(i => i.id), group.key)"
            />
          </div>
        </template>

        <ul>
          <li v-for="item in group.items" :key="item.id" class="flex flex-wrap items-start gap-3 border-t border-(--ui-border) p-3 sm:px-4 first:border-t-0">
            <UCheckbox
              v-if="canManage && group.source"
              :model-value="selected.has(item.id)"
              class="mt-1"
              :aria-label="`اختار ${item.name}`"
              @update:model-value="toggle(item.id, $event === true)"
            />
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                <span class="num">{{ item.qty }}</span> × {{ item.name }}
              </p>
              <p v-if="item.serial" class="num text-xs text-(--ui-text-muted)" dir="ltr">
                IMEI {{ item.serial }}
              </p>
              <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                <UBadge color="warning" variant="subtle" size="sm">
                  {{ item.reason_label }}
                </UBadge>
                <span class="text-(--ui-text-muted)">{{ item.origin_label }}</span>
                <span v-if="item.source" class="text-(--ui-text-muted)">· {{ detectedLabel(item.detected_by) }}<template v-if="item.source_doc"> (<span class="num">{{ item.source_doc }}</span>)</template></span>
                <span class="text-(--ui-text-muted) num">· {{ formatDate(item.created_at) }}</span>
              </div>
              <p v-if="item.note" class="mt-1 text-xs">
                {{ item.note }}
              </p>
            </div>
            <div class="flex items-center gap-1">
              <span v-if="item.value !== null" class="font-bold num">{{ formatMoney(item.value) }}</span>
              <UButton v-if="canManage && !item.source" size="xs" color="warning" variant="soft" icon="i-lucide-truck" label="اختار المصدر" @click="edit(item)" />
              <UDropdownMenu v-if="canManage" :items="itemActions(item)" :content="{ align: 'end' }">
                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis-vertical" square size="sm" :aria-label="`إجراءات ${item.name}`" />
              </UDropdownMenu>
            </div>
          </li>
        </ul>
      </UCard>
    </template>

    <!-- الأذونات -->
    <template v-else>
      <div class="flex flex-wrap gap-2">
        <UButton
          v-for="f in noteFilters"
          :key="f.value"
          :label="f.label"
          size="sm"
          :color="noteFilter === f.value ? 'primary' : 'neutral'"
          :variant="noteFilter === f.value ? 'solid' : 'outline'"
          @click="noteFilter = f.value"
        />
      </div>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <ul>
          <li v-for="note in notes" :key="note.id" class="border-t border-(--ui-border) first:border-t-0">
            <NuxtLink :to="`/supplier-returns/${note.id}`" class="flex flex-wrap items-center gap-3 p-3 hover:bg-(--ui-bg-elevated) sm:px-4">
              <div class="min-w-0 flex-1">
                <p class="font-bold">
                  <span class="num">{{ note.reference }}</span> · {{ note.source.name }}
                </p>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num">{{ note.units }}</span> قطعة · <span class="num">{{ formatDate(note.created_at) }}</span>
                  <span v-if="note.created_by_name"> · {{ note.created_by_name }}</span>
                </p>
              </div>
              <span v-if="note.total_cost !== null" class="font-bold num">{{ formatMoney(note.total_cost) }}</span>
              <UBadge :color="noteStatusColor(note.status)" variant="subtle">
                {{ note.status_label }}
              </UBadge>
            </NuxtLink>
          </li>
          <li v-if="!notes.length && notesStatus !== 'pending'" class="p-10 text-center text-(--ui-text-muted)">
            مفيش أذونات هنا.
          </li>
        </ul>
      </UCard>
      <div v-if="(notesMeta?.last_page ?? 1) > 1" class="flex justify-center">
        <UPagination v-model:page="notesPage" :total="notesMeta?.total ?? 0" :items-per-page="notesMeta?.per_page ?? 30" />
      </div>
    </template>

    <div v-if="tab === 'sort' && selected.size" class="fixed inset-x-0 bottom-0 z-30 border-t border-(--ui-border) bg-(--ui-bg) p-3 shadow-lg lg:ps-64">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
        <p class="text-sm">
          <span class="font-bold num">{{ selected.size }}</span> سطر متحدد من <span class="num">{{ selectedSources }}</span> مصدر
        </p>
        <div class="flex gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء التحديد" @click="selected.clear()" />
          <UButton icon="i-lucide-file-output" :label="selectedSources > 1 ? `اعمل ${selectedSources} أذونات` : 'اعمل إذن مرتجع'" :loading="creating === 'all'" @click="createNotes([...selected], 'all')" />
        </div>
      </div>
    </div>

    <SupplierReturnsItemModal v-model:open="itemOpen" :item="editing" @saved="refreshBin" />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Paginated } from '~/types/api'
import type { BinData, BinGroup, BinItem, ReturnNote } from '~/utils/supplierReturns'

definePageMeta({ permission: 'supplier_returns.view' })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const route = useRoute()
const canManage = computed(() => store.can('supplier_returns.manage'))
const branchKey = computed(() => store.session?.current_branch_id ?? '')

const tabs = [
  { label: 'الفرز', value: 'sort', icon: 'i-lucide-layers' },
  { label: 'أذونات المرتجع', value: 'notes', icon: 'i-lucide-file-text' },
]
const tab = ref(route.query.tab === 'notes' ? 'notes' : 'sort')

const { data: binData, status: binStatus, refresh: refreshBin } = await useAsyncData('supplier-returns-bin', () => api<{ data: BinData }>('/supplier-returns/bin'), { watch: [branchKey] })
const bin = computed(() => binData.value?.data)
const unknownUnits = computed(() => bin.value?.groups.find(g => !g.source)?.units ?? 0)

// Selection survives a refresh only for lines still in the bin.
const selected = reactive(new Set<string>())
watch(bin, (value) => {
  const ids = new Set(value?.groups.flatMap(g => g.items.map(i => i.id)))
  ;[...selected].forEach(id => !ids.has(id) && selected.delete(id))
})

function toggle(id: string, on: boolean) {
  if (on) {
    selected.add(id)
  }
  else {
    selected.delete(id)
  }
}
function selectedIn(group: BinGroup): string[] {
  return group.items.filter(i => selected.has(i.id)).map(i => i.id)
}
function groupState(group: BinGroup): boolean | 'indeterminate' {
  const n = selectedIn(group).length
  return n === 0 ? false : n === group.items.length ? true : 'indeterminate'
}
function toggleGroup(group: BinGroup, on: boolean) {
  group.items.forEach(i => toggle(i.id, on))
}
const selectedSources = computed(() => new Set((bin.value?.groups ?? []).filter(g => selectedIn(g).length).map(g => g.key)).size)

const creating = ref<string | null>(null)
async function createNotes(itemIds: string[], key: string) {
  creating.value = key
  try {
    const res = await api<{ data: ReturnNote[] }>('/supplier-returns/notes', { method: 'POST', body: { item_ids: itemIds } })
    itemIds.forEach(id => selected.delete(id))
    toast.add({ color: 'success', title: res.data.length > 1 ? `اتعمل ${res.data.length} أذونات مرتجع` : `اتعمل إذن المرتجع ${res.data[0]!.reference}` })
    if (res.data.length === 1) {
      await navigateTo(`/supplier-returns/${res.data[0]!.id}`)
      return
    }
    await Promise.all([refreshBin(), refreshNotes()])
    tab.value = 'notes'
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    creating.value = null
  }
}

// Line actions
const itemOpen = ref(false)
const editing = ref<BinItem | null>(null)
function edit(item: BinItem) {
  editing.value = item
  itemOpen.value = true
}

async function binAction(item: BinItem, action: 'restock' | 'write-off') {
  const question = action === 'restock'
    ? `ترجّع ${item.qty} × ${item.name} للمخزون عشان تتباع تاني؟`
    : `تعدم ${item.qty} × ${item.name}؟ هتتحسب خسارة ومش هترجع للمورد.`
  if (!window.confirm(question)) {
    return
  }
  try {
    await api(`/supplier-returns/bin/${item.id}/${action}`, { method: 'POST' })
    toast.add({ color: 'success', title: action === 'restock' ? 'رجعت المخزون' : 'اتعدمت' })
    await refreshBin()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

function itemActions(item: BinItem): DropdownMenuItem[][] {
  return [
    [{ label: item.source ? 'تعديل (المصدر / السبب)' : 'اختار المصدر', icon: 'i-lucide-pencil', onSelect: () => edit(item) }],
    [
      { label: 'رجّعها المخزون', icon: 'i-lucide-package-plus', onSelect: () => binAction(item, 'restock') },
      { label: 'إعدام', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => binAction(item, 'write-off') },
    ],
  ]
}

// Notes
const noteFilters = [
  { label: 'لسه متسوّتش', value: 'open' },
  { label: 'اتسوّت', value: 'settled' },
  { label: 'الكل', value: 'all' },
]
const noteFilter = ref('open')
const notesPage = ref(1)
watch(noteFilter, () => {
  notesPage.value = 1
})
const { data: notesData, status: notesStatus, refresh: refreshNotes } = await useAsyncData(
  'supplier-returns-notes',
  () => api<Paginated<ReturnNote>>('/supplier-returns/notes', { query: { status: noteFilter.value, page: notesPage.value } }),
  { watch: [noteFilter, notesPage, branchKey] },
)
const notes = computed(() => notesData.value?.data ?? [])
const notesMeta = computed(() => notesData.value?.meta)
</script>

<template>
  <div class="max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="قوايم الأعطال" description="الأجزاء والأعطال اللي بتظهر وقت الاستلام والتشخيص. السعر المقترح بيتحط كمصنعية." class="flex-1" />
    </div>

    <!-- Technicians' commission -->
    <UCard v-if="commissions.length">
      <template #header>
        <p class="font-bold">
          عمولة الفنيين
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          الخانات بتتجمع، واللي تسيبه صفر مش بيتحسب: مثلاً 30% من المصنعية + 10% من سعر القطع. بتتحسب وقت تسليم الجهاز المتصلّح وبتظهر في تقرير الصيانة. مفيش عمولة على الرجوع في الضمان.
        </p>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="c in commissions" :key="c.user_id" class="space-y-3 py-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold">{{ c.name }}</span>
            <span class="text-sm text-(--ui-text-muted)">{{ c.description || 'من غير عمولة' }}</span>
          </div>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <UFormField label="من المصنعية">
              <UInput v-model="c.labor" type="number" min="0" max="100" step="any" dir="ltr" class="w-full" :aria-label="`نسبة المصنعية لـ ${c.name}`">
                <template #trailing>
                  <span class="text-xs text-(--ui-text-muted)">%</span>
                </template>
              </UInput>
            </UFormField>
            <UFormField label="من القطع">
              <div class="flex gap-2">
                <UInput v-model="c.parts" type="number" min="0" max="100" step="any" dir="ltr" class="w-24" :aria-label="`نسبة القطع لـ ${c.name}`">
                  <template #trailing>
                    <span class="text-xs text-(--ui-text-muted)">%</span>
                  </template>
                </UInput>
                <USelect v-model="c.parts_base" :items="partsBases" class="flex-1" :aria-label="`من إيه في القطع لـ ${c.name}`" />
              </div>
            </UFormField>
            <UFormField label="من مكسب الجهاز كله">
              <UInput v-model="c.profit" type="number" min="0" max="100" step="any" dir="ltr" class="w-full" :aria-label="`نسبة المكسب لـ ${c.name}`">
                <template #trailing>
                  <span class="text-xs text-(--ui-text-muted)">%</span>
                </template>
              </UInput>
            </UFormField>
            <UFormField label="مبلغ ثابت لكل جهاز">
              <UInput v-model="c.fixedInput" type="number" min="0" step="any" dir="ltr" class="w-full" :aria-label="`المبلغ الثابت لـ ${c.name}`">
                <template #trailing>
                  <span class="text-xs text-(--ui-text-muted)">ج</span>
                </template>
              </UInput>
            </UFormField>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <UCheckbox v-model="c.warranty_clawback" label="لو الجهاز رجع في الضمان، عمولته تتخصم من الرواتب" />
            <UButton size="sm" color="neutral" variant="outline" label="حفظ" class="ms-auto" @click="saveCommission(c)" />
          </div>
        </li>
      </ul>
    </UCard>

    <form class="flex gap-2" @submit.prevent="addCategory">
      <UInput v-model="newCategory" placeholder="جزء جديد (مثلاً: الشبكة)" class="flex-1 sm:max-w-xs" />
      <UButton type="submit" icon="i-lucide-plus" label="إضافة جزء" :disabled="!newCategory.trim()" />
    </form>

    <div class="grid gap-4 md:grid-cols-2">
      <UCard v-for="c in categories" :key="c.id">
        <template #header>
          <div class="flex items-center gap-2">
            <UInput :model-value="c.name" size="sm" class="flex-1 font-bold" :aria-label="`اسم الجزء ${c.name}`" @change="(e: Event) => renameCategory(c.id, (e.target as HTMLInputElement).value)" />
          </div>
        </template>
        <ul class="space-y-2">
          <li v-for="t in c.types" :key="t.id" class="flex items-center gap-2" :class="{ 'opacity-50': !t.is_active }">
            <UInput :model-value="t.name" size="sm" class="flex-1" :aria-label="`اسم العطل ${t.name}`" @change="(e: Event) => updateType(t.id, { name: (e.target as HTMLInputElement).value })" />
            <UInput
              :model-value="t.default_labor_price !== null ? String(t.default_labor_price / 100) : ''"
              size="sm"
              type="number"
              min="0"
              step="any"
              dir="ltr"
              placeholder="السعر"
              class="w-24"
              :aria-label="`المصنعية المقترحة لـ ${t.name}`"
              @change="(e: Event) => updateType(t.id, { default_labor_price: toPiasters((e.target as HTMLInputElement).value) })"
            />
            <UTooltip :text="t.is_active ? 'إخفاء' : 'إظهار'">
              <UButton size="sm" color="neutral" variant="ghost" :icon="t.is_active ? 'i-lucide-eye' : 'i-lucide-eye-off'" square :aria-label="t.is_active ? `إخفاء ${t.name}` : `إظهار ${t.name}`" @click="updateType(t.id, { is_active: !t.is_active })" />
            </UTooltip>
          </li>
        </ul>
        <form class="mt-3 flex gap-2" @submit.prevent="addType(c.id)">
          <UInput v-model="newType[c.id]" size="sm" placeholder="عطل جديد" class="flex-1" />
          <UButton type="submit" size="sm" color="neutral" variant="outline" icon="i-lucide-plus" square aria-label="إضافة عطل" :disabled="!newType[c.id]?.trim()" />
        </form>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RepairFaultCategory } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.settings' })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const { data, refresh } = await useAsyncData('fault-categories', () => api<{ data: RepairFaultCategory[] }>('/repairs/fault-categories'))
const categories = computed(() => data.value?.data ?? [])

interface CommissionRule { user_id: string, name: string, labor_rate: number, parts_rate: number, parts_base: 'price' | 'profit', profit_rate: number, fixed: number, warranty_clawback: boolean, description: string | null }
interface CommissionRow extends CommissionRule { labor: string, parts: string, profit: string, fixedInput: string }
const partsBases = [{ label: 'من سعر القطعة', value: 'price' }, { label: 'من مكسب القطعة', value: 'profit' }]
// The owner's «عمولة الفنيين» switch: off, the section isn't there (and the API refuses it).
const { data: commissionData } = await useAsyncData('repair-commissions', async () => store.hasFeature('repairs.commission')
  ? await api<{ data: CommissionRule[] }>('/repairs/commissions')
  : { data: [] })
const commissions = ref<CommissionRow[]>([])
// Rates are kept in basis points (1500 = 15%), the fixed amount in piasters: shown as people type them.
const shown = (n: number) => (n ? String(n / 100) : '')
const stored = (v: string) => Math.max(0, Math.round(Number(v || 0) * 100))
watch(commissionData, (value) => {
  commissions.value = (value?.data ?? []).map(c => ({ ...c, labor: shown(c.labor_rate), parts: shown(c.parts_rate), profit: shown(c.profit_rate), fixedInput: shown(c.fixed) }))
}, { immediate: true })

async function saveCommission(c: CommissionRow) {
  try {
    const res = await api<{ data: CommissionRule[] }>(`/repairs/commissions/${c.user_id}`, {
      method: 'PUT',
      body: { labor_rate: stored(c.labor), parts_rate: stored(c.parts), parts_base: c.parts_base, profit_rate: stored(c.profit), fixed: stored(c.fixedInput), warranty_clawback: c.warranty_clawback },
    })
    commissionData.value = res
    toast.add({ color: 'success', title: `اتحفظت عمولة ${c.name}` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

const newCategory = ref('')
const newType = reactive<Record<number, string>>({})

async function run(request: () => Promise<unknown>) {
  try {
    await request()
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

const addCategory = () => run(async () => {
  await api('/repairs/fault-categories', { method: 'POST', body: { name: newCategory.value.trim() } })
  newCategory.value = ''
})
const renameCategory = (id: number, name: string) => run(() => api(`/repairs/fault-categories/${id}`, { method: 'PATCH', body: { name } }))
const addType = (categoryId: number) => run(async () => {
  await api(`/repairs/fault-categories/${categoryId}/types`, { method: 'POST', body: { name: newType[categoryId]?.trim() } })
  newType[categoryId] = ''
})
const updateType = (id: number, body: Record<string, unknown>) => run(() => api(`/repairs/fault-types/${id}`, { method: 'PATCH', body }))
</script>

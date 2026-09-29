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
          بتتحسب لوحدها وقت تسليم الجهاز المتصلّح، وبتظهر في تقرير الصيانة. مفيش عمولة على الرجوع في الضمان.
        </p>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="c in commissions" :key="c.user_id" class="flex flex-wrap items-center gap-2 py-2">
          <span class="w-40 font-bold">{{ c.name }}</span>
          <USelect v-model="c.type" :items="commissionTypes" size="sm" class="w-32" :aria-label="`نوع عمولة ${c.name}`" />
          <template v-if="c.type !== 'none'">
            <UInput v-model="c.input" size="sm" type="number" min="0" step="any" dir="ltr" class="w-24" :aria-label="`قيمة عمولة ${c.name}`">
              <template #trailing>
                <span class="text-xs text-(--ui-text-muted)">{{ c.type === 'percent' ? '%' : 'ج' }}</span>
              </template>
            </UInput>
            <USelect v-if="c.type === 'percent'" v-model="c.base" :items="commissionBases" size="sm" class="w-32" :aria-label="`من إيه لـ ${c.name}`" />
          </template>
          <UButton size="sm" color="neutral" variant="outline" label="حفظ" class="ms-auto" @click="saveCommission(c)" />
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
const toast = useToast()
const { data, refresh } = await useAsyncData('fault-categories', () => api<{ data: RepairFaultCategory[] }>('/repairs/fault-categories'))
const categories = computed(() => data.value?.data ?? [])

interface CommissionRow { user_id: string, name: string, type: 'none' | 'percent' | 'fixed', value: number | null, base: 'labor' | 'profit', input: string }
const commissionTypes = [{ label: 'من غير عمولة', value: 'none' }, { label: 'نسبة', value: 'percent' }, { label: 'مبلغ للجهاز', value: 'fixed' }]
const commissionBases = [{ label: 'من المصنعية', value: 'labor' }, { label: 'من المكسب', value: 'profit' }]
const { data: commissionData } = await useAsyncData('repair-commissions', () => api<{ data: Omit<CommissionRow, 'input'>[] }>('/repairs/commissions'))
const commissions = ref<CommissionRow[]>([])
watch(commissionData, (value) => {
  // Percent is kept in basis points (1500 = 15%), fixed in piasters: show both as people type them.
  commissions.value = (value?.data ?? []).map(c => ({ ...c, input: c.value === null ? '' : String(c.value / 100) }))
}, { immediate: true })

async function saveCommission(c: CommissionRow) {
  try {
    const res = await api<{ data: Omit<CommissionRow, 'input'>[] }>(`/repairs/commissions/${c.user_id}`, {
      method: 'PUT',
      body: { type: c.type, value: c.type === 'none' ? null : Math.round(Number(c.input || 0) * 100), base: c.base },
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

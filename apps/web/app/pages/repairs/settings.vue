<template>
  <div class="max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="قوايم الأعطال" description="الأجزاء والأعطال اللي بتظهر وقت الاستلام والتشخيص. السعر المقترح بيتحط كمصنعية." class="flex-1" />
    </div>

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

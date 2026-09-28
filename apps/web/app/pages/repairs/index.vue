<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">
          الصيانة
        </h1>
        <p class="text-(--ui-text-muted)">
          قوايم الأعطال اللي بتظهر وقت استلام الجهاز.
        </p>
      </div>
      <UButton icon="i-lucide-plus" label="استلام جهاز" disabled />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <UCard v-for="category in categories" :key="category.id">
        <template #header>
          <p class="font-semibold">
            {{ category.name }}
          </p>
        </template>
        <div class="flex flex-wrap gap-2">
          <UBadge v-for="type in category.types" :key="type.id" color="neutral" variant="outline">
            {{ type.name }}
          </UBadge>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ module: 'repairs' })

interface FaultCategory {
  id: number
  name: string
  types: { id: number, name: string, default_labor_price: number | null, is_active: boolean }[]
}

const api = useApi()
const { data } = await useAsyncData('fault-categories', () => api<{ data: FaultCategory[] }>('/repairs/fault-categories'))
const categories = computed(() => data.value?.data ?? [])
</script>

<template>
  <div class="space-y-3">
    <div class="flex gap-2 overflow-x-auto pb-1">
      <UButton
        v-for="c in categories"
        :key="c.id"
        size="sm"
        class="shrink-0 rounded-full"
        :color="open === c.id ? 'primary' : 'neutral'"
        :variant="open === c.id ? 'solid' : 'outline'"
        @click="open = c.id"
      >
        {{ c.name }}
        <UBadge v-if="countIn(c)" size="sm" color="neutral" variant="solid" class="num">
          {{ countIn(c) }}
        </UBadge>
      </UButton>
    </div>
    <div v-if="current" class="flex flex-wrap gap-2">
      <UButton
        v-for="t in current.types.filter(t => t.is_active || model.includes(t.id))"
        :key="t.id"
        size="sm"
        :color="model.includes(t.id) ? 'primary' : 'neutral'"
        :variant="model.includes(t.id) ? 'soft' : 'outline'"
        :icon="model.includes(t.id) ? 'i-lucide-check' : undefined"
        :aria-pressed="model.includes(t.id)"
        @click="toggle(t.id)"
      >
        {{ t.name }}
      </UButton>
    </div>
    <div v-if="model.length" class="flex flex-wrap items-center gap-1.5 text-sm">
      <span class="text-(--ui-text-muted)">اتختار:</span>
      <UBadge v-for="f in picked" :key="f.id" color="primary" variant="subtle">
        {{ f.category }} · {{ f.name }}
        <button type="button" class="ms-1" :aria-label="`شيل ${f.name}`" @click="toggle(f.id)">
          <UIcon name="i-lucide-x" class="size-3" />
        </button>
      </UBadge>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RepairFaultCategory } from '~/types/api'

/** Faults picked from the shop's lists: part first, then what's wrong with it. */
const props = defineProps<{ categories: RepairFaultCategory[] }>()
const model = defineModel<number[]>({ default: () => [] })

const open = ref<number | null>(props.categories[0]?.id ?? null)
const current = computed(() => props.categories.find(c => c.id === open.value))
const countIn = (c: RepairFaultCategory) => c.types.filter(t => model.value.includes(t.id)).length
const picked = computed(() => props.categories.flatMap(c => c.types.filter(t => model.value.includes(t.id)).map(t => ({ id: t.id, name: t.name, category: c.name }))))

function toggle(id: number) {
  model.value = model.value.includes(id) ? model.value.filter(x => x !== id) : [...model.value, id]
}
</script>

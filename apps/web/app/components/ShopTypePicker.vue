<template>
  <div class="space-y-3">
    <div class="grid grid-cols-2 gap-2">
      <button
        v-for="type in types"
        :key="type.value"
        type="button"
        role="checkbox"
        :aria-checked="model.includes(type.value)"
        class="flex items-start gap-2 rounded-lg border px-3 py-2 text-start transition"
        :class="model.includes(type.value) ? 'border-primary bg-primary/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
        @click="toggle(type.value)"
      >
        <UIcon :name="icons[type.value] ?? 'i-lucide-store'" class="mt-0.5 size-5 shrink-0" :class="model.includes(type.value) ? 'text-primary' : 'text-(--ui-text-muted)'" />
        <span class="min-w-0 flex-1">
          <span class="block text-sm" :class="model.includes(type.value) ? 'font-bold text-primary' : 'font-semibold'">{{ type.label }}</span>
          <span class="block text-xs text-(--ui-text-muted)">{{ type.description }}</span>
        </span>
        <UIcon v-if="model.includes(type.value)" name="i-lucide-check" class="size-4 shrink-0 text-primary" />
      </button>
    </div>
    <div v-if="modules.length" class="rounded-lg bg-(--ui-bg-elevated) p-3 text-sm">
      <p class="mb-1.5 font-bold">
        {{ modulesTitle }}
      </p>
      <div class="flex flex-wrap gap-1.5">
        <UBadge v-for="m in modules" :key="m.key" :color="m.available && m.trial ? 'primary' : 'neutral'" :variant="m.trial || !m.available ? 'subtle' : 'outline'">
          {{ m.name }}<template v-if="!m.available">
            · قريباً
          </template><template v-else-if="!m.trial">
            · تجربه وقت ما تحب
          </template>
        </UBadge>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
export interface ShopTypeOption {
  value: string
  label: string
  description: string
  modules: { key: string, name: string, available: boolean, trial: boolean }[]
}

/** One or more kinds of shop; shows the extra modules that come with them. */
withDefaults(defineProps<{ modulesTitle?: string }>(), { modulesTitle: 'الأقسام الإضافية اللي تناسبك (تجربة مجانية 14 يوم):' })
const model = defineModel<string[]>({ required: true })

const api = useApi()
const types = useState<ShopTypeOption[]>('shop-types', () => [])
if (!types.value.length) {
  types.value = (await api<{ data: ShopTypeOption[] }>('/shop-types').catch(() => ({ data: [] as ShopTypeOption[] }))).data
}

const icons: Record<string, string> = {
  accessories: 'i-lucide-headphones',
  repair: 'i-lucide-wrench',
  phones: 'i-lucide-smartphone',
  wholesale: 'i-lucide-boxes',
  importer: 'i-lucide-ship',
}

const modules = computed(() => {
  const seen = new Map<string, ShopTypeOption['modules'][number]>()
  for (const type of types.value.filter(t => model.value.includes(t.value))) {
    for (const m of type.modules) {
      // Trial if any picked type starts it.
      seen.set(m.key, { ...m, trial: m.trial || !!seen.get(m.key)?.trial })
    }
  }
  const rank = (m: { available: boolean, trial: boolean }) => (m.available ? 2 : 0) + (m.trial ? 1 : 0)
  return [...seen.values()].sort((a, b) => rank(b) - rank(a))
})

function toggle(value: string) {
  model.value = model.value.includes(value) ? model.value.filter(v => v !== value) : [...model.value, value]
}
</script>

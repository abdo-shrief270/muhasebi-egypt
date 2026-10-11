<template>
  <div class="space-y-3">
    <div class="grid gap-2 sm:grid-cols-2">
      <button
        v-for="need in needs"
        :key="need.key"
        type="button"
        role="checkbox"
        :aria-checked="model.includes(need.key)"
        class="flex items-start gap-2 rounded-lg border px-3 py-2 text-start transition"
        :class="model.includes(need.key) ? 'border-primary bg-primary/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
        @click="toggle(need.key)"
      >
        <UIcon :name="need.icon" class="mt-0.5 size-5 shrink-0" :class="model.includes(need.key) ? 'text-primary' : 'text-(--ui-text-muted)'" />
        <span class="min-w-0 flex-1">
          <span class="block text-sm" :class="model.includes(need.key) ? 'font-bold text-primary' : 'font-semibold'">{{ need.label }}</span>
          <span class="block text-xs text-(--ui-text-muted)">{{ need.description }}</span>
        </span>
        <UIcon v-if="model.includes(need.key)" name="i-lucide-check" class="size-4 shrink-0 text-primary" />
      </button>
    </div>

    <div v-if="recommended" class="rounded-lg bg-(--ui-bg-elevated) p-3 text-sm" aria-live="polite">
      <p class="font-bold">
        الأنسب ليك: باقة «{{ recommended.plan_name }}»<template v-if="recommended.modules.length">
          + {{ recommended.modules.map(m => m.name).join('، ') }}
        </template>
      </p>
      <p class="mt-1 text-(--ui-text-muted)">
        <span class="num font-bold text-(--ui-text)">{{ formatMoney(recommended.monthly) }}</span> في الشهر
        <template v-if="lifetimeOn && recommended.lifetime">
          · أو اشتريه مرة واحدة بـ <span class="num font-bold text-(--ui-text)">{{ formatMoney(recommended.lifetime) }}</span>
          وصيانة <span class="num">{{ formatMoney(recommended.maintenance) }}</span> في السنة
        </template>
      </p>
      <p class="mt-1 text-xs text-(--ui-text-muted)">
        أول 14 يوم ببلاش بالحاجات دي كلها، وتقدر تغيّر بعدين.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Need { key: string, label: string, description: string, icon: string, types: string[] }
interface Recommendation { plan: string, plan_name: string, modules: { key: string, name: string }[], monthly: number, lifetime: number | null, maintenance: number | null }

/**
 * «بتعمل إيه في المحل؟» at sign-up: what the shop does (its trial starts with those modules), ticked
 * in advance from its kinds until the owner changes a tick, with the plan that covers it and its price.
 */
const props = defineProps<{ shopTypes: string[] }>()
const model = defineModel<string[]>({ required: true })

const api = useApi()
const plans = await api<{ data: { needs: Need[], lifetime: unknown | null } }>('/public/plans').catch(() => null)
const needs = computed(() => plans?.data.needs ?? [])
const lifetimeOn = computed(() => !!plans?.data.lifetime)

const touched = ref(false)
function toggle(key: string) {
  touched.value = true
  model.value = model.value.includes(key) ? model.value.filter(k => k !== key) : [...model.value, key]
}
watch(() => props.shopTypes, (types) => {
  if (!touched.value) {
    model.value = needs.value.filter(n => n.types.some(t => types.includes(t))).map(n => n.key)
  }
}, { immediate: true, deep: true })

const recommended = ref<Recommendation | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined
watch(model, (list) => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    const query = new URLSearchParams()
    list.forEach(n => query.append('needs[]', n))
    recommended.value = (await api<{ data: Recommendation | null }>(`/public/plans/recommend?${query}`).catch(() => ({ data: null }))).data
  }, 250)
}, { immediate: true, deep: true })
onBeforeUnmount(() => clearTimeout(timer))
</script>

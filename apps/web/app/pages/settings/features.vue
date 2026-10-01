<template>
  <div class="max-w-4xl space-y-4">
    <PageHeader title="المميزات" description="شكّل السيستم على طريقة شغلك: افتح أو اقفل حاجات جوه كل قسم. التغيير بيسري على كل اللي شغالين في المحل على طول، حتى صاحب المحل." />

    <div class="sticky top-16 z-10 -mx-1 flex flex-wrap items-center gap-2 bg-(--ui-bg-muted)/90 px-1 py-2 backdrop-blur">
      <UInput
        v-model="term"
        icon="i-lucide-search"
        placeholder="دوّر على ميزة… (خصم، واتساب، ضمان)"
        class="min-w-0 flex-1"
        aria-label="دوّر على ميزة"
      >
        <template v-if="term" #trailing>
          <UButton color="neutral" variant="link" size="sm" icon="i-lucide-x" aria-label="امسح البحث" @click="term = ''" />
        </template>
      </UInput>
      <UButton
        :color="onlyChanged ? 'primary' : 'neutral'"
        :variant="onlyChanged ? 'soft' : 'outline'"
        icon="i-lucide-sliders-horizontal"
        :label="`متغيّرة (${changedCount})`"
        :aria-pressed="onlyChanged"
        :disabled="!changedCount && !onlyChanged"
        @click="onlyChanged = !onlyChanged"
      />
    </div>

    <UCard v-for="group in shown" :key="group.module" :ui="{ header: 'p-4 sm:px-5', body: 'p-0 sm:p-0' }">
      <template #header>
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="flex flex-wrap items-center gap-2 font-bold">
              {{ group.name }}
              <UBadge v-if="changedIn(group)" color="primary" variant="subtle" size="sm">
                <span class="num">{{ changedIn(group) }}</span>&nbsp;متغيّرة
              </UBadge>
            </h2>
            <p class="text-sm text-(--ui-text-muted)">
              {{ group.intro }}
            </p>
          </div>
          <UButton
            v-if="changedIn(group)"
            size="sm"
            color="neutral"
            variant="ghost"
            icon="i-lucide-rotate-ccw"
            label="رجّع الافتراضي"
            class="shrink-0"
            :loading="busy === `module:${group.module}`"
            :disabled="busy !== null"
            @click="resetGroup(group)"
          />
        </div>
      </template>

      <ul class="divide-y divide-(--ui-border)">
        <li v-for="f in group.features" :key="f.key" class="px-4 py-3 sm:px-5" :class="f.customized ? 'bg-(--app-primary-soft)/40' : ''">
          <div class="flex items-start justify-between gap-4">
            <label :for="`f-${f.key}`" class="min-w-0 cursor-pointer">
              <span class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold">
                {{ f.label }}
                <UBadge v-if="f.customized" color="primary" variant="soft" size="sm">متغيّرة</UBadge>
              </span>
              <span class="mt-0.5 block text-sm text-(--ui-text-muted)">
                {{ f.description }}
              </span>
              <span class="mt-1 block text-xs text-(--ui-text-dimmed)">
                الافتراضي: {{ f.default ? 'مفتوحة' : 'مقفولة' }}<template v-if="f.setting"> · {{ settingText(f, f.setting.default) }}</template>
                <template v-if="f.customized && f.updated_by_name"> · غيّرها {{ f.updated_by_name }}</template>
              </span>
            </label>
            <div class="flex shrink-0 items-center gap-1">
              <UButton
                v-if="f.customized"
                size="xs"
                color="neutral"
                variant="ghost"
                icon="i-lucide-rotate-ccw"
                :aria-label="`رجّع «${f.label}» للافتراضي`"
                :title="'رجّع الافتراضي'"
                :disabled="busy !== null"
                @click="reset(f)"
              />
              <USwitch
                :id="`f-${f.key}`"
                :model-value="f.enabled"
                :loading="busy === f.key"
                :disabled="busy !== null"
                :aria-label="f.label"
                @update:model-value="v => save(f, { enabled: v })"
              />
            </div>
          </div>

          <!-- The value next to a switch: only while it's on. -->
          <div v-if="f.setting && f.enabled" class="mt-2 flex flex-wrap items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2 text-sm">
            <span class="text-(--ui-text-muted)">{{ f.setting.label }}</span>
            <USelect
              v-if="f.setting.type === 'choice'"
              :model-value="String(f.value)"
              :items="(f.setting.choices ?? []).map(c => ({ label: c.label, value: c.value }))"
              size="sm"
              class="w-40"
              :disabled="busy !== null"
              :aria-label="f.setting.label"
              @update:model-value="v => save(f, { value: v })"
            />
            <template v-else>
              <UInputNumber
                v-model="drafts[f.key]"
                :min="f.setting.min"
                :max="f.setting.max"
                size="sm"
                class="w-32"
                :disabled="busy !== null"
                :aria-label="f.setting.label"
                @blur="commitNumber(f)"
                @keydown.enter="commitNumber(f)"
              />
              <span v-if="f.setting.unit">{{ f.setting.unit }}</span>
              <span class="text-xs text-(--ui-text-dimmed)">(من <span class="num">{{ f.setting.min }}</span> لـ <span class="num">{{ f.setting.max }}</span>)</span>
            </template>
          </div>
        </li>
      </ul>
    </UCard>

    <p v-if="groups.length && !shown.length" class="py-10 text-center text-(--ui-text-muted)">
      {{ onlyChanged && !term ? 'كل المميزات على الافتراضي.' : `مفيش ميزة فيها «${term}».` }}
    </p>
    <p v-if="!groups.length" class="py-10 text-center text-(--ui-text-muted)">
      مفيش مميزات تتظبط في الأقسام المفعّلة.
    </p>
  </div>
</template>

<script setup lang="ts">
interface FeatureSettingInfo {
  type: 'int' | 'choice'
  label: string
  default: number | string
  min?: number
  max?: number
  unit?: string
  choices?: { value: string, label: string }[]
}
interface FeatureInfo {
  key: string
  label: string
  description: string
  default: boolean
  enabled: boolean
  setting: FeatureSettingInfo | null
  value: number | string | null
  customized: boolean
  updated_by_name: string | null
}
interface FeatureGroup { module: string, name: string, intro: string, features: FeatureInfo[] }

definePageMeta({ ownerOnly: true })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const { data } = await useAsyncData('features', () => api<{ data: FeatureGroup[] }>('/features'))
const groups = computed(() => data.value?.data ?? [])
const busy = ref<string | null>(null)

// Search (Arabic folding like the rest of the app) and "only what differs from the default".
const term = ref('')
const onlyChanged = ref(false)
const shown = computed(() => {
  const q = normalizeSearch(term.value.trim())
  return groups.value
    .map((g) => {
      const groupHit = q !== '' && normalizeSearch(`${g.name} ${g.intro}`).includes(q)
      const features = g.features.filter(f => (!onlyChanged.value || f.customized)
        && (q === '' || groupHit || normalizeSearch(`${f.label} ${f.description}`).includes(q)))
      return { ...g, features }
    })
    .filter(g => g.features.length)
})
const changedIn = (g: FeatureGroup) => (groups.value.find(x => x.module === g.module)?.features ?? []).filter(f => f.customized).length
const changedCount = computed(() => groups.value.reduce((n, g) => n + g.features.filter(f => f.customized).length, 0))
watch(changedCount, (n) => {
  if (!n) {
    onlyChanged.value = false
  }
})

function settingText(f: FeatureInfo, value: number | string | null): string {
  if (!f.setting || value === null) {
    return ''
  }
  if (f.setting.type === 'choice') {
    return f.setting.choices?.find(c => c.value === value)?.label ?? String(value)
  }
  return `${f.setting.label} ${value} ${f.setting.unit ?? ''}`.trim()
}

// Number settings are typed, then saved on blur / Enter.
const drafts = reactive<Record<string, number | undefined>>({})
watch(groups, (gs) => {
  for (const f of gs.flatMap(g => g.features)) {
    if (f.setting?.type === 'int') {
      drafts[f.key] = Number(f.value)
    }
  }
}, { immediate: true })
function commitNumber(f: FeatureInfo) {
  const v = drafts[f.key]
  if (v === undefined || v === null || Number.isNaN(v) || v === Number(f.value)) {
    drafts[f.key] = Number(f.value)
    return
  }
  save(f, { value: v })
}

async function run(key: string, request: () => Promise<{ data: FeatureGroup[] }>, done: () => string) {
  busy.value = key
  try {
    data.value = await request()
    await store.load()
    toast.add({ color: 'success', title: done() })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    // Put a rejected number back.
    for (const f of groups.value.flatMap(g => g.features)) {
      if (f.setting?.type === 'int') {
        drafts[f.key] = Number(f.value)
      }
    }
  }
  finally {
    busy.value = null
  }
}

function save(f: FeatureInfo, body: { enabled?: boolean, value?: number | string }) {
  return run(f.key, () => api<{ data: FeatureGroup[] }>(`/features/${f.key}`, { method: 'PUT', body }), () => {
    if (body.enabled !== undefined) {
      return `${body.enabled ? 'اتفتحت' : 'اتقفلت'}: ${f.label}`
    }
    return `اتحفظ: ${settingText(f, body.value ?? null)}`
  })
}

function reset(f: FeatureInfo) {
  return run(f.key, () => api<{ data: FeatureGroup[] }>(`/features/${f.key}`, { method: 'DELETE' }), () => `رجعت للافتراضي: ${f.label}`)
}

function resetGroup(g: FeatureGroup) {
  return run(`module:${g.module}`, () => api<{ data: FeatureGroup[] }>(`/features/modules/${g.module}`, { method: 'DELETE' }), () => `مميزات «${g.name}» رجعت للافتراضي`)
}
</script>

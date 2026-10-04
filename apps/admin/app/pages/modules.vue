<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        الأقسام والمميزات
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        اللي بتغيّره هنا بيمشي على كل المحلات في نفس اللحظة. «زي الأصل» = اللي القسم معمول بيه.
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر على قسم أو ميزة" class="w-full sm:w-72" />
      <USelect v-model="show" :items="showItems" class="w-full sm:w-48" />
    </div>

    <UCard v-for="m in filtered" :key="m.key" :ui="{ body: 'space-y-4' }">
      <template #header>
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-lg font-bold">
                {{ m.name }}
              </p>
              <UBadge :color="statusColor[m.status]" variant="subtle" :label="statusLabel[m.status]" />
              <UBadge v-if="!m.optional" color="neutral" variant="outline" label="أساسي" />
            </div>
            <p class="text-sm text-(--ui-text-muted)">
              {{ m.description }}
            </p>
            <p v-if="m.optional" class="mt-1 text-xs text-(--ui-text-muted)">
              <span class="num">{{ m.shops.paid }}</span> محل مشترك · <span class="num">{{ m.shops.trial }}</span> في التجربة
              <span v-if="m.updated_by_name"> · آخر تعديل: {{ m.updated_by_name }}</span>
            </p>
          </div>
          <div class="flex gap-2">
            <UButton size="sm" variant="ghost" icon="i-lucide-pencil" label="الاسم والوصف" @click="editText(m)" />
            <UButton size="sm" variant="ghost" color="neutral" icon="i-lucide-rotate-ccw" label="زي الأصل" :loading="busy === `${m.key}:reset`" @click="reset(m)" />
          </div>
        </div>
      </template>

      <template v-if="m.optional">
        <div>
          <p class="mb-2 text-sm font-semibold">
            الحالة لكل المحلات
          </p>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <button
              v-for="s in statuses"
              :key="s.value"
              type="button"
              class="rounded-lg border p-3 text-start transition"
              :class="m.status === s.value ? 'border-(--ui-primary) bg-(--ui-primary)/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              :disabled="busy !== null"
              @click="saveModule(m, { status: s.value === m.declared.status ? null : s.value })"
            >
              <span class="flex items-center gap-1.5 font-semibold">
                <UIcon :name="s.icon" class="size-4" /> {{ s.label }}
              </span>
              <span class="mt-1 block text-xs text-(--ui-text-muted)">{{ s.hint }}</span>
            </button>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <USwitch
            :model-value="m.trial_allowed"
            label="فيه تجربة ببلاش"
            description="المحل يقدر يجرّبه قبل ما يدفع."
            :disabled="busy !== null || m.status === 'free'"
            @update:model-value="v => saveModule(m, { trial_allowed: v ? null : false })"
          />
          <USwitch
            :model-value="m.auto_trial"
            label="يبدأ تجربة مع التسجيل"
            description="للمحلات اللي من نوعه وقت ما تسجّل."
            :disabled="busy !== null || !m.trial_allowed || m.status === 'free'"
            @update:model-value="v => saveModule(m, { auto_trial: v === m.declared.auto_trial ? null : v })"
          />
          <UFormField label="مدة التجربة (يوم)">
            <div class="flex gap-2">
              <UInput v-model="days[m.key]" type="number" min="1" max="90" dir="ltr" class="w-24" :disabled="!m.trial_allowed || m.status === 'free'" />
              <UButton
                size="sm"
                variant="soft"
                label="احفظ"
                :disabled="Number(days[m.key]) === m.trial_days || !Number(days[m.key])"
                @click="saveModule(m, { trial_days: Number(days[m.key]) === 14 ? null : Number(days[m.key]) })"
              />
            </div>
          </UFormField>
        </div>

        <div>
          <p class="mb-2 text-sm font-semibold">
            بيظهر لأنهي محلات
            <span class="font-normal text-(--ui-text-muted)">(ولا نوع = كل المحلات)</span>
          </p>
          <div class="flex flex-wrap gap-2">
            <UButton
              v-for="(label, type) in shopTypes"
              :key="type"
              size="sm"
              :variant="m.shop_types.includes(type) ? 'solid' : 'outline'"
              :color="m.shop_types.includes(type) ? 'primary' : 'neutral'"
              :label="label"
              :disabled="busy !== null"
              @click="toggleType(m, type)"
            />
          </div>
        </div>
      </template>

      <div v-if="m.features.length">
        <p class="mb-2 text-sm font-semibold">
          المميزات
        </p>
        <ul class="divide-y divide-(--ui-border) rounded-lg border border-(--ui-border)">
          <li v-for="f in m.features" :key="f.key" class="flex flex-wrap items-center justify-between gap-3 p-3">
            <div class="min-w-0 flex-1">
              <p class="font-medium">
                {{ f.label }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ f.description }}
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <USelect
                :model-value="f.mode"
                :items="featureModes"
                class="w-48"
                :disabled="busy !== null"
                @update:model-value="v => saveFeature(f, { mode: v })"
              />
              <USwitch
                v-if="f.mode === 'shop'"
                :model-value="f.default"
                :label="f.default ? 'بتبدأ مفتوحة' : 'بتبدأ مقفولة'"
                :disabled="busy !== null"
                @update:model-value="v => saveFeature(f, { default: v === f.declared_default ? null : v })"
              />
            </div>
          </li>
        </ul>
      </div>
    </UCard>

    <UModal v-model:open="textOpen" title="اسم القسم ووصفه" description="فاضي = الاسم الأصلي.">
      <template #body>
        <div class="space-y-3">
          <UFormField label="الاسم">
            <UInput v-model="text.name" class="w-full" :placeholder="text.declaredName" maxlength="80" />
          </UFormField>
          <UFormField label="الوصف">
            <UTextarea v-model="text.description" class="w-full" :rows="3" :placeholder="text.declaredDescription" maxlength="300" />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton variant="ghost" color="neutral" label="إلغاء" @click="textOpen = false" />
          <UButton label="احفظ" :loading="busy !== null" @click="saveText" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
type Status = 'live' | 'coming_soon' | 'free' | 'hidden'
type Mode = 'shop' | 'on' | 'off'

interface PlatformFeature {
  key: string
  label: string
  description: string
  default: boolean
  declared_default: boolean
  mode: Mode
}

interface PlatformModule {
  key: string
  name: string
  description: string
  declared: { name: string, description: string, status: Status, shop_types: string[], auto_trial: boolean }
  optional: boolean
  status: Status
  trial_allowed: boolean
  auto_trial: boolean
  trial_days: number
  shop_types: string[]
  shops: { paid: number, trial: number }
  updated_by_name: string | null
  features: PlatformFeature[]
}

const api = useAdminApi()
const toast = useToast()

const statuses: { value: Status, label: string, hint: string, icon: string }[] = [
  { value: 'live', label: 'شغال', hint: 'بالاشتراك أو التجربة', icon: 'i-lucide-circle-check' },
  { value: 'free', label: 'ببلاش للكل', hint: 'كل المحلات من غير اشتراك', icon: 'i-lucide-gift' },
  { value: 'coming_soon', label: 'قريباً', hint: 'ظاهر بس مقفول', icon: 'i-lucide-clock' },
  { value: 'hidden', label: 'مخفي', hint: 'مقفول ومش ظاهر خالص', icon: 'i-lucide-eye-off' },
]
const statusLabel = Object.fromEntries(statuses.map(s => [s.value, s.label])) as Record<Status, string>
const statusColor: Record<Status, 'success' | 'primary' | 'warning' | 'neutral'> = { live: 'success', free: 'primary', coming_soon: 'warning', hidden: 'neutral' }
const featureModes = [
  { label: 'المحل يختار', value: 'shop' },
  { label: 'مفتوحة لكل المحلات', value: 'on' },
  { label: 'مقفولة لكل المحلات', value: 'off' },
]
const showItems = [
  { label: 'كل الأقسام', value: 'all' },
  { label: 'الإضافية بس', value: 'optional' },
  { label: 'الأساسية بس', value: 'core' },
  { label: 'اللي اتغيّرت', value: 'changed' },
]

const { data, refresh } = await useAsyncData('admin-modules', () => api<{ data: { modules: PlatformModule[], shop_types: Record<string, string> } }>('/modules'))
// The ones with a status / trial (optional) first; core modules only carry switches.
const modules = computed(() => [...(data.value?.data.modules ?? [])].sort((a, b) => Number(b.optional) - Number(a.optional)))
const shopTypes = computed(() => data.value?.data.shop_types ?? {})

const q = ref('')
const show = ref('all')
const changed = (m: PlatformModule) => m.updated_by_name !== null || m.features.some(f => f.mode !== 'shop' || f.default !== f.declared_default)
const filtered = computed(() => {
  const needle = q.value.trim()
  return modules.value.filter(m => (show.value === 'all'
    || (show.value === 'optional' && m.optional) || (show.value === 'core' && !m.optional) || (show.value === 'changed' && changed(m)))
  && (!needle || m.name.includes(needle) || m.key.includes(needle) || m.features.some(f => f.label.includes(needle))))
})

const days = reactive<Record<string, number | string>>({})
watchEffect(() => {
  for (const m of modules.value) days[m.key] = m.trial_days
})

const busy = ref<string | null>(null)

async function run(tag: string, fn: () => Promise<{ data: { modules: PlatformModule[], shop_types: Record<string, string> } }>) {
  busy.value = tag
  try {
    data.value = await fn()
    toast.add({ color: 'success', title: 'اتحفظ لكل المحلات' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

function saveModule(m: PlatformModule, body: Record<string, unknown>) {
  return run(m.key, () => api(`/modules/${m.key}`, { method: 'PATCH', body }))
}

function saveFeature(f: PlatformFeature, body: Record<string, unknown>) {
  return run(f.key, () => api(`/features/${f.key}`, { method: 'PATCH', body }))
}

function toggleType(m: PlatformModule, type: string) {
  const next = m.shop_types.includes(type) ? m.shop_types.filter(t => t !== type) : [...m.shop_types, type]
  const same = next.length === m.declared.shop_types.length && next.every(t => m.declared.shop_types.includes(t))
  return saveModule(m, { shop_types: same ? null : next })
}

async function reset(m: PlatformModule) {
  busy.value = `${m.key}:reset`
  try {
    if (m.optional) {
      await api(`/modules/${m.key}`, { method: 'PATCH', body: { status: null, trial_allowed: null, auto_trial: null, trial_days: null, shop_types: null, name: null, description: null } })
    }
    else {
      await api(`/modules/${m.key}`, { method: 'PATCH', body: { name: null, description: null } })
    }
    for (const f of m.features) {
      if (f.mode !== 'shop' || f.default !== f.declared_default) {
        await api(`/features/${f.key}`, { method: 'PATCH', body: { mode: 'shop', default: null } })
      }
    }
    await refresh()
    toast.add({ color: 'success', title: `«${m.declared.name}» رجع زي الأصل` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

const textOpen = ref(false)
const text = reactive({ key: '', name: '', description: '', declaredName: '', declaredDescription: '' })
function editText(m: PlatformModule) {
  Object.assign(text, {
    key: m.key,
    name: m.name === m.declared.name ? '' : m.name,
    description: m.description === m.declared.description ? '' : m.description,
    declaredName: m.declared.name,
    declaredDescription: m.declared.description,
  })
  textOpen.value = true
}
async function saveText() {
  const m = modules.value.find(x => x.key === text.key)
  if (!m) return
  await saveModule(m, { name: text.name.trim() || null, description: text.description.trim() || null })
  textOpen.value = false
}
</script>

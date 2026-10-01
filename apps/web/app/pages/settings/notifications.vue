<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <PageHeader title="الإشعارات" description="اللي يوصلك على الموبايل أو الكمبيوتر حتى لو البرنامج مقفول. الجرس فوق بيفضل فيه كل حاجة." />

    <UCard>
      <template #header>
        <p class="font-bold">
          الجهاز ده
        </p>
      </template>
      <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
          <UIcon :name="stateInfo.icon" class="size-6 shrink-0" :class="stateInfo.class" />
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              {{ stateInfo.title }}
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ stateInfo.text }}
            </p>
          </div>
          <UButton v-if="push.state.value === 'off'" icon="i-lucide-bell-ring" label="شغّل الإشعارات" :loading="busy" @click="enable" />
          <template v-else-if="push.state.value === 'on'">
            <UButton color="neutral" variant="outline" icon="i-lucide-send" label="ابعت تجربة" :loading="testing" @click="test" />
            <UButton color="neutral" variant="ghost" icon="i-lucide-bell-off" label="اقفلها" :loading="busy" @click="disable" />
          </template>
          <UButton v-else-if="push.state.value === 'needs_install'" icon="i-lucide-download" label="نزّل التطبيق" to="/settings/app" />
        </div>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <p v-if="prefs" class="text-xs text-(--ui-text-muted)">
          الإشعارات شغالة على <span class="num">{{ prefs.devices }}</span> {{ prefs.devices === 1 ? 'جهاز' : 'أجهزة' }} باسمك.
        </p>
      </div>
    </UCard>

    <UCard v-if="prefs">
      <template #header>
        <p class="font-bold">
          يوصلك إيه
        </p>
      </template>
      <div class="divide-y divide-(--ui-border)">
        <div v-for="c in prefs.categories" :key="c.key" class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
          <div>
            <p class="font-bold">
              {{ c.label }}
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ c.description }}
            </p>
          </div>
          <USwitch :model-value="!c.muted" :aria-label="c.label" @update:model-value="(on: boolean) => toggle(c.key, on)" />
        </div>
        <p v-if="!prefs.categories.length" class="text-sm text-(--ui-text-muted)">
          مفيش إشعارات متاحة ليك لسه.
        </p>
      </div>
      <p v-if="store.isOwner && store.hasModule('owner_app')" class="mt-4 text-sm text-(--ui-text-muted)">
        حد فرق الدرج وساعة ملخص اليوم من
        <NuxtLink to="/settings/features" class="text-primary hover:underline">المميزات</NuxtLink>.
      </p>
      <p v-else-if="store.isOwner" class="mt-4 text-sm text-(--ui-text-muted)">
        تنبيهات فرق الدرج والمرتجع وملخص آخر اليوم في قسم «تطبيق المالك» من
        <NuxtLink to="/settings/modules" class="text-primary hover:underline">الأقسام</NuxtLink>.
      </p>
    </UCard>

    <UCard v-if="prefs">
      <template #header>
        <p class="font-bold">
          ساعات الهدوء
        </p>
      </template>
      <div class="space-y-3">
        <USwitch v-model="quietOn" label="متبعتليش إشعارات في الساعات دي" />
        <div v-if="quietOn" class="grid grid-cols-2 gap-3 sm:max-w-sm">
          <UFormField label="من">
            <UInput v-model="quietFrom" type="time" class="w-full" />
          </UFormField>
          <UFormField label="لحد">
            <UInput v-model="quietTo" type="time" class="w-full" />
          </UFormField>
        </div>
        <p class="text-xs text-(--ui-text-muted)">
          اللي بيوصل وقتها بيفضل في الجرس، وطلبات الموافقة المستعجلة بتوصل برضه.
        </p>
        <UButton label="احفظ" :loading="saving" @click="save" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
interface Prefs {
  categories: { key: string, label: string, description: string, muted: boolean }[]
  quiet_from: string | null
  quiet_to: string | null
  devices: number
}

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const push = usePush()

const { data, refresh } = await useAsyncData('notification-preferences', () => api<{ data: Prefs }>('/notifications/preferences'))
const prefs = computed(() => data.value?.data ?? null)
const quietOn = ref(false)
const quietFrom = ref('23:00')
const quietTo = ref('09:00')
watch(prefs, (p) => {
  quietOn.value = !!(p?.quiet_from && p.quiet_to)
  quietFrom.value = p?.quiet_from ?? '23:00'
  quietTo.value = p?.quiet_to ?? '09:00'
}, { immediate: true })

onMounted(() => push.refresh())

const stateInfo = computed(() => ({
  on: { icon: 'i-lucide-bell-ring', class: 'text-(--ui-success)', title: 'الإشعارات شغالة على الجهاز ده', text: 'هيوصلك المهم حتى لو البرنامج مقفول.' },
  off: { icon: 'i-lucide-bell', class: 'text-(--ui-text-muted)', title: 'الإشعارات مقفولة على الجهاز ده', text: 'شغّلها عشان يوصلك المهم وإنت برّه البرنامج.' },
  blocked: { icon: 'i-lucide-bell-off', class: 'text-(--ui-warning)', title: 'المتصفح مانع الإشعارات', text: 'اسمح بالإشعارات للموقع ده من إعدادات المتصفح (جنب العنوان)، وبعدين ارجع هنا.' },
  needs_install: { icon: 'i-lucide-smartphone', class: 'text-(--ui-warning)', title: 'على الآيفون لازم تنزّل التطبيق الأول', text: 'من Safari: زرار المشاركة ← «إضافة إلى الشاشة الرئيسية»، وافتحه من الأيقونة وارجع هنا.' },
  unsupported: { icon: 'i-lucide-bell-off', class: 'text-(--ui-text-muted)', title: 'المتصفح ده مش بيدعم الإشعارات', text: 'جرّب Chrome أو Edge أو Safari حديث.' },
})[push.state.value])

const busy = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)

async function enable() {
  busy.value = true
  error.value = null
  try {
    await push.enable()
    await refresh()
    toast.add({ color: 'success', title: 'الإشعارات اتشغّلت' })
  }
  catch (e) {
    error.value = e instanceof Error && !('data' in e) ? e.message : apiErrorMessage(e)
  }
  finally {
    busy.value = false
  }
}

async function disable() {
  busy.value = true
  try {
    await push.disable()
    await refresh()
  }
  finally {
    busy.value = false
  }
}

async function test() {
  testing.value = true
  try {
    const devices = await push.test()
    toast.add({ color: devices ? 'success' : 'warning', title: devices ? 'اتبعتت — المفروض توصلك دلوقتي' : 'مفيش جهاز متسجل' })
  }
  finally {
    testing.value = false
  }
}

const saving = ref(false)
async function persist(muted: string[]) {
  data.value = await api<{ data: Prefs }>('/notifications/preferences', {
    method: 'PUT',
    body: { muted, quiet_from: quietOn.value ? quietFrom.value : null, quiet_to: quietOn.value ? quietTo.value : null },
  })
}

async function toggle(key: string, on: boolean) {
  const muted = (prefs.value?.categories ?? []).filter(c => c.key === key ? !on : c.muted).map(c => c.key)
  try {
    await persist(muted)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function save() {
  saving.value = true
  try {
    await persist((prefs.value?.categories ?? []).filter(c => c.muted).map(c => c.key))
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>

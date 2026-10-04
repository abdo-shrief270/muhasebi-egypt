<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold">
        الأقسام
      </h1>
      <p class="text-(--ui-text-muted)">
        فعّل اللي محتاجه بس. أغلب الأقسام تقدر تجربها مجاناً قبل ما تضيفها لاشتراكك، والإخفاء مش بيمسح أي داتا.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 rounded-lg bg-(--ui-bg-elevated) px-4 py-3 text-sm">
      <UIcon name="i-lucide-store" class="size-5 text-(--ui-text-muted)" />
      <span>بنعرضلك الأقسام اللي تناسب نوع محلك: <b>{{ store.session?.tenant.shop_type_label }}</b></span>
      <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-pencil" label="تغيير نوع المحل" class="ms-auto" @click="openTypes" />
    </div>

    <UAlert
      v-if="needed"
      color="warning"
      variant="subtle"
      icon="i-lucide-lock"
      :title="`قسم «${needed.name}» مش مفعّل في محلك`"
      description="جرّبه أو ضيفه لاشتراكك عشان تقدر تستخدمه."
    />

    <section v-for="group in groups" :key="group.title" class="space-y-3">
      <h2 class="font-bold text-(--ui-text-muted)">
        {{ group.title }}
      </h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <UCard v-for="mod in group.items" :key="mod.key" :class="{ 'ring-2 ring-warning': mod.key === route.query.need, 'opacity-70': !mod.available }">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
              <div class="size-10 shrink-0 grid place-items-center rounded-lg bg-primary/10 text-primary">
                <UIcon :name="mod.menu[0]?.icon ?? 'i-lucide-box'" class="size-5" />
              </div>
              <div class="min-w-0">
                <p class="font-semibold">
                  {{ mod.name }}
                </p>
                <p class="text-sm text-(--ui-text-muted)">
                  {{ mod.description }}
                </p>
              </div>
            </div>
            <UBadge v-if="!mod.available" color="neutral" variant="outline" icon="i-lucide-hourglass" class="shrink-0">
              قريباً
            </UBadge>
            <UBadge v-else-if="mod.free && mod.usable" color="primary" variant="subtle" icon="i-lucide-gift" class="shrink-0">
              ببلاش
            </UBadge>
            <UBadge v-else :color="badgeColor(mod)" variant="subtle" class="shrink-0">
              {{ mod.state_label }}
            </UBadge>
          </div>

          <p v-if="mod.available && mod.state === 'trial' && mod.trial_ends_at" class="mt-3 text-xs text-(--ui-text-muted)">
            التجربة تنتهي {{ formatDate(mod.trial_ends_at) }}
          </p>

          <template v-if="mod.tier === 'optional' && mod.available" #footer>
            <div class="flex gap-2">
              <UButton v-if="mod.trial_available" size="sm" icon="i-lucide-sparkles" :label="`جرّب مجاناً ${mod.trial_days} يوم`" :loading="busy === mod.key" @click="act(mod, 'trial')" />
              <UButton v-if="mod.state === 'disabled'" size="sm" icon="i-lucide-eye" label="إظهار" :loading="busy === mod.key" @click="act(mod, 'enable')" />
              <UButton v-if="mod.usable" size="sm" color="neutral" variant="outline" icon="i-lucide-eye-off" label="إخفاء" :loading="busy === mod.key" @click="act(mod, 'disable')" />
              <p v-if="!mod.entitled && !mod.usable && !mod.trial_available" class="self-center text-xs text-(--ui-text-muted)">
                عشان تضيفه لاشتراكك كلّم خدمة العملاء.
              </p>
            </div>
          </template>
        </UCard>
      </div>
    </section>

    <UModal v-model:open="typesOpen" title="نوع المحل" description="اختار كل اللي محلك بيعمله. الأقسام اللي بتستخدمها فعلاً مش هتختفي.">
      <template #body>
        <ShopTypePicker v-model="shopTypes" modules-title="الأقسام الإضافية اللي هتظهرلك:" />
        <UAlert v-if="typesError" color="error" variant="subtle" :title="typesError" class="mt-3" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="typesOpen = false" />
          <UButton icon="i-lucide-check" label="حفظ" :disabled="!shopTypes.length" :loading="savingTypes" @click="saveTypes" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { ModuleInfo } from '~/types/api'

definePageMeta({ ownerOnly: true })

const api = useApi()
const store = useSessionStore()
const route = useRoute()
const toast = useToast()

const { data, refresh } = await useAsyncData('modules', () => api<{ data: ModuleInfo[] }>('/modules'))
const modules = computed(() => data.value?.data ?? [])
const needed = computed(() => modules.value.find(m => m.key === route.query.need && !m.usable))

const groups = computed(() => [
  { title: 'أقسام إضافية', items: modules.value.filter(m => m.tier === 'optional' && m.available) },
  { title: 'الأقسام الأساسية (في كل الاشتراكات)', items: modules.value.filter(m => m.tier === 'core') },
  { title: 'قريباً', items: modules.value.filter(m => !m.available) },
].filter(g => g.items.length))

const busy = ref<string | null>(null)

const typesOpen = ref(false)
const shopTypes = ref<string[]>([])
const savingTypes = ref(false)
const typesError = ref<string | null>(null)

function openTypes() {
  shopTypes.value = [...(store.session?.tenant.shop_types ?? [])]
  typesError.value = null
  typesOpen.value = true
}

async function saveTypes() {
  savingTypes.value = true
  typesError.value = null
  try {
    await api('/shop/types', { method: 'PUT', body: { shop_types: shopTypes.value } })
    await Promise.all([refresh(), store.load()])
    typesOpen.value = false
    toast.add({ color: 'success', title: 'اتغيّر نوع المحل' })
  }
  catch (e) {
    typesError.value = apiErrorMessage(e)
  }
  finally {
    savingTypes.value = false
  }
}

function badgeColor(mod: ModuleInfo) {
  return ({ enabled: 'success', trial: 'info', disabled: 'neutral', read_only: 'warning', not_entitled: 'neutral' } as const)[mod.state]
}

async function act(mod: ModuleInfo, action: 'trial' | 'enable' | 'disable') {
  busy.value = mod.key
  try {
    await api(`/modules/${mod.key}/${action}`, { method: 'POST' })
    await Promise.all([refresh(), store.load()])
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

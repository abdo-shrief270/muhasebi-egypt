<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold">
        الأقسام
      </h1>
      <p class="text-(--ui-text-muted)">
        فعّل اللي محتاجه بس. أي قسم تقدر تجربه مجاناً 14 يوم، والإخفاء مش بيمسح أي داتا.
      </p>
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
            <UBadge v-else :color="badgeColor(mod)" variant="subtle" class="shrink-0">
              {{ mod.state_label }}
            </UBadge>
          </div>

          <p v-if="mod.available && mod.state === 'trial' && mod.trial_ends_at" class="mt-3 text-xs text-(--ui-text-muted)">
            التجربة تنتهي {{ formatDate(mod.trial_ends_at) }}
          </p>

          <template v-if="mod.tier === 'optional' && mod.available" #footer>
            <div class="flex gap-2">
              <UButton v-if="mod.trial_available" size="sm" icon="i-lucide-sparkles" label="جرّب مجاناً" :loading="busy === mod.key" @click="act(mod, 'trial')" />
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

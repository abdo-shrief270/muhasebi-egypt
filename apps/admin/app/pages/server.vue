<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        السيرفر
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        حالة السيرفر دلوقتي، والأخطاء اللي حصلت فيه متجمعة. «اتحلت» تخفيها لحد ما تحصل تاني.
      </p>
    </div>

    <UCard v-if="health">
      <template #header>
        <div class="flex items-center justify-between gap-2">
          <p class="font-bold">
            الحالة
          </p>
          <UBadge :color="health.ok ? 'success' : 'error'" variant="subtle" :label="health.ok ? 'كله شغال' : 'فيه مشكلة'" />
        </div>
      </template>
      <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <li v-for="(c, name) in health.checks" :key="name" class="rounded-(--ui-radius) border border-(--ui-border) p-3">
          <p class="flex items-center gap-1 font-semibold">
            <UIcon :name="c.ok ? 'i-lucide-circle-check' : 'i-lucide-circle-x'" :class="c.ok ? 'text-(--ui-success)' : 'text-(--ui-error)'" />
            {{ labels[name] ?? name }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            {{ c.detail }}
          </p>
        </li>
      </ul>
      <p v-if="!health.alerts_configured" class="mt-3 text-sm text-(--ui-warning)">
        تنبيهات Telegram لسه متضبطتش: شغّل <span class="num" dir="ltr">./setup-monitoring.sh</span> على السيرفر.
      </p>
    </UCard>

    <div class="flex flex-wrap items-center gap-3">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر في الرسالة…" class="min-w-0 flex-1" />
      <USwitch v-model="showResolved" label="اعرض اللي اتحلت" />
      <p v-if="meta" class="text-sm text-(--ui-text-muted)">
        <span class="num">{{ meta.open }}</span> مفتوحة · <span class="num">{{ meta.last_24h }}</span> آخر 24 ساعة
      </p>
    </div>

    <div class="space-y-3">
      <UCard v-for="e in rows" :key="e.id" :class="e.resolved ? 'opacity-70' : ''">
        <div class="flex flex-wrap items-start gap-3">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge :color="e.resolved ? 'success' : 'error'" variant="subtle">
                {{ e.resolved ? 'اتحلت' : e.class.split('\\').pop() }}
              </UBadge>
              <span class="text-sm text-(--ui-text-muted)">
                <span class="num font-bold text-(--ui-text)">{{ e.count.toLocaleString('en-US') }}</span> مرة · آخر مرة {{ timeAgo(e.last_seen_at) }}
              </span>
            </div>
            <p class="rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2 break-words font-mono text-sm font-bold" dir="ltr">
              {{ e.message }}
            </p>
            <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
              <span class="num" dir="ltr">{{ e.file }}</span>
              <span v-if="e.context" class="num" dir="ltr">{{ e.context }}</span>
              <span>أول مرة <span class="num">{{ formatDate(e.first_seen_at, true) }}</span></span>
              <NuxtLink v-if="e.last_tenant_id" :to="`/shops/${e.last_tenant_id}`" class="text-primary">آخر محل</NuxtLink>
            </p>
            <details v-if="e.trace" class="text-xs">
              <summary class="cursor-pointer text-(--ui-text-muted)">
                الـ Trace
              </summary>
              <pre class="mt-2 max-h-64 overflow-auto rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 whitespace-pre-wrap break-all" dir="ltr">{{ e.trace }}</pre>
            </details>
          </div>
          <UButton
            size="sm"
            :color="e.resolved ? 'neutral' : 'success'"
            variant="soft"
            :icon="e.resolved ? 'i-lucide-rotate-ccw' : 'i-lucide-check'"
            :label="e.resolved ? 'رجّعها مفتوحة' : 'اتحلت'"
            :loading="busy === e.id"
            @click="resolve(e)"
          />
        </div>
      </UCard>
      <p v-if="!rows.length" class="py-12 text-center text-(--ui-text-muted)">
        مفيش أخطاء {{ showResolved ? '' : 'مفتوحة' }}.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
interface ServerError {
  id: string
  class: string
  message: string
  file: string | null
  trace: string | null
  context: string | null
  last_tenant_id: string | null
  count: number
  first_seen_at: string
  last_seen_at: string
  resolved: boolean
}
interface HealthResult { ok: boolean, alerts_configured: boolean, checks: Record<string, { ok: boolean, detail: string }> }

const api = useAdminApi()
const toast = useToast()
const labels: Record<string, string> = { database: 'قاعدة البيانات', cache: 'الكاش (Redis)', queue: 'الطوابير', scheduler: 'المهام المجدولة', disk: 'المساحة' }

const q = ref('')
const showResolved = ref(false)
const { data: healthData } = await useAsyncData('admin-health', () => api<{ data: HealthResult }>('/health').catch(e => ({ data: (e as { data?: { data?: HealthResult } }).data?.data ?? null })))
const health = computed(() => healthData.value?.data ?? null)
const { data, refresh } = await useAsyncData('admin-server-errors', () => api<{ data: ServerError[], meta: { open: number, last_24h: number } }>('/server-errors', {
  query: { resolved: showResolved.value ? 1 : 0, q: q.value || undefined },
}), { watch: [showResolved, q] })
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const busy = ref<string | null>(null)
async function resolve(e: ServerError) {
  busy.value = e.id
  try {
    await api(`/server-errors/${e.id}/resolve`, { method: 'POST', body: { resolved: !e.resolved } })
    await refresh()
  }
  catch (err) {
    toast.add({ color: 'error', title: apiErrorMessage(err) })
  }
  finally {
    busy.value = null
  }
}
</script>

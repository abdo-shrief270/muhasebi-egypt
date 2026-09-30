<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-2">
      <div>
        <h1 class="text-2xl font-extrabold">
          أخطاء الواجهة
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          أخطاء حصلت في متصفحات المحلات، متجمعة بالرسالة. «اتحلت» تخفيها لحد ما تحصل تاني.
        </p>
      </div>
      <p v-if="meta" class="text-sm text-(--ui-text-muted)">
        <span class="num">{{ meta.open_errors }}</span> مفتوحة · <span class="num">{{ meta.errors_24h }}</span> حصلت آخر 24 ساعة
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <UInput v-model="q" icon="i-lucide-search" placeholder="دوّر في الرسالة…" class="min-w-0 flex-1" />
      <USwitch v-model="showResolved" label="اعرض اللي اتحلت" />
    </div>

    <div class="space-y-3">
      <UCard v-for="g in rows" :key="g.fingerprint" :class="g.resolved ? 'opacity-70' : ''">
        <div class="flex flex-wrap items-start gap-3">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge :color="g.resolved ? 'success' : 'error'" variant="subtle">
                {{ g.resolved ? 'اتحلت' : g.kind === 'rejection' ? 'Promise' : 'Error' }}
              </UBadge>
              <span class="text-sm text-(--ui-text-muted)">
                <span class="num font-bold text-(--ui-text)">{{ g.count.toLocaleString('en-US') }}</span> مرة ·
                <span class="num">{{ g.shops.length }}</span> محل ·
                آخر مرة {{ timeAgo(g.last_seen_at) }}
              </span>
            </div>
            <p class="rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2 break-words font-mono text-sm font-bold" dir="ltr">
              {{ g.message }}
            </p>
            <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
              <span v-if="g.page">الصفحة <span class="num" dir="ltr">{{ g.page }}</span></span>
              <span v-if="g.app_version">النسخة <span class="num" dir="ltr">{{ g.app_version }}</span></span>
              <span>أول مرة <span class="num">{{ formatDate(g.first_seen_at, true) }}</span></span>
            </p>
            <div class="flex flex-wrap gap-1">
              <UButton v-for="s in g.shops" :key="s.id" :to="`/shops/${s.id}`" size="xs" color="neutral" variant="soft" :label="s.name" />
            </div>
            <details v-if="g.stack || g.source || g.user_agent" class="text-xs">
              <summary class="cursor-pointer text-(--ui-text-muted)">
                التفاصيل
              </summary>
              <pre class="mt-2 max-h-64 overflow-auto rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 whitespace-pre-wrap break-all" dir="ltr">{{ [g.source, g.stack, g.user_agent].filter(Boolean).join('\n\n') }}</pre>
            </details>
          </div>
          <UButton
            size="sm"
            :color="g.resolved ? 'neutral' : 'success'"
            variant="soft"
            :icon="g.resolved ? 'i-lucide-rotate-ccw' : 'i-lucide-check'"
            :label="g.resolved ? 'رجّعها مفتوحة' : 'اتحلت'"
            :loading="busy === g.fingerprint"
            @click="resolve(g)"
          />
        </div>
      </UCard>
      <p v-if="!rows.length" class="py-8 text-center text-(--ui-text-muted)">
        مفيش أخطاء. 🎉
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ClientErrorGroup, InboxCounts } from '~/types/api'

const api = useAdminApi()
const toast = useToast()

const q = ref('')
const debounced = ref('')
const showResolved = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debounced.value = v.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const { data, refresh } = await useAsyncData('admin-client-errors', () => api<{ data: ClientErrorGroup[], meta: InboxCounts }>('/client-errors', {
  query: { q: debounced.value || undefined, resolved: showResolved.value ? 1 : undefined },
}), { watch: [debounced, showResolved] })
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const busy = ref<string | null>(null)
async function resolve(g: ClientErrorGroup) {
  busy.value = g.fingerprint
  try {
    await api(`/client-errors/${g.fingerprint}/resolve`, { method: 'POST', body: { resolved: !g.resolved } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

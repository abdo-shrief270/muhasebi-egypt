<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-2">
      <h1 class="text-2xl font-extrabold">
        الملاحظات
      </h1>
      <p v-if="meta" class="text-sm text-(--ui-text-muted)">
        <span class="num">{{ meta.new_feedback }}</span> جديدة · <span class="num">{{ meta.total }}</span> في النتيجة
      </p>
    </div>

    <div class="grid gap-3 md:grid-cols-[1fr_180px_180px]">
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم المحل، الكود، أو الموبايل…" class="w-full" />
      <USelect v-model="status" :items="statusItems" class="w-full" aria-label="الحالة" />
      <USelect v-model="type" :items="typeItems" class="w-full" aria-label="النوع" />
    </div>

    <div class="space-y-3">
      <UCard v-for="f in rows" :key="f.id" :class="f.status === 'new' ? 'ring-1 ring-primary/40' : ''">
        <div class="flex flex-wrap items-start gap-3">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge :color="feedbackTypeColor(f.type)" variant="subtle">
                {{ f.type_label }}
              </UBadge>
              <NuxtLink v-if="f.shop" :to="`/shops/${f.shop.id}`" class="font-bold hover:underline">
                {{ f.shop.name }}
              </NuxtLink>
              <span class="text-sm text-(--ui-text-muted)">{{ f.user_name }} · <span class="num">{{ formatDate(f.created_at, true) }}</span></span>
            </div>
            <p class="whitespace-pre-line break-words">
              {{ f.message }}
            </p>
            <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
              <span v-if="f.page">الصفحة <span class="num" dir="ltr">{{ f.page }}</span></span>
              <span v-if="f.screen">الشاشة <span class="num" dir="ltr">{{ f.screen }}</span></span>
              <span v-if="f.app_version">النسخة <span class="num" dir="ltr">{{ f.app_version }}</span></span>
              <span v-if="f.user_agent" class="max-w-full truncate" dir="ltr" :title="f.user_agent">{{ browser(f.user_agent) }}</span>
            </p>
            <p v-if="f.status_changed_by" class="text-xs text-(--ui-text-dimmed)">
              «{{ f.status_label }}» — {{ f.status_changed_by }} <span class="num">{{ formatDate(f.status_changed_at, true) }}</span>
            </p>
          </div>
          <div class="flex flex-col items-end gap-2">
            <UButton v-if="f.has_screenshot" size="sm" color="neutral" variant="outline" icon="i-lucide-image" label="الصورة" :loading="loadingShot === f.id" @click="showShot(f)" />
            <div class="flex gap-1" role="group" aria-label="الحالة">
              <UButton
                v-for="s in statuses"
                :key="s.value"
                size="xs"
                :color="f.status === s.value ? feedbackStatusColor(s.value) : 'neutral'"
                :variant="f.status === s.value ? 'soft' : 'ghost'"
                :label="s.label"
                :aria-pressed="f.status === s.value"
                :loading="busy === `${f.id}:${s.value}`"
                @click="setStatus(f, s.value)"
              />
            </div>
          </div>
        </div>
      </UCard>
      <p v-if="!rows.length" class="py-8 text-center text-(--ui-text-muted)">
        مفيش ملاحظات.
      </p>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex justify-center">
      <UPagination v-model:page="page" :total="meta.total" :items-per-page="30" />
    </div>

    <UModal v-model:open="shotOpen" title="صورة الشاشة" :ui="{ content: 'sm:max-w-4xl' }">
      <template #body>
        <img v-if="shotUrl" :src="shotUrl" alt="صورة الشاشة من المستخدم" class="mx-auto max-h-[75vh]">
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { AdminFeedback, InboxCounts } from '~/types/api'

const api = useAdminApi()
const config = useRuntimeConfig()
const { token } = useAdminToken()
const toast = useToast()

const ALL = 'all'
const statuses = [
  { value: 'new', label: 'جديدة' },
  { value: 'seen', label: 'اتشافت' },
  { value: 'done', label: 'خلصت' },
] as const
const statusItems = [{ label: 'كل الحالات', value: ALL }, ...statuses.map(s => ({ label: s.label, value: s.value }))]
const typeItems = [
  { label: 'كل الأنواع', value: ALL },
  { label: 'مشكلة', value: 'problem' },
  { label: 'اقتراح', value: 'suggestion' },
  { label: 'سؤال', value: 'question' },
]

const q = ref('')
const debounced = ref('')
const status = ref<string>('new')
const type = ref<string>(ALL)
const page = ref(1)
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debounced.value = v.trim()
    page.value = 1
  }, 300)
})
watch([status, type], () => {
  page.value = 1
})
onBeforeUnmount(() => clearTimeout(timer))

type Meta = InboxCounts & { current_page: number, last_page: number, total: number }
const { data, refresh } = await useAsyncData('admin-feedback', () => api<{ data: AdminFeedback[], meta: Meta }>('/feedback', {
  query: {
    q: debounced.value || undefined,
    status: status.value === ALL ? undefined : status.value,
    type: type.value === ALL ? undefined : type.value,
    page: page.value,
  },
}), { watch: [debounced, status, type, page] })
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const busy = ref<string | null>(null)
async function setStatus(f: AdminFeedback, value: AdminFeedback['status']) {
  if (f.status === value) {
    return
  }
  busy.value = `${f.id}:${value}`
  try {
    const res = await api<{ data: AdminFeedback }>(`/feedback/${f.id}`, { method: 'PATCH', body: { status: value } })
    Object.assign(f, res.data)
    // Leaves the filtered list on the next refresh; the counts change now.
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

/** "Chrome 129 · Android" from a user agent, enough to reproduce. */
function browser(ua: string): string {
  const name = ua.match(/(Edg|OPR|SamsungBrowser|Chrome|Firefox|Version)\/(\d+)/)
  const os = ua.match(/Android [\d.]+|iPhone OS [\d_]+|Windows NT [\d.]+|Mac OS X [\d_]+|Linux/)
  const label = name ? `${name[1] === 'Version' ? 'Safari' : name[1] === 'Edg' ? 'Edge' : name[1]} ${name[2]}` : ua.slice(0, 40)
  return os ? `${label} · ${os[0].replace(/_/g, '.')}` : label
}

// The screenshot needs the admin's token, so it's fetched and shown from memory (CSP: img-src blob:).
const shotOpen = ref(false)
const shotUrl = ref<string | null>(null)
const loadingShot = ref<string | null>(null)
async function showShot(f: AdminFeedback) {
  loadingShot.value = f.id
  try {
    const blob = await $fetch<Blob>(`${config.public.apiBase}/admin/feedback/${f.id}/screenshot`, { headers: { Authorization: `Bearer ${token.value}` }, responseType: 'blob' })
    if (shotUrl.value) {
      URL.revokeObjectURL(shotUrl.value)
    }
    shotUrl.value = URL.createObjectURL(blob)
    shotOpen.value = true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    loadingShot.value = null
  }
}
onBeforeUnmount(() => shotUrl.value && URL.revokeObjectURL(shotUrl.value))
</script>

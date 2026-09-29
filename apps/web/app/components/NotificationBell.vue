<template>
  <UPopover v-model:open="open" :content="{ align: 'end', sideOffset: 8 }">
    <UChip :show="unread > 0" :text="unread > 99 ? '99+' : unread" color="error" size="2xl" inset>
      <UButton color="neutral" variant="ghost" icon="i-lucide-bell" square :aria-label="unread ? `الإشعارات (${unread} جديد)` : 'الإشعارات'" />
    </UChip>

    <template #content>
      <div class="flex max-h-[70vh] w-[min(24rem,calc(100vw-2rem))] flex-col">
        <div class="flex items-center justify-between gap-2 border-b border-(--ui-border) px-4 py-3">
          <p class="font-bold">
            الإشعارات
          </p>
          <UButton v-if="unread" size="xs" color="neutral" variant="ghost" icon="i-lucide-check-check" label="علّم الكل مقروء" :loading="markingAll" @click="readAll" />
        </div>

        <div class="flex-1 overflow-y-auto">
          <div v-if="loading && !items.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            بيحمّل…
          </div>
          <div v-else-if="!items.length" class="flex flex-col items-center gap-2 p-8 text-center text-sm text-(--ui-text-muted)">
            <UIcon name="i-lucide-bell-off" class="size-6" />
            مفيش إشعارات لسه.
          </div>
          <button
            v-for="n in items"
            :key="n.id"
            type="button"
            class="flex w-full items-start gap-3 border-b border-(--ui-border) px-4 py-3 text-start transition last:border-b-0 hover:bg-(--ui-bg-elevated)"
            :class="{ 'app-soft': !n.read }"
            @click="openItem(n)"
          >
            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-(--ui-bg-elevated)">
              <UIcon :name="n.icon || 'i-lucide-bell'" class="size-4" :class="n.read ? 'text-(--ui-text-muted)' : 'text-primary'" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm" :class="n.read ? '' : 'font-bold'">{{ n.title }}</span>
              <span v-if="n.body" class="block truncate text-xs text-(--ui-text-muted)">{{ n.body }}</span>
              <span class="block text-xs text-(--ui-text-dimmed)">{{ ago(n.created_at) }}</span>
            </span>
            <span v-if="!n.read" class="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-label="جديد" />
          </button>
        </div>

        <div v-if="page < lastPage" class="border-t border-(--ui-border) p-2 text-center">
          <UButton size="xs" color="neutral" variant="ghost" label="أقدم" :loading="loading" @click="load(page + 1)" />
        </div>
      </div>
    </template>
  </UPopover>
</template>

<script setup lang="ts">
import type { AppNotification, Paginated } from '~/types/api'

/** The top-bar bell: partner shops' activity. Polls the unread count every minute and when the window gets focus. */
const api = useApi()
const toast = useToast()

const open = ref(false)
const unread = ref(0)
const items = ref<AppNotification[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const markingAll = ref(false)

async function refreshCount() {
  if (document.visibilityState === 'hidden') {
    return
  }
  try {
    unread.value = (await api<{ data: { count: number } }>('/notifications/unread')).data.count
  }
  catch {
    // Offline or signed out: the next poll tries again.
  }
}

async function load(p = 1) {
  loading.value = true
  try {
    const res = await api<Paginated<AppNotification> & { meta: { unread: number } }>('/notifications', { query: { page: p } })
    items.value = p === 1 ? res.data : [...items.value, ...res.data]
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    unread.value = res.meta.unread
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    loading.value = false
  }
}

watch(open, (isOpen) => {
  if (isOpen) {
    load(1)
  }
})

async function openItem(n: AppNotification) {
  open.value = false
  if (!n.read) {
    n.read = true
    unread.value = Math.max(0, unread.value - 1)
    api<{ data: { count: number } }>(`/notifications/${n.id}/read`, { method: 'POST' })
      .then((res) => {
        unread.value = res.data.count
      })
      .catch(() => {})
  }
  if (n.to) {
    await navigateTo(n.to)
  }
}

async function readAll() {
  markingAll.value = true
  try {
    await api('/notifications/read-all', { method: 'POST' })
    items.value.forEach((n) => {
      n.read = true
    })
    unread.value = 0
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    markingAll.value = false
  }
}

const relative = new Intl.RelativeTimeFormat('ar-EG-u-nu-latn', { numeric: 'auto' })
function ago(iso: string): string {
  const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60000)
  if (minutes > -1) {
    return 'دلوقتي'
  }
  if (minutes > -60) {
    return relative.format(minutes, 'minute')
  }
  if (minutes > -60 * 24) {
    return relative.format(Math.round(minutes / 60), 'hour')
  }
  if (minutes > -60 * 24 * 7) {
    return relative.format(Math.round(minutes / 1440), 'day')
  }
  return formatDate(iso, true)
}

let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  refreshCount()
  timer = setInterval(refreshCount, 60_000)
  window.addEventListener('focus', refreshCount)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  window.removeEventListener('focus', refreshCount)
})
</script>

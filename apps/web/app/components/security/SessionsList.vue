<template>
  <div class="space-y-3">
    <div v-if="pending && !sessions.length" class="p-4 text-sm text-(--ui-text-muted)">
      بنحمّل…
    </div>
    <p v-else-if="!sessions.length" class="p-4 text-sm text-(--ui-text-muted)">
      {{ own ? 'مفيش أجهزة.' : 'مش داخل من أي جهاز دلوقتي.' }}
    </p>

    <ul v-else class="divide-y divide-(--ui-border)">
      <li v-for="s in sessions" :key="s.id" class="flex items-center gap-3 py-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <UIcon :name="deviceIcon(s.user_agent)" class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="flex flex-wrap items-center gap-2 font-bold">
            <span class="truncate">{{ s.device_name || describeUserAgent(s.user_agent) || 'جهاز' }}</span>
            <UBadge v-if="s.current" color="success" variant="subtle" size="sm">
              الجهاز ده
            </UBadge>
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            <span v-if="describeUserAgent(s.user_agent) && describeUserAgent(s.user_agent) !== s.device_name">{{ describeUserAgent(s.user_agent) }} · </span>
            <span v-if="s.ip_address" class="num" dir="ltr">{{ s.ip_address }}</span>
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            دخل <span class="num">{{ formatDate(s.created_at, true) }}</span>
            · آخر استخدام <span class="num">{{ s.last_used_at ? formatDate(s.last_used_at, true) : '—' }}</span>
          </p>
        </div>
        <UButton
          v-if="!s.current"
          size="sm"
          color="error"
          variant="ghost"
          icon="i-lucide-log-out"
          label="خروج"
          :loading="busy === s.id"
          @click="revoke(s)"
        />
      </li>
    </ul>

    <div v-if="othersCount > 0" class="flex justify-end">
      <UButton
        color="error"
        variant="soft"
        icon="i-lucide-log-out"
        :label="own ? 'اخرج من كل الأجهزة التانية' : 'اخرج من كل الأجهزة'"
        :loading="busy === 'all'"
        @click="revokeAll"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { DeviceSession } from '~/types/api'

/**
 * Signed-in devices of a user: `/account/sessions` (your own) or `/users/{id}/sessions` (owner).
 */
const props = defineProps<{ endpoint: string, own?: boolean }>()

const api = useApi()
const toast = useToast()

const { data, pending, refresh } = await useAsyncData(`sessions:${props.endpoint}`, () => api<{ data: DeviceSession[] }>(props.endpoint), { watch: [() => props.endpoint] })
const sessions = computed(() => data.value?.data ?? [])
const othersCount = computed(() => sessions.value.filter(s => !s.current).length)
const busy = ref<number | 'all' | null>(null)

async function revoke(s: DeviceSession) {
  busy.value = s.id
  try {
    await api(`${props.endpoint}/${s.id}`, { method: 'DELETE' })
    toast.add({ color: 'success', title: `خرج من «${s.device_name}»` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
    await refresh()
  }
}

async function revokeAll() {
  busy.value = 'all'
  try {
    const res = await api<{ data: { revoked: number } }>(props.endpoint, { method: 'DELETE' })
    toast.add({ color: 'success', title: `خرج من ${res.data.revoked} جهاز` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
    await refresh()
  }
}

defineExpose({ refresh })
</script>

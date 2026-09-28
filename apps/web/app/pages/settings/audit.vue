<template>
  <div class="space-y-6 max-w-5xl">
    <PageHeader title="سجل العمليات" description="كل العمليات المهمة: مين عمل إيه وإمتى." />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="!entries.length && status !== 'pending'" class="py-12 text-center text-(--ui-text-muted)">
        مفيش عمليات لسه.
      </div>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="entry in entries" :key="entry.id" class="flex items-start gap-3 px-6 py-3">
          <div class="mt-0.5 size-9 shrink-0 grid place-items-center rounded-full bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <UIcon :name="iconFor(entry.action)" class="size-4" />
          </div>
          <div class="min-w-0 flex-1">
            <p>
              <span class="font-bold">{{ entry.user_name }}</span> {{ entry.description }}
            </p>
            <p class="text-xs text-(--ui-text-muted)">
              {{ formatDate(entry.created_at, true) }}<template v-if="entry.ip">
                · <span class="num">{{ entry.ip }}</span>
              </template>
            </p>
          </div>
        </li>
      </ul>
      <div v-if="nextCursor" class="border-t border-(--ui-border) p-3 text-center">
        <UButton color="neutral" variant="ghost" label="أقدم" :loading="loadingMore" @click="loadMore" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { AuditEntry } from '~/types/api'

definePageMeta({ permission: 'audit.view' })

interface Page { data: AuditEntry[], meta: { next_cursor: string | null } }

const api = useApi()
const entries = ref<AuditEntry[]>([])
const nextCursor = ref<string | null>(null)
const loadingMore = ref(false)

const { data, status } = await useAsyncData('audit-log', () => api<Page>('/audit-log'))
watchEffect(() => {
  entries.value = data.value?.data ?? []
  nextCursor.value = data.value?.meta.next_cursor ?? null
})

async function loadMore() {
  loadingMore.value = true
  try {
    const page = await api<Page>('/audit-log', { query: { cursor: nextCursor.value } })
    entries.value.push(...page.data)
    nextCursor.value = page.meta.next_cursor
  }
  finally {
    loadingMore.value = false
  }
}

function iconFor(action: string): string {
  const prefix = action.split('.')[0]
  return ({ users: 'i-lucide-user', roles: 'i-lucide-shield-check', branches: 'i-lucide-store', modules: 'i-lucide-blocks', shop_orders: 'i-lucide-handshake', shop: 'i-lucide-sparkles' } as Record<string, string>)[prefix ?? ''] ?? 'i-lucide-history'
}
</script>

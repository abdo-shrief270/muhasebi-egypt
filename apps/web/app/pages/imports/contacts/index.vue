<template>
  <div class="space-y-6">
    <PageHeader title="جهات الاستيراد" description="المصانع والتجار والوسطاء وشركات الشحن والمخلّصين، وحساب كل واحد.">
      <UButton to="/imports" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الاستيراد" />
      <UButton v-if="canManage" icon="i-lucide-plus" label="جهة جديدة" @click="adding = true" />
    </PageHeader>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="tab in [{ value: 'all', label: 'الكل' }, ...IMPORT_CONTACT_TYPES]"
        :key="tab.value"
        type="button"
        class="h-10 rounded-full px-4 font-semibold transition"
        :class="type === tab.value ? 'bg-primary text-white' : 'bg-(--ui-bg-elevated) hover:bg-(--ui-border)'"
        @click="type = tab.value"
      >
        {{ tab.label }}
      </button>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div v-if="!contacts.length" class="py-16 text-center text-(--ui-text-muted)">
        مفيش جهات لسه.
      </div>
      <ul v-else class="divide-y divide-(--ui-border)">
        <li v-for="c in contacts" :key="c.id">
          <NuxtLink :to="`/imports/contacts/${c.id}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-(--ui-bg-muted)" :class="c.is_active ? '' : 'opacity-60'">
            <div class="min-w-0 flex-1">
              <p class="font-bold">
                {{ c.name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                {{ c.type_label }}<template v-if="c.country || c.city">
                  · {{ [c.city, c.country].filter(Boolean).join('، ') }}
                </template>
              </p>
            </div>
            <span class="num font-bold" :class="c.balance > 0 ? 'text-(--ui-error)' : c.balance < 0 ? 'text-(--ui-success)' : 'text-(--ui-text-muted)'">
              {{ importBalanceLabel(c.balance) }}
            </span>
          </NuxtLink>
        </li>
      </ul>
    </UCard>

    <ImportsContactModal v-model:open="adding" @saved="c => navigateTo(`/imports/contacts/${c.id}`)" />
  </div>
</template>

<script setup lang="ts">
import type { ImportContact } from '~/types/api'

definePageMeta({ permission: 'imports.view', module: 'imports' })

const api = useApi()
const store = useSessionStore()
const canManage = computed(() => store.can('imports.manage'))
const type = ref<string>('all')
const adding = ref(false)

const { data } = await useAsyncData('import-contacts', () => api<{ data: ImportContact[] }>('/imports/contacts', { query: { type: type.value === 'all' ? undefined : type.value } }), { watch: [type] })
const contacts = computed(() => data.value?.data ?? [])
</script>

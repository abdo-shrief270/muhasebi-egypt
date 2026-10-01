<template>
  <div class="space-y-6">
    <PageHeader title="قوالب الرسائل" description="نص رسايل WhatsApp اللي بتتبعت للعملاء. دوس على متغير عشان تحطه في مكان المؤشر؛ السطر اللي متغيره فاضي بيتشال لوحده." />

    <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
      <nav class="space-y-4">
        <div v-for="group in groups" :key="group.key">
          <p class="mb-1 text-xs font-bold text-(--ui-text-muted)">
            {{ group.label }}
          </p>
          <button
            v-for="t in group.items"
            :key="t.key"
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-(--ui-radius) px-3 py-2 text-start text-sm"
            :class="t.key === selectedKey ? 'bg-(--ui-bg-elevated) font-bold' : 'hover:bg-(--ui-bg-elevated)'"
            @click="select(t.key)"
          >
            {{ t.label }}
            <UBadge v-if="t.feature && !store.hasFeature(t.feature)" size="sm" color="neutral" variant="subtle">
              مقفولة
            </UBadge>
            <UBadge v-else-if="t.customized" size="sm" color="primary" variant="subtle">
              معدّل
            </UBadge>
          </button>
        </div>
      </nav>

      <UCard v-if="selected">
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="font-bold">
                {{ selected.label }}
              </h2>
              <p v-if="selected.feature && !store.hasFeature(selected.feature)" class="text-xs text-(--ui-text-muted)">
                الرسالة دي مقفولة من <NuxtLink v-if="store.isOwner" to="/settings/features" class="font-bold text-primary">المميزات</NuxtLink><template v-else>المميزات</template>، ومش هتظهر لحد ما تتفتح.
              </p>
              <p v-if="selected.customized && selected.updated_by_name" class="text-xs text-(--ui-text-muted)">
                آخر تعديل: {{ selected.updated_by_name }}
              </p>
            </div>
            <div class="flex gap-2">
              <UButton v-if="selected.customized" color="neutral" variant="ghost" icon="i-lucide-rotate-ccw" label="رجّع النص الأصلي" :loading="resetting" @click="reset" />
              <UButton icon="i-lucide-check" label="حفظ" :disabled="!dirty || !draft.trim()" :loading="saving" @click="save" />
            </div>
          </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-2">
          <div class="space-y-3">
            <UTextarea ref="textareaRef" v-model="draft" :rows="10" autoresize class="w-full" :maxlength="1000" aria-label="نص الرسالة" />
            <div class="flex flex-wrap gap-1.5">
              <UButton
                v-for="v in selected.variables"
                :key="v.name"
                size="xs"
                color="neutral"
                variant="outline"
                :label="v.label"
                @click="insert(v.name)"
              />
            </div>
            <UAlert v-if="error" color="error" variant="subtle" :title="error" />
          </div>
          <div>
            <p class="mb-2 text-sm font-bold text-(--ui-text-muted)">
              شكل الرسالة
            </p>
            <div class="whitespace-pre-line rounded-[calc(var(--ui-radius)*1.5)] bg-(--ui-bg-muted) p-4 text-sm leading-relaxed">
              {{ preview }}
            </div>
          </div>
        </div>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { MessageTemplate } from '~/composables/useMessages'

definePageMeta({ permission: 'messages.templates' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()
const { templates, load } = useMessages()
await load(true)

const groups = computed(() => {
  const byGroup = new Map<string, { key: string, label: string, items: MessageTemplate[] }>()
  for (const t of templates.value ?? []) {
    if (!byGroup.has(t.group)) {
      byGroup.set(t.group, { key: t.group, label: t.group_label, items: [] })
    }
    byGroup.get(t.group)!.items.push(t)
  }
  return [...byGroup.values()]
})

const selectedKey = ref(templates.value?.[0]?.key ?? '')
const selected = computed(() => templates.value?.find(t => t.key === selectedKey.value))
const draft = ref(selected.value?.body ?? '')
const dirty = computed(() => draft.value !== selected.value?.body)
const error = ref<string | null>(null)
const saving = ref(false)
const resetting = ref(false)

function select(key: string) {
  selectedKey.value = key
  draft.value = selected.value?.body ?? ''
  error.value = null
}

const sample: Record<string, string> = {
  customer: 'محمد أحمد',
  device: 'iPhone 13 Pro',
  ticket: 'RP-00042',
  link: 'https://…/t/abc123',
  expected: formatDate(new Date(Date.now() + 86400000).toISOString(), true),
  faults: 'كسر شاشة، مش بيشحن',
  cost: formatMoney(150000),
  due: formatMoney(120000),
  warranty: formatDate(new Date(Date.now() + 90 * 86400000).toISOString()),
  invoice: 'INV-000128',
  total: formatMoney(45000),
  balance: formatMoney(75000),
}
const preview = computed(() => renderTemplate(draft.value, { ...sample, shop: store.session?.tenant.name ?? 'المحل' }))

const textareaRef = ref<{ textareaRef?: HTMLTextAreaElement } | null>(null)
function insert(name: string) {
  const el = textareaRef.value?.textareaRef
  const token = `{${name}}`
  if (!el) {
    draft.value += token
    return
  }
  const start = el.selectionStart ?? draft.value.length
  const end = el.selectionEnd ?? start
  draft.value = draft.value.slice(0, start) + token + draft.value.slice(end)
  nextTick(() => {
    el.focus()
    el.setSelectionRange(start + token.length, start + token.length)
  })
}

async function save() {
  saving.value = true
  error.value = null
  try {
    templates.value = (await api<{ data: MessageTemplate[] }>(`/messages/templates/${selectedKey.value}`, { method: 'PUT', body: { body: draft.value } })).data
    toast.add({ color: 'success', title: 'اتحفظ القالب' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}

async function reset() {
  resetting.value = true
  try {
    templates.value = (await api<{ data: MessageTemplate[] }>(`/messages/templates/${selectedKey.value}`, { method: 'DELETE' })).data
    draft.value = selected.value?.body ?? ''
    toast.add({ color: 'success', title: 'رجع النص الأصلي' })
  }
  finally {
    resetting.value = false
  }
}
</script>

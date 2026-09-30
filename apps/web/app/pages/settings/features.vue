<template>
  <div class="max-w-4xl space-y-6">
    <PageHeader title="المميزات" description="افتح أو اقفل حاجات صغيرة جوه كل قسم على حسب طريقة شغلك. التغيير بيسري على الكل على طول." />

    <UCard v-for="group in groups" :key="group.module">
      <template #header>
        <h2 class="font-bold">
          {{ group.name }}
        </h2>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="f in group.features" :key="f.key" class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
          <div class="min-w-0">
            <p class="font-semibold">
              {{ f.label }}
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ f.description }}
            </p>
          </div>
          <USwitch
            :model-value="f.enabled"
            :loading="busy === f.key"
            :disabled="busy !== null"
            :aria-label="f.label"
            @update:model-value="v => toggle(f.key, v)"
          />
        </li>
      </ul>
    </UCard>
    <p v-if="!groups.length" class="py-10 text-center text-(--ui-text-muted)">
      مفيش مميزات تتظبط في الأقسام المفعّلة.
    </p>
  </div>
</template>

<script setup lang="ts">
interface FeatureGroup { module: string, name: string, features: { key: string, label: string, description: string, default: boolean, enabled: boolean }[] }

definePageMeta({ ownerOnly: true })

const api = useApi()
const store = useSessionStore()
const toast = useToast()
const { data } = await useAsyncData('features', () => api<{ data: FeatureGroup[] }>('/features'))
const groups = computed(() => data.value?.data ?? [])
const busy = ref<string | null>(null)

async function toggle(key: string, enabled: boolean) {
  busy.value = key
  try {
    data.value = await api<{ data: FeatureGroup[] }>(`/features/${key}`, { method: 'PUT', body: { enabled } })
    await store.load()
    const f = groups.value.flatMap(g => g.features).find(x => x.key === key)
    toast.add({ color: 'success', title: `${enabled ? 'اتفتحت' : 'اتقفلت'}: ${f?.label ?? ''}` })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
</script>

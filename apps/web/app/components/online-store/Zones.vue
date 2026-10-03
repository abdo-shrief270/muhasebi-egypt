<template>
  <div class="space-y-3">
    <h3 class="font-semibold">
      مناطق التوصيل
    </h3>
    <p v-if="!zones.length" class="text-sm text-(--ui-text-muted)">
      ضيف المناطق اللي بتوصّلها ومصاريف كل واحدة. الزبون بيختار منطقته وهو بيطلب.
    </p>
    <ul v-else class="divide-y divide-(--ui-border) rounded-(--ui-radius) border border-(--ui-border)">
      <li v-for="z in zones" :key="z.id" class="flex flex-wrap items-center gap-2 px-3 py-2">
        <UInput v-model="edits[z.id]!.name" class="min-w-40 flex-1" maxlength="80" :aria-label="`اسم المنطقة ${z.name}`" @change="update(z)" />
        <UInput v-model="edits[z.id]!.fee" type="number" min="0" step="any" class="w-28" :aria-label="`مصاريف ${z.name}`" @change="update(z)">
          <template #trailing>
            <span class="text-xs text-(--ui-text-muted)">ج</span>
          </template>
        </UInput>
        <USwitch :model-value="z.is_active" :aria-label="`${z.name} شغالة`" @update:model-value="v => update(z, { is_active: v })" />
        <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`شيل ${z.name}`" @click="remove(z)" />
      </li>
    </ul>
    <form class="flex flex-wrap items-end gap-2" @submit.prevent="add">
      <UFormField label="منطقة جديدة" class="min-w-40 flex-1">
        <UInput v-model="name" class="w-full" maxlength="80" placeholder="مدينة نصر" />
      </UFormField>
      <UFormField label="المصاريف (ج)" class="w-28">
        <UInput v-model="fee" type="number" min="0" step="any" class="w-full" placeholder="30" />
      </UFormField>
      <UButton type="submit" icon="i-lucide-plus" label="ضيف" :loading="adding" :disabled="name.trim().length < 2 || fee === ''" />
    </form>
  </div>
</template>

<script setup lang="ts">
import type { DeliveryZone } from '~/types/api'

/** The store's delivery zones and fees (online_store.manage); saved as they're edited. */
const api = useApi()
const toast = useToast()

const { data } = await useAsyncData('online-store-zones', () => api<{ data: DeliveryZone[] }>('/online-store/zones'))
const zones = ref<DeliveryZone[]>(data.value?.data ?? [])
const edits = reactive<Record<string, { name: string, fee: string }>>({})
watch(zones, (list) => {
  for (const z of list) {
    edits[z.id] = { name: z.name, fee: String(z.fee / 100) }
  }
}, { immediate: true })

const name = ref('')
const fee = ref('')
const adding = ref(false)

async function add() {
  adding.value = true
  try {
    zones.value = (await api<{ data: DeliveryZone[] }>('/online-store/zones', { method: 'POST', body: { name: name.value.trim(), fee: toPiasters(fee.value) ?? 0 } })).data
    name.value = ''
    fee.value = ''
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    adding.value = false
  }
}

async function update(z: DeliveryZone, extra: Partial<DeliveryZone> = {}) {
  const edit = edits[z.id]
  if (!edit) {
    return
  }
  try {
    zones.value = (await api<{ data: DeliveryZone[] }>(`/online-store/zones/${z.id}`, {
      method: 'PATCH',
      body: { name: edit.name.trim(), fee: toPiasters(edit.fee) ?? 0, ...extra },
    })).data
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    edits[z.id] = { name: z.name, fee: String(z.fee / 100) }
  }
}

async function remove(z: DeliveryZone) {
  try {
    await api(`/online-store/zones/${z.id}`, { method: 'DELETE' })
    zones.value = zones.value.filter(x => x.id !== z.id)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

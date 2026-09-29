<template>
  <UModal v-model:open="open" title="ابعته لمحل شريك" description="الجهاز هيروح للمحل الشريك كـ «شغل صيانة»، وتتابع حالته وحسابه من هنا.">
    <template #body>
      <form id="outsource-form" class="space-y-4" @submit.prevent="save">
        <UFormField label="المحل" required>
          <USelect v-model="partnerId" :items="partnerItems" placeholder="اختار المحل الشريك" class="w-full" />
        </UFormField>
        <p v-if="!partnerItems.length && status !== 'pending'" class="text-sm text-(--ui-text-muted)">
          مفيش شركاء لسه.
          <ULink to="/shop-orders/partners" class="font-bold text-primary">ضيف محل شريك</ULink> بالكود بتاعه.
        </p>
        <UFormField label="ملاحظة للمحل" hint="اختياري">
          <UInput v-model="note" placeholder="مثلاً: غالباً السوكيت، العميل مستعجل" class="w-full" />
        </UFormField>
        <UFormField label="محتاجه يوم" hint="اختياري">
          <UInput v-model="neededBy" type="date" class="w-full" />
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="outsource-form" icon="i-lucide-send" label="ابعت" :disabled="!partnerId" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { RepairTicket } from '~/types/api'

interface Connection { id: string, status: string, shop: { id: string, name: string, code: string } | null }

const props = defineProps<{ ticket: RepairTicket }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [ticket: RepairTicket] }>()

const api = useApi()
const toast = useToast()
const { data, status, refresh } = await useAsyncData('outsource-partners', () => api<{ data: Connection[] }>('/shop-connections'), { immediate: false })
const partnerItems = computed(() => (data.value?.data ?? [])
  .filter(c => c.status === 'accepted' && c.shop)
  .map(c => ({ label: `${c.shop!.name} (${c.shop!.code})`, value: c.shop!.id })))

const partnerId = ref<string | undefined>()
const note = ref('')
const neededBy = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (isOpen) {
    refresh()
    partnerId.value = undefined
    note.value = ''
    neededBy.value = ''
    error.value = null
  }
})
watch(partnerItems, (items) => {
  if (!partnerId.value && items.length === 1) {
    partnerId.value = items[0]!.value
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${props.ticket.id}/outsource`, {
      method: 'POST',
      body: { partner_tenant_id: partnerId.value, note: note.value || null, needed_by: neededBy.value || null },
    })
    toast.add({ color: 'success', title: `اتبعت لـ «${res.data.outsourced?.shop}»`, description: `طلب ${res.data.outsourced?.reference}` })
    open.value = false
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

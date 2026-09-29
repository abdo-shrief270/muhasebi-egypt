<template>
  <UCard>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0 space-y-1">
        <NuxtLink v-if="payment.shop" :to="`/admin/shops/${payment.tenant_id}`" class="text-lg font-extrabold hover:underline">
          {{ payment.shop.name }}
        </NuxtLink>
        <p class="text-sm text-(--ui-text-muted)">
          <span class="num" dir="ltr">{{ payment.shop?.code }}</span> ·
          {{ payment.shop?.owner_name }} <span class="num" dir="ltr">{{ localPhone(payment.shop?.owner_phone) }}</span>
        </p>
        <p class="text-sm">
          باقة <b>{{ payment.plan_name }}</b> · {{ payment.cycle === 'yearly' ? 'سنوي' : 'شهري' }}
          <span v-if="payment.modules.length"> + {{ payment.modules.map(m => m.name).join('، ') }}</span>
        </p>
        <p class="text-sm">
          رقم العملية <b class="num" dir="ltr">{{ payment.reference }}</b>
          <span v-if="payment.sender_name"> · {{ payment.sender_name }}</span>
          <span v-if="payment.sender_phone" class="num" dir="ltr"> {{ payment.sender_phone }}</span>
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          {{ formatDate(payment.created_at, true) }} · {{ payment.requested_by_name }}
        </p>
        <p v-if="payment.status === 'rejected'" class="text-sm text-(--ui-error)">
          اترفض: {{ payment.rejection_reason }}
        </p>
        <p v-else-if="payment.status === 'approved'" class="text-sm text-(--ui-success)">
          اتقبل — {{ payment.reviewed_by_name }} {{ payment.reviewed_at ? formatDate(payment.reviewed_at, true) : '' }}
        </p>
      </div>
      <div class="space-y-2 text-end">
        <p class="num text-2xl font-extrabold">
          {{ formatMoney(payment.amount) }}
        </p>
        <UButton v-if="payment.has_proof" size="sm" color="neutral" variant="outline" icon="i-lucide-image" label="صورة التحويل" :loading="loadingProof" @click="showProof" />
      </div>
    </div>

    <template v-if="payment.status === 'pending'" #footer>
      <div class="flex flex-wrap items-end gap-2">
        <UFormField label="المبلغ اللي وصل" hint="لو مختلف" class="w-40">
          <UInput v-model="amount" type="number" min="0" step="any" dir="ltr" :placeholder="String(payment.amount / 100)" />
        </UFormField>
        <UButton color="success" icon="i-lucide-check" label="تأكيد وتفعيل" :loading="busy === 'approve'" @click="approve" />
        <UInput v-model="reason" placeholder="سبب الرفض" class="min-w-48 flex-1" />
        <UButton color="error" variant="soft" icon="i-lucide-x" label="رفض" :disabled="!reason.trim()" :loading="busy === 'reject'" @click="reject" />
      </div>
    </template>

    <UModal v-model:open="proofOpen" title="صورة التحويل" :ui="{ content: 'sm:max-w-3xl' }">
      <template #body>
        <img v-if="proofUrl && !proofIsPdf" :src="proofUrl" alt="صورة التحويل" class="mx-auto max-h-[70vh]">
        <iframe v-else-if="proofUrl" :src="proofUrl" class="h-[70vh] w-full" title="صورة التحويل" />
      </template>
    </UModal>
  </UCard>
</template>

<script setup lang="ts">
import type { AdminPayment } from '~/types/api'

const props = defineProps<{ payment: AdminPayment }>()
const emit = defineEmits<{ changed: [] }>()

const api = useAdminApi()
const config = useRuntimeConfig()
const { token } = useAdminToken()
const toast = useToast()
const amount = ref('')
const reason = ref('')
const busy = ref<'approve' | 'reject' | null>(null)

async function approve() {
  busy.value = 'approve'
  try {
    await api(`/payments/${props.payment.id}/approve`, { method: 'POST', body: { amount: amount.value ? toPiasters(amount.value) : undefined } })
    toast.add({ color: 'success', title: `اتفعّل اشتراك «${props.payment.shop?.name}»` })
    emit('changed')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

async function reject() {
  busy.value = 'reject'
  try {
    await api(`/payments/${props.payment.id}/reject`, { method: 'POST', body: { reason: reason.value } })
    emit('changed')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

// The proof needs the admin's token, so it's fetched and shown from memory.
const proofOpen = ref(false)
const proofUrl = ref<string | null>(null)
const proofIsPdf = ref(false)
const loadingProof = ref(false)
async function showProof() {
  loadingProof.value = true
  try {
    const blob = await $fetch<Blob>(`${config.public.apiBase}/admin/payments/${props.payment.id}/proof`, { headers: { Authorization: `Bearer ${token.value}` }, responseType: 'blob' })
    if (proofUrl.value) {
      URL.revokeObjectURL(proofUrl.value)
    }
    proofIsPdf.value = blob.type === 'application/pdf'
    proofUrl.value = URL.createObjectURL(blob)
    proofOpen.value = true
  }
  finally {
    loadingProof.value = false
  }
}
onBeforeUnmount(() => proofUrl.value && URL.revokeObjectURL(proofUrl.value))
</script>

<template>
  <div class="text-sm">
    <p v-if="sent" class="rounded-xl bg-mint px-3 py-2" role="status">
      شكراً، البلاغ وصلنا وهنراجعه.
    </p>
    <button v-else-if="!open" type="button" class="text-muted underline" @click="open = true">
      في حاجة غلط في العرض ده؟
    </button>
    <form v-else class="card space-y-3 p-4" @submit.prevent="send">
      <fieldset class="space-y-1.5">
        <legend class="mb-1 font-bold">
          إيه المشكلة؟
        </legend>
        <label v-for="(label, key) in REASONS" :key="key" class="flex items-center gap-2">
          <input v-model="reason" type="radio" name="reason" :value="key" class="accent-teal">
          {{ label }}
        </label>
      </fieldset>
      <textarea v-model="note" rows="2" maxlength="500" placeholder="تفاصيل (اختياري)" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" aria-label="تفاصيل" />
      <input v-model="contact" type="tel" inputmode="tel" maxlength="30" placeholder="رقمك لو حابب نكلمك (اختياري)" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" aria-label="رقمك">
      <!-- For bots only. -->
      <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
      <p v-if="error" class="text-warn" role="alert">
        {{ error }}
      </p>
      <div class="flex gap-2">
        <button type="submit" class="btn-ink" :disabled="!reason || sending">
          {{ sending ? 'بيتبعت…' : 'ابعت البلاغ' }}
        </button>
        <button type="button" class="btn-line" @click="open = false">
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ offerId: string }>()

const REASONS: Record<string, string> = {
  wrong_price: 'السعر غلط',
  not_available: 'مش موجود في المحل',
  fake: 'شكله مضروب / مش أصلي',
  offensive: 'كلام أو صور مش لايقة',
  other: 'حاجة تانية',
}

const open = ref(false)
const reason = ref('')
const note = ref('')
const contact = ref('')
const website = ref('')
const sending = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)

async function send() {
  sending.value = true
  error.value = null
  try {
    await $fetch('/api/market/reports', { method: 'POST', body: { offer_id: props.offerId, reason: reason.value, note: note.value || null, contact: contact.value || null, website: website.value || null } })
    sent.value = true
  }
  catch (e) {
    error.value = (e as { data?: { message?: string } }).data?.message ?? 'ما اتبعتش، جرّب كمان شوية.'
  }
  finally {
    sending.value = false
  }
}
</script>

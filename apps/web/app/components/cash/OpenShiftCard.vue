<template>
  <UCard class="mx-auto w-full max-w-md">
    <form class="space-y-4" @submit.prevent="openShift">
      <div class="flex items-center gap-3">
        <span class="grid size-11 place-items-center rounded-lg bg-(--app-primary-soft) text-(--app-primary-strong)">
          <UIcon name="i-lucide-wallet" class="size-6" />
        </span>
        <div>
          <p class="text-lg font-bold">
            افتح ورديتك
          </p>
          <p class="text-sm text-(--ui-text-muted)">
            {{ hint ?? 'البيع والتحصيل بيتسجلوا على درجك لحد ما تقفل الوردية.' }}
          </p>
        </div>
      </div>
      <UFormField label="الكاش اللي في الدرج دلوقتي" hint="بالجنيه">
        <UInput v-model="openingCash" type="number" min="0" step="any" inputmode="decimal" dir="ltr" size="lg" class="w-full" autofocus placeholder="0" />
      </UFormField>
      <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      <UButton type="submit" block size="lg" icon="i-lucide-play" label="افتح الوردية" :loading="saving" />
    </form>
  </UCard>
</template>

<script setup lang="ts">
import type { CashShift } from '~/types/api'

defineProps<{ hint?: string }>()
const emit = defineEmits<{ opened: [shift: CashShift] }>()

const api = useApi()
const openingCash = ref('')
const saving = ref(false)
const error = ref<string | null>(null)

async function openShift() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: CashShift }>('/cash/shifts', { method: 'POST', body: { opening_cash: toPiasters(openingCash.value) ?? 0 } })
    emit('opened', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

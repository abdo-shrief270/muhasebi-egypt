<template>
  <USlideover v-model:open="panelOpen" title="فواتير مستنية" description="فواتير اتعملت والنت فاصل. بتتسجل لوحدها بالترتيب أول ما النت يرجع." :ui="{ content: 'max-w-md' }">
    <template #body>
      <div class="space-y-3">
        <UAlert
          v-if="!online"
          color="warning"
          variant="subtle"
          icon="i-lucide-wifi-off"
          title="النت فاصل"
          description="الفواتير متخزنة على الجهاز ده. متمسحش بيانات المتصفح لحد ما تتسجل."
        />
        <UAlert
          v-if="failed.length"
          color="error"
          variant="subtle"
          icon="i-lucide-cloud-alert"
          :title="`${failed.length} فاتورة السيرفر رفضها`"
          description="اقرا السبب، صلّحه (مثلاً افتح وردية) ودوس «حاول تاني»، أو امسحها لو مش هتتسجل."
        />

        <ul v-if="entries.length" class="divide-y divide-(--ui-border) rounded-(--ui-radius) border border-(--ui-border)">
          <li v-for="e in entries" :key="e.id" class="space-y-2 p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-bold">
                  {{ e.receipt.reference }}
                  <span class="text-xs font-normal text-(--ui-text-muted)">· {{ e.lines.length }} صنف</span>
                </p>
                <p class="text-xs text-(--ui-text-muted) num">
                  {{ formatDate(e.created_at, true) }}<template v-if="e.branch_id !== currentBranchId">
                    · {{ branchName(e.branch_id) }}
                  </template>
                </p>
              </div>
              <div class="text-end">
                <p class="font-extrabold num">
                  {{ formatMoney(e.receipt.total) }}
                </p>
                <UBadge size="sm" variant="subtle" :color="e.status === 'failed' ? 'error' : 'warning'">
                  {{ e.status === 'failed' ? 'اترفضت' : 'مستنية' }}
                </UBadge>
              </div>
            </div>
            <p v-if="e.error" class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2 text-sm text-error">
              {{ e.error }}
            </p>
            <div class="flex flex-wrap gap-2">
              <UButton v-if="e.status === 'failed'" size="xs" icon="i-lucide-refresh-cw" label="حاول تاني" :disabled="!online" :loading="syncing" @click="retry(e.id)" />
              <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-printer" label="الإيصال" @click="showReceipt(e)" />
              <UButton size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" label="امسحها" class="ms-auto" @click="toDiscard = e" />
            </div>
          </li>
        </ul>
        <p v-else class="py-10 text-center text-(--ui-text-muted)">
          كل الفواتير اتسجلت على السيرفر.
        </p>
      </div>
    </template>
    <template v-if="pending.length" #footer>
      <UButton block icon="i-lucide-cloud-upload" label="سجّلها دلوقتي" :disabled="!online" :loading="syncing" @click="sync(true)" />
    </template>
  </USlideover>

  <UModal :open="!!toDiscard" title="تمسح الفاتورة دي؟" :ui="{ content: 'sm:max-w-sm' }" @update:open="v => { if (!v) toDiscard = null }">
    <template #body>
      <p class="text-sm">
        الفاتورة <b>{{ toDiscard?.receipt.reference }}</b> بـ <b class="num">{{ formatMoney(toDiscard?.receipt.total) }}</b> هتتمسح من الجهاز ومش هتتسجل خالص:
        البضاعة مش هتخرج من المخزون والفلوس مش هتدخل الدرج. اعملها تاني من الكاشير لو اتباعت فعلاً.
      </p>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="رجوع" @click="toDiscard = null" />
        <UButton color="error" icon="i-lucide-trash-2" label="امسحها" @click="confirmDiscard" />
      </div>
    </template>
  </UModal>

  <PosReceiptModal v-model:open="receiptOpen" :sale="receipt" />
</template>

<script setup lang="ts">
import type { Sale } from '~/types/api'
import type { OutboxEntry } from '~/composables/useOutbox'

/** «فواتير مستنية»: the sales made offline on this device, waiting for the server (top bar / POS). */
const store = useSessionStore()
const { online } = useConnectivity()
const { entries, pending, failed, syncing, panelOpen, sync, retry, discard } = useOutbox()

const currentBranchId = computed(() => store.session?.current_branch_id)
const branchName = (id: string) => store.session?.branches.find(b => b.id === id)?.name ?? ''

const toDiscard = ref<OutboxEntry | null>(null)
async function confirmDiscard() {
  if (toDiscard.value) {
    await discard(toDiscard.value.id)
  }
  toDiscard.value = null
}

const receipt = ref<Sale | null>(null)
const receiptOpen = ref(false)
function showReceipt(e: OutboxEntry) {
  receipt.value = e.receipt
  receiptOpen.value = true
}
</script>

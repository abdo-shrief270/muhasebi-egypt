<template>
  <div v-if="contact" class="space-y-6">
    <PageHeader :title="contact.name" :description="[contact.type_label, contact.city, contact.country].filter(Boolean).join(' · ')">
      <UButton to="/imports/contacts" color="neutral" variant="ghost" icon="i-lucide-arrow-right" label="الجهات" />
      <UButton v-if="canManage" color="neutral" variant="outline" icon="i-lucide-pencil" label="تعديل" @click="editing = true" />
      <UButton v-if="canManage" icon="i-lucide-banknote" label="دفعة" @click="paying = true" />
    </PageHeader>

    <div class="grid gap-3 sm:grid-cols-3">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          الرصيد
        </p>
        <p class="num text-2xl font-extrabold" :class="contact.balance > 0 ? 'text-(--ui-error)' : contact.balance < 0 ? 'text-(--ui-success)' : ''">
          {{ importBalanceLabel(contact.balance) }}
        </p>
      </UCard>
      <UCard v-if="contact.phone || contact.whatsapp || contact.wechat || contact.notes" class="sm:col-span-2">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
          <p v-if="contact.phone">
            موبايل: <a :href="`tel:${contact.phone}`" class="num" dir="ltr">{{ contact.phone }}</a>
          </p>
          <p v-if="contact.whatsapp">
            WhatsApp: <a :href="`https://wa.me/${contact.whatsapp.replace(/\D/g, '')}`" target="_blank" class="num" dir="ltr">{{ contact.whatsapp }}</a>
          </p>
          <p v-if="contact.wechat">
            WeChat: <span class="num" dir="ltr">{{ contact.wechat }}</span>
          </p>
        </div>
        <p v-if="contact.notes" class="mt-2 whitespace-pre-line text-sm text-(--ui-text-muted)">
          {{ contact.notes }}
        </p>
      </UCard>
    </div>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <h2 class="font-bold">
          كشف الحساب
        </h2>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                التاريخ
              </th>
              <th class="p-3 text-start">
                البيان
              </th>
              <th class="p-3 text-end">
                عليك
              </th>
              <th class="p-3 text-end">
                ليك
              </th>
              <th class="p-3 text-end">
                الرصيد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in contact.statement" :key="line.id" class="border-t border-(--ui-border)">
              <td class="num p-3 whitespace-nowrap">
                {{ formatDate(line.created_at, true) }}
              </td>
              <td class="p-3">
                <span class="font-semibold">{{ line.type_label }}</span>
                <span v-if="line.note" class="text-(--ui-text-muted)"> — {{ line.note }}</span>
                <NuxtLink v-if="line.ref_type === 'import_shipment'" :to="`/imports/shipments/${line.ref_id}`" class="ms-1 text-(--ui-primary)">فتح</NuxtLink>
              </td>
              <td class="num p-3 text-end">
                {{ line.amount > 0 ? formatMoney(line.amount) : '' }}
              </td>
              <td class="num p-3 text-end">
                {{ line.amount < 0 ? formatMoney(-line.amount) : '' }}
              </td>
              <td class="num p-3 text-end font-semibold">
                {{ formatMoney(line.balance_after) }}
              </td>
            </tr>
            <tr v-if="!contact.statement.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                لسه مفيش حركة.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <div class="grid gap-6 lg:grid-cols-2">
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            الشحنات
          </h2>
        </template>
        <ul class="divide-y divide-(--ui-border)">
          <li v-for="s in contact.shipments" :key="s.id">
            <NuxtLink :to="`/imports/shipments/${s.id}`" class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-(--ui-bg-muted)">
              <span class="num flex-1 font-bold" dir="ltr">{{ s.reference }}</span>
              <span class="num">{{ formatMoney(s.goods_total) }}</span>
              <UBadge :color="IMPORT_STATUS_COLORS[s.status]" variant="subtle" :label="s.status_label" />
            </NuxtLink>
          </li>
          <li v-if="!contact.shipments.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مفيش شحنات.
          </li>
        </ul>
      </UCard>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <h2 class="font-bold">
            الدفعات
          </h2>
        </template>
        <ul class="divide-y divide-(--ui-border)">
          <li v-for="p in contact.payments" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" :class="p.reversed ? 'opacity-50 line-through' : ''">
            <span class="num">{{ formatDate(p.paid_on) }}</span>
            <span class="flex-1">{{ p.method_label }}<template v-if="p.from_drawer"> (من الدرج)</template><template v-if="p.reference"> · <span class="num" dir="ltr">{{ p.reference }}</span></template><template v-if="p.received_by"> · {{ p.received_by }}</template></span>
            <span class="num font-bold">{{ formatMoney(p.amount) }}</span>
            <UButton v-if="p.has_proof" size="xs" color="neutral" variant="ghost" icon="i-lucide-image" aria-label="صورة الإيصال" @click="proofOf = p.id" />
            <UButton v-if="canManage && !p.reversed" size="xs" color="error" variant="ghost" icon="i-lucide-undo-2" aria-label="الغي الدفعة" @click="reversing = p" />
          </li>
          <li v-if="!contact.payments.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
            مفيش دفعات.
          </li>
        </ul>
      </UCard>
    </div>

    <ImportsContactModal v-model:open="editing" :contact="contact" @saved="refresh()" />
    <ImportsPaymentModal
      v-model:open="paying"
      :contact-id="contact.id"
      :contact-name="contact.name"
      :shipments="contact.shipments.filter(s => s.status !== 'cancelled')"
      @saved="refresh()"
    />
    <UModal :open="!!proofOf" title="صورة الإيصال" @update:open="v => !v && (proofOf = null)">
      <template #body>
        <UsedDevicesSecureImage v-if="proofOf" :src="`/imports/payments/${proofOf}/proof`" alt="صورة الإيصال" class="h-96 w-full" />
      </template>
    </UModal>
    <UModal :open="!!reversing" title="إلغاء الدفعة" description="الفلوس هترجع على حساب الجهة، والدفعة هتفضل ظاهرة متشطبة." @update:open="v => !v && (reversing = null)">
      <template #body>
        <UFormField label="السبب">
          <UInput v-model="reason" class="w-full" maxlength="255" />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="reversing = null" />
          <UButton color="error" label="الغي الدفعة" :disabled="!reason.trim()" @click="reverse" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { ImportContact, ImportPayment, ImportShipment, ImportStatementLine } from '~/types/api'

definePageMeta({ permission: 'imports.view', module: 'imports' })

const route = useRoute()
const api = useApi()
const toast = useToast()
const store = useSessionStore()
const canManage = computed(() => store.can('imports.manage'))

type Detail = ImportContact & { statement: ImportStatementLine[], shipments: ImportShipment[], payments: ImportPayment[] }
const { data, refresh } = await useAsyncData(`import-contact-${route.params.id}`, () => api<{ data: Detail }>(`/imports/contacts/${route.params.id}`))
const contact = computed(() => data.value?.data ?? null)

const editing = ref(false)
const paying = ref(false)
const proofOf = ref<string | null>(null)
const reversing = ref<ImportPayment | null>(null)
const reason = ref('')
async function reverse() {
  try {
    await api(`/imports/payments/${reversing.value!.id}/reverse`, { method: 'POST', body: { reason: reason.value.trim() } })
    reversing.value = null
    reason.value = ''
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

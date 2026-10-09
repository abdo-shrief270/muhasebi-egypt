<template>
  <div v-if="a" class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <NuxtLink to="/partners" class="text-sm text-(--ui-text-muted) hover:underline">← الشركاء</NuxtLink>
        <h1 class="mt-1 text-2xl font-extrabold">
          {{ a.name }}
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          <span class="num" dir="ltr">{{ a.code }}</span> · <span class="num" dir="ltr">{{ localPhone(a.phone) }}</span>
          <template v-if="a.email">
            · <span dir="ltr">{{ a.email }}</span>
          </template>
          · سجّل {{ formatDate(a.created_at) }} · آخر دخول {{ timeAgo(a.last_login_at) }}
        </p>
        <p v-if="a.channel" class="text-sm">
          بيسوّق في: {{ a.channel }}
        </p>
      </div>
      <UBadge :color="a.status === 'active' ? 'success' : 'error'" variant="subtle" size="lg" :label="a.status === 'active' ? 'شغال' : 'موقوف'" />
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
      <UCard v-for="b in balances" :key="b.label">
        <p class="text-sm text-(--ui-text-muted)">
          {{ b.label }}
        </p>
        <p class="num text-xl font-extrabold">
          {{ formatMoney(b.value) }}
        </p>
      </UCard>
    </div>

    <UCard>
      <template #header>
        <p class="font-bold">
          الإعدادات
        </p>
      </template>
      <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="save">
        <UFormField label="الحالة">
          <USelect v-model="form.status" :items="[{ label: 'شغال', value: 'active' }, { label: 'موقوف', value: 'suspended' }]" class="w-full" />
        </UFormField>
        <UFormField label="نسبة خاصة %" hint="فاضي = نسبة البرنامج">
          <UInput v-model="form.rate_percent" type="number" min="0" max="60" step="0.5" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="الاستلام">
          <p class="text-sm">
            {{ a.payout_method ?? '—' }} <span class="num" dir="ltr">{{ a.payout_account }}</span> {{ a.payout_name ? `(${a.payout_name})` : '' }}
          </p>
        </UFormField>
        <UFormField label="ملاحظات (ليك بس)" class="sm:col-span-3">
          <UTextarea v-model="form.admin_note" :rows="2" class="w-full" />
        </UFormField>
        <div class="flex justify-end sm:col-span-3">
          <UButton type="submit" icon="i-lucide-check" label="حفظ" :loading="saving" />
        </div>
      </form>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          المحلات ({{ a.referrals.length }})
        </p>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                المحل
              </th>
              <th class="p-3 text-start">
                سجّل
              </th>
              <th class="p-3 text-start">
                أول دفعة
              </th>
              <th class="p-3 text-start">
                العمولة لحد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in a.referrals" :key="r.tenant_id" class="border-t border-(--ui-border)">
              <td class="p-3">
                <NuxtLink :to="`/shops/${r.tenant_id}`" class="font-bold hover:underline">
                  {{ r.shop }}
                </NuxtLink>
              </td>
              <td class="p-3">
                {{ formatDate(r.registered_at) }}
              </td>
              <td class="p-3">
                {{ r.first_paid_at ? formatDate(r.first_paid_at) : 'لسه' }}
              </td>
              <td class="p-3">
                {{ formatDate(r.commission_until) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          العمولات
        </p>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                التاريخ
              </th>
              <th class="p-3 text-start">
                المحل / الفاتورة
              </th>
              <th class="p-3 text-end">
                المدفوع
              </th>
              <th class="p-3 text-end">
                العمولة
              </th>
              <th class="p-3 text-start">
                الحالة
              </th>
              <th class="p-3" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in a.commissions" :key="c.id" class="border-t border-(--ui-border)">
              <td class="p-3">
                {{ formatDate(c.created_at) }}
              </td>
              <td class="p-3">
                {{ c.shop }} <span class="num text-xs text-(--ui-text-muted)">{{ c.invoice }}</span>
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(c.base) }}
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(c.amount) }} ({{ c.rate_percent }}%)
              </td>
              <td class="p-3">
                {{ STATES[c.state] ?? c.state }}<span v-if="c.void_reason" class="text-xs text-(--ui-text-muted)"> — {{ c.void_reason }}</span>
              </td>
              <td class="p-3 text-end">
                <UButton v-if="c.state === 'held' || c.state === 'available'" size="xs" color="error" variant="ghost" label="إلغاء" @click="voidOne(c)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UCard v-if="a.payouts.length" :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          السحب
        </p>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="p in a.payouts" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
          <span>{{ formatDate(p.created_at) }} · {{ p.method_label }} <span class="num" dir="ltr">{{ p.account }}</span></span>
          <span class="num font-bold">{{ formatMoney(p.amount) }}</span>
          <span>{{ STATES[p.status] ?? p.status }} {{ p.reference ?? p.note ?? '' }} {{ p.decided_by_name ? `— ${p.decided_by_name}` : '' }}</span>
        </li>
      </ul>
    </UCard>
  </div>
</template>

<script setup lang="ts">
interface Detail {
  id: string, name: string, phone: string, email: string | null, code: string, status: string, channel: string | null, clicks: number
  rate_percent: number, custom_rate: boolean, last_login_at: string | null, created_at: string
  payout_method: string | null, payout_account: string | null, payout_name: string | null, admin_note: string | null
  balances: { held: number, available: number, requested: number, paid: number }
  referrals: { tenant_id: string, shop: string, registered_at: string, first_paid_at: string | null, commission_until: string | null }[]
  commissions: { id: string, shop: string, invoice: string, base: number, rate_percent: number, amount: number, state: string, void_reason: string | null, created_at: string }[]
  payouts: { id: string, amount: number, method_label: string, account: string, status: string, reference: string | null, note: string | null, decided_by_name: string | null, created_at: string }[]
}

const STATES: Record<string, string> = { held: 'متعلّقة', available: 'متاحة', requested: 'في طلب سحب', paid: 'اتدفعت', void: 'اتلغت', rejected: 'اترفض' }

const route = useRoute()
const api = useAdminApi()
const toast = useToast()
const { data, refresh } = await useAsyncData(`admin-affiliate-${route.params.id}`, () => api<{ data: Detail }>(`/affiliates/${route.params.id}`))
const a = computed(() => data.value?.data)

const form = reactive({ status: 'active', rate_percent: '', admin_note: '' })
watch(a, (v) => {
  if (v) Object.assign(form, { status: v.status, rate_percent: v.custom_rate ? String(v.rate_percent) : '', admin_note: v.admin_note ?? '' })
}, { immediate: true })

const balances = computed(() => a.value
  ? [
      { label: 'متعلّق', value: a.value.balances.held },
      { label: 'متاح', value: a.value.balances.available },
      { label: 'في طلب سحب', value: a.value.balances.requested },
      { label: 'اتدفعله', value: a.value.balances.paid },
    ]
  : [])

const saving = ref(false)
async function save() {
  saving.value = true
  try {
    await api(`/affiliates/${route.params.id}`, { method: 'PATCH', body: { status: form.status, rate_percent: String(form.rate_percent).trim() === '' ? null : Number(form.rate_percent), admin_note: form.admin_note.trim() || null } })
    toast.add({ color: 'success', title: 'اتحفظ' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}

async function voidOne(c: Detail['commissions'][number]) {
  const reason = window.prompt(`سبب إلغاء عمولة ${formatMoney(c.amount)}؟`)
  if (!reason?.trim()) return
  try {
    await api(`/affiliate-commissions/${c.id}/void`, { method: 'POST', body: { reason: reason.trim() } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

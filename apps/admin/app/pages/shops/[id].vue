<template>
  <div v-if="detail" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/shops" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold">
          {{ detail.shop.name }}
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          <span class="num" dir="ltr">{{ detail.shop.code }}</span> · {{ detail.shop.types }} · سجّل {{ formatDate(detail.shop.created_at) }}
        </p>
      </div>
      <UBadge :color="subscriptionStatusColor(sub.status)" variant="subtle" size="lg">
        {{ sub.status_label }}
      </UBadge>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
      <div class="space-y-6">
        <UCard>
          <dl class="grid gap-4 sm:grid-cols-3">
            <div>
              <dt class="text-xs text-(--ui-text-muted)">
                صاحب المحل
              </dt>
              <dd class="font-bold">
                {{ detail.shop.owner_name }}
              </dd>
              <dd class="num text-sm" dir="ltr">
                {{ localPhone(detail.shop.owner_phone) }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-(--ui-text-muted)">
                الاشتراك
              </dt>
              <dd class="font-bold">
                {{ sub.plan_name ?? 'تجربة' }}<span v-if="sub.cycle"> · {{ sub.cycle === 'yearly' ? 'سنوي' : 'شهري' }}</span>
              </dd>
              <dd class="text-sm">
                لحد <span class="num">{{ formatDate(sub.paid_until) }}</span> (<span class="num">{{ sub.days_left }}</span> يوم)
              </dd>
            </div>
            <div>
              <dt class="text-xs text-(--ui-text-muted)">
                الحجم
              </dt>
              <dd class="text-sm">
                <span class="num">{{ detail.shop.users }}</span> مستخدم · <span class="num">{{ detail.shop.branches }}</span> فرع
              </dd>
              <dd v-if="sub.monthly_value" class="text-sm">
                <span class="num">{{ formatMoney(sub.monthly_value) }}</span> / شهر
              </dd>
            </div>
          </dl>
          <p v-if="sub.suspended_reason" class="mt-3 text-sm text-(--ui-error)">
            موقوف: {{ sub.suspended_reason }}
          </p>
        </UCard>

        <UCard v-if="detail.requests.length">
          <template #header>
            <p class="font-bold">
              طلبات الدفع
            </p>
          </template>
          <div class="space-y-3">
            <PaymentCard v-for="p in detail.requests" :key="p.id" :payment="{ ...p, shop: detail.shop }" @changed="refresh" />
          </div>
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              الفواتير
            </p>
          </template>
          <table v-if="detail.invoices.length" class="w-full text-sm">
            <tbody>
              <tr v-for="inv in detail.invoices" :key="inv.id" class="border-t border-(--ui-border) first:border-0">
                <td class="num p-3 font-bold" dir="ltr">
                  {{ inv.reference }}
                </td>
                <td class="p-3">
                  {{ inv.plan_name }} · {{ inv.method_label }}<span v-if="inv.issued_by_name"> · {{ inv.issued_by_name }}</span>
                </td>
                <td class="num p-3">
                  {{ formatDate(inv.period_start) }} ← {{ formatDate(inv.period_end) }}
                </td>
                <td class="num p-3 font-bold">
                  {{ formatMoney(inv.total) }}
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="py-6 text-center text-(--ui-text-muted)">
            مفيش فواتير.
          </p>
        </UCard>
      </div>

      <div class="space-y-6">
        <UCard>
          <template #header>
            <p class="font-bold">
              تفعيل / تجديد
            </p>
          </template>
          <form class="space-y-3" @submit.prevent="activate">
            <UFormField label="الباقة">
              <USelect v-model="form.plan" :items="planItems" class="w-full" />
            </UFormField>
            <UFormField label="المدة">
              <USelect v-model="form.cycle" :items="[{ label: 'شهري', value: 'monthly' }, { label: 'سنوي', value: 'yearly' }]" class="w-full" />
            </UFormField>
            <UFormField label="أقسام إضافية">
              <USelectMenu v-model="form.modules" :items="moduleItems" value-key="value" multiple placeholder="—" class="w-full" />
            </UFormField>
            <div class="grid grid-cols-2 gap-3">
              <UFormField label="عدد الشهور" hint="اختياري">
                <UInput v-model="form.months" type="number" min="1" max="36" dir="ltr" />
              </UFormField>
              <UFormField label="المدفوع (ج)" hint="0 = هدية">
                <UInput v-model="form.amount" type="number" min="0" step="any" dir="ltr" :placeholder="String((listPrice ?? 0) / 100)" />
              </UFormField>
            </div>
            <UFormField label="مرجع / ملاحظة">
              <UInput v-model="form.note" class="w-full" placeholder="مثلاً: كاش مع المندوب" />
            </UFormField>
            <UButton type="submit" block icon="i-lucide-check" label="فعّل" :loading="busy === 'activate'" />
          </form>
        </UCard>

        <UCard>
          <div class="space-y-3">
            <div v-if="sub.on_trial" class="flex items-end gap-2">
              <UFormField label="مدّ التجربة (أيام)" class="flex-1">
                <UInput v-model="trialDays" type="number" min="1" max="90" dir="ltr" />
              </UFormField>
              <UButton color="neutral" variant="outline" label="مدّ" :loading="busy === 'trial'" @click="extendTrial" />
            </div>
            <div v-if="sub.status !== 'suspended' || !sub.suspended_reason" class="flex items-end gap-2">
              <UFormField label="إيقاف المحل" class="flex-1">
                <UInput v-model="suspendReason" placeholder="السبب" />
              </UFormField>
              <UButton color="error" variant="soft" label="وقّف" :disabled="!suspendReason.trim()" :loading="busy === 'suspend'" @click="suspend" />
            </div>
            <UButton v-else block color="success" variant="soft" icon="i-lucide-rotate-ccw" label="رجّع المحل" :loading="busy === 'unsuspend'" @click="unsuspend" />
          </div>
        </UCard>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { AdminOverview, AdminShop, BillingInvoiceInfo, PaymentRequestInfo, SubscriptionInfo } from '~/types/api'


interface Detail { shop: AdminShop, subscription: SubscriptionInfo, requests: PaymentRequestInfo[], invoices: BillingInvoiceInfo[] }

const api = useAdminApi()
const route = useRoute()
const toast = useToast()
const id = route.params.id as string

const { data, refresh } = await useAsyncData(`admin-shop-${id}`, () => api<{ data: Detail }>(`/shops/${id}`))
const { data: overviewData } = await useAsyncData('admin-overview', () => api<{ data: AdminOverview }>('/overview'))
const detail = computed(() => data.value?.data)
const sub = computed(() => detail.value!.subscription)

const planItems = computed(() => (overviewData.value?.data.plans ?? []).map(p => ({ label: `${p.name} (${formatMoney(p.monthly)} / شهر)`, value: p.key })))
const moduleItems = computed(() => (overviewData.value?.data.extra_modules ?? []).map(m => ({ label: m.name, value: m.key })))

const form = reactive({
  plan: sub.value?.plan ?? 'accessories',
  cycle: (sub.value?.cycle ?? 'monthly') as 'monthly' | 'yearly',
  modules: (sub.value?.modules ?? []).map(m => m.key) as string[],
  months: '',
  amount: '',
  note: '',
})
const listPrice = computed(() => {
  const o = overviewData.value?.data
  const plan = o?.plans.find(p => p.key === form.plan)
  if (!o || !plan) {
    return null
  }
  const monthly = plan.monthly + form.modules.filter(k => !plan.modules.some(m => m.key === k)).reduce((sum, k) => sum + (o.extra_modules.find(m => m.key === k)?.monthly ?? 0), 0)
  // A year costs as many months as the plan's yearly price says (yearly_months).
  return form.cycle === 'yearly' ? monthly * Math.round(plan.yearly / plan.monthly) : monthly
})

const busy = ref<string | null>(null)
async function run(key: string, fn: () => Promise<unknown>, done: string) {
  busy.value = key
  try {
    await fn()
    toast.add({ color: 'success', title: done })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

const activate = () => run('activate', () => api(`/shops/${id}/activate`, {
  method: 'POST',
  body: {
    plan: form.plan,
    cycle: form.cycle,
    modules: form.modules,
    months: form.months ? Number(form.months) : undefined,
    amount: form.amount !== '' ? toPiasters(form.amount) : undefined,
    note: form.note || undefined,
  },
}), 'اتفعّل الاشتراك')

const trialDays = ref('7')
const extendTrial = () => run('trial', () => api(`/shops/${id}/trial`, { method: 'POST', body: { days: Number(trialDays.value) } }), 'اتمدّت التجربة')
const suspendReason = ref('')
const suspend = () => run('suspend', () => api(`/shops/${id}/suspend`, { method: 'POST', body: { reason: suspendReason.value } }), 'اتوقف المحل')
const unsuspend = () => run('unsuspend', () => api(`/shops/${id}/unsuspend`, { method: 'POST' }), 'رجع المحل')
</script>

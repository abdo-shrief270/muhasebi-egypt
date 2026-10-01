<template>
  <div v-if="plan" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/installments" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold">
          <span class="num">{{ plan.reference }}</span>
          <UBadge :color="installmentStatusColor(plan.status)" variant="subtle" class="ms-2 align-middle">
            {{ plan.status_label }}
          </UBadge>
          <UBadge v-if="plan.status === 'active' && plan.late_count" color="error" variant="subtle" class="ms-1 align-middle">
            متأخر <span class="num">{{ plan.late_count }}</span> قسط
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          <NuxtLink v-if="store.can('customers.view')" :to="`/customers/${plan.customer_id}`" class="font-bold hover:text-primary">
            {{ plan.customer_name }}
          </NuxtLink>
          <span v-else class="font-bold">{{ plan.customer_name }}</span>
          <template v-if="plan.customer_phone">
            · <a :href="`tel:${plan.customer_phone}`" class="num hover:text-primary" dir="ltr">{{ localPhone(plan.customer_phone) }}</a>
          </template>
          <template v-if="plan.sale_reference">
            · <NuxtLink v-if="plan.sale_id && store.can('sales.view')" :to="`/sales/${plan.sale_id}`" class="num hover:text-primary">{{ plan.sale_reference }}</NuxtLink>
            <span v-else class="num">{{ plan.sale_reference }}</span>
          </template>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton v-if="canCancel" color="neutral" variant="ghost" icon="i-lucide-ban" label="إلغاء التقسيط" @click="cancelOpen = true" />
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع الاتفاق" @click="print" />
        <UButton
          v-if="plan.status === 'active' && plan.customer_phone && plan.next_due && store.hasFeature('installments.reminders')"
          color="neutral"
          variant="outline"
          icon="i-lucide-message-circle"
          label="فكّره على واتساب"
          @click="messages.sendInstallmentReminder(plan, plan.next_due)"
        />
        <UButton v-if="plan.status === 'active'" icon="i-lucide-banknote" label="حصّل قسط" @click="collectOpen = true" />
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div v-for="s in stats" :key="s.label" class="app-card p-4">
        <p class="text-sm text-(--ui-text-muted)">
          {{ s.label }}
        </p>
        <p class="text-2xl font-extrabold num" :class="s.class">
          {{ s.value }}
        </p>
        <p v-if="s.hint" class="text-xs text-(--ui-text-muted)">
          {{ s.hint }}
        </p>
      </div>
    </div>

    <UAlert
      v-if="plan.status === 'cancelled'"
      color="neutral"
      variant="subtle"
      icon="i-lucide-ban"
      :title="`اتلغى ${formatDate(plan.cancelled_at, true)}${plan.cancelled_by_name ? ` — ${plan.cancelled_by_name}` : ''}`"
      description="اللي كان باقي فضل آجل عادي على حساب العميل."
    />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <p class="font-bold">
            الأقساط
          </p>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="w-10 p-3 text-center font-bold">
                  #
                </th>
                <th class="p-3 text-start font-bold">
                  الميعاد
                </th>
                <th class="p-3 text-end font-bold">
                  القسط
                </th>
                <th class="p-3 text-end font-bold">
                  اتدفع
                </th>
                <th class="p-3 text-start font-bold">
                  الحالة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in plan.items ?? []" :key="i.id" class="border-t border-(--ui-border)">
                <td class="p-3 text-center text-(--ui-text-muted) num">
                  {{ i.seq }}
                </td>
                <td class="p-3 num">
                  {{ formatDate(i.due_on) }}
                </td>
                <td class="p-3 text-end num">
                  {{ formatMoney(i.amount) }}
                </td>
                <td class="p-3 text-end num">
                  {{ i.paid ? formatMoney(i.paid) : '—' }}
                </td>
                <td class="p-3">
                  <UBadge v-if="i.remaining === 0" color="success" variant="subtle" icon="i-lucide-check">
                    اتدفع
                  </UBadge>
                  <span v-else-if="plan.status === 'active'" class="text-xs font-bold" :class="i.days_late ? 'text-(--ui-error)' : 'text-(--ui-text-muted)'">
                    {{ dueLabel(i) }}
                  </span>
                  <span v-else class="text-(--ui-text-muted)">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <div class="space-y-6">
        <UCard>
          <template #header>
            <p class="font-bold">
              التفاصيل
            </p>
          </template>
          <dl class="space-y-2 text-sm">
            <div v-for="d in details" :key="d.label" class="flex justify-between gap-3">
              <dt class="text-(--ui-text-muted)">
                {{ d.label }}
              </dt>
              <dd class="text-end font-bold" :class="d.num ? 'num' : ''">
                {{ d.value }}
              </dd>
            </div>
          </dl>
          <p v-if="plan.notes" class="mt-3 border-t border-(--ui-border) pt-3 text-sm">
            {{ plan.notes }}
          </p>
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold">
              المدفوعات
            </p>
          </template>
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="p in plan.payments ?? []" :key="p.id" class="border-t border-(--ui-border) first:border-t-0">
                <td class="p-3">
                  <span class="num">{{ formatDate(p.created_at, true) }}</span>
                  <p class="text-xs text-(--ui-text-muted)">
                    {{ p.source === 'account' ? 'من تحصيل على الحساب' : methodLabel(p.method) }}<template v-if="p.user_name">
                      · {{ p.user_name }}
                    </template>
                  </p>
                </td>
                <td class="p-3 text-end font-bold num">
                  {{ formatMoney(p.amount) }}
                </td>
              </tr>
              <tr v-if="!(plan.payments ?? []).length">
                <td colspan="2" class="p-6 text-center text-(--ui-text-muted)">
                  لسه مفيش مدفوعات.
                </td>
              </tr>
            </tbody>
          </table>
        </UCard>
      </div>
    </div>

    <InstallmentsCollectModal v-model:open="collectOpen" :target="collectTarget" @saved="onSaved" />

    <UModal v-model:open="cancelOpen" title="إلغاء التقسيط" :description="`الباقي (${formatMoney(plan.remaining)}) هيفضل آجل عادي على حساب ${plan.customer_name}.`">
      <template #body>
        <UFormField label="السبب">
          <UInput v-model="cancelReason" class="w-full" placeholder="مثلاً: العميل رجّع الجهاز" />
        </UFormField>
        <UAlert v-if="cancelError" color="error" variant="subtle" :title="cancelError" class="mt-3" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="رجوع" @click="cancelOpen = false" />
          <UButton color="error" icon="i-lucide-ban" label="إلغاء التقسيط" :loading="cancelling" @click="cancelPlan" />
        </div>
      </template>
    </UModal>

    <PrintSheet v-if="printing" page-size="A4" margin="14mm">
      <InstallmentsPlanPrint :plan="plan" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { InstallmentPlan } from '~/types/api'

definePageMeta({ module: 'installments', permission: 'installments.collect' })

const api = useApi()
const route = useRoute()
const router = useRouter()
const store = useSessionStore()
const toast = useToast()
const messages = useMessages()
const { printing, print } = usePrint()

const { data, refresh } = await useAsyncData(`installment-${route.params.id}`, () => api<{ data: InstallmentPlan }>(`/installments/${route.params.id}`))
const plan = computed(() => data.value?.data)
const canCancel = computed(() => plan.value?.status === 'active' && store.can('installments.manage'))

const stats = computed(() => {
  const p = plan.value
  if (!p) {
    return []
  }
  return [
    { label: 'الإجمالي', value: formatMoney(p.total), hint: p.markup ? `منهم ${formatMoney(p.markup)} زيادة` : null, class: '' },
    { label: 'اتدفع', value: formatMoney(p.paid), hint: `${p.paid_count ?? 0} من ${p.count} قسط`, class: '' },
    { label: 'الباقي', value: formatMoney(p.status === 'active' ? p.remaining : 0), hint: null, class: '' },
    { label: 'متأخر', value: formatMoney(p.status === 'active' ? p.late_amount ?? 0 : 0), hint: null, class: p.status === 'active' && p.late_amount ? 'text-(--ui-error)' : '' },
  ]
})

const details = computed(() => {
  const p = plan.value
  if (!p) {
    return []
  }
  return [
    { label: 'المبلغ المقسّط', value: formatMoney(p.principal), num: true },
    { label: 'الزيادة', value: p.markup ? `${formatMoney(p.markup)}${p.markup_rate ? ` (${p.markup_rate / 100}% في الشهر)` : ''}` : 'من غير زيادة', num: true },
    { label: 'الأقساط', value: `${p.count} قسط — ${p.interval_months === 1 ? 'كل شهر' : `كل ${p.interval_months} شهور`}`, num: false },
    { label: 'الضامن', value: p.guarantor_name ? `${p.guarantor_name}${p.guarantor_phone ? ` · ${localPhone(p.guarantor_phone)}` : ''}` : '—', num: false },
    { label: 'اتعمل', value: `${formatDate(p.created_at, true)}${p.created_by_name ? ` — ${p.created_by_name}` : ''}`, num: false },
    ...(p.completed_at ? [{ label: 'خلص', value: formatDate(p.completed_at, true), num: false }] : []),
  ]
})

const collectOpen = ref(false)
const collectTarget = computed(() => plan.value
  ? { id: plan.value.id, reference: plan.value.reference, customer_name: plan.value.customer_name, remaining: plan.value.remaining, amount: plan.value.next_due?.remaining ?? plan.value.remaining }
  : null)
function onSaved(updated: InstallmentPlan) {
  if (data.value) {
    data.value = { data: updated }
  }
}

function methodLabel(method: string | null): string {
  return CASH_METHODS.find(m => m.value === method)?.label ?? 'تحصيل'
}

const cancelOpen = ref(false)
const cancelReason = ref('')
const cancelling = ref(false)
const cancelError = ref<string | null>(null)
async function cancelPlan() {
  cancelling.value = true
  cancelError.value = null
  try {
    await api(`/installments/${route.params.id}/cancel`, { method: 'POST', body: { reason: cancelReason.value || null } })
    toast.add({ color: 'success', title: 'اتلغى التقسيط' })
    cancelOpen.value = false
    await refresh()
  }
  catch (e) {
    cancelError.value = apiErrorMessage(e)
  }
  finally {
    cancelling.value = false
  }
}

// Right after making a plan: print the agreement to sign.
onMounted(async () => {
  if (route.query.print === '1' && plan.value) {
    await router.replace({ query: {} })
    await print()
  }
})
</script>

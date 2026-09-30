<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-extrabold">
      سجل الإدارة
    </h1>
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <table class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              إمتى
            </th>
            <th class="p-3 text-start font-bold">
              مين
            </th>
            <th class="p-3 text-start font-bold">
              عمل إيه
            </th>
            <th class="p-3 text-start font-bold">
              IP
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(a, i) in rows" :key="i" class="border-t border-(--ui-border)">
            <td class="num p-3 text-(--ui-text-muted)">
              {{ formatDate(a.created_at, true) }}
            </td>
            <td class="p-3">
              {{ a.admin_name ?? '—' }}
            </td>
            <td class="p-3">
              <span :class="a.action.startsWith('login_') ? 'text-(--ui-error)' : ''">{{ labels[a.action] ?? a.action }}</span>
              <NuxtLink v-if="a.tenant_id" :to="`/shops/${a.tenant_id}`" class="ms-2 text-primary">المحل</NuxtLink>
              <span v-if="a.details" class="ms-2 text-xs text-(--ui-text-muted)">{{ summary(a.details) }}</span>
            </td>
            <td class="num p-3 text-(--ui-text-muted)" dir="ltr">
              {{ a.ip }}
            </td>
          </tr>
        </tbody>
      </table>
    </UCard>
  </div>
</template>

<script setup lang="ts">
interface Action { action: string, admin_name: string | null, tenant_id: string | null, details: Record<string, unknown> | null, ip: string | null, created_at: string }

const api = useAdminApi()
const { data } = await useAsyncData('admin-activity', () => api<{ data: Action[] }>('/activity'))
const rows = computed(() => data.value?.data ?? [])

const labels: Record<string, string> = {
  login: 'دخول',
  logout: 'خروج',
  login_failed: 'محاولة دخول غلط',
  login_locked: 'دخول مقفول (محاولات كتير)',
  payment_approved: 'وافق على تحويل',
  payment_rejected: 'رفض تحويل',
  proof_viewed: 'فتح صورة تحويل',
  shop_activated: 'فعّل اشتراك',
  trial_extended: 'مدّ التجربة',
  shop_suspended: 'وقّف محل',
  shop_unsuspended: 'رجّع محل',
  beta_granted: 'ادّى فترة Beta مجانية',
  feedback_status: 'غيّر حالة ملاحظة',
  feedback_screenshot_viewed: 'فتح صورة ملاحظة',
  client_error_resolved: 'علّم خطأ واجهة إنه اتحل',
  client_error_reopened: 'رجّع خطأ واجهة مفتوح',
}

function summary(details: Record<string, unknown>): string {
  return Object.entries(details).filter(([, v]) => v !== null && v !== '').map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join('، ') : typeof v === 'number' && k === 'amount' ? formatMoney(v) : String(v)}`).join(' · ')
}
</script>

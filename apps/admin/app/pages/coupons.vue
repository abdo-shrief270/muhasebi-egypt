<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-extrabold">
      كوبونات الاشتراك
    </h1>

    <UCard>
      <template #header>
        <p class="font-bold">
          كوبون جديد
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          صاحب المحل بيكتبه في صفحة «الاشتراك». الرصيد بيدخل حسابه على طول، والخصم بيتحسب في الدفعات الجاية.
        </p>
      </template>
      <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="create">
        <UFormField label="الكود">
          <UInput v-model="form.code" dir="ltr" class="w-full" placeholder="EID2026" @update:model-value="v => form.code = String(v).toUpperCase()" />
        </UFormField>
        <UFormField label="النوع">
          <USelect v-model="form.kind" :items="kinds" class="w-full" />
        </UFormField>
        <UFormField :label="form.kind === 'percent' ? 'النسبة %' : 'المبلغ (ج)'">
          <UInput v-model="form.value" type="number" min="1" step="any" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField v-if="form.kind === 'percent'" label="لمدة (شهور)">
          <UInput v-model="form.months" type="number" min="1" max="24" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="آخر يوم" hint="اختياري">
          <UInput v-model="form.ends_on" type="date" class="w-full" />
        </UFormField>
        <UFormField label="عدد المحلات" hint="فاضي = مفتوح">
          <UInput v-model="form.max_redemptions" type="number" min="1" dir="ltr" class="w-full" />
        </UFormField>
        <UFormField label="ملاحظة" hint="اختياري" class="sm:col-span-2">
          <UInput v-model="form.note" class="w-full" placeholder="مثلاً: عرض معرض القاهرة" />
        </UFormField>
        <UCheckbox v-model="form.new_shops_only" class="self-end" label="للمحلات الجديدة بس (اللي ما دفعتش قبل كده)" />
        <div class="flex justify-end sm:col-span-3">
          <UButton type="submit" icon="i-lucide-plus" label="اعمل الكوبون" :loading="saving" :disabled="form.code.length < 3 || !Number(form.value)" />
        </div>
      </form>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                الكود
              </th>
              <th class="p-3 text-start">
                القيمة
              </th>
              <th class="p-3 text-start">
                الشروط
              </th>
              <th class="p-3 text-end">
                اتستخدم
              </th>
              <th class="p-3 text-end">
                شغال
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in coupons" :key="c.id" class="border-t border-(--ui-border)" :class="c.is_active ? '' : 'opacity-60'">
              <td class="num p-3 font-bold" dir="ltr">
                {{ c.code }}
              </td>
              <td class="p-3">
                {{ c.label }}
              </td>
              <td class="p-3 text-xs text-(--ui-text-muted)">
                {{ [c.new_shops_only ? 'محلات جديدة بس' : null, c.ends_on ? `لحد ${formatDate(c.ends_on)}` : null, c.note].filter(Boolean).join(' · ') || '—' }}
              </td>
              <td class="num p-3 text-end">
                {{ c.redemptions }}{{ c.max_redemptions ? ` / ${c.max_redemptions}` : '' }}
              </td>
              <td class="p-3 text-end">
                <USwitch :model-value="c.is_active" :aria-label="`${c.code} شغال`" @update:model-value="v => toggle(c, v)" />
              </td>
            </tr>
            <tr v-if="!coupons.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                مفيش كوبونات لسه.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
interface Coupon {
  id: string
  code: string
  kind: 'percent' | 'amount' | 'credit'
  value: number
  months: number
  label: string
  new_shops_only: boolean
  ends_on: string | null
  max_redemptions: number | null
  redemptions: number
  is_active: boolean
  note: string | null
}

const api = useAdminApi()
const toast = useToast()
const kinds = [
  { label: 'خصم نسبة', value: 'percent' },
  { label: 'خصم مبلغ (مرة)', value: 'amount' },
  { label: 'رصيد في الحساب', value: 'credit' },
]

const { data, refresh } = await useAsyncData('admin-coupons', () => api<{ data: Coupon[] }>('/coupons'))
const coupons = computed(() => data.value?.data ?? [])

const blank = () => ({ code: '', kind: 'percent', value: '', months: '1', ends_on: '', max_redemptions: '', note: '', new_shops_only: false })
const form = reactive(blank())
const saving = ref(false)

async function create() {
  saving.value = true
  try {
    await api('/coupons', {
      method: 'POST',
      body: {
        code: form.code.trim(),
        kind: form.kind,
        value: form.kind === 'percent' ? Math.round(Number(form.value)) : toPiasters(form.value),
        months: form.kind === 'percent' ? Number(form.months) || 1 : 1,
        ends_on: form.ends_on || null,
        max_redemptions: form.max_redemptions ? Number(form.max_redemptions) : null,
        note: form.note.trim() || null,
        new_shops_only: form.new_shops_only,
      },
    })
    Object.assign(form, blank())
    toast.add({ color: 'success', title: 'اتعمل الكوبون' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}

async function toggle(c: Coupon, active: boolean) {
  try {
    await api(`/coupons/${c.id}`, { method: 'PATCH', body: { is_active: active } })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

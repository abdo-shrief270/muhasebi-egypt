<template>
  <div class="space-y-3">
    <p v-if="!coupons.length" class="text-sm text-(--ui-text-muted)">
      اعمل كود خصم (نسبة أو مبلغ) تنشره على صفحتك أو واتساب، والزبون يكتبه في السلة. الخصم بيتحسب من البرنامج، وبيبقى خصم الفاتورة لما تحوّل الطلب.
    </p>
    <ul v-else class="divide-y divide-(--ui-border) rounded-(--ui-radius) border border-(--ui-border)">
      <li v-for="c in coupons" :key="c.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2" :class="c.is_active ? '' : 'opacity-60'">
        <span class="num font-extrabold tracking-wide" dir="ltr">{{ c.code }}</span>
        <span class="text-sm">{{ c.label }}</span>
        <span class="flex-1 text-xs text-(--ui-text-muted)">{{ couponRules(c) }}</span>
        <span class="num text-xs text-(--ui-text-muted)">اتستخدم {{ c.uses }}{{ c.max_uses ? ` من ${c.max_uses}` : '' }}</span>
        <USwitch :model-value="c.is_active" :aria-label="`${c.code} شغال`" @update:model-value="v => update(c, { is_active: v })" />
        <UButton v-if="!c.uses" color="neutral" variant="ghost" icon="i-lucide-trash-2" :aria-label="`امسح ${c.code}`" @click="remove(c)" />
      </li>
    </ul>

    <form class="grid gap-3 rounded-(--ui-radius) border border-dashed border-(--ui-border) p-3 sm:grid-cols-3" @submit.prevent="add">
      <UFormField label="الكود" :error="errors.code">
        <UInput v-model="form.code" dir="ltr" class="w-full" maxlength="32" placeholder="RAMADAN10" @update:model-value="v => form.code = String(v).toUpperCase()" />
      </UFormField>
      <UFormField label="الخصم" :error="errors.value">
        <div class="flex gap-2">
          <UInput v-model="form.value" type="number" min="1" step="any" dir="ltr" class="min-w-0 flex-1" aria-label="قيمة الخصم" />
          <USelect v-model="form.kind" :items="[{ value: 'percent', label: '%' }, { value: 'amount', label: 'ج' }]" class="w-20" aria-label="نوع الخصم" />
        </div>
      </UFormField>
      <UFormField v-if="form.kind === 'percent'" label="أقصى خصم (ج)" hint="اختياري" :error="errors.max_discount">
        <UInput v-model="form.max_discount" type="number" min="0" step="any" dir="ltr" class="w-full" />
      </UFormField>
      <UFormField label="لطلب من (ج)" hint="اختياري" :error="errors.min_order">
        <UInput v-model="form.min_order" type="number" min="0" step="any" dir="ltr" class="w-full" />
      </UFormField>
      <UFormField label="من يوم" hint="اختياري">
        <UInput v-model="form.starts_on" type="date" class="w-full" />
      </UFormField>
      <UFormField label="لحد يوم" hint="اختياري" :error="errors.ends_on">
        <UInput v-model="form.ends_on" type="date" class="w-full" />
      </UFormField>
      <UFormField label="عدد الطلبات" hint="فاضي = مفتوح" :error="errors.max_uses">
        <UInput v-model="form.max_uses" type="number" min="1" dir="ltr" class="w-full" />
      </UFormField>
      <UCheckbox v-model="form.once_per_phone" class="self-end sm:col-span-2" label="مرة واحدة لكل رقم موبايل" />
      <div class="flex justify-end sm:col-span-3">
        <UButton type="submit" icon="i-lucide-plus" label="ضيف الكود" :loading="adding" :disabled="form.code.length < 3 || !Number(form.value)" />
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { OnlineCoupon } from '~/types/api'

/** The store's discount codes (online_store.manage). */
const api = useApi()
const toast = useToast()

const { data } = await useAsyncData('online-store-coupons', () => api<{ data: OnlineCoupon[] }>('/online-store/coupons'))
const coupons = ref<OnlineCoupon[]>(data.value?.data ?? [])

const blank = () => ({ code: '', kind: 'percent', value: '', max_discount: '', min_order: '', starts_on: '', ends_on: '', max_uses: '', once_per_phone: true })
const form = reactive(blank())
const errors = ref<Record<string, string>>({})
const adding = ref(false)

/** «لطلب من 200 ج · لحد 30/10» */
function couponRules(c: OnlineCoupon): string {
  return [
    c.min_order ? `لطلب من ${formatMoney(c.min_order)}` : null,
    c.starts_on ? `من ${formatDate(c.starts_on)}` : null,
    c.ends_on ? `لحد ${formatDate(c.ends_on)}` : null,
    c.once_per_phone ? 'مرة لكل رقم' : null,
  ].filter(Boolean).join(' · ')
}

async function add() {
  adding.value = true
  errors.value = {}
  try {
    coupons.value = (await api<{ data: OnlineCoupon[] }>('/online-store/coupons', {
      method: 'POST',
      body: {
        code: form.code.trim(),
        kind: form.kind,
        value: form.kind === 'percent' ? Math.round(Number(form.value)) : toPiasters(form.value) ?? 0,
        max_discount: form.kind === 'percent' && form.max_discount ? toPiasters(form.max_discount) : null,
        min_order: toPiasters(form.min_order) ?? 0,
        starts_on: form.starts_on || null,
        ends_on: form.ends_on || null,
        max_uses: form.max_uses ? Number(form.max_uses) : null,
        once_per_phone: form.once_per_phone,
      },
    })).data
    Object.assign(form, blank())
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    if (!Object.keys(errors.value).length) {
      toast.add({ color: 'error', title: apiErrorMessage(e) })
    }
  }
  finally {
    adding.value = false
  }
}

async function update(c: OnlineCoupon, body: Partial<OnlineCoupon>) {
  try {
    coupons.value = (await api<{ data: OnlineCoupon[] }>(`/online-store/coupons/${c.id}`, { method: 'PATCH', body })).data
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function remove(c: OnlineCoupon) {
  try {
    await api(`/online-store/coupons/${c.id}`, { method: 'DELETE' })
    coupons.value = coupons.value.filter(x => x.id !== c.id)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

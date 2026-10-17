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
              <th class="p-3">
                <span class="sr-only">تعديل</span>
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
                {{ [c.new_shops_only ? 'محلات جديدة بس' : null, c.starts_on ? `من ${formatDate(c.starts_on)}` : null, c.ends_on ? `لحد ${formatDate(c.ends_on)}` : null, c.note].filter(Boolean).join(' · ') || '—' }}
              </td>
              <td class="num p-3 text-end">
                {{ c.redemptions }}{{ c.max_redemptions ? ` / ${c.max_redemptions}` : '' }}
              </td>
              <td class="p-3 text-end">
                <USwitch :model-value="c.is_active" :aria-label="`${c.code} شغال`" @update:model-value="v => toggle(c, v)" />
              </td>
              <td class="p-3 text-end">
                <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="openEdit(c)" />
              </td>
            </tr>
            <tr v-if="!coupons.length">
              <td colspan="6" class="p-6 text-center text-(--ui-text-muted)">
                مفيش كوبونات لسه.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UModal v-model:open="editOpen" :title="editing ? `تعديل الكوبون ${editing.code}` : 'تعديل الكوبون'">
      <template #body>
        <form id="coupon-edit" class="grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit">
          <UFormField label="الكود">
            <UInput v-model="edit.code" dir="ltr" class="w-full" @update:model-value="v => edit.code = String(v).toUpperCase()" />
          </UFormField>
          <UFormField label="النوع">
            <USelect v-model="edit.kind" :items="kinds" class="w-full" />
          </UFormField>
          <UFormField :label="edit.kind === 'percent' ? 'النسبة %' : 'المبلغ (ج)'">
            <UInput v-model="edit.value" type="number" min="1" step="any" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField v-if="edit.kind === 'percent'" label="لمدة (شهور)">
            <UInput v-model="edit.months" type="number" min="1" max="24" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="أول يوم" hint="اختياري">
            <UInput v-model="edit.starts_on" type="date" class="w-full" />
          </UFormField>
          <UFormField label="آخر يوم" hint="اختياري">
            <UInput v-model="edit.ends_on" type="date" class="w-full" />
          </UFormField>
          <UFormField label="عدد المحلات" :hint="editing ? `اتستخدم ${editing.redemptions} · فاضي = مفتوح` : 'فاضي = مفتوح'">
            <UInput v-model="edit.max_redemptions" type="number" min="1" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظة" hint="اختياري">
            <UInput v-model="edit.note" class="w-full" />
          </UFormField>
          <UCheckbox v-model="edit.new_shops_only" label="للمحلات الجديدة بس (اللي ما دفعتش قبل كده)" />
          <USwitch v-model="edit.is_active" label="شغال" />
        </form>
        <p class="mt-4 text-xs text-(--ui-text-muted)">
          المحلات اللي استخدمت الكوبون قبل كده بتفضل بالخصم اللي أخدته. التعديل بيسري على اللي هيستخدموه بعد كده، ولو غيّرت الكود فالكود القديم بيبطل.
        </p>
        <UAlert v-if="editError" class="mt-3" color="error" variant="subtle" :title="editError" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="editOpen = false" />
          <UButton type="submit" form="coupon-edit" icon="i-lucide-check" label="احفظ التعديل" :loading="savingEdit" :disabled="edit.code.length < 3 || !Number(edit.value)" />
        </div>
      </template>
    </UModal>
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
  starts_on: string | null
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

// Editing a coupon: every field, in a modal. Amounts are shown in pounds, sent in piasters.
const editOpen = ref(false)
const editing = ref<Coupon | null>(null)
const edit = reactive({ code: '', kind: 'percent' as Coupon['kind'], value: '', months: '1', starts_on: '', ends_on: '', max_redemptions: '', note: '', new_shops_only: false, is_active: true })
const editError = ref<string | null>(null)
const savingEdit = ref(false)

function openEdit(c: Coupon) {
  editing.value = c
  Object.assign(edit, {
    code: c.code,
    kind: c.kind,
    value: String(c.kind === 'percent' ? c.value : c.value / 100),
    months: String(c.months),
    starts_on: c.starts_on ?? '',
    ends_on: c.ends_on ?? '',
    max_redemptions: c.max_redemptions ? String(c.max_redemptions) : '',
    note: c.note ?? '',
    new_shops_only: c.new_shops_only,
    is_active: c.is_active,
  })
  editError.value = null
  editOpen.value = true
}

async function saveEdit() {
  if (!editing.value) return
  savingEdit.value = true
  editError.value = null
  try {
    await api(`/coupons/${editing.value.id}`, {
      method: 'PATCH',
      body: {
        code: edit.code.trim(),
        kind: edit.kind,
        value: edit.kind === 'percent' ? Math.round(Number(edit.value)) : toPiasters(edit.value),
        months: edit.kind === 'percent' ? Number(edit.months) || 1 : 1,
        starts_on: edit.starts_on || null,
        ends_on: edit.ends_on || null,
        max_redemptions: edit.max_redemptions ? Number(edit.max_redemptions) : null,
        note: edit.note.trim() || null,
        new_shops_only: edit.new_shops_only,
        is_active: edit.is_active,
      },
    })
    editOpen.value = false
    toast.add({ color: 'success', title: 'اتعدّل الكوبون' })
    await refresh()
  }
  catch (e) {
    editError.value = apiErrorMessage(e)
  }
  finally {
    savingEdit.value = false
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

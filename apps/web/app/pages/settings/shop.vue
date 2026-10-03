<template>
  <div class="space-y-6">
    <PageHeader title="بيانات المحل والإيصال" description="اسم المحل وتليفونه وعنوان كل فرع، واللي بيتطبع على الإيصال. وشكل الإيصال بيتغير قدامك وإنت بتكتب." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
      <form class="space-y-6" @submit.prevent="save">
        <UCard>
          <template #header>
            <h2 class="font-bold">
              المحل
            </h2>
          </template>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="اسم المحل" required :error="errors.name">
              <UInput v-model="form.name" class="w-full" maxlength="120" />
            </UFormField>
            <UFormField label="موبايل المحل" required hint="بيظهر لو الفرع مالوش رقم" :error="errors.phone">
              <UInput v-model="form.phone" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
            </UFormField>
            <UFormField label="رقم التسجيل الضريبي" hint="اختياري" :error="errors.tax_number">
              <UInput v-model="form.tax_number" dir="ltr" class="w-full" maxlength="30" />
            </UFormField>
            <UFormField label="رقم السجل التجاري" hint="اختياري" :error="errors.commercial_register">
              <UInput v-model="form.commercial_register" dir="ltr" class="w-full" maxlength="30" />
            </UFormField>
            <UFormField label="آخر سطر في الإيصال" class="sm:col-span-2" :hint="`${form.footer.length}/200`" :error="errors.footer">
              <UTextarea v-model="form.footer" :rows="2" autoresize class="w-full" maxlength="200" placeholder="شكراً لزيارتك 🌷 · الضمان 14 يوم بالإيصال" />
            </UFormField>
            <UFormField label="ورق الطابعة الحرارية" class="sm:col-span-2">
              <URadioGroup
                v-model="form.paper"
                orientation="horizontal"
                :items="[{ value: '80', label: '80 مم (العادي)' }, { value: '58', label: '58 مم (الصغير)' }]"
              />
            </UFormField>
            <div class="space-y-3 sm:col-span-2">
              <USwitch v-model="form.show_cashier" label="اطبع اسم الكاشير" />
              <USwitch v-model="form.show_customer" label="اطبع اسم العميل" />
              <USwitch v-model="form.show_serials" label="اطبع IMEI / سيريال الأجهزة" description="مفيد للضمان؛ اقفله لو مش عايز الرقم يبان على الإيصال." />
            </div>
          </div>
        </UCard>

        <UCard v-if="branchForm">
          <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h2 class="font-bold">
                عنوان وتليفون الفرع
              </h2>
              <USelect v-if="branches.length > 1" v-model="branchId" :items="branches.map(b => ({ label: b.name, value: b.id }))" class="w-48" />
            </div>
          </template>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="العنوان" class="sm:col-span-2" :error="errors.address">
              <UInput v-model="branchForm.address" class="w-full" maxlength="255" placeholder="مثلاً: 12 شارع 9، المعادي" />
            </UFormField>
            <UFormField label="تليفون الفرع" hint="اختياري" :error="errors.branch_phone">
              <UInput v-model="branchForm.phone" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
            </UFormField>
          </div>
        </UCard>

        <div class="flex justify-end">
          <UButton type="submit" icon="i-lucide-check" label="حفظ" :loading="saving" />
        </div>
      </form>

      <div class="lg:sticky lg:top-20 lg:self-start">
        <p class="mb-2 text-sm font-bold text-(--ui-text-muted)">
          شكل الإيصال
        </p>
        <div class="rounded-(--ui-radius) border border-(--ui-border) bg-white p-3 shadow-(--app-shadow)">
          <PrintReceipt :data="preview" :width="form.paper === '58' ? '48mm' : '72mm'" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Branch, ReceiptData } from '~/types/api'

definePageMeta({ ownerOnly: true })

const api = useApi()
const toast = useToast()
const store = useSessionStore()

const tenant = store.session!.tenant
const form = reactive({
  name: tenant.name,
  phone: localPhone(tenant.phone),
  tax_number: tenant.receipt?.tax_number ?? '',
  commercial_register: tenant.receipt?.commercial_register ?? '',
  footer: tenant.receipt?.footer ?? '',
  show_cashier: tenant.receipt?.show_cashier ?? true,
  show_customer: tenant.receipt?.show_customer ?? true,
  show_serials: tenant.receipt?.show_serials ?? true,
  paper: (tenant.receipt?.paper ?? '80') as '80' | '58',
})

const branches = computed<Branch[]>(() => store.session?.branches ?? [])
const branchId = ref(store.currentBranch?.id ?? branches.value[0]?.id ?? '')
const branchForm = ref<{ address: string, phone: string } | null>(null)
watch(branchId, (id) => {
  const b = branches.value.find(x => x.id === id)
  branchForm.value = b ? { address: b.address ?? '', phone: b.phone ? localPhone(b.phone) : '' } : null
}, { immediate: true })

const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function save() {
  saving.value = true
  errors.value = {}
  try {
    await api('/shop/profile', { method: 'PUT', body: form })
    if (branchForm.value && branchId.value) {
      await api(`/branches/${branchId.value}`, { method: 'PATCH', body: { address: branchForm.value.address || null, phone: branchForm.value.phone || null } })
        .catch((e) => {
          // The branch's fields sit under their own inputs.
          const { address, phone } = apiValidationErrors(e)
          errors.value = { ...(address ? { address } : {}), ...(phone ? { branch_phone: phone } : {}) }
          throw e
        })
    }
    await store.load()
    toast.add({ color: 'success', title: 'اتحفظت بيانات المحل' })
  }
  catch (e) {
    if (!Object.keys(errors.value).length) {
      errors.value = apiValidationErrors(e)
    }
    if (!Object.keys(errors.value).length) {
      toast.add({ color: 'error', title: apiErrorMessage(e) })
    }
  }
  finally {
    saving.value = false
  }
}

// A sample sale, so the owner sees exactly what the customer gets.
const preview = computed<ReceiptData>(() => ({
  shop: {
    name: form.name || 'اسم المحل',
    phone: branchForm.value?.phone || form.phone,
    address: branchForm.value?.address || null,
    receipt: {
      tax_number: form.tax_number || null, commercial_register: form.commercial_register || null, footer: form.footer.trim() || 'شكراً لزيارتك 🌷',
      show_cashier: form.show_cashier, show_customer: form.show_customer, show_serials: form.show_serials, paper: form.paper,
    },
  },
  branch: branches.value.length > 1 ? branches.value.find(b => b.id === branchId.value)?.name ?? null : null,
  reference: 'INV-000128',
  completed_at: new Date().toISOString(),
  cashier_name: store.session?.user.name ?? null,
  customer_name: 'محمد أحمد',
  subtotal: 57000,
  discount: 0,
  total: 57000,
  paid: 60000,
  change: 3000,
  refunded: 0,
  items: [
    { name: 'شاحن سريع 20W', qty: 1, unit_price: 45000, discount: 0, line_total: 45000, returned_qty: 0 },
    { name: 'جراب سيليكون — أسود', qty: 1, unit_price: 12000, discount: 0, line_total: 12000, returned_qty: 0 },
  ],
  payments: [{ method_label: 'كاش', amount: 60000 }],
}))
</script>

<template>
  <div v-if="info" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <h1 class="flex-1 text-2xl font-extrabold">
        باقة المؤسسين
      </h1>
      <UBadge :color="info.open ? 'success' : 'neutral'" variant="subtle" size="lg">
        {{ info.open ? 'مفتوحة للمحلات' : (info.offer.enabled ? 'مفتوحة بس خلصت (الأماكن / التاريخ)' : 'مقفولة') }}
      </UBadge>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <UCard v-for="s in stats" :key="s.label">
        <p class="text-xs text-(--ui-text-muted)">
          {{ s.label }}
        </p>
        <p class="num text-xl font-extrabold">
          {{ s.value }}
        </p>
      </UCard>
    </div>

    <form class="space-y-6" @submit.prevent="save">
      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1">
              <p class="font-bold">
                العرض
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                سعر مرة واحدة مدى الحياة (بيغطي أول سنة)، وبعدها رسوم سنوية صغيرة. شروط كل محل بتتثبت أول ما دفعته الأولى تتوافق، وأي تعديل هنا بيمشي على الجداد بس.
              </p>
            </div>
            <USwitch v-model="form.enabled" label="مفتوحة" />
          </div>
        </template>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="الاسم">
            <UInput v-model="form.name" class="w-full" maxlength="80" />
          </UFormField>
          <UFormField label="عدد الأماكن" hint="فاضي = من غير حد">
            <UInput v-model="form.seats" type="number" min="1" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="الجملة اللي تحت الاسم" class="sm:col-span-2">
            <UInput v-model="form.tagline" class="w-full" maxlength="255" />
          </UFormField>
          <UFormField label="آخر يوم" hint="اختياري">
            <UInput v-model="form.ends_at" type="date" class="w-full" />
          </UFormField>
          <UFormField label="الأقساط المتاحة" hint="كل قسط بيغطي 12 ÷ العدد شهور">
            <div class="flex flex-wrap gap-3 pt-1">
              <UCheckbox v-for="n in info.allowed_installments" :key="n" :model-value="form.installments.includes(n)" :label="n === 1 ? 'دفعة واحدة' : `${n} أقساط`" @update:model-value="v => toggleInstallment(n, !!v)" />
            </div>
          </UFormField>
        </div>
      </UCard>

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <p class="font-bold">
            الأسعار
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            بالجنيه شامل الضريبة. المقارنة = كام شهر من الاشتراك الشهري العادي بيساوي السعر.
          </p>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start">
                  الباقة
                </th>
                <th class="p-3 text-start">
                  متاحة
                </th>
                <th class="p-3 text-start">
                  سعر مدى الحياة (ج)
                </th>
                <th class="p-3 text-start">
                  الرسوم السنوية (ج)
                </th>
                <th class="p-3 text-end">
                  المقارنة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in info.plans" :key="p.key" class="border-t border-(--ui-border)" :class="form.plans[p.key]?.enabled ? '' : 'opacity-60'">
                <td class="p-3">
                  <p class="font-bold">
                    {{ p.name }}
                  </p>
                  <p class="num text-xs text-(--ui-text-muted)">
                    عادي {{ formatMoney(p.monthly) }} / شهر
                  </p>
                </td>
                <td class="p-3">
                  <USwitch v-model="form.plans[p.key]!.enabled" :aria-label="`${p.name} متاحة`" />
                </td>
                <td class="p-3">
                  <UInput v-model="form.plans[p.key]!.price" type="number" min="0" step="any" dir="ltr" class="w-32" />
                </td>
                <td class="p-3">
                  <UInput v-model="form.plans[p.key]!.annual_fee" type="number" min="0" step="any" dir="ltr" class="w-32" />
                </td>
                <td class="num p-3 text-end text-xs text-(--ui-text-muted)">
                  {{ compare(p) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <UCard>
        <template #header>
          <p class="font-bold">
            المميزات والشروط
          </p>
        </template>
        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField label="خصم الأقسام الإضافية %">
            <UInput v-model="form.module_discount_percent" type="number" min="0" max="100" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="نسبة الشركاء على المؤسسين %" hint="فاضي = نسبتهم العادية">
            <UInput v-model="form.affiliate_rate_percent" type="number" min="0" max="100" dir="ltr" class="w-full" />
          </UFormField>
          <UCheckbox v-model="form.allow_coupons" class="self-end" label="الكوبونات والخصومات تنفع عليها" />
        </div>
        <UFormField label="المميزات اللي بتظهر للمحلات وعلى الموقع" class="mt-4">
          <div class="space-y-2">
            <div v-for="(_, i) in form.perks" :key="i" class="flex gap-2">
              <UInput v-model="form.perks[i]" class="w-full" maxlength="160" />
              <UButton color="neutral" variant="ghost" icon="i-lucide-x" square aria-label="شيل" @click="form.perks.splice(i, 1)" />
            </div>
            <UButton v-if="form.perks.length < 12" color="neutral" variant="outline" icon="i-lucide-plus" label="ميزة" size="sm" @click="form.perks.push('')" />
          </div>
        </UFormField>
      </UCard>

      <div class="flex justify-end">
        <UButton type="submit" icon="i-lucide-check" label="احفظ" size="lg" :loading="saving" />
      </div>
    </form>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          المؤسسين
        </p>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                #
              </th>
              <th class="p-3 text-start">
                المحل
              </th>
              <th class="p-3 text-start">
                الباقة
              </th>
              <th class="p-3 text-end">
                اتدفع / السعر
              </th>
              <th class="p-3 text-start">
                الاشتراك لحد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in info.founders" :key="f.tenant_id" class="border-t border-(--ui-border)">
              <td class="num p-3 font-bold">
                {{ f.number }}
              </td>
              <td class="p-3">
                <NuxtLink :to="`/shops/${f.tenant_id}`" class="font-bold hover:underline">
                  {{ f.shop?.name ?? '—' }}
                </NuxtLink>
                <p class="num text-xs text-(--ui-text-muted)">
                  من {{ formatDate(f.since) }}
                </p>
              </td>
              <td class="p-3">
                {{ f.plan_name }}
                <p class="text-xs text-(--ui-text-muted)">
                  {{ f.installments === 1 ? 'دفعة واحدة' : `${f.installments} أقساط` }} · سنوي <span class="num">{{ formatMoney(f.annual_fee) }}</span>
                </p>
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(f.paid) }} / {{ formatMoney(f.price) }}
                <p v-if="f.remaining" class="text-xs text-(--ui-warning)">
                  باقي {{ formatMoney(f.remaining) }}
                </p>
              </td>
              <td class="p-3">
                <span class="num">{{ formatDate(f.paid_until) }}</span>
              </td>
            </tr>
            <tr v-if="!info.founders.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                مفيش مؤسسين لسه.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { AdminShop, FounderInfo } from '~/types/api'

interface PlanPrice { enabled: boolean, price: number, annual_fee: number }
interface Offer {
  enabled: boolean
  name: string
  tagline: string | null
  seats: number | null
  ends_at: string | null
  plans: Record<string, PlanPrice>
  installments: number[]
  perks: string[]
  module_discount_percent: number
  affiliate_rate_percent: number | null
  allow_coupons: boolean
}
interface FoundersInfo {
  offer: Offer
  open: boolean
  plans: { key: string, name: string, monthly: number }[]
  allowed_installments: number[]
  stats: { founders: number, pending: number, seats_left: number | null, sold: number, collected: number, owed: number }
  founders: (FounderInfo & { tenant_id: string, shop: AdminShop | null, paid_until: string, status: string })[]
}

const api = useAdminApi()
const toast = useToast()
const { data } = await useAsyncData('admin-founders', () => api<{ data: FoundersInfo }>('/founders'))
const info = computed(() => data.value?.data)

const stats = computed(() => {
  const s = info.value!.stats
  return [
    { label: 'المؤسسين', value: s.founders },
    { label: 'مستنيين موافقة', value: s.pending },
    { label: 'أماكن فاضلة', value: s.seats_left ?? 'مفتوح' },
    { label: 'اتباع بـ', value: formatMoney(s.sold) },
    { label: 'باقي على الأقساط', value: formatMoney(s.owed) },
  ]
})

type PlanForm = { enabled: boolean, price: string, annual_fee: string }
const form = reactive({
  enabled: false,
  name: '',
  tagline: '',
  seats: '',
  ends_at: '',
  plans: {} as Record<string, PlanForm>,
  installments: [] as number[],
  perks: [] as string[],
  module_discount_percent: '0',
  affiliate_rate_percent: '',
  allow_coupons: false,
})

function fill(o: Offer) {
  Object.assign(form, {
    enabled: o.enabled,
    name: o.name,
    tagline: o.tagline ?? '',
    seats: o.seats !== null ? String(o.seats) : '',
    ends_at: o.ends_at ? o.ends_at.slice(0, 10) : '',
    plans: Object.fromEntries(Object.entries(o.plans).map(([k, p]) => [k, { enabled: p.enabled, price: String(p.price / 100), annual_fee: String(p.annual_fee / 100) }])),
    installments: [...o.installments],
    perks: [...o.perks],
    module_discount_percent: String(o.module_discount_percent),
    affiliate_rate_percent: o.affiliate_rate_percent !== null ? String(o.affiliate_rate_percent) : '',
    allow_coupons: o.allow_coupons,
  })
}
watch(() => info.value?.offer, o => o && fill(o), { immediate: true })

function toggleInstallment(n: number, on: boolean) {
  form.installments = on ? [...new Set([...form.installments, n])].sort((a, b) => a - b) : form.installments.filter(x => x !== n)
}

function compare(p: { key: string, monthly: number }) {
  const price = toPiasters(form.plans[p.key]?.price ?? 0) ?? 0
  return price && p.monthly ? `${(price / p.monthly).toFixed(1)} شهر` : '—'
}

const saving = ref(false)
async function save() {
  saving.value = true
  try {
    const res = await api<{ data: FoundersInfo }>('/founders/offer', {
      method: 'PUT',
      body: {
        enabled: form.enabled,
        name: form.name.trim(),
        tagline: form.tagline.trim() || null,
        seats: form.seats !== '' ? Number(form.seats) : null,
        ends_at: form.ends_at || null,
        plans: Object.fromEntries(Object.entries(form.plans).map(([k, p]) => [k, { enabled: p.enabled, price: toPiasters(p.price) ?? 0, annual_fee: toPiasters(p.annual_fee) ?? 0 }])),
        installments: form.installments,
        perks: form.perks.map(p => p.trim()).filter(Boolean),
        module_discount_percent: Number(form.module_discount_percent) || 0,
        affiliate_rate_percent: form.affiliate_rate_percent !== '' ? Number(form.affiliate_rate_percent) : null,
        allow_coupons: form.allow_coupons,
      },
    })
    data.value = res
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>

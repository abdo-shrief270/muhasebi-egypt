<template>
  <div v-if="info" class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold">
          الأسعار ومدى الحياة
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          كل الأسعار بالجنيه شامل الضريبة. التعديل بيمشي على الدفعات الجاية؛ اللي اشترى مدى الحياة سعره وصيانته متثبتين.
        </p>
      </div>
      <UBadge :color="info.open ? 'success' : 'neutral'" variant="subtle" size="lg">
        مدى الحياة {{ info.open ? 'متاح للمحلات' : 'مقفول' }}
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

    <!-- Plans -->
    <form class="space-y-6" @submit.prevent="saveCatalog">
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <p class="font-bold">
            الباقات
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            مدى الحياة = 0 يعني الباقة دي مش بتتباع مدى الحياة. «يساوي» = سعر مدى الحياة بكام شهر من الشهري.
          </p>
        </template>
        <div class="divide-y divide-(--ui-border)">
          <div v-for="p in catalog.plans" :key="p.key" class="space-y-3 p-4" :class="p.enabled ? '' : 'opacity-60'">
            <div class="flex flex-wrap items-center gap-3">
              <UInput v-model="p.name" class="w-40 font-bold" maxlength="80" />
              <USwitch v-model="p.enabled" label="مفتوحة" />
              <UCheckbox v-model="p.featured" label="الأكثر اختياراً" />
              <span class="num ms-auto text-xs text-(--ui-text-muted)">يساوي {{ monthsOf(p.lifetime, p.monthly) }}</span>
            </div>
            <UInput v-model="p.description" class="w-full" maxlength="255" />
            <div class="grid gap-3 sm:grid-cols-3">
              <UFormField label="شهري">
                <UInput v-model="p.monthly" type="number" min="0" step="any" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="مدى الحياة (مرة واحدة)">
                <UInput v-model="p.lifetime" type="number" min="0" step="any" dir="ltr" class="w-full" />
              </UFormField>
              <UFormField label="الصيانة في السنة">
                <UInput v-model="p.maintenance" type="number" min="0" step="any" dir="ltr" class="w-full" />
              </UFormField>
            </div>
            <UFormField label="الأقسام اللي فيها">
              <USelectMenu v-model="p.modules" :items="moduleItems" value-key="value" multiple class="w-full" />
            </UFormField>
          </div>
        </div>
      </UCard>

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <p class="font-bold">
            الأقسام الإضافية
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            اللي المحل بيضيفه فوق باقته. مدى الحياة بيضاف لسعر الشراء والصيانة بتضاف للصيانة السنوية.
          </p>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start">
                  القسم
                </th>
                <th class="p-3 text-start">
                  متاح
                </th>
                <th class="p-3 text-start">
                  شهري
                </th>
                <th class="p-3 text-start">
                  مدى الحياة
                </th>
                <th class="p-3 text-start">
                  صيانة / سنة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in catalog.modules" :key="m.key" class="border-t border-(--ui-border)" :class="m.enabled ? '' : 'opacity-60'">
                <td class="p-3 font-bold">
                  {{ m.name }}
                </td>
                <td class="p-3">
                  <USwitch v-model="m.enabled" :aria-label="`${m.name} متاح`" />
                </td>
                <td class="p-3">
                  <UInput v-model="m.monthly" type="number" min="0" step="any" dir="ltr" class="w-28" />
                </td>
                <td class="p-3">
                  <UInput v-model="m.lifetime" type="number" min="0" step="any" dir="ltr" class="w-28" />
                </td>
                <td class="p-3">
                  <UInput v-model="m.maintenance" type="number" min="0" step="any" dir="ltr" class="w-28" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <div class="flex justify-end">
        <UButton type="submit" icon="i-lucide-check" label="احفظ الأسعار" size="lg" :loading="saving === 'catalog'" />
      </div>
    </form>

    <!-- Switches -->
    <form @submit.prevent="saveSettings">
      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center gap-3">
            <p class="flex-1 font-bold">
              مدى الحياة والاشتراك السنوي
            </p>
            <USwitch v-model="settings.lifetime_enabled" label="مدى الحياة متاح" />
            <USwitch v-model="settings.yearly_enabled" label="الاشتراك السنوي متاح" />
          </div>
        </template>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="الاسم اللي بيظهر">
            <UInput v-model="settings.lifetime_name" class="w-full" maxlength="80" />
          </UFormField>
          <UFormField v-if="settings.yearly_enabled" label="السنة بكام شهر">
            <UInput v-model="settings.yearly_months" type="number" min="1" max="12" dir="ltr" class="w-full" />
          </UFormField>
          <UFormField label="الجملة اللي تحته" class="sm:col-span-2">
            <UInput v-model="settings.lifetime_tagline" class="w-full" maxlength="255" />
          </UFormField>
          <UFormField label="الأقساط المتاحة" hint="كل قسط بيغطي 12 ÷ العدد شهور" class="sm:col-span-2">
            <div class="flex flex-wrap gap-3 pt-1">
              <UCheckbox v-for="n in info.allowed_installments" :key="n" :model-value="settings.lifetime_installments.includes(n)" :label="n === 1 ? 'دفعة واحدة' : `${n} أقساط`" @update:model-value="v => toggleInstallment(n, !!v)" />
            </div>
          </UFormField>
          <UFormField label="خصم الإطلاق %" hint="على سعر مدى الحياة لأول المشترين؛ 0 = مفيش">
            <UInput v-model="settings.launch_discount_percent" type="number" min="0" max="90" dir="ltr" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-2 gap-3">
            <UFormField label="لأول كام محل" hint="فاضي = من غير حد">
              <UInput v-model="settings.launch_seats" type="number" min="1" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="لحد يوم" hint="اختياري">
              <UInput v-model="settings.launch_ends_at" type="date" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="نسبة الشركاء على مدى الحياة %" hint="فاضي = نسبتهم العادية">
            <UInput v-model="settings.lifetime_affiliate_rate_percent" type="number" min="0" max="100" dir="ltr" class="w-full" />
          </UFormField>
          <UCheckbox v-model="settings.lifetime_allow_coupons" class="self-end" label="الكوبونات والخصومات تنفع على مدى الحياة" />
        </div>
        <p v-if="info.launch" class="mt-3 text-sm text-(--ui-success)">
          خصم الإطلاق شغال: <span class="num">{{ info.launch.percent }}%</span>
          <span v-if="info.launch.seats_left !== null"> · فاضل <span class="num">{{ info.launch.seats_left }}</span> محل</span>
        </p>
        <UFormField label="المميزات اللي بتظهر للمحلات وعلى الموقع" class="mt-4">
          <div class="space-y-2">
            <div v-for="(_, i) in settings.lifetime_perks" :key="i" class="flex gap-2">
              <UInput v-model="settings.lifetime_perks[i]" class="w-full" maxlength="160" />
              <UButton color="neutral" variant="ghost" icon="i-lucide-x" square aria-label="شيل" @click="settings.lifetime_perks.splice(i, 1)" />
            </div>
            <UButton v-if="settings.lifetime_perks.length < 12" color="neutral" variant="outline" icon="i-lucide-plus" label="ميزة" size="sm" @click="settings.lifetime_perks.push('')" />
          </div>
        </UFormField>
        <template #footer>
          <div class="flex justify-end">
            <UButton type="submit" icon="i-lucide-check" label="احفظ" :loading="saving === 'settings'" />
          </div>
        </template>
      </UCard>
    </form>

    <!-- Lifetime shops -->
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          المحلات اللي اشترت مدى الحياة
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
                مدفوع لحد
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in info.lifetime_shops" :key="f.tenant_id" class="border-t border-(--ui-border)">
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
                  {{ f.installments === 1 ? 'دفعة واحدة' : `${f.installments} أقساط` }} · صيانة <span class="num">{{ formatMoney(f.maintenance) }}</span> / سنة
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
            <tr v-if="!info.lifetime_shops.length">
              <td colspan="5" class="p-6 text-center text-(--ui-text-muted)">
                لسه محدش اشترى.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { AdminShop, LifetimeInfo } from '~/types/api'

interface Prices { monthly: number, lifetime: number, maintenance: number, enabled: boolean }
interface PlanRow extends Prices { key: string, name: string, description: string, featured: boolean, modules: string[] }
interface ModuleRow extends Prices { key: string, name: string }
interface Settings {
  yearly_enabled: boolean
  yearly_months: number
  lifetime_enabled: boolean
  lifetime_name: string
  lifetime_tagline: string | null
  lifetime_installments: number[]
  lifetime_perks: string[]
  lifetime_affiliate_rate_percent: number | null
  lifetime_allow_coupons: boolean
  launch_discount_percent: number
  launch_seats: number | null
  launch_ends_at: string | null
}
interface PricingInfo {
  plans: PlanRow[]
  modules: ModuleRow[]
  settings: Settings
  open: boolean
  launch: { percent: number, seats: number | null, seats_left: number | null, ends_at: string | null } | null
  allowed_installments: number[]
  stats: { shops: number, pending: number, sold: number, collected: number, owed: number, maintenance_yearly: number }
  lifetime_shops: (LifetimeInfo & { tenant_id: string, shop: AdminShop | null, paid_until: string, status: string })[]
}

const api = useAdminApi()
const toast = useToast()
const { data } = await useAsyncData('admin-pricing', () => api<{ data: PricingInfo }>('/pricing'))
const info = computed(() => data.value?.data)

const stats = computed(() => {
  const s = info.value!.stats
  return [
    { label: 'اشتروا مدى الحياة', value: s.shops },
    { label: 'مستنيين موافقة', value: s.pending },
    { label: 'اتباع بـ', value: formatMoney(s.sold) },
    { label: 'باقي على الأقساط', value: formatMoney(s.owed) },
    { label: 'صيانة في السنة', value: formatMoney(s.maintenance_yearly) },
  ]
})

// The forms hold pounds as text; the API takes piasters.
const pounds = (p: number) => String(p / 100)
type Editable<T> = Omit<T, 'monthly' | 'lifetime' | 'maintenance'> & { monthly: string, lifetime: string, maintenance: string }
const catalog = reactive({ plans: [] as Editable<PlanRow>[], modules: [] as Editable<ModuleRow>[] })
const settings = reactive({
  yearly_enabled: false,
  yearly_months: '10',
  lifetime_enabled: false,
  lifetime_name: '',
  lifetime_tagline: '',
  lifetime_installments: [] as number[],
  lifetime_perks: [] as string[],
  lifetime_affiliate_rate_percent: '',
  lifetime_allow_coupons: false,
  launch_discount_percent: '0',
  launch_seats: '',
  launch_ends_at: '',
})
const moduleItems = computed(() => (info.value?.modules ?? []).map(m => ({ label: m.name, value: m.key })))

function fill(i: PricingInfo) {
  catalog.plans = i.plans.map(p => ({ ...p, modules: [...p.modules], monthly: pounds(p.monthly), lifetime: pounds(p.lifetime), maintenance: pounds(p.maintenance) }))
  catalog.modules = i.modules.map(m => ({ ...m, monthly: pounds(m.monthly), lifetime: pounds(m.lifetime), maintenance: pounds(m.maintenance) }))
  const s = i.settings
  Object.assign(settings, {
    yearly_enabled: s.yearly_enabled,
    yearly_months: String(s.yearly_months),
    lifetime_enabled: s.lifetime_enabled,
    lifetime_name: s.lifetime_name,
    lifetime_tagline: s.lifetime_tagline ?? '',
    lifetime_installments: [...s.lifetime_installments],
    lifetime_perks: [...s.lifetime_perks],
    lifetime_affiliate_rate_percent: s.lifetime_affiliate_rate_percent !== null ? String(s.lifetime_affiliate_rate_percent) : '',
    lifetime_allow_coupons: s.lifetime_allow_coupons,
    launch_discount_percent: String(s.launch_discount_percent),
    launch_seats: s.launch_seats !== null ? String(s.launch_seats) : '',
    launch_ends_at: s.launch_ends_at ? s.launch_ends_at.slice(0, 10) : '',
  })
}
watch(info, i => i && fill(i), { immediate: true })

function monthsOf(lifetime: string, monthly: string) {
  const l = Number(lifetime)
  const m = Number(monthly)
  return l && m ? `${(l / m).toFixed(1)} شهر` : '—'
}

function toggleInstallment(n: number, on: boolean) {
  settings.lifetime_installments = on ? [...new Set([...settings.lifetime_installments, n])].sort((a, b) => a - b) : settings.lifetime_installments.filter(x => x !== n)
}

const saving = ref<'catalog' | 'settings' | null>(null)
async function save(kind: 'catalog' | 'settings', body: Record<string, unknown>) {
  saving.value = kind
  try {
    data.value = await api<{ data: PricingInfo }>(`/pricing/${kind}`, { method: 'PUT', body })
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = null
  }
}

const money = (p: { monthly: string, lifetime: string, maintenance: string }) => ({
  monthly: toPiasters(p.monthly) ?? 0,
  lifetime: toPiasters(p.lifetime) ?? 0,
  maintenance: toPiasters(p.maintenance) ?? 0,
})

const saveCatalog = () => save('catalog', {
  plans: Object.fromEntries(catalog.plans.map(p => [p.key, { name: p.name.trim(), description: p.description?.trim() || null, enabled: p.enabled, featured: p.featured, modules: p.modules, ...money(p) }])),
  modules: Object.fromEntries(catalog.modules.map(m => [m.key, { enabled: m.enabled, ...money(m) }])),
})

const saveSettings = () => save('settings', {
  yearly_enabled: settings.yearly_enabled,
  yearly_months: Number(settings.yearly_months) || 10,
  lifetime_enabled: settings.lifetime_enabled,
  lifetime_name: settings.lifetime_name.trim(),
  lifetime_tagline: settings.lifetime_tagline.trim() || null,
  lifetime_installments: settings.lifetime_installments,
  lifetime_perks: settings.lifetime_perks.map(p => p.trim()).filter(Boolean),
  lifetime_affiliate_rate_percent: settings.lifetime_affiliate_rate_percent !== '' ? Number(settings.lifetime_affiliate_rate_percent) : null,
  lifetime_allow_coupons: settings.lifetime_allow_coupons,
  launch_discount_percent: Number(settings.launch_discount_percent) || 0,
  launch_seats: settings.launch_seats !== '' ? Number(settings.launch_seats) : null,
  launch_ends_at: settings.launch_ends_at || null,
})
</script>

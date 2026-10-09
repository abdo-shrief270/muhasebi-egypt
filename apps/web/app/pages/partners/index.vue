<template>
  <div class="min-h-dvh bg-(--ui-bg-muted)">
    <header class="border-b border-(--ui-border) bg-(--ui-bg)">
      <div class="mx-auto flex h-16 max-w-5xl items-center gap-3 px-4">
        <BrandMark class="size-8" />
        <div class="min-w-0">
          <p class="font-extrabold leading-tight">
            شركاء محاسبي
          </p>
          <p v-if="me" class="truncate text-xs text-(--ui-text-muted)">
            {{ me.affiliate.name }}
          </p>
        </div>
        <UButton class="ms-auto" color="neutral" variant="ghost" icon="i-lucide-log-out" label="خروج" @click="logout" />
      </div>
    </header>

    <main v-if="me" class="mx-auto max-w-5xl space-y-6 p-4">
      <UAlert v-if="me.affiliate.status !== 'active'" color="error" variant="subtle" title="حسابك موقوف" description="مش هتتحسب عمولات جديدة. كلّمنا لو فيه حاجة غلط." />

      <UCard>
        <div class="space-y-4">
          <div>
            <p class="font-bold">
              لينكك
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              أي محل يسجّل منه ويدفع اشتراكه، بتاخد <b class="num">{{ me.program.rate_percent }}%</b> من اللي دفعه لمدة <b class="num">{{ me.program.months }}</b> شهر.
              <template v-if="me.program.welcome_discount">
                والمحل نفسه بياخد خصم <b class="num">{{ me.program.welcome_discount.percent }}%</b> على أول <b class="num">{{ me.program.welcome_discount.months }}</b> شهور.
              </template>
            </p>
          </div>
          <div v-for="l in links" :key="l.url" class="flex flex-wrap items-center gap-2">
            <span class="w-24 text-sm text-(--ui-text-muted)">{{ l.label }}</span>
            <UInput :model-value="l.url" dir="ltr" readonly class="min-w-0 flex-1" />
            <UButton color="neutral" variant="outline" icon="i-lucide-copy" :label="copied === l.url ? 'اتنسخ' : 'انسخ'" @click="copy(l.url)" />
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="w-24 text-sm text-(--ui-text-muted)">الكود</span>
            <span class="num rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-1 text-lg font-bold" dir="ltr">{{ me.affiliate.code }}</span>
            <UButton :to="shareLink" target="_blank" color="success" variant="soft" icon="i-lucide-message-circle" label="ابعته على واتساب" />
          </div>
        </div>
      </UCard>

      <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <UCard v-for="s in stats" :key="s.label">
          <p class="text-sm text-(--ui-text-muted)">
            {{ s.label }}
          </p>
          <p class="num mt-1 text-2xl font-extrabold">
            {{ s.value }}
          </p>
          <p v-if="s.hint" class="text-xs text-(--ui-text-dimmed)">
            {{ s.hint }}
          </p>
        </UCard>
      </div>

      <UCard>
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold">
              فلوسك
            </h2>
            <UButton icon="i-lucide-banknote" label="اسحب المتاح" :disabled="me.balances.available < me.program.min_payout || me.affiliate.status !== 'active'" :loading="requesting" @click="requestPayout" />
          </div>
        </template>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <div v-for="b in balances" :key="b.label">
            <p class="text-sm text-(--ui-text-muted)">
              {{ b.label }}
            </p>
            <p class="num text-xl font-bold" :class="b.class">
              {{ formatMoney(b.value) }}
            </p>
            <p class="text-xs text-(--ui-text-dimmed)">
              {{ b.hint }}
            </p>
          </div>
        </div>
        <p class="mt-4 text-sm text-(--ui-text-muted)">
          أقل سحب {{ formatMoney(me.program.min_payout) }}. العمولة بتفضل متعلّقة {{ me.program.hold_days }} يوم بعد ما المحل يدفع، وبعدين تبقى متاحة.
        </p>
      </UCard>

      <UCard>
        <template #header>
          <h2 class="font-bold">
            هتستلم فلوسك إزاي
          </h2>
        </template>
        <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="savePayout">
          <UFormField label="الطريقة" :error="errors.payout_method">
            <USelect v-model="payout.payout_method" :items="methodItems" placeholder="اختار" class="w-full" />
          </UFormField>
          <UFormField :label="payout.payout_method === 'bank' ? 'رقم الحساب / IBAN' : payout.payout_method === 'instapay' ? 'عنوان InstaPay أو الموبايل' : 'رقم المحفظة'" :error="errors.payout_account">
            <UInput v-model="payout.payout_account" dir="ltr" class="w-full" maxlength="80" />
          </UFormField>
          <UFormField label="الاسم على الحساب" :error="errors.payout_name">
            <UInput v-model="payout.payout_name" class="w-full" maxlength="120" />
          </UFormField>
          <div class="sm:col-span-3 flex justify-end">
            <UButton type="submit" color="neutral" variant="outline" icon="i-lucide-check" label="حفظ" :loading="saving" />
          </div>
        </form>
      </UCard>

      <UCard>
        <template #header>
          <h2 class="font-bold">
            المحلات اللي جت عن طريقك
          </h2>
        </template>
        <p v-if="!me.referrals.length" class="text-sm text-(--ui-text-muted)">
          لسه مفيش. ابعت لينكك لأصحاب المحلات اللي تعرفهم، أو انشره على صفحتك.
        </p>
        <template v-else>
        <div class="-mx-4 overflow-x-auto sm:-mx-6">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  المحل
                </th>
                <th class="p-3 text-start font-bold">
                  سجّل
                </th>
                <th class="p-3 text-start font-bold">
                  أول دفعة
                </th>
                <th class="p-3 text-start font-bold">
                  العمولة لحد
                </th>
                <th class="p-3 text-start font-bold">
                  كسبت منه
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in me.referrals" :key="r.shop + r.registered_at" class="border-t border-(--ui-border)">
                <td class="p-3">
                  {{ r.shop }}
                </td>
                <td class="p-3">
                  {{ formatDate(r.registered_at) }}
                </td>
                <td class="p-3">
                  {{ r.first_paid_at ? formatDate(r.first_paid_at) : 'لسه في التجربة' }}
                </td>
                <td class="p-3">
                  {{ formatDate(r.commission_until) }}
                </td>
                <td class="p-3">
                  <span class="num">{{ formatMoney(r.earned) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        </template>
      </UCard>

      <UCard v-if="me.commissions.length">
        <template #header>
          <h2 class="font-bold">
            العمولات
          </h2>
        </template>
        <div class="-mx-4 overflow-x-auto sm:-mx-6">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  التاريخ
                </th>
                <th class="p-3 text-start font-bold">
                  المحل
                </th>
                <th class="p-3 text-start font-bold">
                  المحل دفع
                </th>
                <th class="p-3 text-start font-bold">
                  عمولتك
                </th>
                <th class="p-3 text-start font-bold">
                  الحالة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in me.commissions" :key="r.id" class="border-t border-(--ui-border)">
                <td class="p-3">
                  {{ formatDate(r.created_at) }}
                </td>
                <td class="p-3">
                  {{ r.shop }}
                </td>
                <td class="p-3">
                  <span class="num">{{ formatMoney(r.base) }}</span>
                </td>
                <td class="p-3">
                  <span class="num">{{ formatMoney(r.amount) }} ({{ r.rate_percent }}%)</span>
                </td>
                <td class="p-3">
                  <UBadge :color="STATES[r.state]?.color ?? 'neutral'" variant="subtle" :label="STATES[r.state]?.label ?? r.state" />
                  <span v-if="r.state === 'held'" class="ms-1 text-xs text-(--ui-text-muted)">لحد {{ formatDate(r.available_at) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <UCard v-if="me.payouts.length">
        <template #header>
          <h2 class="font-bold">
            طلبات السحب
          </h2>
        </template>
        <div class="-mx-4 overflow-x-auto sm:-mx-6">
          <table class="w-full text-sm">
            <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
              <tr>
                <th class="p-3 text-start font-bold">
                  طلبته
                </th>
                <th class="p-3 text-start font-bold">
                  المبلغ
                </th>
                <th class="p-3 text-start font-bold">
                  على
                </th>
                <th class="p-3 text-start font-bold">
                  الحالة
                </th>
                <th class="p-3 text-start font-bold">
                  رقم العملية / ملاحظة
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in me.payouts" :key="r.id" class="border-t border-(--ui-border)">
                <td class="p-3">
                  {{ formatDate(r.created_at) }}
                </td>
                <td class="p-3">
                  <span class="num">{{ formatMoney(r.amount) }}</span>
                </td>
                <td class="p-3">
                  <span dir="ltr">{{ r.account }}</span>
                </td>
                <td class="p-3">
                  <UBadge :color="STATES[r.status]?.color ?? 'neutral'" variant="subtle" :label="STATES[r.status]?.label ?? r.status" />
                </td>
                <td class="p-3">
                  {{ r.reference ?? r.note ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>

      <p class="pb-6 text-center text-xs text-(--ui-text-dimmed)">
        <ULink to="/partners/terms" class="underline">شروط البرنامج</ULink>
      </p>
    </main>
    <div v-else class="mx-auto max-w-5xl space-y-4 p-4">
      <USkeleton class="h-40" />
      <USkeleton class="h-24" />
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ public: true, layout: false })
useHead({ title: 'شركاء محاسبي' })

interface Dashboard {
  affiliate: { name: string, phone: string, email: string | null, code: string, status: string, channel: string | null, payout_method: string | null, payout_account: string | null, payout_name: string | null, rate_percent: number }
  links: { site: string, pricing: string, register: string }
  program: { rate_percent: number, months: number, hold_days: number, min_payout: number, welcome_discount: { percent: number, months: number } | null, payout_methods: Record<string, string> }
  stats: { clicks: number, signups: number, paying: number }
  balances: { held: number, available: number, requested: number, paid: number }
  referrals: { shop: string, registered_at: string, first_paid_at: string | null, commission_until: string | null, earned: number }[]
  commissions: { id: string, shop: string, invoice: string, base: number, rate_percent: number, amount: number, state: string, available_at: string, created_at: string }[]
  payouts: { id: string, amount: number, method: string, account: string, status: string, reference: string | null, note: string | null, created_at: string, decided_at: string | null }[]
}

const api = usePartnerApi()
const auth = usePartnerToken()
const toast = useToast()
const me = ref<Dashboard | null>(null)

onMounted(async () => {
  if (!auth.token.value) {
    await navigateTo('/partners/login')
    return
  }
  fill((await api<{ data: Dashboard }>('/affiliates/me')).data)
})

const payout = reactive({ payout_method: undefined as string | undefined, payout_account: '', payout_name: '' })
function fill(d: Dashboard) {
  me.value = d
  payout.payout_method = d.affiliate.payout_method ?? undefined
  payout.payout_account = d.affiliate.payout_account ?? ''
  payout.payout_name = d.affiliate.payout_name ?? ''
}

const links = computed(() => me.value
  ? [
      { label: 'الموقع', url: me.value.links.site },
      { label: 'الأسعار', url: me.value.links.pricing },
      { label: 'التسجيل', url: me.value.links.register },
    ]
  : [])
const shareLink = computed(() => me.value
  ? `https://wa.me/?text=${encodeURIComponent(`جرّب محاسبي لمحلك: كاشير ومخزون وصيانة وحسابات في برنامج واحد بالعربي.\n${me.value.program.welcome_discount ? `وهتاخد خصم ${me.value.program.welcome_discount.percent}% على أول ${me.value.program.welcome_discount.months} شهور.\n` : ''}${me.value.links.site}`)}`
  : '#')

const copied = ref<string | null>(null)
async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = text
    setTimeout(() => (copied.value = null), 2000)
  }
  catch {
    toast.add({ color: 'error', title: 'مقدرناش ننسخ، انسخه بإيدك' })
  }
}

const stats = computed(() => me.value
  ? [
      { label: 'زيارات من لينكك', value: me.value.stats.clicks.toLocaleString('en-US'), hint: '' },
      { label: 'محلات سجّلت', value: me.value.stats.signups.toLocaleString('en-US'), hint: '' },
      { label: 'محلات دفعت', value: me.value.stats.paying.toLocaleString('en-US'), hint: '' },
      { label: 'كسبت لحد دلوقتي', value: formatMoney(me.value.balances.held + me.value.balances.available + me.value.balances.requested + me.value.balances.paid), hint: '' },
    ]
  : [])
const balances = computed(() => me.value
  ? [
      { label: 'متاح للسحب', value: me.value.balances.available, hint: 'تقدر تطلبه دلوقتي', class: 'text-(--ui-success)' },
      { label: 'متعلّق', value: me.value.balances.held, hint: 'لسه في فترة الانتظار', class: '' },
      { label: 'في طلب سحب', value: me.value.balances.requested, hint: 'بيتراجع ويتحوّل', class: '' },
      { label: 'اتحوّل ليك', value: me.value.balances.paid, hint: 'من الأول', class: '' },
    ]
  : [])

const methodItems = computed(() => Object.entries(me.value?.program.payout_methods ?? {}).map(([value, label]) => ({ value, label })))
const errors = ref<Record<string, string>>({})
const saving = ref(false)
async function savePayout() {
  saving.value = true
  errors.value = {}
  try {
    fill((await api<{ data: Dashboard }>('/affiliates/me', { method: 'PUT', body: { ...payout, payout_name: payout.payout_name || null } })).data)
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}

const requesting = ref(false)
async function requestPayout() {
  requesting.value = true
  try {
    fill((await api<{ data: Dashboard }>('/affiliates/payouts', { method: 'POST' })).data)
    toast.add({ color: 'success', title: 'طلب السحب وصل', description: 'هنحوّلك الفلوس ونبعتلك رقم العملية.' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    requesting.value = false
  }
}

async function logout() {
  await api('/affiliates/logout', { method: 'POST' }).catch(() => {})
  auth.set(null)
  await navigateTo('/partners/login')
}

const STATES: Record<string, { label: string, color: 'neutral' | 'warning' | 'success' | 'info' | 'error' }> = {
  held: { label: 'متعلّق', color: 'warning' },
  available: { label: 'متاح', color: 'success' },
  requested: { label: 'في طلب سحب', color: 'info' },
  paid: { label: 'اتدفع', color: 'neutral' },
  void: { label: 'اتلغى', color: 'error' },
  rejected: { label: 'اترفض', color: 'error' },
}
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-2">
    <!-- Credit and points -->
    <UCard>
      <template #header>
        <p class="font-bold">
          رصيدك ونقاطك
        </p>
      </template>
      <div class="grid grid-cols-2 gap-3">
        <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
          <p class="text-sm text-(--ui-text-muted)">
            الرصيد
          </p>
          <p class="num text-2xl font-extrabold">
            {{ formatMoney(wallet.credit) }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            بيتخصم لوحده من التجديد الجاي
          </p>
        </div>
        <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
          <p class="text-sm text-(--ui-text-muted)">
            النقاط
          </p>
          <p class="num text-2xl font-extrabold">
            {{ wallet.points }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            كل <span class="num">{{ wallet.points_per_pound }}</span> نقطة = 1 ج
          </p>
        </div>
      </div>
      <form v-if="wallet.points >= wallet.min_convert" class="mt-3 flex items-end gap-2" @submit.prevent="convert">
        <UFormField label="حوّل نقاط لرصيد" class="flex-1">
          <UInput v-model="points" type="number" :min="wallet.min_convert" :step="wallet.points_per_pound" dir="ltr" class="w-full" />
        </UFormField>
        <UButton type="submit" icon="i-lucide-repeat" :label="`حوّل (${formatMoney(convertValue)})`" :loading="busy === 'convert'" :disabled="!convertValue" />
      </form>
      <p v-else class="mt-3 text-sm text-(--ui-text-muted)">
        تقدر تحوّل النقاط لرصيد من <span class="num">{{ wallet.min_convert }}</span> نقطة.
      </p>
      <div class="mt-4 space-y-1 text-sm">
        <p class="font-semibold">
          تكسب نقاط لما:
        </p>
        <ul class="space-y-0.5 text-(--ui-text-muted)">
          <li>محل يسجّل بكودك ويدفع: <b class="num text-(--ui-text)">{{ wallet.earn.referral }}</b> نقطة</li>
          <li>تجدد قبل ما الاشتراك يخلص: <b class="num text-(--ui-text)">{{ wallet.earn.early_renewal }}</b></li>
          <li>تشترك سنوي: <b class="num text-(--ui-text)">{{ wallet.earn.yearly }}</b></li>
          <li>تخلّص «ابدأ من هنا»: <b class="num text-(--ui-text)">{{ wallet.earn.onboarding }}</b></li>
        </ul>
      </div>
    </UCard>

    <!-- Invite -->
    <UCard>
      <template #header>
        <p class="font-bold">
          ادعي محل صاحبك
        </p>
      </template>
      <p class="text-sm">
        المحل اللي يسجّل بكودك ياخد <b>خصم <span class="num">{{ referral.welcome.percent }}%</span> أول <span class="num">{{ referral.welcome.months }}</span> شهور</b>،
        وانت تاخد <b class="num">{{ referral.points }}</b> نقطة أول ما يدفع اشتراكه.
      </p>
      <div v-if="referral.code" class="mt-3 space-y-2">
        <div class="flex items-center gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
          <span class="text-sm text-(--ui-text-muted)">الكود</span>
          <span class="num flex-1 text-lg font-extrabold tracking-widest" dir="ltr">{{ referral.code }}</span>
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-copy" aria-label="انسخ الكود" @click="copy(referral.code)" />
        </div>
        <div class="flex flex-wrap gap-2">
          <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-link" label="انسخ لينك التسجيل" @click="copy(referral.link ?? '')" />
          <UButton size="sm" color="success" variant="soft" icon="i-lucide-message-circle" label="ابعته واتساب" :href="`https://wa.me/?text=${encodeURIComponent(inviteText)}`" target="_blank" />
        </div>
      </div>
      <p class="mt-3 text-sm text-(--ui-text-muted)">
        سجّل بكودك <b class="num text-(--ui-text)">{{ referral.joined }}</b> محل، ودفع منهم <b class="num text-(--ui-text)">{{ referral.paid }}</b>.
      </p>
    </UCard>

    <!-- Coupons -->
    <UCard>
      <template #header>
        <p class="font-bold">
          كوبون خصم
        </p>
      </template>
      <form class="flex gap-2" @submit.prevent="redeem">
        <UInput v-model="code" dir="ltr" class="min-w-0 flex-1" placeholder="اكتب الكوبون" aria-label="الكوبون" @update:model-value="v => code = String(v).toUpperCase()" />
        <UButton type="submit" label="استخدم" :loading="busy === 'coupon'" :disabled="code.trim().length < 3" />
      </form>
      <ul v-if="discounts.length" class="mt-3 space-y-1 text-sm">
        <li v-for="d in discounts" :key="d.id" class="flex items-center gap-2 text-(--ui-success)">
          <UIcon name="i-lucide-badge-percent" class="size-4" /> {{ d.label }}
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-(--ui-text-muted)">
        الخصم بيتحسب لوحده في الدفعة الجاية.
      </p>
    </UCard>

    <!-- History -->
    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <template #header>
        <p class="font-bold">
          حركة الرصيد والنقاط
        </p>
      </template>
      <ul v-if="wallet.history.length" class="max-h-72 divide-y divide-(--ui-border) overflow-y-auto">
        <li v-for="t in wallet.history" :key="t.id" class="flex items-center gap-3 px-4 py-2 text-sm">
          <span class="flex-1">
            {{ t.type_label }}<span v-if="t.note" class="text-(--ui-text-muted)"> — {{ t.note }}</span>
            <span class="num block text-xs text-(--ui-text-muted)">{{ formatDate(t.created_at, true) }}</span>
          </span>
          <span class="num font-bold" :class="t.amount > 0 ? 'text-(--ui-success)' : 'text-(--ui-error)'" dir="ltr">
            {{ t.amount > 0 ? '+' : '' }}{{ t.unit === 'credit' ? formatMoney(t.amount) : `${t.amount} نقطة` }}
          </span>
        </li>
      </ul>
      <p v-else class="p-6 text-center text-sm text-(--ui-text-muted)">
        لسه مفيش حركة.
      </p>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { BillingDiscount, BillingReferral, BillingWallet } from '~/types/api'

/** The shop's credit, points, invite code and coupons on «الاشتراك». */
const props = defineProps<{ wallet: BillingWallet, referral: BillingReferral, discounts: BillingDiscount[], shopName: string }>()
const emit = defineEmits<{ changed: [] }>()

const api = useApi()
const toast = useToast()
const busy = ref<string | null>(null)

const points = ref(String(props.wallet.points - (props.wallet.points % props.wallet.points_per_pound)))
watch(() => props.wallet.points, (p) => {
  points.value = String(p - (p % props.wallet.points_per_pound))
})
const convertValue = computed(() => {
  const n = Math.floor(Number(points.value) || 0)
  return n >= props.wallet.min_convert && n <= props.wallet.points && n % props.wallet.points_per_pound === 0 ? (n / props.wallet.points_per_pound) * 100 : 0
})

const inviteText = computed(() => `بستخدم «محاسبي» لإدارة محل ${props.shopName}: كاشير ومخزون وصيانة وحسابات. سجّل بالكود ${props.referral.code} وخد خصم ${props.referral.welcome.percent}% أول ${props.referral.welcome.months} شهور:\n${props.referral.link}`)

async function act(key: string, fn: () => Promise<string>) {
  busy.value = key
  try {
    toast.add({ color: 'success', title: await fn() })
    emit('changed')
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

function convert() {
  return act('convert', async () => {
    const res = await api<{ data: { credit: number } }>('/billing/points/convert', { method: 'POST', body: { points: Number(points.value) } })
    return `اتضاف ${formatMoney(res.data.credit)} لرصيدك`
  })
}

const code = ref('')
function redeem() {
  return act('coupon', async () => {
    const res = await api<{ data: { label: string } }>('/billing/coupon', { method: 'POST', body: { code: code.value.trim() } })
    code.value = ''
    return `تمام: ${res.data.label}`
  })
}

async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ color: 'success', title: 'اتنسخ' })
  }
  catch {
    toast.add({ color: 'error', title: 'مقدرناش ننسخ؛ انسخه بإيدك.' })
  }
}
</script>

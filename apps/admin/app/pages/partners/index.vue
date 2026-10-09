<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-extrabold">
          الشركاء
        </h1>
        <p class="text-sm text-(--ui-text-muted)">
          ناس (مش محلات) بيرشّحوا محاسبي بلينك، وبياخدوا نسبة من اشتراك كل محل جه من عندهم.
          مستحق عليك دلوقتي: <b class="num">{{ formatMoney(meta?.owed ?? 0) }}</b>
        </p>
      </div>
      <UInput v-model="q" icon="i-lucide-search" placeholder="اسم، موبايل أو كود" class="w-64" />
    </div>

    <UCard v-if="payouts.length">
      <template #header>
        <p class="font-bold">
          طلبات سحب مستنية ({{ payouts.length }})
        </p>
      </template>
      <ul class="divide-y divide-(--ui-border)">
        <li v-for="p in payouts" :key="p.id" class="flex flex-wrap items-center gap-3 py-3">
          <div class="min-w-0 flex-1">
            <p class="font-bold">
              <NuxtLink :to="`/partners/${p.affiliate.id}`" class="hover:underline">{{ p.affiliate.name }}</NuxtLink>
              <span class="num ms-2 text-sm text-(--ui-text-muted)" dir="ltr">{{ p.affiliate.code }}</span>
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ p.method_label }}: <span class="num" dir="ltr">{{ p.account }}</span>{{ p.account_name ? ` (${p.account_name})` : '' }} · {{ formatDate(p.created_at, true) }}
            </p>
          </div>
          <p class="num text-lg font-extrabold">
            {{ formatMoney(p.amount) }}
          </p>
          <UButton icon="i-lucide-check" label="اتحوّل" @click="decide(p, 'paid')" />
          <UButton color="error" variant="soft" icon="i-lucide-x" label="رفض" @click="decide(p, 'reject')" />
        </li>
      </ul>
    </UCard>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
            <tr>
              <th class="p-3 text-start">
                الشريك
              </th>
              <th class="p-3 text-start">
                بيسوّق فين
              </th>
              <th class="p-3 text-end">
                زيارات
              </th>
              <th class="p-3 text-end">
                سجّلوا / دفعوا
              </th>
              <th class="p-3 text-end">
                كسب / اتدفعله
              </th>
              <th class="p-3 text-end">
                النسبة
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in affiliates" :key="a.id" class="border-t border-(--ui-border)" :class="a.status === 'active' ? '' : 'opacity-60'">
              <td class="p-3">
                <NuxtLink :to="`/partners/${a.id}`" class="font-bold hover:underline">
                  {{ a.name }}
                </NuxtLink>
                <p class="text-xs text-(--ui-text-muted)">
                  <span class="num" dir="ltr">{{ a.code }}</span> · <span class="num" dir="ltr">{{ localPhone(a.phone) }}</span>
                  <UBadge v-if="a.status !== 'active'" color="error" variant="subtle" size="sm" label="موقوف" class="ms-1" />
                </p>
              </td>
              <td class="p-3 text-xs text-(--ui-text-muted)">
                {{ a.channel || '—' }}
              </td>
              <td class="num p-3 text-end">
                {{ a.clicks }}
              </td>
              <td class="num p-3 text-end">
                {{ a.signups }} / {{ a.paying }}
              </td>
              <td class="num p-3 text-end">
                {{ formatMoney(a.earned) }} / {{ formatMoney(a.paid) }}
              </td>
              <td class="num p-3 text-end">
                {{ a.rate_percent }}%<span v-if="a.custom_rate" class="text-xs text-(--ui-text-muted)"> (خاصة)</span>
              </td>
            </tr>
            <tr v-if="!affiliates.length">
              <td colspan="6" class="p-6 text-center text-(--ui-text-muted)">
                مفيش شركاء لسه. صفحة البرنامج على الموقع: /partners
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </UCard>

    <UModal v-model:open="deciding" :title="action === 'paid' ? 'سجّل التحويل' : 'رفض طلب السحب'">
      <template #body>
        <form class="space-y-4" @submit.prevent="confirm">
          <p v-if="current" class="text-sm">
            {{ current.affiliate.name }}: <b class="num">{{ formatMoney(current.amount) }}</b> على {{ current.method_label }} <span class="num" dir="ltr">{{ current.account }}</span>
          </p>
          <UFormField :label="action === 'paid' ? 'رقم العملية' : 'السبب (هيظهر للشريك)'">
            <UInput v-model="text" class="w-full" :dir="action === 'paid' ? 'ltr' : 'rtl'" autofocus />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="ghost" label="رجوع" @click="deciding = false" />
            <UButton type="submit" :color="action === 'paid' ? 'primary' : 'error'" :label="action === 'paid' ? 'اتحوّل' : 'ارفض'" :loading="busy" :disabled="!text.trim()" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
interface Row { id: string, name: string, phone: string, code: string, status: string, channel: string | null, clicks: number, rate_percent: number, custom_rate: boolean, signups: number, paying: number, earned: number, paid: number }
interface Payout { id: string, amount: number, method_label: string, account: string, account_name: string | null, created_at: string, affiliate: { id: string, name: string, phone: string, code: string } }

const api = useAdminApi()
const toast = useToast()
const q = ref('')

const { data, refresh } = await useAsyncData('admin-affiliates', () => api<{ data: Row[], meta: { requested_payouts: number, owed: number } }>('/affiliates', { query: { q: q.value || undefined } }), { watch: [q] })
const affiliates = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const { data: payoutData, refresh: refreshPayouts } = await useAsyncData('admin-affiliate-payouts', () => api<{ data: Payout[] }>('/affiliate-payouts'))
const payouts = computed(() => payoutData.value?.data ?? [])

const deciding = ref(false)
const action = ref<'paid' | 'reject'>('paid')
const current = ref<Payout | null>(null)
const text = ref('')
const busy = ref(false)

function decide(p: Payout, a: 'paid' | 'reject') {
  current.value = p
  action.value = a
  text.value = ''
  deciding.value = true
}

async function confirm() {
  if (!current.value) return
  busy.value = true
  try {
    await api(`/affiliate-payouts/${current.value.id}/${action.value}`, { method: 'POST', body: action.value === 'paid' ? { reference: text.value.trim() } : { note: text.value.trim() } })
    deciding.value = false
    toast.add({ color: 'success', title: action.value === 'paid' ? 'اتسجّل التحويل' : 'اترفض الطلب' })
    await Promise.all([refresh(), refreshPayouts()])
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = false
  }
}
</script>

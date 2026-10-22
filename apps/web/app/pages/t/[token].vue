<template>
  <div class="mx-auto w-full max-w-md space-y-4 py-6">
    <template v-if="t">
      <div class="text-center">
        <p class="text-xl font-extrabold">
          {{ t.shop?.name }}
        </p>
        <p class="text-(--ui-text-muted)">
          متابعة جهازك · <span class="num">{{ t.reference }}</span>
        </p>
      </div>

      <UCard>
        <p class="text-lg font-bold">
          {{ t.device_name }}
        </p>
        <p v-if="t.faults.length" class="text-sm text-(--ui-text-muted)">
          {{ t.faults.join('، ') }}
        </p>
        <div class="mt-4 rounded-(--ui-radius) bg-(--app-primary-soft) p-3 text-center">
          <p class="text-sm text-(--ui-text-muted)">
            الحالة دلوقتي
          </p>
          <p class="text-2xl font-extrabold text-(--app-primary-strong)">
            {{ t.status_label }}
          </p>
        </div>
        <ol class="mt-4 grid grid-cols-4 gap-1 text-center text-xs">
          <li v-for="(s, i) in t.steps" :key="s.value">
            <span class="mx-auto mb-1 block h-1.5 rounded-full" :class="i <= step ? 'bg-primary' : 'bg-(--ui-bg-elevated)'" />
            <span :class="i <= step ? 'font-bold' : 'text-(--ui-text-muted)'">{{ s.label }}</span>
          </li>
        </ol>
      </UCard>

      <UCard>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              الاستلام
            </dt><dd class="num">
              {{ formatDate(t.received_at, true) }}
            </dd>
          </div>
          <div v-if="t.expected_at && !t.delivered_at" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              التسليم المتوقع
            </dt><dd class="font-bold num">
              {{ formatDate(t.expected_at, true) }}
            </dd>
          </div>
          <div v-if="t.delivered_at" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              اتسلّم
            </dt><dd class="num">
              {{ formatDate(t.delivered_at, true) }}
            </dd>
          </div>
          <div v-if="t.total" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              الحساب
            </dt><dd class="num">
              {{ formatMoney(t.total) }}
            </dd>
          </div>
          <div v-if="t.due" class="flex justify-between font-bold">
            <dt>المطلوب عند الاستلام</dt><dd class="num">
              {{ formatMoney(t.due) }}
            </dd>
          </div>
          <div v-if="t.warranty_until && !t.warranties?.length" class="flex justify-between">
            <dt class="text-(--ui-text-muted)">
              الضمان لحد
            </dt><dd class="num">
              {{ formatDate(t.warranty_until) }}
            </dd>
          </div>
        </dl>
      </UCard>

      <UCard v-if="t.warranties?.length">
        <p class="mb-2 font-bold">
          الضمان
        </p>
        <ul class="space-y-1 text-sm">
          <li v-for="(w, i) in t.warranties" :key="i" class="flex justify-between gap-2">
            <span>{{ w.name }}</span>
            <span class="num" :class="w.state === 'active' ? 'text-success' : 'text-(--ui-text-muted)'">{{ w.state === 'voided' ? 'اتلغى' : `لحد ${formatDate(w.ends_at)}` }}</span>
          </li>
        </ul>
        <p v-if="t.warranty_terms" class="mt-2 text-xs text-(--ui-text-muted)">
          {{ t.warranty_terms }}
        </p>
      </UCard>

      <UButton v-if="t.shop?.phone" :to="`tel:${t.shop.phone}`" block color="neutral" variant="outline" icon="i-lucide-phone" :label="`كلّم المحل ${localPhone(t.shop.phone)}`" />
    </template>
    <p v-else class="py-24 text-center text-(--ui-text-muted)">
      التذكرة دي مش موجودة.
    </p>
    <p class="text-center text-xs text-(--ui-text-muted)">
      <NuxtLink to="/privacy" class="hover:text-primary hover:underline">الخصوصية وبياناتك</NuxtLink>
    </p>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth', public: true })

interface PublicTicket {
  shop: { name: string, phone: string | null } | null
  reference: string
  status: string
  status_label: string
  steps: { value: string, label: string }[]
  device_name: string
  faults: string[]
  received_at: string
  expected_at: string | null
  ready_at: string | null
  delivered_at: string | null
  total: number
  due: number
  warranty_until: string | null
  warranties?: { kind: string, name: string, days: number, ends_at: string, state: string }[]
  warranty_terms?: string | null
}

const route = useRoute()
const config = useRuntimeConfig()
const { data } = await useAsyncData(`public-ticket-${route.params.token}`, () =>
  $fetch<{ data: PublicTicket }>(`${config.public.apiBase}/public/repairs/${route.params.token}`, { headers: { Accept: 'application/json' } }).catch(() => null))
const t = computed(() => data.value?.data ?? null)
// received → repairing → ready → delivered
const step = computed(() => {
  const s = t.value?.status
  return s === 'delivered' ? 3 : s === 'ready' || s === 'rejected' ? 2 : s === 'received' ? 0 : 1
})
</script>

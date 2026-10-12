<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        بلاغات السوق والتقييمات
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        اللي الزوار بلّغوا عنه في «سوق محاسبي»، وتقييمات الزباين للمحلات بعد الطلبات اللي اتسلّمت.
      </p>
    </div>

    <div class="flex flex-wrap gap-1.5">
      <UButton v-for="t in tabs" :key="t.value" size="sm" :color="tab === t.value ? 'primary' : 'neutral'" :variant="tab === t.value ? 'soft' : 'ghost'" :label="t.label" @click="tab = t.value" />
    </div>

    <template v-if="tab === 'reports' || tab === 'resolved'">
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <p v-if="!reports.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
          {{ tab === 'reports' ? 'مفيش بلاغات مفتوحة.' : 'مفيش بلاغات اتقفلت لسه.' }}
        </p>
        <ul v-else class="divide-y divide-(--ui-border)">
          <li v-for="r in reports" :key="r.id" class="flex flex-wrap items-start gap-3 px-4 py-3">
            <div class="min-w-0 flex-1 space-y-0.5">
              <p class="flex flex-wrap items-center gap-2 font-bold">
                {{ r.title ?? r.offer_id }}
                <UBadge color="warning" variant="subtle" size="sm" :label="r.reason_label" />
                <UBadge v-if="r.count > 1" color="neutral" variant="outline" size="sm" :label="`${r.count} بلاغ`" class="num" />
                <UBadge v-if="r.blocked" color="error" variant="subtle" size="sm" label="مستخبي من السوق" />
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                {{ r.shop ?? 'محل مش معروف' }} · {{ formatDate(r.at, true) }}<template v-if="r.contact">
                  · للتواصل: <span dir="ltr" class="num">{{ r.contact }}</span>
                </template>
              </p>
              <p v-if="r.note" class="text-sm">
                «{{ r.note }}»
              </p>
              <p v-if="r.status === 'resolved'" class="text-xs text-(--ui-text-dimmed)">
                {{ r.resolution === 'hidden' ? 'اتخبى' : 'اتقفل من غير إجراء' }} · {{ r.resolved_by_name }}
              </p>
            </div>
            <div class="flex flex-wrap gap-1">
              <UButton v-if="marketUrl" size="xs" color="neutral" variant="ghost" icon="i-lucide-external-link" label="العرض" :href="`${marketUrl}/p/${encodeURIComponent(r.offer_id)}`" target="_blank" />
              <template v-if="r.status === 'new'">
                <UButton size="xs" color="error" variant="soft" icon="i-lucide-eye-off" label="اخفي العرض" :disabled="!r.variant_id" :loading="busy === `${r.id}:hide`" @click="resolve(r, 'hide')" />
                <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-check" label="مفيش مشكلة" :loading="busy === `${r.id}:dismiss`" @click="resolve(r, 'dismiss')" />
              </template>
              <UButton v-else-if="r.blocked" size="xs" color="neutral" variant="outline" icon="i-lucide-eye" label="رجّعه للسوق" :loading="busy === `${r.id}:unblock`" @click="unblock(r)" />
            </div>
          </li>
        </ul>
      </UCard>
    </template>

    <template v-else>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <p v-if="!reviews.length" class="p-6 text-center text-sm text-(--ui-text-muted)">
          مفيش تقييمات لسه.
        </p>
        <ul v-else class="divide-y divide-(--ui-border)">
          <li v-for="r in reviews" :key="r.id" class="flex flex-wrap items-start gap-3 px-4 py-3" :class="r.hidden ? 'opacity-60' : ''">
            <div class="min-w-0 flex-1 space-y-0.5">
              <p class="flex flex-wrap items-center gap-2">
                <span class="text-(--ui-warning)" :aria-label="`${r.rating} من 5`">{{ '★'.repeat(r.rating) }}<span class="text-(--ui-text-dimmed)">{{ '★'.repeat(5 - r.rating) }}</span></span>
                <span class="font-bold">{{ r.shop ?? '—' }}</span>
                <span class="num text-xs text-(--ui-text-muted)" dir="ltr">{{ r.order }}</span>
                <UBadge v-if="r.source === 'market'" color="info" variant="subtle" size="sm" label="من السوق" />
                <UBadge v-if="r.hidden" color="neutral" variant="outline" size="sm" label="مستخبي" />
              </p>
              <p v-if="r.comment" class="text-sm">
                «{{ r.comment }}» — {{ r.name }}
              </p>
              <p class="text-xs text-(--ui-text-dimmed)">
                {{ formatDate(r.at, true) }}
              </p>
            </div>
            <UButton size="xs" color="neutral" variant="outline" :icon="r.hidden ? 'i-lucide-eye' : 'i-lucide-eye-off'" :label="r.hidden ? 'اظهره' : 'اخفيه'" :loading="busy === r.id" @click="toggleReview(r)" />
          </li>
        </ul>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
interface Report {
  id: string
  offer_id: string
  tenant_id: string | null
  variant_id: string | null
  title: string | null
  reason_label: string
  note: string | null
  contact: string | null
  status: 'new' | 'resolved'
  resolution: string | null
  resolved_by_name: string | null
  blocked: boolean
  count: number
  shop: string | null
  at: string
}

interface Review {
  id: string
  shop: string | null
  order: string
  rating: number
  comment: string | null
  name: string
  hidden: boolean
  source: string
  at: string
}

const api = useAdminApi()
const toast = useToast()
const marketUrl = String(useRuntimeConfig().public.marketUrl ?? '').replace(/\/$/, '')

const tabs = [
  { value: 'reports', label: 'بلاغات مفتوحة' },
  { value: 'resolved', label: 'بلاغات اتقفلت' },
  { value: 'reviews', label: 'التقييمات' },
]
const tab = ref('reports')

const reports = ref<Report[]>([])
const reviews = ref<Review[]>([])
async function load() {
  if (tab.value === 'reviews') {
    reviews.value = (await api<{ data: Review[] }>('/market/reviews')).data
  }
  else {
    reports.value = (await api<{ data: Report[] }>('/market/reports', { query: { status: tab.value === 'reports' ? 'new' : 'resolved' } })).data
  }
}
watch(tab, load)
onMounted(load)

const busy = ref<string | null>(null)
async function act(tag: string, fn: () => Promise<unknown>, done: string) {
  busy.value = tag
  try {
    await fn()
    await load()
    toast.add({ color: 'success', title: done })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}
const resolve = (r: Report, action: 'hide' | 'dismiss') => act(`${r.id}:${action}`, () => api(`/market/reports/${r.id}/resolve`, { method: 'POST', body: { action } }), action === 'hide' ? 'العرض اتخبى من السوق' : 'البلاغ اتقفل')
const unblock = (r: Report) => act(`${r.id}:unblock`, () => api('/market/unblock', { method: 'POST', body: { tenant_id: r.tenant_id, variant_id: r.variant_id } }), 'العرض رجع للسوق')
const toggleReview = (r: Review) => act(r.id, () => api(`/market/reviews/${r.id}`, { method: 'PATCH', body: { hidden: !r.hidden } }), r.hidden ? 'التقييم ظاهر' : 'التقييم اتخبى')
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="سوق محاسبي" description="كل محلات محاسبي في موقع واحد للزباين: يدوّروا على الصنف، يقارنوا الأسعار، ويلاقوا أقرب محل ليهم. محلك وأصنافه بيظهروا لوحدهم، وتقدر تخفي اللي انت عايزه." />

    <div v-if="settings" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
      <div class="space-y-6">
        <UCard>
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
              <p class="font-bold">
                محلك في السوق
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                {{ form.listed ? 'ظاهر: أي صنف ليه سعر وموجود في فرع بيظهر للزباين لوحده.' : 'مخفي: محدش من الزباين هيشوف محلك ولا أصنافك في السوق.' }}
              </p>
            </div>
            <USwitch v-model="form.listed" :label="form.listed ? 'ظاهر' : 'مخفي'" @update:model-value="save()" />
          </div>
        </UCard>

        <UCard v-if="form.listed">
          <template #header>
            <h2 class="font-bold">
              الفروع
            </h2>
          </template>
          <ul class="divide-y divide-(--ui-border)">
            <li v-for="b in settings.branches" :key="b.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
              <div class="min-w-0">
                <p class="font-medium">
                  {{ b.name }}
                </p>
                <p class="text-sm" :class="b.located && b.has_governorate ? 'text-(--ui-text-muted)' : 'text-(--ui-warning)'">
                  <template v-if="b.located && b.has_governorate">
                    {{ [b.area, b.governorate_label].filter(Boolean).join('، ') }}
                  </template>
                  <template v-else>
                    مكانه مش متحدد: الزباين مش هيلاقوه في «الأقرب ليك» ولا في فلتر المحافظة.
                    <ULink to="/settings/shop" class="underline">حدده من بيانات المحل</ULink>
                  </template>
                </p>
              </div>
              <USwitch :model-value="!form.hidden_branches.includes(b.id)" :label="form.hidden_branches.includes(b.id) ? 'مخفي' : 'ظاهر'" @update:model-value="toggle(form.hidden_branches, b.id, !$event); save()" />
            </li>
          </ul>
        </UCard>

        <UCard v-if="form.listed">
          <template #header>
            <h2 class="font-bold">
              أقسام مش عايزها تظهر
            </h2>
          </template>
          <div class="space-y-3">
            <p class="text-sm text-(--ui-text-muted)">
              مثلاً قطع الغيار لو بتبيعها للفنيين بس.
            </p>
            <USelectMenu
              v-model="form.hidden_categories"
              :items="settings.categories.map(c => ({ label: c.name, value: c.id }))"
              value-key="value"
              multiple
              placeholder="مفيش أقسام مخفية"
              class="w-full"
              @update:open="(open: boolean) => { if (!open) save() }"
            />
          </div>
        </UCard>

        <UCard v-if="form.listed">
          <template #header>
            <h2 class="font-bold">
              أصناف مش عايزها تظهر
            </h2>
          </template>
          <div class="space-y-3">
            <UInputMenu
              v-model:search-term="productQuery"
              :items="productResults"
              :loading="searching"
              ignore-filter
              placeholder="دوّر على صنف تخفيه…"
              icon="i-lucide-search"
              class="w-full"
              @update:model-value="(item: { id: string, label: string } | undefined) => item && hideProduct(item)"
            />
            <p v-if="!hiddenProducts.length" class="text-sm text-(--ui-text-muted)">
              كل الأصناف ظاهرة.
            </p>
            <div v-else class="flex flex-wrap gap-2">
              <UBadge v-for="p in hiddenProducts" :key="p.id" color="neutral" variant="subtle" size="lg" class="gap-1">
                {{ p.name }}
                <UButton size="xs" color="neutral" variant="link" icon="i-lucide-x" :aria-label="`رجّع ${p.name}`" @click="showProduct(p.id)" />
              </UBadge>
            </div>
          </div>
        </UCard>
      </div>

      <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <UCard>
          <div class="space-y-3 text-sm">
            <p class="flex items-center gap-2 font-bold">
              <UIcon name="i-lucide-shopping-basket" class="size-5 text-(--ui-primary)" />
              الحالة
            </p>
            <p v-if="!settings.status.live" class="rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3">
              <UBadge color="primary" variant="subtle" label="قريباً" class="mb-1" />
              <br>
              السوق لسه ما نزلش للزباين. لما ينزل، محلك هيبقى فيه من أول يوم بالإعدادات دي.
            </p>
            <p v-else>
              <ULink :to="settings.status.url!" target="_blank" class="text-(--ui-primary) underline">
                افتح سوق محاسبي
              </ULink>
            </p>
            <p v-if="!settings.status.subscription_ok" class="text-(--ui-error)">
              محلك مش ظاهر لأن الاشتراك منتهي. <ULink to="/settings/billing" class="underline">جدد الاشتراك</ULink>
            </p>
            <p v-else-if="settings.status.offers !== null && form.listed">
              <span class="num font-bold">{{ settings.status.offers.toLocaleString('ar-EG-u-nu-latn') }}</span> عرض من أصنافك في البحث دلوقتي.
            </p>
            <p class="text-(--ui-text-muted)">
              اللي بيظهر: اسم الصنف، صوره، سعر البيع (سعر الأونلاين لو حاطه)، ومتاح ولا قرب يخلص في كل فرع. التكلفة والباركود وأسعار الجملة عمرها ما تظهر.
            </p>
          </div>
        </UCard>
      </div>
    </div>
    <USkeleton v-else class="h-64" />
  </div>
</template>

<script setup lang="ts">
import type { Paginated, Product } from '~/types/api'

definePageMeta({ permission: 'marketplace.manage' })

interface MarketSettings {
  listed: boolean
  hidden_branches: string[]
  hidden_categories: number[]
  hidden_products: { id: string, name: string }[]
  status: { subscription: string, subscription_ok: boolean, live: boolean, url: string | null, search: boolean, offers: number | null }
  branches: { id: string, name: string, governorate_label: string | null, area: string | null, located: boolean, has_governorate: boolean }[]
  categories: { id: number, name: string }[]
}

const api = useApi()
const toast = useToast()

const settings = ref<MarketSettings | null>(null)
const form = reactive({ listed: true, hidden_branches: [] as string[], hidden_categories: [] as number[] })
const hiddenProducts = ref<{ id: string, name: string }[]>([])

function fill(s: MarketSettings) {
  settings.value = s
  form.listed = s.listed
  form.hidden_branches = [...s.hidden_branches]
  form.hidden_categories = [...s.hidden_categories]
  hiddenProducts.value = [...s.hidden_products]
}

onMounted(async () => {
  fill((await api<{ data: MarketSettings }>('/marketplace/settings')).data)
})

function toggle<T>(list: T[], value: T, on: boolean) {
  const i = list.indexOf(value)
  if (on && i === -1) list.push(value)
  if (!on && i !== -1) list.splice(i, 1)
}

let saving: Promise<void> | null = null
async function save() {
  await saving
  saving = (async () => {
    try {
      const res = await api<{ data: MarketSettings }>('/marketplace/settings', {
        method: 'PUT',
        body: { ...form, hidden_products: hiddenProducts.value.map(p => p.id) },
      })
      fill(res.data)
      toast.add({ color: 'success', title: 'اتحفظ', description: 'السوق هيتحدث في خلال دقيقة.' })
    }
    catch (e) {
      toast.add({ color: 'error', title: apiErrorMessage(e) })
    }
  })()
  await saving
}

const productQuery = ref('')
const productResults = ref<{ id: string, label: string }[]>([])
const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
watch(productQuery, (q) => {
  clearTimeout(timer)
  if (q.trim().length < 2) {
    productResults.value = []
    return
  }
  timer = setTimeout(async () => {
    searching.value = true
    try {
      const res = await api<Paginated<Product>>('/products', { query: { q } })
      productResults.value = res.data
        .filter(p => !hiddenProducts.value.some(h => h.id === p.id))
        .map(p => ({ id: p.id, label: p.name }))
    }
    finally {
      searching.value = false
    }
  }, 250)
})

function hideProduct(item: { id: string, label: string }) {
  hiddenProducts.value.push({ id: item.id, name: item.label })
  productQuery.value = ''
  productResults.value = []
  save()
}

function showProduct(id: string) {
  hiddenProducts.value = hiddenProducts.value.filter(p => p.id !== id)
  save()
}
</script>

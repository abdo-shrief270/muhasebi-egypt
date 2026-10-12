<template>
  <div class="space-y-10 sm:space-y-12">
    <section class="flex flex-col items-center gap-5 text-center">
      <h1 class="text-3xl leading-snug font-bold sm:text-[44px] sm:leading-tight">
        محتاج إيه لموبايلك؟<br><span class="text-teal">هنوريك أقرب محل عنده.</span>
      </h1>
      <div class="w-full max-w-2xl">
        <SearchBox big />
      </div>
      <div class="flex flex-wrap justify-center gap-2">
        <NuxtLink v-for="q in quick" :key="q" :to="{ path: '/search', query: { q } }" class="rounded-full bg-sand px-3.5 py-1.5 text-sm text-ink hover:bg-sand-dark hover:text-ink">
          {{ q }}
        </NuxtLink>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-5">
      <div class="flex flex-col gap-3 lg:col-span-2">
        <div class="flex items-baseline justify-between">
          <h2 class="text-xl font-bold sm:text-2xl">
            محلات {{ place ? place.label : 'في السوق' }}
          </h2>
          <span class="text-[13px] text-muted">{{ place?.point ? 'مرتّبة بالأقرب' : 'اللي عندها أصناف أكتر الأول' }}</span>
        </div>
        <ShopRow v-for="s in shops.slice(0, 5)" :key="s.branch_id" :shop="s" />
        <p v-if="!shops.length" class="card p-6 text-center text-sm text-muted">
          لسه مفيش محلات هنا. جرّب محافظة تانية.
        </p>
        <NuxtLink v-if="shops.length > 5" to="/shops" class="text-sm font-semibold">
          كل المحلات
        </NuxtLink>
      </div>
      <ClientOnly>
        <MarketMap
          class="min-h-80 border border-line lg:col-span-3 lg:min-h-[470px]"
          :pins="shopPins"
          :me="place?.point ?? null"
          :hint="place?.point ? '' : 'اسمح بالموقع عشان تشوف الأقرب ليك'"
        />
        <template #fallback>
          <div class="min-h-80 rounded-2xl border border-line bg-sand-dark lg:col-span-3 lg:min-h-[470px]" />
        </template>
      </ClientOnly>
    </section>

    <section class="space-y-3">
      <div class="flex items-baseline justify-between">
        <h2 class="text-xl font-bold sm:text-2xl">
          {{ place ? 'متاح دلوقتي قريب منك' : 'جديد في السوق' }}
        </h2>
        <NuxtLink :to="{ path: '/search', query: place?.point ? { sort: 'nearest' } : {} }" class="text-sm font-semibold">
          كل العروض
        </NuxtLink>
      </div>
      <div class="card overflow-hidden">
        <OfferRow v-for="o in offers" :key="o.id" :offer="o" />
        <p v-if="!offers.length" class="p-6 text-center text-sm text-muted">
          مفيش عروض لسه.
        </p>
      </div>
    </section>

    <section class="grid gap-5 sm:grid-cols-2">
      <div class="flex flex-col gap-2.5 rounded-2xl bg-ink p-7 text-paper">
        <span class="text-xl font-bold sm:text-2xl">موبايلك محتاج صيانة؟</span>
        <span class="text-[15px] opacity-85">احجز في محل صيانة قريب منك، أو دوّر على قطعة الغيار بنفسك.</span>
        <span class="mt-1.5 flex flex-wrap gap-2">
          <NuxtLink to="/repair" class="btn bg-paper text-ink hover:bg-white hover:text-ink">
            احجز صيانة
          </NuxtLink>
          <NuxtLink to="/search?category=part" class="btn border border-paper/40 text-paper hover:bg-paper/10 hover:text-paper">
            قطع الغيار حواليك
          </NuxtLink>
        </span>
      </div>
      <div class="flex flex-col gap-2.5 rounded-2xl bg-mint p-7">
        <span class="text-xl font-bold sm:text-2xl">مستعمل من محل قريب</span>
        <span class="text-[15px] text-[#33494A]">شوف الجهاز بنفسك قبل ما تشتري. كل جهاز متسجّل في المحل بدرجته.</span>
        <NuxtLink to="/used" class="btn-teal mt-1.5 self-start">
          المستعمل حواليك
        </NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { SearchResult, ShopRow } from '~/types'

const quick = ['شاحن تايب سي', 'جراب ايفون', 'اسكرينة', 'بطارية', 'سماعة بلوتوث']
const { place, query } = usePlace()

// Rendered for everyone first (cached), then again for the visitor's place.
const { data: shopsData } = await useAsyncData('home-shops', () => marketFetch<{ data: ShopRow[] }>('/shops', { ...query.value, limit: 30 }).then(r => r.data).catch(() => []), { watch: [query] })
const { data: offersData } = await useAsyncData('home-offers', () => marketFetch<{ data: SearchResult }>('/search', {
  ...query.value,
  sort: place.value?.point ? 'nearest' : 'new',
  per_page: 8,
}).then(r => r.data.items).catch(() => []), { watch: [query] })

const shops = computed(() => shopsData.value ?? [])
const offers = computed(() => offersData.value ?? [])
const shopPins = computed(() => shops.value.filter(s => s.location).map(s => ({
  ...s.location!,
  label: s.distance_km !== null ? `${s.name} · ${formatKm(s.distance_km)}` : s.name,
  to: `/s/${s.slug}`,
})))

useSeoMeta({
  title: 'إكسسوارات وقطع غيار وموبايلات من أقرب محل',
  description: 'دوّر على إكسسوارات وقطع غيار وموبايلات جديدة ومستعملة في المحلات اللي حواليك، وشوف السعر والمسافة قبل ما تنزل.',
})
</script>

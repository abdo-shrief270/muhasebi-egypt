<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold sm:text-3xl">
      المحلات {{ place ? place.label : 'في السوق' }}
    </h1>
    <ClientOnly>
      <MarketMap class="h-72 border border-line sm:h-96" :pins="pins" :me="place?.point ?? null" />
    </ClientOnly>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <ShopRow v-for="s in shops" :key="s.branch_id" :shop="s" />
    </div>
    <p v-if="!shops.length" class="card p-6 text-center text-muted">
      لسه مفيش محلات هنا.
    </p>
  </div>
</template>

<script setup lang="ts">
import type { ShopRow } from '~/types'

const { place, query } = usePlace()
const { data } = await useAsyncData('shops', () => marketFetch<{ data: ShopRow[] }>('/shops', { ...query.value, limit: 100 }).then(r => r.data).catch(() => []), { watch: [query] })
const shops = computed(() => data.value ?? [])
const pins = computed(() => shops.value.filter(s => s.location).map(s => ({ ...s.location!, label: s.name, to: `/s/${s.slug}` })))

useSeoMeta({ title: 'محلات الموبايلات والإكسسوارات', description: 'محلات الموبايلات والإكسسوارات والصيانة على الخريطة، بالأقرب ليك وعدد الأصناف المتاحة في كل محل.' })
</script>

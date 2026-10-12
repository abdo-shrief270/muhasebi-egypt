<template>
  <div class="space-y-6">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold sm:text-3xl">
        أسعار الموبايلات في مصر النهارده
      </h1>
      <p class="text-muted">
        أقل وأعلى سعر لكل موبايل، جديد ومستعمل، من مخزون المحلات اللي على سوق محاسبي. بيتحدّث لوحده.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <input v-model="q" type="search" placeholder="دوّر على موبايل…" class="h-11 w-full rounded-xl border border-line-strong bg-white px-4 sm:w-72" aria-label="دوّر على موبايل">
      <label class="flex items-center gap-2 text-sm">
        <input v-model="onSale" type="checkbox" class="size-4 accent-teal"> المتاح بس
      </label>
    </div>
    <div class="flex flex-wrap gap-2" role="group" aria-label="الماركة">
      <NuxtLink :to="{ query: {} }" class="chip" :class="!brand ? 'chip-on' : 'chip-off'">
        الكل
      </NuxtLink>
      <NuxtLink v-for="b in brands" :key="b.key" :to="{ query: { brand: b.key } }" class="chip" :class="brand === b.key ? 'chip-on' : 'chip-off'">
        {{ b.key }}
      </NuxtLink>
    </div>

    <div v-if="rows.length" class="card overflow-x-auto">
      <table class="w-full min-w-[560px] text-sm">
        <thead class="bg-sand text-start text-muted">
          <tr>
            <th class="px-4 py-3 text-start font-semibold">
              الموبايل
            </th>
            <th class="px-4 py-3 text-start font-semibold">
              جديد
            </th>
            <th class="px-4 py-3 text-start font-semibold">
              مستعمل من
            </th>
            <th class="px-4 py-3 text-start font-semibold">
              المحلات
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in rows" :key="p.slug" class="border-t border-line">
            <td class="px-4 py-3">
              <NuxtLink v-if="has(p)" :to="`/phones/${p.slug}`" class="font-semibold"><bdi>{{ p.name }}</bdi></NuxtLink>
              <bdi v-else class="text-muted">{{ p.name }}</bdi>
              <span v-if="p.year" class="num ms-1 text-xs text-muted">{{ p.year }}</span>
            </td>
            <td class="num px-4 py-3 font-semibold whitespace-nowrap">
              {{ p.prices.new ? range(p.prices.new.min, p.prices.new.max) : '—' }}
            </td>
            <td class="num px-4 py-3 whitespace-nowrap">
              {{ p.prices.used ? formatPrice(p.prices.used.min) : '—' }}
            </td>
            <td class="num px-4 py-3 text-muted">
              {{ (p.prices.new?.shops ?? 0) + (p.prices.used?.shops ?? 0) || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="card p-6 text-center text-muted">
      {{ onSale ? 'مفيش موبايلات متاحة هنا دلوقتي. شيل «المتاح بس» تشوف القايمة كلها.' : 'مفيش موبايلات بالبحث ده.' }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { PhoneRow } from '~/types'

const route = useRoute()
const brand = computed(() => (typeof route.query.brand === 'string' ? route.query.brand : ''))

const { data } = await useAsyncData('phones', () => marketFetch<{ data: { phones: PhoneRow[], brands: { key: string, count: number }[] } }>('/phones').then(r => r.data).catch(() => null))

const q = ref('')
const onSale = ref(true)
const has = (p: PhoneRow) => !!(p.prices.new || p.prices.used)
const fold = (s: string) => s.toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي')
const brands = computed(() => data.value?.brands ?? [])
const rows = computed(() => {
  const words = fold(q.value.trim()).split(/\s+/).filter(Boolean)
  return (data.value?.phones ?? []).filter(p => (!brand.value || p.brand === brand.value)
    && (!onSale.value || has(p))
    && words.every(w => fold(`${p.name} ${p.series ?? ''}`).includes(w)))
})
const range = (min: number, max: number) => (min === max ? formatPrice(min) : `${formatPrice(min)} – ${formatPrice(max)}`)

useSeoMeta({
  title: () => (brand.value ? `أسعار موبايلات ${brand.value} في مصر النهارده` : 'أسعار الموبايلات في مصر النهارده'),
  description: () => `أقل وأعلى سعر لكل موبايل${brand.value ? ` ${brand.value}` : ''} جديد ومستعمل من محلات مصر، بيتحدّث كل دقيقة من مخزون المحلات.`,
})
</script>

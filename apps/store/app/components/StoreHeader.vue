<template>
  <header class="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2.5">
      <NuxtLink :to="place.path()" class="flex min-w-0 items-center gap-2">
        <img v-if="store.logo" :src="store.logo['128']" :alt="store.name" width="40" height="40" class="size-10 rounded-xl object-cover">
        <span v-else class="flex size-10 items-center justify-center rounded-xl bg-brand text-lg font-extrabold text-white">{{ store.name.slice(0, 1) }}</span>
        <span class="truncate text-base font-extrabold sm:text-lg">{{ store.name }}</span>
      </NuxtLink>
      <form class="relative ms-auto hidden max-w-md flex-1 sm:block" role="search" @submit.prevent="search">
        <input v-model="q" type="search" class="input pe-10" placeholder="دوّر على صنف أو موبايل…" aria-label="بحث">
        <button type="submit" class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-muted" aria-label="ابحث">
          <StoreIcon name="search" />
        </button>
      </form>
      <span v-if="!store.show_prices" class="ms-auto sm:hidden" />
      <NuxtLink v-if="store.show_prices" :to="place.path('/cart')" class="relative ms-auto flex size-11 items-center justify-center rounded-xl hover:bg-soft sm:ms-0" aria-label="السلة">
        <StoreIcon name="cart" :size="22" />
        <span v-if="cart.count.value" class="num absolute -top-0.5 -end-0.5 min-w-5 rounded-full bg-brand px-1 text-center text-xs font-bold text-white">{{ cart.count.value }}</span>
      </NuxtLink>
    </div>
    <form class="relative px-4 pb-2.5 sm:hidden" role="search" @submit.prevent="search">
      <input v-model="q" type="search" class="input pe-10" placeholder="دوّر على صنف أو موبايل…" aria-label="بحث">
      <button type="submit" class="absolute inset-y-0 end-4 flex w-10 items-center justify-center text-muted" aria-label="ابحث">
        <StoreIcon name="search" />
      </button>
    </form>
  </header>
</template>

<script setup lang="ts">
import type { PublicStore } from '~/types'

const props = defineProps<{ store: PublicStore }>()
const route = useRoute()
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const cart = useCart(props.store.slug)
const place = useStorePlace()

function search() {
  const term = q.value.trim()
  if (term) {
    navigateTo({ path: place.path('/search'), query: { q: term } })
  }
}
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-2">
    <section aria-label="صور الصنف" class="space-y-3">
      <div class="card relative aspect-square overflow-hidden bg-white">
        <img
          v-if="current"
          :src="current.urls['800']"
          :srcset="srcset(current.urls)"
          sizes="(min-width: 1024px) 560px, 100vw"
          :alt="product.name"
          width="800"
          height="800"
          fetchpriority="high"
          class="size-full object-contain p-3"
        >
        <div v-else class="flex size-full items-center justify-center text-line">
          <StoreIcon name="image" :size="64" />
        </div>
      </div>
      <div v-if="product.images.length > 1" class="flex gap-2 overflow-x-auto">
        <button
          v-for="(img, i) in product.images"
          :key="img.id"
          type="button"
          class="card size-16 shrink-0 overflow-hidden"
          :class="i === shown ? 'ring-2 ring-brand' : ''"
          :aria-label="`صورة ${i + 1}`"
          @click="shown = i"
        >
          <img :src="img.urls['320']" alt="" loading="lazy" width="64" height="64" class="size-full object-contain p-1">
        </button>
      </div>
    </section>

    <section class="space-y-5">
      <div class="space-y-1">
        <NuxtLink :to="`/${slug}/c/${product.category.id}`" class="text-sm font-semibold text-brand">
          {{ product.category.name }}
        </NuxtLink>
        <h1 class="text-2xl font-extrabold leading-snug">
          {{ product.name }}
        </h1>
        <p v-if="product.brand" class="text-sm text-muted">
          {{ product.brand }}
        </p>
      </div>

      <div class="flex items-end justify-between gap-3">
        <p class="num text-3xl font-extrabold text-brand">
          {{ formatPrice(variant.price) }}
        </p>
        <p class="text-sm font-bold" :class="AVAILABILITY[variant.availability]!.class">
          {{ AVAILABILITY[variant.availability]!.label }}
          <span v-if="variant.quantity !== undefined && variant.availability !== 'out'" class="num font-normal text-muted">({{ variant.quantity }})</span>
        </p>
      </div>

      <div v-if="product.variants.length > 1" class="space-y-2">
        <p class="text-sm font-semibold">
          اختار النوع
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="v in product.variants"
            :key="v.id"
            type="button"
            class="chip"
            :class="[v.id === variant.id ? 'chip-on' : '', v.availability === 'out' ? 'opacity-50 line-through' : '']"
            @click="variantId = v.id"
          >
            {{ variantLabel(v) }}
          </button>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center rounded-xl border border-line bg-white">
          <button type="button" class="flex size-11 items-center justify-center" aria-label="أقل" @click="qty = Math.max(1, qty - 1)">
            <StoreIcon name="minus" :size="18" />
          </button>
          <span class="num w-8 text-center font-bold">{{ qty }}</span>
          <button type="button" class="flex size-11 items-center justify-center" aria-label="أكتر" @click="qty = Math.min(99, qty + 1)">
            <StoreIcon name="plus" :size="18" />
          </button>
        </div>
        <button type="button" class="btn-brand flex-1" :disabled="variant.availability === 'out'" @click="addToCart">
          <StoreIcon name="cart" :size="18" /> {{ added ? 'اتضاف للسلة ✓' : 'ضيف للسلة' }}
        </button>
      </div>
      <a v-if="store.whatsapp && variant.availability !== 'out'" :href="orderNow" target="_blank" rel="noopener" class="btn-wa w-full">
        <StoreIcon name="whatsapp" :size="18" /> اطلبه على واتساب
      </a>

      <div v-if="product.device_models.length" class="space-y-2">
        <p class="text-sm font-semibold">
          بيركب على
        </p>
        <div class="flex flex-wrap gap-2">
          <NuxtLink v-for="m in product.device_models" :key="m.id" :to="`/${slug}/m/${m.id}`" class="chip" dir="ltr">
            {{ m.full_name }}
          </NuxtLink>
        </div>
      </div>

      <div v-if="product.description" class="space-y-2">
        <p class="text-sm font-semibold">
          الوصف
        </p>
        <p class="whitespace-pre-line leading-relaxed text-ink/90">
          {{ product.description }}
        </p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { ProductDetail, ProductVariant } from '~/types'

const slug = useStoreSlug()
const route = useRoute()
const config = useRuntimeConfig()
const home = await useStoreHome()
const store = computed(() => home.value.store)
const id = String(route.params.id)

const { data, error } = await useFetch<{ data: ProductDetail }>(`/api/stores/${slug}/products/${id}`, { key: `product:${slug}:${id}` })
if (error.value || !data.value) {
  throw createError({ statusCode: error.value?.statusCode === 404 ? 404 : 502, fatal: true })
}
const product = computed(() => data.value!.data)

const shown = ref(0)
const current = computed(() => product.value.images[shown.value] ?? product.value.image)
const firstAvailable = product.value.variants.find(v => v.availability !== 'out') ?? product.value.variants[0]!
const variantId = ref(firstAvailable.id)
const variant = computed(() => product.value.variants.find(v => v.id === variantId.value) ?? firstAvailable)
const qty = ref(1)
const added = ref(false)

function variantLabel(v: ProductVariant): string {
  return [v.name, v.quality_label].filter(Boolean).join(' · ') || product.value.name
}

const cart = useCart(slug)
function addToCart() {
  cart.add({
    variantId: variant.value.id,
    productId: product.value.id,
    name: product.value.name,
    variant: product.value.variants.length > 1 ? variantLabel(variant.value) : null,
    price: variant.value.price,
    image: product.value.image?.urls['320'] ?? null,
  }, qty.value)
  added.value = true
  setTimeout(() => (added.value = false), 2000)
}

const url = computed(() => `${config.public.storeUrl}/${slug}/p/${product.value.id}`)
const orderNow = computed(() => whatsappLink(store.value.whatsapp ?? '', [
  `السلام عليكم، عايز أطلب من ${store.value.name}:`,
  `${qty.value} × ${product.value.name}${product.value.variants.length > 1 ? ` (${variantLabel(variant.value)})` : ''} — ${formatPrice(variant.value.price * qty.value)}`,
  url.value,
].join('\n')))

const description = computed(() => (product.value.description ?? `${product.value.name} من ${store.value.name}`).slice(0, 160))
useSeoMeta({
  title: () => product.value.name,
  description,
  ogTitle: () => `${product.value.name} — ${formatPrice(product.value.price)}`,
  ogDescription: description,
  ogUrl: url,
  ogImage: () => (product.value.image ? `${config.public.storeUrl}${product.value.image.urls['800']}` : undefined),
})
useHead(() => ({
  // og:type "product" (Facebook / WhatsApp previews); useSeoMeta's typing only knows the generic ones.
  meta: [{ property: 'og:type', content: 'product' }],
  link: [{ rel: 'canonical', href: url.value }],
  script: [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Product',
      'name': product.value.name,
      'description': product.value.description ?? undefined,
      'image': product.value.images.map(i => `${config.public.storeUrl}${i.urls['800']}`),
      'category': product.value.category.name,
      ...(product.value.brand ? { brand: { '@type': 'Brand', 'name': product.value.brand } } : {}),
      'offers': {
        '@type': product.value.variants.length > 1 ? 'AggregateOffer' : 'Offer',
        'priceCurrency': 'EGP',
        ...(product.value.variants.length > 1
          ? { lowPrice: product.value.price / 100, highPrice: product.value.price_max / 100, offerCount: product.value.variants.length }
          : { price: product.value.price / 100 }),
        'availability': product.value.availability === 'out' ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        'url': url.value,
        'seller': { '@type': 'Organization', 'name': store.value.name },
      },
    }),
  }],
}))
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <h1 class="text-2xl font-extrabold">
      السلة
    </h1>

    <div v-if="!cart.lines.value.length" class="card space-y-4 px-4 py-12 text-center">
      <p class="text-muted">
        السلة فاضية.
      </p>
      <NuxtLink :to="place.path()" class="btn-brand">
        كمّل تسوّق
      </NuxtLink>
    </div>

    <template v-else>
      <ul class="card divide-y divide-line">
        <li v-for="line in cart.lines.value" :key="line.variantId" class="flex items-center gap-3 p-3">
          <img v-if="line.image" :src="line.image" alt="" width="64" height="64" class="size-16 shrink-0 rounded-xl bg-soft object-contain p-1">
          <div class="min-w-0 flex-1">
            <NuxtLink :to="place.path(`/p/${line.productId}`)" class="line-clamp-2 text-sm font-semibold">
              {{ line.name }}
            </NuxtLink>
            <p v-if="line.variant" class="text-xs text-muted">
              {{ line.variant }}
            </p>
            <p class="num text-sm font-bold text-brand">
              {{ formatPrice(line.price * line.qty) }}
            </p>
            <p v-if="gone.has(line.variantId)" class="text-xs font-bold text-bad">
              مش متوفر دلوقتي
            </p>
            <p v-else-if="short.has(line.variantId)" class="text-xs font-bold text-bad">
              المتوفر {{ short.get(line.variantId) }} بس
            </p>
          </div>
          <div class="flex items-center rounded-xl border border-line">
            <button type="button" class="flex size-9 items-center justify-center" :aria-label="`أقل ${line.name}`" @click="cart.setQty(line.variantId, line.qty - 1)">
              <StoreIcon :name="line.qty === 1 ? 'x' : 'minus'" :size="16" />
            </button>
            <span class="num w-6 text-center text-sm font-bold">{{ line.qty }}</span>
            <button type="button" class="flex size-9 items-center justify-center" :aria-label="`أكتر ${line.name}`" @click="cart.setQty(line.variantId, line.qty + 1)">
              <StoreIcon name="plus" :size="16" />
            </button>
          </div>
        </li>
      </ul>

      <p v-if="changed" class="rounded-xl bg-warn/10 px-3 py-2 text-sm text-warn">
        الأسعار اتحدثت من المحل.
      </p>

      <!-- Orders saved at the shop -->
      <form v-if="ordering" class="card space-y-4 p-4" @submit.prevent="placeOrder">
        <h2 class="font-extrabold">
          بياناتك
        </h2>
        <input v-model="name" class="input" autocomplete="name" placeholder="اسمك" minlength="2" maxlength="80" required>
        <input v-model="phone" class="input num" type="tel" inputmode="tel" autocomplete="tel" placeholder="رقم موبايلك (01xxxxxxxxx)" dir="ltr" pattern="^(\+?20|0)?1[0125][0-9]{8}$" required>
        <!-- Bots fill every field; people never see this one. -->
        <input v-model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset v-if="ordering.pickup && ordering.delivery" class="flex gap-2">
          <legend class="sr-only">
            الاستلام
          </legend>
          <button type="button" class="chip flex-1 justify-center" :class="delivery ? '' : 'chip-on'" @click="delivery = false">
            <StoreIcon name="store" :size="16" /> هستلم من المحل
          </button>
          <button type="button" class="chip flex-1 justify-center gap-1" :class="delivery ? 'chip-on' : ''" @click="delivery = true">
            <StoreIcon name="truck" :size="16" /> توصيل
          </button>
        </fieldset>
        <p v-else class="text-sm text-muted">
          {{ ordering.delivery ? 'المحل بيوصّل الطلب.' : 'هتستلم الطلب من المحل.' }}
        </p>

        <template v-if="delivery">
          <select v-model="zoneId" class="input" required>
            <option value="" disabled>
              اختار منطقتك
            </option>
            <option v-for="z in zones" :key="z.id" :value="z.id">
              {{ z.name }} — {{ z.fee ? formatPrice(z.fee) : 'ببلاش' }}
            </option>
          </select>
          <textarea v-model="address" class="input" rows="2" autocomplete="street-address" placeholder="العنوان بالتفصيل (الشارع، رقم العمارة، الدور، علامة مميزة)" maxlength="500" required />
        </template>
        <textarea v-model="notes" class="input" rows="2" placeholder="ملاحظات (اختياري)" maxlength="500" />

        <fieldset v-if="ordering.pay_cod && ordering.pay_transfer" class="space-y-2">
          <legend class="mb-2 font-bold">
            الدفع
          </legend>
          <label class="flex items-center gap-2">
            <input v-model="payment" type="radio" value="cod" class="accent-(--brand)">
            {{ delivery ? 'كاش عند الاستلام' : 'كاش في المحل' }}
          </label>
          <label class="flex items-center gap-2">
            <input v-model="payment" type="radio" value="transfer" class="accent-(--brand)">
            تحويل InstaPay / محفظة
          </label>
        </fieldset>
        <div v-if="payment === 'transfer'" class="space-y-2 rounded-xl bg-soft p-3 text-sm">
          <p>حوّل <b class="num">{{ formatPrice(total) }}</b> على:</p>
          <p v-if="ordering.transfer_instapay">
            InstaPay: <b class="num" dir="ltr">{{ ordering.transfer_instapay }}</b>
          </p>
          <p v-if="ordering.transfer_wallet">
            محفظة: <b class="num" dir="ltr">{{ localPhone(ordering.transfer_wallet) }}</b>
          </p>
          <label class="btn-line w-full cursor-pointer">
            <StoreIcon name="upload" :size="16" />
            {{ proof ? 'غيّر صورة التحويل' : 'ارفع صورة التحويل' }}
            <input type="file" accept="image/*" class="sr-only" @change="pickProof">
          </label>
          <img v-if="proofUrl" :src="proofUrl" alt="صورة التحويل" class="mx-auto max-h-48 rounded-lg">
        </div>

        <dl class="space-y-1 border-t border-line pt-3 text-sm">
          <div class="flex justify-between">
            <dt>الأصناف</dt><dd class="num">
              {{ formatPrice(cart.total.value) }}
            </dd>
          </div>
          <div v-if="delivery" class="flex justify-between">
            <dt>التوصيل</dt><dd class="num">
              {{ zone ? (fee ? formatPrice(fee) : 'ببلاش') : '—' }}
            </dd>
          </div>
          <div class="flex justify-between text-lg font-extrabold">
            <dt>الإجمالي</dt><dd class="num text-brand">
              {{ formatPrice(total) }}
            </dd>
          </div>
          <p v-if="freeHint" class="text-xs text-ok">
            {{ freeHint }}
          </p>
        </dl>

        <label class="flex items-start gap-2 text-sm">
          <input v-model="consent" type="checkbox" class="mt-1 accent-(--brand)">
          <span>احفظوا بياناتي عند {{ store.name }} للطلبات الجاية. <span class="text-muted">(تقدر تطلب مسحها في أي وقت)</span></span>
        </label>

        <p v-if="!ordering.open_now && ordering.hours" class="rounded-xl bg-warn/10 px-3 py-2 text-sm text-warn">
          المحل بيستقبل الطلبات من <span class="num" dir="ltr">{{ ordering.hours.from }}</span> لـ <span class="num" dir="ltr">{{ ordering.hours.until }}</span>. السلة هتفضل محفوظة لحد ما تطلب.
        </p>
        <p v-if="belowMin" class="rounded-xl bg-warn/10 px-3 py-2 text-sm text-warn">
          أقل طلب {{ formatPrice(ordering.min_order) }}.
        </p>
        <p v-if="error" class="rounded-xl bg-bad/10 px-3 py-2 text-sm font-bold text-bad" role="alert">
          {{ error }}
        </p>
        <button type="submit" class="btn-brand w-full py-3 text-base" :disabled="sending || belowMin || gone.size > 0 || !ordering.open_now">
          {{ sending ? 'بنبعت الطلب…' : `اطلب (${formatPrice(total)})` }}
        </button>
        <p class="text-xs text-muted">
          المحل هيأكد معاك الطلب، وتقدر تتابعه من صفحة الطلب.
        </p>
      </form>

      <!-- «واتساب بس» -->
      <template v-else>
        <p class="flex items-center justify-between text-lg font-extrabold">
          الإجمالي <span class="num text-brand">{{ formatPrice(cart.total.value) }}</span>
        </p>
        <form class="card space-y-3 p-4" @submit.prevent="send">
          <h2 class="font-extrabold">
            بياناتك
          </h2>
          <input v-model="name" class="input" autocomplete="name" placeholder="اسمك" maxlength="80" required>
          <div class="flex gap-2">
            <button type="button" class="chip flex-1 justify-center" :class="delivery ? '' : 'chip-on'" @click="delivery = false">
              هستلم من المحل
            </button>
            <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'chip-on' : ''" @click="delivery = true">
              توصيل
            </button>
          </div>
          <input v-if="delivery" v-model="address" class="input" autocomplete="street-address" placeholder="العنوان (المنطقة، الشارع، علامة مميزة)" maxlength="200" required>
          <textarea v-model="notes" class="input" rows="2" placeholder="ملاحظات (اختياري)" maxlength="300" />
          <button type="submit" class="btn-wa w-full" :disabled="!store.whatsapp">
            <StoreIcon name="whatsapp" :size="18" /> ابعت الطلب للمحل على واتساب
          </button>
          <p class="text-xs text-muted">
            الطلب بيروح للمحل رسالة على واتساب، وهو بيأكد معاك السعر والتوصيل.
          </p>
        </form>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PlacedOrder, ProductDetail } from '~/types'

const slug = useStoreSlug()
const place = useStorePlace()
const home = await useStoreHome()
const store = computed(() => home.value.store)
const ordering = computed(() => store.value.mode === 'orders' ? store.value.ordering : null)
const zones = computed(() => home.value.zones ?? [])
const cart = useCart(slug)
const name = ref('')
const phone = ref('')
const address = ref('')
const notes = ref('')
const website = ref('')
const delivery = ref(false)
const zoneId = ref('')
const payment = ref<'cod' | 'transfer'>('cod')
const consent = ref(true)
const proof = ref<Blob | null>(null)
const proofUrl = ref<string | null>(null)
const gone = ref(new Set<string>())
const short = ref(new Map<string, number>())
const changed = ref(false)
const sending = ref(false)
const error = ref<string | null>(null)

useSeoMeta({ title: 'السلة', robots: 'noindex' })

// What the store offers decides the defaults.
watchEffect(() => {
  const o = ordering.value
  if (o) {
    if (!o.pickup && o.delivery) {
      delivery.value = true
    }
    if (o.pickup && !o.delivery) {
      delivery.value = false
    }
    if (!o.pay_cod && o.pay_transfer) {
      payment.value = 'transfer'
    }
  }
})

const zone = computed(() => zones.value.find(z => z.id === zoneId.value) ?? null)
const fee = computed(() => {
  const o = ordering.value
  if (!delivery.value || !zone.value) {
    return 0
  }
  return o?.free_delivery_over != null && cart.total.value >= o.free_delivery_over ? 0 : zone.value.fee
})
const total = computed(() => cart.total.value + fee.value)
const belowMin = computed(() => !!ordering.value && cart.total.value < ordering.value.min_order)
const freeHint = computed(() => {
  const over = ordering.value?.free_delivery_over
  if (!delivery.value || over == null || !zone.value?.fee) {
    return null
  }
  return cart.total.value >= over ? 'التوصيل ببلاش على الطلب ده.' : `التوصيل ببلاش لو الطلب وصل ${formatPrice(over)}.`
})

// Prices and stock from the store now, not from when the line was added.
onMounted(async () => {
  cart.load()
  try {
    const saved = JSON.parse(localStorage.getItem(`muhasebi-store-customer:${slug}`) ?? 'null')
    if (saved) {
      name.value = saved.name ?? ''
      phone.value = saved.phone ?? ''
      address.value = saved.address ?? ''
    }
  }
  catch {
    // Nothing kept.
  }
  const ids = [...new Set(cart.lines.value.map(l => l.productId))]
  const products = await Promise.all(ids.map(id => $fetch<{ data: ProductDetail }>(`/api/stores/${slug}/products/${id}`).then(r => r.data).catch(() => null)))
  const variants = new Map(products.flatMap(p => p?.variants ?? []).map(v => [v.id, v]))
  for (const line of cart.lines.value) {
    const v = variants.get(line.variantId)
    if (!v || v.availability === 'out' || v.price === null) {
      gone.value.add(line.variantId)
    }
    else if (v.price !== line.price) {
      line.price = v.price
      changed.value = true
    }
  }
})

async function pickProof(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) {
    return
  }
  proof.value = await shrinkPhoto(file)
  if (proofUrl.value) {
    URL.revokeObjectURL(proofUrl.value)
  }
  proofUrl.value = URL.createObjectURL(proof.value)
}

// One id per checkout: a retry after a dropped connection isn't saved twice.
let orderId = crypto.randomUUID()

async function placeOrder() {
  error.value = null
  if (payment.value === 'transfer' && !proof.value) {
    error.value = 'ارفع صورة التحويل الأول.'
    return
  }
  const form = new FormData()
  form.append('id', orderId)
  form.append('name', name.value.trim())
  form.append('phone', phone.value.trim())
  form.append('fulfilment', delivery.value ? 'delivery' : 'pickup')
  if (delivery.value) {
    form.append('zone_id', zoneId.value)
    form.append('address', address.value.trim())
  }
  if (notes.value.trim()) {
    form.append('notes', notes.value.trim())
  }
  form.append('payment', payment.value)
  if (payment.value === 'transfer' && proof.value) {
    form.append('proof', proof.value, 'transfer.jpg')
  }
  form.append('consent', consent.value ? '1' : '0')
  form.append('website', website.value)
  cart.lines.value.forEach((l, i) => {
    form.append(`items[${i}][variant_id]`, l.variantId)
    form.append(`items[${i}][qty]`, String(l.qty))
  })

  sending.value = true
  try {
    const res = await $fetch<{ data: PlacedOrder }>(`/api/stores/${slug}/orders`, { method: 'POST', body: form, timeout: 30_000 })
    try {
      // Filled in next time (only when they agreed to be remembered).
      if (consent.value) {
        localStorage.setItem(`muhasebi-store-customer:${slug}`, JSON.stringify({ name: name.value.trim(), phone: phone.value.trim(), address: address.value.trim() }))
      }
      const mine = JSON.parse(localStorage.getItem(`muhasebi-store-orders:${slug}`) ?? '[]')
      localStorage.setItem(`muhasebi-store-orders:${slug}`, JSON.stringify([{ token: res.data.token, reference: res.data.reference, at: res.data.created_at }, ...(Array.isArray(mine) ? mine : [])].slice(0, 20)))
    }
    catch {
      // Storage blocked: the order page link still works.
    }
    cart.clear()
    orderId = crypto.randomUUID()
    await navigateTo(place.path(`/o/${res.data.token}`))
  }
  catch (e) {
    const data = (e as { data?: { message?: string, code?: string, context?: { items?: { variant_id: string, available: number }[], variant_ids?: string[] } } }).data
    if (data?.code === 'out_of_stock') {
      short.value = new Map((data.context?.items ?? []).map(i => [i.variant_id, i.available]))
    }
    if (data?.code === 'items_unavailable') {
      gone.value = new Set(data.context?.variant_ids ?? [])
    }
    error.value = data?.message ?? 'النت فصل أو المتجر مش بيرد. جرّب تاني.'
  }
  finally {
    sending.value = false
  }
}

function send() {
  if (!store.value.whatsapp) {
    return
  }
  const lines = cart.lines.value.map(l => `• ${l.qty} × ${l.name}${l.variant ? ` (${l.variant})` : ''} — ${formatPrice(l.price * l.qty)}`)
  const text = [
    `السلام عليكم، طلب جديد من متجر ${store.value.name}:`,
    ...lines,
    `الإجمالي: ${formatPrice(cart.total.value)}`,
    '',
    `الاسم: ${name.value.trim()}`,
    delivery.value ? `توصيل إلى: ${address.value.trim()}` : 'هستلم من المحل',
    ...(notes.value.trim() ? [`ملاحظات: ${notes.value.trim()}`] : []),
  ].join('\n')
  window.open(whatsappLink(store.value.whatsapp, text), '_blank', 'noopener')
}
</script>

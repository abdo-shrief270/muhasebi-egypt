<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <NuxtLink to="/cart" class="text-sm">‹ السلة</NuxtLink>
    <h1 class="text-2xl font-bold">
      طلبك من {{ group?.shop.name ?? 'المحل' }}
    </h1>

    <p v-if="!group && ready" class="card p-6 text-center text-muted">
      الجزء ده مش في السلة. <NuxtLink to="/cart">ارجع للسلة</NuxtLink>
    </p>
    <p v-else-if="loadError" class="card p-6 text-center text-muted">
      {{ loadError }}
    </p>

    <template v-else-if="group && ordering">
      <ul class="card divide-y divide-line">
        <li v-for="l in group.lines" :key="l.offerId" class="flex justify-between gap-3 px-4 py-2.5 text-sm">
          <span><span class="num">{{ l.qty }}</span> × {{ l.title }}</span>
          <span class="num font-semibold">{{ formatPrice(l.price * l.qty) }}</span>
        </li>
      </ul>

      <form class="card space-y-4 p-4" @submit.prevent="placeOrder">
        <h2 class="font-bold">
          بياناتك
        </h2>
        <input v-model="name" class="field" autocomplete="name" placeholder="اسمك" minlength="2" maxlength="80" required>
        <input v-model="phone" class="field num" type="tel" inputmode="tel" autocomplete="tel" placeholder="رقم موبايلك (01xxxxxxxxx)" dir="ltr" pattern="^(\+?20|0)?1[0125][0-9]{8}$" required>
        <!-- Bots fill every field; people never see this one. -->
        <input v-model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset v-if="ordering.pickup && ordering.delivery" class="flex gap-2">
          <legend class="sr-only">
            الاستلام
          </legend>
          <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'border-line-strong bg-white' : 'border-ink bg-ink text-white'" @click="delivery = false">
            هستلم من المحل
          </button>
          <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'border-ink bg-ink text-white' : 'border-line-strong bg-white'" @click="delivery = true">
            توصيل
          </button>
        </fieldset>
        <p v-else class="text-sm text-muted">
          {{ ordering.delivery ? 'المحل بيوصّل الطلب.' : 'هتستلم الطلب من المحل.' }}
        </p>

        <template v-if="delivery">
          <select v-model="zoneId" class="field" required>
            <option value="" disabled>
              اختار منطقتك
            </option>
            <option v-for="z in zones" :key="z.id" :value="z.id">
              {{ z.name }} — {{ z.fee ? formatPrice(z.fee) : 'ببلاش' }}
            </option>
          </select>
          <textarea v-model="address" class="field" rows="2" autocomplete="street-address" placeholder="العنوان بالتفصيل (الشارع، رقم العمارة، الدور، علامة مميزة)" maxlength="500" required />
        </template>
        <textarea v-model="notes" class="field" rows="2" placeholder="ملاحظات (اختياري)" maxlength="500" />

        <fieldset v-if="ordering.pay_cod && ordering.pay_transfer" class="space-y-2">
          <legend class="mb-2 font-bold">
            الدفع
          </legend>
          <label class="flex items-center gap-2"><input v-model="payment" type="radio" value="cod" class="accent-teal"> {{ delivery ? 'كاش عند الاستلام' : 'كاش في المحل' }}</label>
          <label class="flex items-center gap-2"><input v-model="payment" type="radio" value="transfer" class="accent-teal"> تحويل InstaPay / محفظة</label>
        </fieldset>
        <div v-if="payment === 'transfer'" class="space-y-2 rounded-xl bg-paper p-3 text-sm">
          <p>حوّل <b class="num">{{ formatPrice(total) }}</b> على:</p>
          <p v-if="ordering.transfer_instapay">
            InstaPay: <b class="num" dir="ltr">{{ ordering.transfer_instapay }}</b>
          </p>
          <p v-if="ordering.transfer_wallet">
            محفظة: <b class="num" dir="ltr">{{ localPhone(ordering.transfer_wallet) }}</b>
          </p>
          <label class="btn-line w-full cursor-pointer">
            {{ proof ? 'غيّر صورة التحويل' : 'ارفع صورة التحويل' }}
            <input type="file" accept="image/*" class="sr-only" @change="pickProof">
          </label>
          <img v-if="proofUrl" :src="proofUrl" alt="صورة التحويل" class="mx-auto max-h-48 rounded-lg">
        </div>

        <div v-if="ordering.coupons || coupon" class="space-y-2">
          <button v-if="!couponOpen && !coupon" type="button" class="text-sm font-semibold text-teal" @click="couponOpen = true">
            عندك كود خصم من المحل؟
          </button>
          <div v-else-if="!coupon" class="flex gap-2">
            <input v-model="couponCode" class="field num flex-1 uppercase" dir="ltr" maxlength="32" placeholder="الكود" aria-label="كود الخصم" @keydown.enter.prevent="applyCoupon()">
            <button type="button" class="btn-line px-4" :disabled="checkingCoupon || couponCode.trim().length < 3" @click="applyCoupon()">
              {{ checkingCoupon ? '…' : 'طبّق' }}
            </button>
          </div>
          <p v-if="coupon" class="flex items-center justify-between rounded-xl bg-mint px-3 py-2 text-sm text-ok">
            <span><b class="num" dir="ltr">{{ coupon.code }}</b> — {{ coupon.label }}</span>
            <button type="button" class="text-xs underline" @click="coupon = null">شيله</button>
          </p>
          <p v-if="couponError" class="text-sm text-warn">
            {{ couponError }}
          </p>
        </div>

        <dl class="space-y-1 border-t border-line pt-3 text-sm">
          <div class="flex justify-between">
            <dt>الأصناف</dt><dd class="num">
              {{ formatPrice(group.total) }}
            </dd>
          </div>
          <div v-if="coupon" class="flex justify-between text-ok">
            <dt>الخصم</dt><dd class="num">
              − {{ formatPrice(coupon.discount) }}
            </dd>
          </div>
          <div v-if="delivery" class="flex justify-between">
            <dt>التوصيل</dt><dd class="num">
              {{ zone ? (fee ? formatPrice(fee) : 'ببلاش') : '—' }}
            </dd>
          </div>
          <div class="flex justify-between text-lg font-bold">
            <dt>الإجمالي</dt><dd class="num">
              {{ formatPrice(total) }}
            </dd>
          </div>
        </dl>

        <label class="flex items-start gap-2 text-sm">
          <input v-model="consent" type="checkbox" class="mt-1 accent-teal">
          <span>احفظوا بياناتي عند {{ group.shop.name }} للطلبات الجاية. <span class="text-muted">(تقدر تطلب مسحها في أي وقت)</span></span>
        </label>

        <p v-if="!ordering.open_now && ordering.hours" class="rounded-xl bg-sand px-3 py-2 text-sm text-warn">
          المحل بيستقبل الطلبات من <span class="num" dir="ltr">{{ ordering.hours.from }}</span> لـ <span class="num" dir="ltr">{{ ordering.hours.until }}</span>. السلة هتفضل محفوظة لحد ما تطلب.
        </p>
        <p v-if="belowMin" class="rounded-xl bg-sand px-3 py-2 text-sm text-warn">
          أقل طلب من المحل ده {{ formatPrice(ordering.min_order) }}.
        </p>
        <p v-if="error" class="rounded-xl bg-sand px-3 py-2 text-sm font-semibold text-warn" role="alert">
          {{ error }}
        </p>
        <button type="submit" class="btn-teal w-full py-3 text-base" :disabled="sending || belowMin || !ordering.open_now">
          {{ sending ? 'بنبعت الطلب…' : `اطلب (${formatPrice(total)})` }}
        </button>
        <p class="text-xs text-muted">
          المحل هيأكد معاك الطلب، وتقدر تتابعه من صفحة الطلب. الدفع للمحل نفسه.
        </p>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ShopPage, StoreCheckout } from '~/types'

useSeoMeta({ title: 'إتمام الطلب', robots: 'noindex' })

const route = useRoute()
const cart = useCart()
const ready = ref(false)
const group = computed(() => cart.groups.value.find(g => g.key === route.params.key) ?? null)
const checkout = ref<StoreCheckout | null>(null)
const storeSlug = ref('')
const loadError = ref<string | null>(null)
const ordering = computed(() => checkout.value?.store.ordering ?? null)
const zones = computed(() => checkout.value?.zones ?? [])

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
const sending = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  cart.load()
  ready.value = true
  try {
    const saved = JSON.parse(localStorage.getItem('souq:customer') ?? 'null')
    if (saved) {
      name.value = saved.name ?? ''
      phone.value = saved.phone ?? ''
      address.value = saved.address ?? ''
    }
  }
  catch {
    // Nothing kept.
  }
  if (!group.value) {
    return
  }
  const shop = await marketFetch<{ data: ShopPage }>(`/shops/${group.value.shop.slug}`).then(r => r.data).catch(() => null)
  if (!shop?.store?.takes_orders) {
    loadError.value = 'المحل ده مش بياخد طلبات من هنا دلوقتي. ارجع للسلة وابعتله على واتساب.'
    return
  }
  storeSlug.value = shop.store.slug
  checkout.value = await marketFetch<{ data: StoreCheckout }>(`/stores/${shop.store.slug}`).then(r => r.data).catch(() => null)
  if (!checkout.value?.store.ordering) {
    loadError.value = 'المحل ده مش بياخد طلبات دلوقتي.'
    return
  }
  const o = checkout.value.store.ordering
  delivery.value = !o.pickup && o.delivery
  if (!o.pay_cod && o.pay_transfer) {
    payment.value = 'transfer'
  }
})

const zone = computed(() => zones.value.find(z => z.id === zoneId.value) ?? null)
const subtotal = computed(() => group.value?.total ?? 0)
const fee = computed(() => {
  const o = ordering.value
  if (!delivery.value || !zone.value) {
    return 0
  }
  return o?.free_delivery_over != null && subtotal.value >= o.free_delivery_over ? 0 : zone.value.fee
})
const belowMin = computed(() => !!ordering.value && subtotal.value < ordering.value.min_order)

const couponOpen = ref(false)
const couponCode = ref('')
const coupon = ref<{ code: string, label: string, discount: number } | null>(null)
const couponError = ref<string | null>(null)
const checkingCoupon = ref(false)
const total = computed(() => subtotal.value - (coupon.value?.discount ?? 0) + fee.value)
async function applyCoupon() {
  checkingCoupon.value = true
  couponError.value = null
  try {
    coupon.value = (await $fetch<{ data: { code: string, label: string, discount: number } }>(`/api/market/stores/${storeSlug.value}/coupon`, {
      method: 'POST',
      body: { code: couponCode.value.trim(), subtotal: subtotal.value },
    })).data
  }
  catch (e) {
    coupon.value = null
    couponError.value = (e as { data?: { message?: string } }).data?.message ?? 'جرّب تاني.'
  }
  finally {
    checkingCoupon.value = false
  }
}

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
let orderId = import.meta.client ? crypto.randomUUID() : ''

async function placeOrder() {
  const g = group.value
  if (!g) {
    return
  }
  error.value = null
  if (payment.value === 'transfer' && !proof.value) {
    error.value = 'ارفع صورة التحويل الأول.'
    return
  }
  const form = new FormData()
  form.append('id', orderId)
  form.append('branch_id', g.branchId)
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
  if (coupon.value) {
    form.append('coupon_code', coupon.value.code)
  }
  g.lines.forEach((l, i) => {
    form.append(`items[${i}][variant_id]`, l.variantId)
    form.append(`items[${i}][qty]`, String(l.qty))
  })

  sending.value = true
  try {
    const res = await $fetch<{ data: { token: string, reference: string } }>(`/api/market/stores/${storeSlug.value}/orders`, { method: 'POST', body: form, timeout: 30_000 })
    try {
      if (consent.value) {
        localStorage.setItem('souq:customer', JSON.stringify({ name: name.value.trim(), phone: phone.value.trim(), address: address.value.trim() }))
      }
      const mine = JSON.parse(localStorage.getItem('souq:orders') ?? '[]')
      localStorage.setItem('souq:orders', JSON.stringify([{ store: storeSlug.value, token: res.data.token, reference: res.data.reference, shop: g.shop.name }, ...(Array.isArray(mine) ? mine : [])].slice(0, 30)))
    }
    catch {
      // Storage blocked: the order page link still works.
    }
    cart.removeGroup(g.key)
    orderId = crypto.randomUUID()
    await navigateTo(`/o/${storeSlug.value}/${res.data.token}`)
  }
  catch (e) {
    const data = (e as { data?: { message?: string, code?: string } }).data
    if (data?.code?.startsWith('coupon_')) {
      coupon.value = null
    }
    error.value = data?.message ?? 'النت فصل أو المحل مش بيرد. جرّب تاني.'
  }
  finally {
    sending.value = false
  }
}
</script>

<style scoped>
.field {
  width: 100%;
  border: 1px solid var(--color-line-strong);
  border-radius: 0.75rem;
  background: #fff;
  padding: 0.6rem 0.75rem;
}
</style>

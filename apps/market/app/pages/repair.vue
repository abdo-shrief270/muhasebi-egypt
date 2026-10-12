<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="space-y-2">
      <h1 class="text-2xl font-bold sm:text-3xl">
        احجز صيانة لموبايلك في محل قريب
      </h1>
      <p class="text-muted">
        قول جهازك وإيه اللي فيه، واختار محل من اللي حواليك. المحل هيكلّمك يحدد معاك المعاد والسعر.
      </p>
    </div>

    <div v-if="done" class="card space-y-3 p-6 text-center" role="status">
      <p class="text-2xl font-bold">
        الحجز وصل للمحل
      </p>
      <p>
        رقم الحجز <span class="num font-bold" dir="ltr">{{ done.reference }}</span> في {{ done.shop }}.
        <template v-if="done.preferred_on">
          جاي يوم <span class="num">{{ done.preferred_on }}</span>.
        </template>
      </p>
      <p class="text-sm text-muted">
        المحل هيكلّمك على الرقم اللي كتبته.
      </p>
      <NuxtLink to="/" class="btn-line mx-auto">
        رجوع للسوق
      </NuxtLink>
    </div>

    <form v-else class="space-y-6" @submit.prevent="book">
      <section class="card space-y-4 p-5">
        <h2 class="font-bold">
          1. الجهاز والمشكلة
        </h2>
        <div class="space-y-1">
          <label for="device" class="text-sm font-semibold">الجهاز</label>
          <input id="device" v-model="device" list="phones" required minlength="2" maxlength="120" placeholder="مثلاً: iPhone 13، Samsung A54" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2.5" autocomplete="off">
          <datalist id="phones">
            <option v-for="p in phoneNames" :key="p" :value="p" />
          </datalist>
        </div>
        <div class="space-y-2">
          <p class="text-sm font-semibold">
            المشكلة
          </p>
          <div class="flex flex-wrap gap-2">
            <button v-for="f in FAULTS" :key="f" type="button" class="chip" :class="faults.includes(f) ? 'chip-on' : 'chip-off'" :aria-pressed="faults.includes(f)" @click="toggle(f)">
              {{ f }}
            </button>
          </div>
          <textarea v-model="details" rows="2" maxlength="800" placeholder="تفاصيل تانية (اختياري)" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" aria-label="تفاصيل المشكلة" />
        </div>
      </section>

      <section class="card space-y-3 p-5">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="flex-1 font-bold">
            2. اختار المحل
          </h2>
          <PlaceButton />
        </div>
        <p v-if="pending" class="text-sm text-muted">
          بندوّر على محلات…
        </p>
        <p v-else-if="!shops.length" class="text-sm text-muted">
          مفيش محلات بتاخد حجز صيانة أونلاين{{ place ? ' في المكان ده' : '' }} لسه. جرّب مكان تاني.
        </p>
        <ul v-else class="space-y-2" role="radiogroup" aria-label="المحلات">
          <li v-for="s in shops" :key="s.branch_id">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3" :class="chosen?.branch_id === s.branch_id ? 'border-teal bg-mint' : 'border-line hover:bg-paper'">
              <input v-model="chosenId" type="radio" name="shop" :value="s.branch_id" class="mt-1 accent-teal">
              <span class="flex-1 space-y-0.5">
                <span class="block font-bold">{{ s.name }}<span v-if="s.branch" class="font-normal text-muted"> · {{ s.branch }}</span></span>
                <span class="block text-sm text-muted">{{ [s.address, s.area, s.governorate_label].filter(Boolean).join('، ') }}</span>
                <StarRating v-if="s.rating" :rating="s.rating" :reviews="s.reviews" class="text-sm" />
                <span v-if="s.note" class="block text-sm font-semibold text-teal">{{ s.note }}</span>
              </span>
              <span v-if="s.distance_km !== null" class="num shrink-0 font-bold">{{ formatKm(s.distance_km) }}</span>
            </label>
          </li>
        </ul>
      </section>

      <section class="card space-y-3 p-5">
        <h2 class="font-bold">
          3. بياناتك
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <input v-model="name" required minlength="2" maxlength="120" placeholder="اسمك" class="rounded-xl border border-line-strong bg-white px-3 py-2.5" aria-label="اسمك" autocomplete="name">
          <input v-model="phone" required type="tel" inputmode="tel" placeholder="رقم الموبايل" class="num rounded-xl border border-line-strong bg-white px-3 py-2.5" aria-label="رقم الموبايل" autocomplete="tel" dir="ltr">
        </div>
        <label class="block space-y-1 text-sm">
          <span class="font-semibold">يوم تحب تيجي فيه (اختياري)</span>
          <input v-model="preferredOn" type="date" :min="today" class="num block rounded-xl border border-line-strong bg-white px-3 py-2">
        </label>
        <label class="flex items-start gap-2 text-sm">
          <input v-model="consent" type="checkbox" class="mt-0.5 accent-teal">
          <span>المحل يحتفظ باسمي ورقمي عشان يتواصل معايا.</span>
        </label>
        <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <p v-if="error" class="text-sm text-warn" role="alert">
          {{ error }}
        </p>
        <button type="submit" class="btn-teal w-full text-base" :disabled="sending || !chosen || !problem">
          {{ sending ? 'بيتبعت…' : chosen ? `احجز في ${chosen.name}` : 'اختار محل الأول' }}
        </button>
      </section>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { PhoneRow, RepairShop } from '~/types'

const FAULTS = ['الشاشة مكسورة', 'البطارية بتفصل بسرعة', 'مش بيشحن', 'الصوت / السماعة', 'المايك', 'الكاميرا', 'وقع في مية', 'سوفتوير / بطء', 'الزراير']

const { place, query } = usePlace()
const { data: shopsData, pending } = await useAsyncData('repair-shops', () => marketFetch<{ data: RepairShop[] }>('/repair-shops', query.value).then(r => r.data).catch(() => []), { watch: [query] })
const shops = computed(() => shopsData.value ?? [])
const { data: phonesData } = await useAsyncData('repair-phones', () => marketFetch<{ data: { phones: PhoneRow[] } }>('/phones').then(r => r.data.phones.map(p => p.name)).catch(() => []), { server: false })
const phoneNames = computed(() => phonesData.value ?? [])

const device = ref('')
const faults = ref<string[]>([])
const details = ref('')
const problem = computed(() => [...faults.value, details.value.trim()].filter(Boolean).join('، '))
function toggle(f: string) {
  faults.value = faults.value.includes(f) ? faults.value.filter(x => x !== f) : [...faults.value, f]
}

const chosenId = ref<string | null>(null)
const chosen = computed(() => shops.value.find(s => s.branch_id === chosenId.value) ?? null)
const name = ref('')
const phone = ref('')
const preferredOn = ref('')
const consent = ref(true)
const website = ref('')
const today = new Date().toISOString().slice(0, 10)

// Made in the browser on the first try (never during rendering: the page is cached for everyone), kept for retries.
const id = ref('')
const sending = ref(false)
const error = ref<string | null>(null)
const done = ref<{ reference: string, shop: string, preferred_on: string | null } | null>(null)

async function book() {
  if (!chosen.value) return
  sending.value = true
  error.value = null
  id.value ||= crypto.randomUUID()
  try {
    const res = await $fetch<{ data: { reference: string, preferred_on: string | null } }>(`/api/market/stores/${chosen.value.store}/repair-bookings`, {
      method: 'POST',
      body: { id: id.value || null, name: name.value, phone: phone.value, device: device.value, problem: problem.value, preferred_on: preferredOn.value || null, consent: consent.value, website: website.value || null },
    })
    done.value = { reference: res.data.reference, shop: chosen.value.name, preferred_on: res.data.preferred_on }
  }
  catch (e) {
    error.value = (e as { data?: { message?: string } }).data?.message ?? 'الحجز ما اتبعتش، جرّب تاني.'
  }
  finally {
    sending.value = false
  }
}

useSeoMeta({
  title: 'احجز صيانة موبايل في محل قريب منك',
  description: 'شاشة مكسورة، بطارية، شحن، صوت… احجز صيانة موبايلك في محل قريب منك من سوق محاسبي، والمحل يكلّمك يحدد المعاد.',
})
</script>

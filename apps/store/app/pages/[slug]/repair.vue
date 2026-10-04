<template>
  <article class="card mx-auto max-w-xl space-y-5 p-5 sm:p-8">
    <div class="flex items-center gap-3">
      <span class="flex size-12 items-center justify-center rounded-xl bg-brand/10 text-brand"><StoreIcon name="wrench" :size="24" /></span>
      <div>
        <h1 class="text-2xl font-extrabold">
          احجز صيانة
        </h1>
        <p class="text-sm text-muted">
          عند {{ store.name }}
        </p>
      </div>
    </div>

    <template v-if="!store.repair_booking">
      <p>المحل مش بياخد حجز صيانة من هنا دلوقتي.</p>
      <a v-if="store.whatsapp" :href="whatsappLink(store.whatsapp, `السلام عليكم، عايز أحجز صيانة`)" target="_blank" rel="noopener" class="btn-wa">
        <StoreIcon name="whatsapp" :size="18" /> كلّمنا واتساب
      </a>
    </template>

    <div v-else-if="done" class="space-y-4 text-center">
      <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-ok/10 text-ok"><StoreIcon name="check" :size="28" /></span>
      <p class="text-lg font-extrabold">
        حجزك وصل المحل
      </p>
      <p>
        رقم الحجز <b class="num" dir="ltr">{{ done.reference }}</b>. المحل هيكلّمك يأكد الميعاد<template v-if="done.preferred_on">
          (اخترت يوم <span class="num">{{ formatDay(done.preferred_on) }}</span>)
        </template>.
      </p>
      <a v-if="store.whatsapp" :href="whatsappLink(store.whatsapp, `السلام عليكم، حجزت صيانة ${device} برقم ${done.reference}`)" target="_blank" rel="noopener" class="btn-wa">
        <StoreIcon name="whatsapp" :size="18" /> كلّم المحل واتساب
      </a>
      <NuxtLink :to="place.path()" class="block font-semibold text-brand">
        ارجع للمتجر
      </NuxtLink>
    </div>

    <form v-else class="space-y-4" @submit.prevent="send">
      <p v-if="store.repair_booking.note" class="rounded-xl bg-brand/10 px-3 py-2 text-sm font-semibold text-brand">
        {{ store.repair_booking.note }}
      </p>
      <label class="block space-y-1">
        <span class="text-sm font-semibold">الجهاز</span>
        <input v-model="device" class="input" placeholder="مثلاً iPhone 13 أو Samsung A54" minlength="2" maxlength="120" required>
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold">المشكلة</span>
        <textarea v-model="problem" class="input min-h-24" placeholder="الشاشة مكسورة، مش بيشحن، البطارية بتفصل…" minlength="3" maxlength="1000" required />
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold">جاي يوم <span class="font-normal text-muted">(اختياري)</span></span>
        <input v-model="preferredOn" class="input num" type="date" :min="today" :max="maxDay">
      </label>
      <input v-model="name" class="input" autocomplete="name" placeholder="اسمك" minlength="2" maxlength="120" required>
      <input v-model="phone" class="input num" type="tel" inputmode="tel" autocomplete="tel" placeholder="رقم موبايلك (01xxxxxxxxx)" dir="ltr" pattern="^(\+?20|0)?1[0125][0-9]{8}$" required>
      <!-- Bots fill every field; people never see this one. -->
      <input v-model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="flex items-start gap-2 text-sm">
        <input v-model="consent" type="checkbox" class="mt-1 accent-(--brand)">
        <span>احفظوا بياناتي عند {{ store.name }}. <span class="text-muted">(تقدر تطلب مسحها في أي وقت)</span></span>
      </label>
      <p v-if="error" class="rounded-xl bg-warn/10 px-3 py-2 text-sm text-warn">
        {{ error }}
      </p>
      <button type="submit" class="btn-brand w-full py-3 text-base" :disabled="sending">
        {{ sending ? 'بنبعت الحجز…' : 'احجز' }}
      </button>
      <p class="text-center text-xs text-muted">
        مش هتدفع حاجة دلوقتي. المحل هيكلّمك يأكد الميعاد والتكلفة.
      </p>
    </form>
  </article>
</template>

<script setup lang="ts">
const slug = useStoreSlug()
const place = useStorePlace()
const home = await useStoreHome()
const store = computed(() => home.value.store)
useSeoMeta({ title: 'احجز صيانة', description: () => `احجز صيانة موبايلك عند ${store.value.name}.` })

const device = ref('')
const problem = ref('')
const preferredOn = ref('')
const name = ref('')
const phone = ref('')
const website = ref('')
const consent = ref(true)
const sending = ref(false)
const error = ref<string | null>(null)
const done = ref<{ reference: string, preferred_on: string | null } | null>(null)

const cairoDay = (offset: number) => new Date(Date.now() + offset * 86_400_000).toLocaleDateString('en-CA', { timeZone: 'Africa/Cairo' })
const today = cairoDay(0)
const maxDay = cairoDay(30)
const formatDay = (d: string) => new Date(`${d}T12:00:00`).toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' })

onMounted(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(`muhasebi-store-customer:${slug}`) ?? 'null')
    name.value = saved?.name ?? ''
    phone.value = saved?.phone ?? ''
  }
  catch {
    // Nothing kept.
  }
})

// One id per booking: a retry after a dropped connection isn't saved twice.
let bookingId = crypto.randomUUID()

async function send() {
  sending.value = true
  error.value = null
  try {
    done.value = (await $fetch<{ data: { reference: string, preferred_on: string | null } }>(`/api/stores/${slug}/repair-bookings`, {
      method: 'POST',
      body: {
        id: bookingId, name: name.value.trim(), phone: phone.value.trim(), device: device.value.trim(), problem: problem.value.trim(),
        preferred_on: preferredOn.value || null, consent: consent.value, website: website.value,
      },
      timeout: 20_000,
    })).data
    bookingId = crypto.randomUUID()
    if (consent.value) {
      try {
        const saved = JSON.parse(localStorage.getItem(`muhasebi-store-customer:${slug}`) ?? '{}')
        localStorage.setItem(`muhasebi-store-customer:${slug}`, JSON.stringify({ ...saved, name: name.value.trim(), phone: phone.value.trim() }))
      }
      catch {
        // Storage blocked.
      }
    }
  }
  catch (e) {
    const data = (e as { data?: { message?: string } }).data
    error.value = data?.message ?? 'النت فصل أو المتجر مش بيرد. جرّب تاني.'
  }
  finally {
    sending.value = false
  }
}
</script>

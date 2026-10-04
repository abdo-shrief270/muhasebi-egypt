<template>
  <div class="space-y-6">
    <PageHeader title="المتجر الأونلاين" description="صفحة لمحلك بأصنافك وأسعارك ومخزونك من البرنامج. الزبون يدوّر بموديل موبايله ويطلب، والطلب يوصلك هنا أو على واتساب.">
      <UButton v-if="session.can('online_store.orders')" to="/online-store/orders" color="neutral" variant="outline" icon="i-lucide-shopping-bag" label="الطلبات" />
    </PageHeader>

    <div v-if="settings" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
      <form class="space-y-6" @submit.prevent="save()">
        <UCard>
          <template #header>
            <h2 class="font-bold">
              العنوان والحالة
            </h2>
          </template>
          <div class="space-y-4">
            <UFormField label="عنوان المتجر" :hint="`\u200E${storeBase}\u200E`" :error="errors.slug">
              <UInput v-model="form.slug" dir="ltr" class="w-full" maxlength="40" placeholder="elnour" />
            </UFormField>
            <URadioGroup
              v-model="form.mode"
              :items="[
                { value: 'off', label: 'مقفول', description: 'محدش يقدر يفتح المتجر.' },
                { value: 'orders', label: 'شغال — الطلبات توصل البرنامج', description: 'الزبون يطلب ويتابع طلبه، والطلب يوصلك إشعار وتحوّله لفاتورة بضغطة.' },
                { value: 'whatsapp', label: 'شغال — الطلبات على واتساب بس', description: 'الزبون يملأ السلة ويبعتها رسالة على رقم الواتساب.' },
              ]"
            />
            <UFormField :label="form.mode === 'orders' ? 'رقم الواتساب (الزبون يكلمك عليه)' : 'رقم الواتساب اللي هتوصله الطلبات'" :error="errors.whatsapp">
              <UInput v-model="form.whatsapp" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
            </UFormField>
          </div>
        </UCard>

        <UCard v-if="form.mode === 'orders'">
          <template #header>
            <h2 class="font-bold">
              الاستلام والدفع
            </h2>
          </template>
          <div class="space-y-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <USwitch v-model="form.pickup" label="استلام من المحل" />
              <USwitch v-model="form.delivery" label="توصيل" description="بمصاريف لكل منطقة تحت." />
              <UFormField label="أقل طلب (ج)" hint="0 = مفيش" :error="errors.min_order">
                <UInput v-model="form.min_order" type="number" min="0" step="any" class="w-full" />
              </UFormField>
              <UFormField label="مواعيد استقبال الطلبات" hint="فاضي = طول اليوم" :error="errors.orders_from || errors.orders_until">
                <div class="flex items-center gap-2">
                  <UInput v-model="form.orders_from" type="time" class="flex-1" aria-label="من" />
                  <span class="text-sm text-(--ui-text-muted)">لـ</span>
                  <UInput v-model="form.orders_until" type="time" class="flex-1" aria-label="لحد" />
                </div>
              </UFormField>
              <UFormField v-if="form.delivery" label="التوصيل ببلاش لو الطلب فوق (ج)" hint="اختياري" :error="errors.free_delivery_over">
                <UInput v-model="form.free_delivery_over" type="number" min="0" step="any" class="w-full" />
              </UFormField>
            </div>
            <OnlineStoreZones v-if="form.delivery" />
            <USeparator />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <USwitch v-model="form.pay_cod" label="كاش عند الاستلام" />
              <USwitch v-model="form.pay_transfer" label="تحويل InstaPay / محفظة" description="الزبون يرفع صورة التحويل مع الطلب." />
              <template v-if="form.pay_transfer">
                <UFormField label="عنوان InstaPay" :error="errors.transfer_instapay">
                  <UInput v-model="form.transfer_instapay" dir="ltr" class="w-full" maxlength="60" placeholder="elnour@instapay" />
                </UFormField>
                <UFormField label="رقم المحفظة" :error="errors.transfer_wallet">
                  <UInput v-model="form.transfer_wallet" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
                </UFormField>
              </template>
            </div>
          </div>
        </UCard>

        <UCard v-if="settings?.repairs_available && form.mode !== 'off'">
          <template #header>
            <h2 class="font-bold">
              حجز صيانة
            </h2>
          </template>
          <div class="space-y-4">
            <USwitch v-model="form.repair_booking" label="«احجز صيانة» في المتجر" description="الزبون يكتب جهازه والمشكلة واليوم اللي جاي فيه، والحجز يوصلك إشعار. لما الجهاز يوصل بتعمله تذكرة بضغطة." />
            <UFormField v-if="form.repair_booking" label="سطر للزبون فوق الفورم" hint="اختياري" :error="errors.repair_booking_note">
              <UInput v-model="form.repair_booking_note" class="w-full" maxlength="255" placeholder="الكشف ببلاش · الإصلاح في نفس اليوم لأغلب الأعطال" />
            </UFormField>
            <ULink v-if="settings.repair_booking" to="/online-store/bookings" class="inline-flex items-center gap-1 text-sm font-bold text-primary">
              <UIcon name="i-lucide-wrench" class="size-4" /> حجوزات الصيانة
            </ULink>
          </div>
        </UCard>

        <UCard v-if="form.mode === 'orders'">
          <template #header>
            <h2 class="font-bold">
              أكواد الخصم
            </h2>
          </template>
          <OnlineStoreCoupons />
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-bold">
              الشكل
            </h2>
          </template>
          <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
              <UFormField label="اسم المتجر" :error="errors.name">
                <UInput v-model="form.name" class="w-full" maxlength="120" />
              </UFormField>
              <UFormField label="اللون">
                <input v-model="form.color" type="color" class="h-9 w-16 cursor-pointer rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg)" aria-label="لون المتجر">
              </UFormField>
            </div>
            <UFormField label="جملة تحت الاسم" hint="اختياري" :error="errors.tagline">
              <UInput v-model="form.tagline" class="w-full" maxlength="160" placeholder="إكسسوارات وقطع غيار أصلية — توصيل في المنصورة" />
            </UFormField>
            <div class="grid gap-4 sm:grid-cols-2">
              <OnlineStoreMediaField kind="logo" label="اللوجو" hint="مربع، 512×512 أو أكبر" :urls="settings.logo" @changed="settings = $event" />
              <OnlineStoreMediaField kind="cover" label="صورة الغلاف" hint="عريضة، 1600×600 مثلاً" :urls="settings.cover" @changed="settings = $event" />
            </div>
          </div>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-bold">
              اللي بيظهر في المتجر
            </h2>
          </template>
          <div class="space-y-4">
            <UFormField v-if="branches.length > 1" label="مخزون أنهي فرع يظهر" :error="errors.branch_id">
              <USelect v-model="form.branch_id" :items="branches.map(b => ({ label: b.name, value: b.id }))" class="w-full" />
            </UFormField>
            <USwitch v-model="form.show_out_of_stock" label="اعرض الأصناف اللي خلصت" description="بتظهر مكتوب عليها «خلص» بدل ما تختفي." />
            <USwitch v-model="form.show_quantity" label="اعرض العدد" description="الزبون يشوف الكمية بدل «متوفر / قرّب يخلص»." />
            <USwitch v-model="form.show_prices" label="اعرض الأسعار" description="لو قفلتها: مكتوب «اسأل عن السعر» والزبون يسألك على واتساب (من غير سلة)." :disabled="form.mode === 'orders'" />
            <p v-if="form.mode === 'orders'" class="-mt-2 text-xs text-(--ui-text-muted)">
              الطلبات في البرنامج محتاجة الأسعار تبان.
            </p>
            <USwitch v-model="form.show_models" label="«اختار موبايلك»" description="الزبون يدوّر بماركة وموديل موبايله، ويشوف الموبايلات اللي كل صنف بيركب عليها." />
            <USwitch v-model="form.show_latest" label="«وصل جديد» في الرئيسية" />
            <USwitch v-model="form.show_brand" label="اعرض الماركة" description="ماركة الصنف (Anker، Oraimo…) تحت اسمه." />
            <USwitch v-model="form.show_whatsapp" label="زراير «كلّمنا واتساب» و«اطلبه على واتساب»" />
            <UFormField label="شريط إعلان فوق المتجر" hint="اختياري" :error="errors.announcement">
              <UInput v-model="form.announcement" class="w-full" maxlength="160" placeholder="توصيل ببلاش فوق 500 ج · خصم 10% على الجرابات" />
            </UFormField>
            <UCollapsible v-if="categories.length" class="space-y-3">
              <UButton color="neutral" variant="link" trailing-icon="i-lucide-chevron-down" label="أسامي الأقسام في المتجر" class="px-0" />
              <template #content>
                <p class="text-xs text-(--ui-text-muted)">
                  لو عايز القسم يظهر للزبون باسم تاني (مثلاً «جرابات» تبقى «كفرات وجرابات»). الفاضي = نفس اسمه في البرنامج.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                  <UFormField v-for="c in categories" :key="c.id" :label="c.name">
                    <UInput v-model="form.category_names[String(c.id)]" class="w-full" maxlength="60" :placeholder="c.name" />
                  </UFormField>
                </div>
              </template>
            </UCollapsible>
            <p class="text-sm text-(--ui-text-muted)">
              كل الأصناف الشغالة بتظهر بسعر «الأونلاين» لو حاطه، وإلا بسعر القطاعي. تقدر تخفي صنف أو تضيف صوره ووصفه من صفحة الصنف.
            </p>
          </div>
        </UCard>

        <UCard>
          <template #header>
            <h2 class="font-bold">
              معلومات المحل
            </h2>
          </template>
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="التليفون" :error="errors.phone">
              <UInput v-model="form.phone" dir="ltr" inputmode="tel" class="w-full" placeholder="01xxxxxxxxx" />
            </UFormField>
            <UFormField label="المواعيد" :error="errors.hours">
              <UInput v-model="form.hours" class="w-full" maxlength="255" placeholder="كل يوم من 11 الصبح لـ 12 بالليل" />
            </UFormField>
            <UFormField label="العنوان" class="sm:col-span-2" :error="errors.address">
              <UInput v-model="form.address" class="w-full" maxlength="255" />
            </UFormField>
            <UFormField label="لينك الخريطة" hint="من جوجل ماب ← مشاركة" class="sm:col-span-2" :error="errors.map_url">
              <UInput v-model="form.map_url" dir="ltr" class="w-full" placeholder="https://maps.app.goo.gl/…" />
            </UFormField>
            <UFormField label="فيسبوك" :error="errors.facebook">
              <UInput v-model="form.facebook" dir="ltr" class="w-full" placeholder="https://facebook.com/…" />
            </UFormField>
            <UFormField label="إنستجرام" :error="errors.instagram">
              <UInput v-model="form.instagram" dir="ltr" class="w-full" placeholder="https://instagram.com/…" />
            </UFormField>
            <UFormField label="عن المحل" class="sm:col-span-2" :error="errors.about">
              <UTextarea v-model="form.about" :rows="3" autoresize class="w-full" maxlength="3000" />
            </UFormField>
            <UFormField label="سياسة الاستبدال والاسترجاع" class="sm:col-span-2" :error="errors.policy">
              <UTextarea v-model="form.policy" :rows="3" autoresize class="w-full" maxlength="3000" placeholder="الاستبدال خلال 14 يوم بالفاتورة والكرتونة." />
            </UFormField>
          </div>
        </UCard>

        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <div class="flex justify-end">
          <UButton type="submit" size="lg" icon="i-lucide-check" label="احفظ" :loading="saving" />
        </div>
      </form>

      <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <UCard>
          <div class="space-y-3 text-center">
            <UBadge :color="settings.mode === 'off' ? 'neutral' : 'success'" variant="subtle" :label="settings.mode === 'off' ? 'المتجر مقفول' : 'المتجر شغال'" />
            <p class="break-all text-sm font-semibold" dir="ltr">
              {{ settings.url }}
            </p>
            <div v-if="settings.mode !== 'off'" class="flex justify-center">
              <PrintQrCode :value="settings.url" :size="160" />
            </div>
            <div class="flex flex-wrap justify-center gap-2">
              <UButton :to="settings.url" target="_blank" icon="i-lucide-external-link" label="افتح المتجر" :disabled="settings.mode === 'off'" />
              <UButton color="neutral" variant="outline" icon="i-lucide-copy" label="انسخ اللينك" @click="copy()" />
            </div>
            <p class="text-xs text-(--ui-text-muted)">
              حط اللينك في البايو بتاع فيسبوك وإنستجرام، واطبع الكود وحطه على الكاونتر.
            </p>
          </div>
        </UCard>
        <UCard v-if="settings.mode !== 'off'">
          <div class="space-y-3 text-sm">
            <h2 class="font-bold">
              فيسبوك وإنستجرام وجوجل
            </h2>
            <p class="text-(--ui-text-muted)">
              ده لينك بكل أصنافك بالأسعار والصور والتوفر. حطه مرة واحدة والأسعار والمخزون بيتحدثوا لوحدهم كل كام ساعة.
            </p>
            <p class="break-all rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2 text-xs" dir="ltr">
              {{ settings.feed_url }}
            </p>
            <UButton block color="neutral" variant="outline" icon="i-lucide-copy" label="انسخ لينك الأصناف" @click="copy(settings.feed_url)" />
            <ul class="list-disc space-y-1 ps-5 text-xs text-(--ui-text-muted)">
              <li><b>فيسبوك وإنستجرام:</b> Commerce Manager ← الكتالوج ← مصادر البيانات ← Data feed ← Scheduled feed، والصق اللينك.</li>
              <li><b>جوجل:</b> Merchant Center ← المنتجات ← Feeds ← Scheduled fetch، والصق اللينك.</li>
              <li>الأصناف اللي من غير صورة مش بتدخل، ولو الأسعار مقفولة اللينك بيبقى فاضي.</li>
            </ul>
          </div>
        </UCard>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Category, OnlineStoreSettings } from '~/types/api'

definePageMeta({ permission: 'online_store.manage', module: 'online_store' })

const api = useApi()
const toast = useToast()
const session = useSessionStore()
const branches = computed(() => session.session?.branches ?? [])

const { data } = await useAsyncData('online-store-settings', () => api<{ data: OnlineStoreSettings }>('/online-store/settings'))
const { data: categoryData } = await useAsyncData('online-store-categories', () => api<{ data: Category[] }>('/catalog/categories').catch(() => ({ data: [] as Category[] })))
const categories = computed(() => categoryData.value?.data ?? [])
const settings = ref<OnlineStoreSettings | null>(data.value?.data ?? null)
// The address with the name left out: https://….muhasebi.com or https://store.muhasebi.com/…
const storeBase = computed(() => (settings.value ? settings.value.url.replace(settings.value.slug, '…') : ''))

const form = reactive({
  slug: '', mode: 'off' as OnlineStoreSettings['mode'], whatsapp: '', name: '', color: '#0f766e', tagline: '', branch_id: '',
  show_out_of_stock: true, show_quantity: false, phone: '', hours: '', address: '', map_url: '', facebook: '', instagram: '',
  about: '', policy: '',
  show_prices: true, show_models: true, show_latest: true, show_whatsapp: true, show_brand: true, announcement: '',
  category_names: {} as Record<string, string>, orders_from: '', orders_until: '',
  pickup: true, delivery: false, min_order: '', free_delivery_over: '',
  pay_cod: true, pay_transfer: false, transfer_instapay: '', transfer_wallet: '',
  repair_booking: false, repair_booking_note: '',
})

function fill(s: OnlineStoreSettings) {
  Object.assign(form, {
    slug: s.slug, mode: s.mode, whatsapp: localPhone(s.whatsapp), name: s.name, color: s.color, tagline: s.tagline ?? '',
    branch_id: s.branch_id ?? '', show_out_of_stock: s.show_out_of_stock, show_quantity: s.show_quantity,
    phone: localPhone(s.phone), hours: s.hours ?? '', address: s.address ?? '', map_url: s.map_url ?? '',
    facebook: s.facebook ?? '', instagram: s.instagram ?? '', about: s.about ?? '', policy: s.policy ?? '',
    show_prices: s.show_prices, show_models: s.show_models, show_latest: s.show_latest, show_whatsapp: s.show_whatsapp,
    show_brand: s.show_brand, announcement: s.announcement ?? '',
    category_names: { ...s.category_names }, orders_from: s.orders_from ?? '', orders_until: s.orders_until ?? '',
    pickup: s.pickup, delivery: s.delivery, min_order: s.min_order ? String(s.min_order / 100) : '',
    free_delivery_over: s.free_delivery_over !== null ? String(s.free_delivery_over / 100) : '',
    pay_cod: s.pay_cod, pay_transfer: s.pay_transfer, transfer_instapay: s.transfer_instapay ?? '', transfer_wallet: localPhone(s.transfer_wallet),
    repair_booking: s.repair_booking, repair_booking_note: s.repair_booking_note ?? '',
  })
}
if (settings.value) {
  fill(settings.value)
}

const saving = ref(false)
const error = ref<string | null>(null)
const errors = ref<Record<string, string>>({})

async function save() {
  saving.value = true
  error.value = null
  errors.value = {}
  const nullable = (v: string) => (v.trim() === '' ? null : v.trim())
  try {
    const res = await api<{ data: OnlineStoreSettings }>('/online-store/settings', {
      method: 'PUT',
      body: {
        slug: form.slug.trim().toLowerCase(), mode: form.mode, name: form.name, color: form.color,
        tagline: nullable(form.tagline), whatsapp: nullable(form.whatsapp), phone: nullable(form.phone),
        branch_id: form.branch_id || null, show_out_of_stock: form.show_out_of_stock, show_quantity: form.show_quantity,
        hours: nullable(form.hours), address: nullable(form.address), map_url: nullable(form.map_url),
        facebook: nullable(form.facebook), instagram: nullable(form.instagram), about: nullable(form.about), policy: nullable(form.policy),
        show_prices: form.show_prices, show_models: form.show_models, show_latest: form.show_latest, show_whatsapp: form.show_whatsapp,
        show_brand: form.show_brand, announcement: nullable(form.announcement), category_names: form.category_names,
        orders_from: form.orders_from || null, orders_until: form.orders_until || null,
        pickup: form.pickup, delivery: form.delivery, min_order: toPiasters(form.min_order) ?? 0,
        free_delivery_over: toPiasters(form.free_delivery_over), pay_cod: form.pay_cod, pay_transfer: form.pay_transfer,
        ...(settings.value?.repairs_available ? { repair_booking: form.repair_booking, repair_booking_note: nullable(form.repair_booking_note) } : {}),
        transfer_instapay: nullable(form.transfer_instapay), transfer_wallet: nullable(form.transfer_wallet),
      },
    })
    settings.value = res.data
    fill(res.data)
    toast.add({ color: 'success', title: 'اتحفظت إعدادات المتجر' })
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    const code = apiErrorCode(e)
    if (code === 'slug_invalid' || code === 'slug_taken') {
      errors.value.slug = apiErrorMessage(e)
    }
    else if (code === 'whatsapp_required') {
      errors.value.whatsapp = apiErrorMessage(e)
    }
    else if (code === 'orders_need_prices') {
      error.value = apiErrorMessage(e)
    }
    else if (code === 'transfer_details_required') {
      errors.value.transfer_instapay = apiErrorMessage(e)
    }
    else if (!Object.keys(errors.value).length) {
      error.value = apiErrorMessage(e)
    }
  }
  finally {
    saving.value = false
  }
}

async function copy(text?: string) {
  try {
    await navigator.clipboard.writeText(text ?? settings.value?.url ?? '')
    toast.add({ color: 'success', title: 'اتنسخ اللينك' })
  }
  catch {
    toast.add({ color: 'error', title: 'مقدرناش ننسخ؛ انسخه بإيدك.' })
  }
}
</script>

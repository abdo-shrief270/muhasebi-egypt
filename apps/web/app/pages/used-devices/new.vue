<template>
  <div class="max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/used-devices" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="شراء جهاز مستعمل" description="سجّل البايع ببطاقته، وافحص الجهاز، وادفع — والجهاز ينزل المخزن ويتباع من الكاشير بالـ IMEI." class="flex-1" />
    </div>

    <form class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]" novalidate @submit.prevent="save">
      <div class="min-w-0 space-y-6">
        <!-- Seller -->
        <UCard>
          <p class="mb-3 flex items-center gap-2 font-bold">
            <UIcon name="i-lucide-id-card" class="size-5 text-primary" />
            البايع
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="الرقم القومي" required class="sm:col-span-2" :error="nationalIdError ?? undefined">
              <UInput
                v-model="form.national_id"
                dir="ltr"
                inputmode="numeric"
                maxlength="20"
                placeholder="14 رقم من البطاقة"
                class="w-full"
                :ui="{ base: 'num tracking-wider text-lg' }"
              />
            </UFormField>
            <div v-if="parsedId?.valid" class="flex flex-wrap items-center gap-1.5 sm:col-span-2">
              <UBadge color="neutral" variant="subtle" icon="i-lucide-cake">
                <span class="num">{{ formatDate(parsedId.birthDate) }}</span> · <span class="num">{{ parsedId.age }}</span> سنة
              </UBadge>
              <UBadge color="neutral" variant="subtle">
                {{ parsedId.gender === 'male' ? 'ذكر' : 'أنثى' }}
              </UBadge>
              <UBadge color="neutral" variant="subtle" icon="i-lucide-map-pin">
                {{ parsedId.governorate }}
              </UBadge>
              <UBadge v-if="parsedId.age < 18" color="error" variant="subtle" icon="i-lucide-ban">
                أقل من 18 سنة: مينفعش نشتري منه
              </UBadge>
              <UBadge v-if="idCheck?.known" color="info" variant="subtle" icon="i-lucide-history">
                باعلك قبل كده <span class="num">{{ idCheck.devices_count }}</span> {{ idCheck.devices_count === 1 ? 'جهاز' : 'أجهزة' }}
              </UBadge>
            </div>
            <UFormField label="الاسم زي البطاقة" required>
              <UInput v-model="form.seller_name" autocomplete="off" class="w-full" />
            </UFormField>
            <UFormField label="الموبايل">
              <UInput v-model="form.seller_phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
            </UFormField>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-3">
            <UsedDevicesPhotoInput v-model="idFront" label="البطاقة — الوش" required :invalid="tried && !idFront.length" />
            <UsedDevicesPhotoInput v-model="idBack" label="البطاقة — الضهر" required :invalid="tried && !idBack.length" />
          </div>
          <p class="mt-2 text-xs text-(--ui-text-muted)">
            الرقم القومي بيتحفظ متشفّر، وصور البطاقة مش بيشوفها غير صاحب المحل والمدير.
          </p>
        </UCard>

        <!-- Device -->
        <UCard>
          <p class="mb-3 flex items-center gap-2 font-bold">
            <UIcon name="i-lucide-smartphone" class="size-5 text-primary" />
            الجهاز
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="الموديل" required class="sm:col-span-2">
              <DeviceModelPicker v-if="!freeModel" v-model="form.device_model_id" endpoint="/used-devices/device-models" placeholder="دوّر على الموديل" />
              <UInput v-else v-model="form.model_name" placeholder="مثلاً Nokia 3310" class="w-full" />
              <template #hint>
                <button type="button" class="text-primary hover:underline" @click="freeModel = !freeModel">
                  {{ freeModel ? 'اختار من القايمة' : 'مش في القايمة؟ اكتبه' }}
                </button>
              </template>
            </UFormField>
            <UFormField label="المساحة">
              <UInput v-model="form.storage" dir="ltr" placeholder="128GB" class="w-full" />
              <div class="mt-1.5 flex flex-wrap gap-1">
                <UButton v-for="s in storages" :key="s" size="xs" :color="form.storage === s ? 'primary' : 'neutral'" :variant="form.storage === s ? 'soft' : 'outline'" :label="s" @click="form.storage = s" />
              </div>
            </UFormField>
            <UFormField label="اللون">
              <UInput v-model="form.color" class="w-full" />
            </UFormField>
            <UFormField label="IMEI" required hint="اطلب ‎*#06#‎" :error="imeiError ?? undefined">
              <UInput v-model="form.imei" dir="ltr" inputmode="numeric" maxlength="20" placeholder="15 رقم" class="w-full" :ui="{ base: 'num tracking-wider' }" />
            </UFormField>
            <UFormField label="IMEI التاني" hint="لو الجهاز دبل شريحة" :error="imei2Error ?? undefined">
              <UInput v-model="form.imei2" dir="ltr" inputmode="numeric" maxlength="20" class="w-full" :ui="{ base: 'num tracking-wider' }" />
            </UFormField>
          </div>
          <UAlert
            v-if="imeiCheck?.sold_before || imeiCheck?.previous.length"
            class="mt-3"
            color="info"
            variant="subtle"
            icon="i-lucide-history"
            :title="imeiCheck.sold_before ? 'الجهاز ده اتباع من عندك قبل كده — ده شراء راجع عادي.' : 'الجهاز ده اتشرى مستعمل عندك قبل كده.'"
          >
            <template #description>
              <ul class="mt-1 space-y-0.5 text-xs">
                <li v-for="(e, i) in imeiCheck.events" :key="i">
                  <span class="num">{{ formatDate(e.created_at) }}</span> — {{ e.type_label }}<span v-if="e.note"> ({{ e.note }})</span>
                </li>
                <li v-for="p in imeiCheck.previous" :key="p.id">
                  <NuxtLink :to="`/used-devices/${p.id}`" class="text-primary hover:underline num">{{ p.reference }}</NuxtLink> — اتشرى <span class="num">{{ formatDate(p.bought_at) }}</span>
                </li>
              </ul>
            </template>
          </UAlert>
        </UCard>

        <!-- Condition -->
        <UCard v-if="options">
          <p class="mb-3 flex items-center gap-2 font-bold">
            <UIcon name="i-lucide-clipboard-check" class="size-5 text-primary" />
            الفحص والحالة
          </p>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="g in options.grades"
              :key="g.value"
              type="button"
              class="rounded-(--ui-radius) border-2 p-2 text-center transition-colors"
              :class="form.grade === g.value ? 'border-primary bg-(--ui-primary)/10' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
              :aria-pressed="form.grade === g.value"
              @click="form.grade = g.value"
            >
              <span class="block text-xl font-extrabold">{{ g.value }}</span>
              <span class="block text-xs text-(--ui-text-muted)">{{ g.label }}</span>
            </button>
          </div>
          <UFormField label="صحة البطارية" hint="%" class="mt-4 max-w-40">
            <UInput v-model="form.battery_health" type="number" min="1" max="100" inputmode="numeric" dir="ltr" class="w-full" />
          </UFormField>
          <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <div
              v-for="c in options.checklist"
              :key="c.key"
              class="flex items-center justify-between gap-2 rounded-(--ui-radius) border px-3 py-1.5"
              :class="c.required && tried && form.checklist[c.key] !== 'yes' ? 'border-(--ui-error)' : 'border-(--ui-border)'"
            >
              <span class="text-sm">{{ c.label }}<span v-if="c.required" class="text-(--ui-error)"> *</span></span>
              <UFieldGroup size="xs" class="shrink-0">
                <UButton v-for="v in checkValues" :key="v.value" :color="form.checklist[c.key] === v.value ? v.color : 'neutral'" :variant="form.checklist[c.key] === v.value ? 'soft' : 'outline'" :label="v.label" @click="form.checklist[c.key] = v.value" />
              </UFieldGroup>
            </div>
          </div>
          <UFormField label="ملاحظات" class="mt-4">
            <UTextarea v-model="form.notes" :rows="2" placeholder="خدش في الضهر، الشاشة متغيرة…" class="w-full" />
          </UFormField>
          <UsedDevicesPhotoInput v-model="devicePhotos" class="mt-4" :label="photosRequired ? 'صور الجهاز (مطلوبة)' : 'صور الجهاز'" :max="options.max_device_photos" :hint="photosRequired ? 'صورة واحدة على الأقل: الوش والضهر وأي عيب.' : 'اختياري: الوش والضهر وأي عيب.'" />
        </UCard>
      </div>

      <!-- Money -->
      <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <UCard>
          <div class="space-y-4">
            <UFormField label="اتشرى بكام" required hint="بالجنيه">
              <UInput v-model="form.purchase_price" type="number" min="0" step="any" inputmode="decimal" dir="ltr" size="lg" class="w-full" />
            </UFormField>
            <UFormField label="هيتباع بكام" required hint="السعر في الكاشير">
              <UInput v-model="form.asking_price" type="number" min="0" step="any" inputmode="decimal" dir="ltr" size="lg" class="w-full" />
            </UFormField>
            <p v-if="margin !== null" class="text-sm" :class="margin > 0 ? 'text-(--ui-success)' : 'text-(--ui-error)'">
              المكسب المتوقع: <b class="num">{{ formatMoney(margin) }}</b>
            </p>
            <UFormField v-if="options" label="الدفع">
              <div class="grid grid-cols-2 gap-2">
                <UButton
                  v-for="m in options.payment_methods"
                  :key="m.value"
                  block
                  :color="form.payment_method === m.value ? 'primary' : 'neutral'"
                  :variant="form.payment_method === m.value ? 'soft' : 'outline'"
                  :label="m.label"
                  @click="form.payment_method = m.value"
                />
              </div>
              <p class="mt-1.5 text-xs text-(--ui-text-muted)">
                {{ form.payment_method === 'cash' ? 'بيطلع من درج ورديتك.' : 'مش بيطلع من الدرج.' }}
              </p>
            </UFormField>
          </div>
        </UCard>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" :actions="needsShift ? [{ label: 'افتح وردية', to: '/cash' }] : []" />
        <UButton type="submit" block size="xl" icon="i-lucide-check" label="اشتري واطبع الإقرار" :loading="saving" :disabled="imeiCheck?.in_stock || (parsedId?.valid && parsedId.age < 18) || (photosRequired && !devicePhotos.length)" />
      </div>
    </form>

    <PrintSheet v-if="printing && created" page-size="A4" margin="14mm">
      <UsedDevicesDeclaration :device="created" :shop="shop" :price-paid="paid" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { CheckValue, ImeiCheck, NationalIdCheck, UsedDevice, UsedDeviceGrade, UsedDeviceOptions, UsedDevicePaymentMethod } from '~/types/api'

definePageMeta({ module: 'used_devices', permission: 'used_devices.manage' })

const api = useApi()
const store = useSessionStore()
const shop = computed(() => ({
  name: store.session?.tenant.name ?? '',
  phone: store.currentBranch?.phone ?? store.session?.tenant.phone ?? null,
  branch: store.currentBranch?.name ?? null,
  address: store.currentBranch?.address ?? null,
}))

const { data: optionsData } = await useAsyncData('used-device-options', () => api<{ data: UsedDeviceOptions }>('/used-devices/options'))
const options = computed(() => optionsData.value?.data)

const storages = ['64GB', '128GB', '256GB', '512GB', '1TB']
const checkValues = [
  { value: 'yes', label: 'آه', color: 'success' },
  { value: 'no', label: 'لأ', color: 'error' },
  { value: 'na', label: 'مش عارف', color: 'neutral' },
] as const

const form = reactive({
  national_id: '',
  seller_name: '',
  seller_phone: '',
  device_model_id: undefined as number | undefined,
  model_name: '',
  storage: '',
  color: '',
  imei: '',
  imei2: '',
  grade: 'B' as UsedDeviceGrade,
  battery_health: '',
  checklist: {} as Record<string, CheckValue>,
  notes: '',
  purchase_price: '',
  asking_price: '',
  payment_method: 'cash' as UsedDevicePaymentMethod,
})
const freeModel = ref(false)
const idFront = ref<File[]>([])
const idBack = ref<File[]>([])
const devicePhotos = ref<File[]>([])
// The owner's «صور الجهاز إجباري».
const photosRequired = computed(() => store.hasFeature('used_devices.device_photos_required'))
const tried = ref(false)

// National ID: checked as it's typed; once valid, the API says whether this person sold here before.
const parsedId = computed(() => latinDigits(form.national_id).length >= 14 ? parseNationalId(form.national_id) : null)
const nationalIdError = computed(() => parsedId.value && !parsedId.value.valid ? parsedId.value.error : (tried.value && !parsedId.value ? 'اكتب الرقم القومي (14 رقم).' : null))
const idCheck = ref<NationalIdCheck | null>(null)
watch(() => parsedId.value?.valid ? latinDigits(form.national_id) : null, async (nid) => {
  idCheck.value = null
  if (!nid) {
    return
  }
  try {
    idCheck.value = (await api<{ data: NationalIdCheck }>('/used-devices/national-id-check', { query: { national_id: nid } })).data
    if (idCheck.value.seller && !form.seller_name) {
      form.seller_name = idCheck.value.seller.name
      form.seller_phone = localPhone(idCheck.value.seller.phone)
    }
  }
  catch {
    // Only a hint; buying works without it.
  }
})

// IMEI: Luhn here, then the API refuses one in stock and tells a buy-back.
const imeiDigits = computed(() => latinDigits(form.imei).replace(/\D/g, ''))
const imeiError = computed(() => {
  if (imeiCheck.value?.in_stock) {
    return 'الجهاز ده موجود في مخزنك فعلاً.'
  }
  if (imeiDigits.value.length >= 15 && !isValidImei(imeiDigits.value)) {
    return 'الـ IMEI ده مش صحيح — راجع الأرقام.'
  }
  return tried.value && imeiDigits.value.length < 15 ? 'الـ IMEI لازم 15 رقم.' : null
})
const imei2Error = computed(() => form.imei2 && !isValidImei(form.imei2) ? 'الـ IMEI ده مش صحيح.' : null)
const imeiCheck = ref<ImeiCheck | null>(null)
watch(() => isValidImei(imeiDigits.value) ? imeiDigits.value : null, async (imei) => {
  imeiCheck.value = null
  if (!imei) {
    return
  }
  try {
    imeiCheck.value = (await api<{ data: ImeiCheck }>('/used-devices/imei-check', { query: { imei } })).data
  }
  catch {
    // The server checks again when buying.
  }
})

const margin = computed(() => {
  const buy = toPiasters(form.purchase_price)
  const sell = toPiasters(form.asking_price)
  return buy && sell ? sell - buy : null
})

const saving = ref(false)
const error = ref<string | null>(null)
const needsShift = ref(false)
const created = ref<UsedDevice | null>(null)
const paid = ref<number | null>(null)
const { printing, print } = usePrint()

function localError(): string | null {
  if (!parsedId.value?.valid) {
    return nationalIdError.value ?? 'اكتب الرقم القومي صح.'
  }
  if (form.seller_name.trim().length < 3) {
    return 'اكتب اسم البايع زي البطاقة.'
  }
  if (!idFront.value.length || !idBack.value.length) {
    return 'صوّر البطاقة وش وضهر.'
  }
  if (freeModel.value ? !form.model_name.trim() : !form.device_model_id) {
    return 'اختار الموديل.'
  }
  if (!isValidImei(imeiDigits.value)) {
    return imeiError.value ?? 'اكتب الـ IMEI صح.'
  }
  if (form.checklist.account_removed !== 'yes') {
    return 'لازم حساب iCloud / جوجل يتشال من الجهاز قبل ما تشتريه.'
  }
  if (!toPiasters(form.purchase_price) || !toPiasters(form.asking_price)) {
    return 'اكتب سعر الشراء وسعر البيع.'
  }
  return null
}

async function save() {
  tried.value = true
  error.value = localError()
  needsShift.value = false
  if (error.value) {
    return
  }
  saving.value = true
  try {
    const body = new FormData()
    const fields: Record<string, string | number | null | undefined> = {
      seller_name: form.seller_name.trim(),
      seller_phone: form.seller_phone.trim() || null,
      seller_national_id: latinDigits(form.national_id),
      device_model_id: freeModel.value ? null : form.device_model_id,
      model_name: freeModel.value ? form.model_name.trim() : null,
      storage: form.storage.trim() || null,
      color: form.color.trim() || null,
      imei: imeiDigits.value,
      imei2: form.imei2 ? latinDigits(form.imei2) : null,
      grade: form.grade,
      battery_health: form.battery_health || null,
      notes: form.notes.trim() || null,
      purchase_price: toPiasters(form.purchase_price),
      asking_price: toPiasters(form.asking_price),
      payment_method: form.payment_method,
    }
    for (const [k, v] of Object.entries(fields)) {
      if (v !== null && v !== undefined) {
        body.append(k, String(v))
      }
    }
    for (const c of options.value?.checklist ?? []) {
      body.append(`checklist[${c.key}]`, form.checklist[c.key] ?? 'na')
    }
    body.append('id_front', idFront.value[0]!)
    body.append('id_back', idBack.value[0]!)
    devicePhotos.value.forEach(f => body.append('device_photos[]', f))

    const res = await api<{ data: UsedDevice }>('/used-devices', { method: 'POST', body })
    created.value = res.data
    paid.value = toPiasters(form.purchase_price)
    await print()
    await navigateTo(`/used-devices/${res.data.id}`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
    needsShift.value = apiErrorCode(e) === 'shift_not_open'
  }
  finally {
    saving.value = false
  }
}
</script>

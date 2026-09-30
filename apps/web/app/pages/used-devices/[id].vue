<template>
  <div v-if="device" class="max-w-5xl space-y-6">
    <div class="flex items-start gap-3">
      <UButton to="/used-devices" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="text-xl font-extrabold sm:text-2xl">
          {{ device.title }}
        </h1>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-sm">
          <span class="num text-(--ui-text-muted)">{{ device.reference }}</span>
          <UBadge :color="usedStatusColor(device.status)" variant="subtle">
            {{ device.status_label }}
          </UBadge>
          <UBadge :color="gradeColor(device.grade)" variant="subtle">
            فئة {{ device.grade }} — {{ device.grade_label }}
          </UBadge>
        </div>
      </div>
      <div class="flex shrink-0 flex-wrap justify-end gap-2">
        <UButton v-if="device.seller" color="neutral" variant="outline" icon="i-lucide-printer" label="الإقرار" @click="print()" />
        <UButton v-if="device.status === 'in_stock'" icon="i-lucide-tag" label="غيّر السعر" @click="openPrice" />
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0 space-y-6">
        <UCard>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
            <div v-for="f in facts" :key="f.label">
              <dt class="text-xs text-(--ui-text-muted)">
                {{ f.label }}
              </dt>
              <dd class="font-bold" :class="f.ltr ? 'num' : ''" :dir="f.ltr ? 'ltr' : undefined" :style="f.ltr ? 'text-align: right' : undefined">
                {{ f.value }}
              </dd>
            </div>
          </dl>
          <p v-if="device.notes" class="mt-4 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
            {{ device.notes }}
          </p>
        </UCard>

        <UCard>
          <p class="mb-3 font-bold">
            الفحص وقت الشراء
          </p>
          <ul class="grid gap-1.5 sm:grid-cols-2">
            <li v-for="c in device.checklist" :key="c.key" class="flex items-center gap-2 text-sm">
              <UIcon :name="checkIcon(c.value)" class="size-4 shrink-0" :class="checkClass(c.value)" />
              <span :class="c.value === 'na' ? 'text-(--ui-text-muted)' : ''">{{ c.label }}</span>
            </li>
          </ul>
        </UCard>

        <UCard>
          <p class="mb-3 font-bold">
            الصور
          </p>
          <div v-if="devicePhotos.length" class="flex flex-wrap gap-2">
            <UsedDevicesSecureImage v-for="p in devicePhotos" :key="p.id" :src="`/used-devices/${device.id}/photos/${p.id}`" alt="صورة الجهاز" class="size-28" />
          </div>
          <p v-else class="text-sm text-(--ui-text-muted)">
            مفيش صور للجهاز.
          </p>
        </UCard>
      </div>

      <div class="space-y-6">
        <UCard>
          <div class="space-y-3">
            <div class="flex items-baseline justify-between">
              <span class="text-sm text-(--ui-text-muted)">سعر البيع</span>
              <span class="text-xl font-extrabold num">{{ formatMoney(device.asking_price) }}</span>
            </div>
            <div v-if="device.purchase_price !== null" class="flex items-baseline justify-between">
              <span class="text-sm text-(--ui-text-muted)">اتشرى بـ</span>
              <span class="font-bold num">{{ formatMoney(device.purchase_price) }}</span>
            </div>
            <template v-if="device.sale">
              <USeparator />
              <div class="flex items-baseline justify-between">
                <span class="text-sm text-(--ui-text-muted)">اتباع بـ</span>
                <span class="font-bold num">{{ formatMoney(device.sale.price) }}</span>
              </div>
              <div v-if="device.profit !== null" class="flex items-baseline justify-between">
                <span class="text-sm text-(--ui-text-muted)">المكسب</span>
                <span class="text-lg font-extrabold num" :class="device.profit >= 0 ? 'text-(--ui-success)' : 'text-(--ui-error)'">{{ formatMoney(device.profit) }}</span>
              </div>
              <UButton :to="`/sales/${device.sale.id}`" color="neutral" variant="outline" size="sm" block icon="i-lucide-receipt" :label="`الفاتورة ${device.sale.reference ?? ''}`" />
            </template>
          </div>
        </UCard>

        <UCard>
          <p class="mb-3 flex items-center gap-2 font-bold">
            <UIcon name="i-lucide-id-card" class="size-5 text-primary" />
            البايع
          </p>
          <template v-if="device.seller">
            <p class="font-bold">
              {{ device.seller.name }}
            </p>
            <p v-if="device.seller.phone" class="text-sm num" dir="ltr" style="text-align: right">
              {{ localPhone(device.seller.phone) }}
            </p>
            <p v-if="device.seller.national_id" class="mt-2 text-sm">
              الرقم القومي: <span class="num font-bold" dir="ltr">{{ device.seller.national_id }}</span>
            </p>
            <p v-if="device.seller.birth_date" class="text-xs text-(--ui-text-muted)">
              {{ device.seller.gender === 'male' ? 'ذكر' : 'أنثى' }} · <span class="num">{{ device.seller.age }}</span> سنة · {{ device.seller.governorate }}
            </p>
            <UAlert v-if="device.seller.erased" class="mt-3" color="neutral" variant="subtle" icon="i-lucide-user-x" :title="device.seller.id_purged ? 'بيانات البايع اتمسحت خالص.' : 'اسمه وموبايله اتمسحوا؛ البطاقة محفوظة لحد نهاية مدة الاحتفاظ.'" />
            <div v-if="idPhotos.length" class="mt-3 grid grid-cols-2 gap-2">
              <UsedDevicesSecureImage v-for="p in idPhotos" :key="p.id" :src="`/used-devices/${device.id}/photos/${p.id}`" :alt="p.kind === 'id_front' ? 'البطاقة — الوش' : 'البطاقة — الضهر'" class="aspect-[1.58] w-full" />
            </div>
            <UButton :to="`/used-devices/sellers?id=${device.seller.id}`" class="mt-3" color="neutral" variant="outline" size="sm" block icon="i-lucide-history" label="كل اللي باعه" />
          </template>
          <p v-else class="text-sm text-(--ui-text-muted)">
            <UIcon name="i-lucide-lock" class="size-4 align-middle" />
            بيانات البايع وصور البطاقة بتظهر لصاحب المحل والمدير بس.
          </p>
        </UCard>
      </div>
    </div>

    <UModal v-model:open="priceOpen" title="سعر البيع" :description="device.title">
      <template #body>
        <form id="price-form" class="space-y-3" @submit.prevent="savePrice">
          <UFormField label="السعر الجديد" hint="بالجنيه">
            <UInput v-model="newPrice" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" autofocus />
          </UFormField>
          <p v-if="device.purchase_price !== null && toPiasters(newPrice)" class="text-sm">
            المكسب: <b class="num">{{ formatMoney((toPiasters(newPrice) ?? 0) - device.purchase_price) }}</b>
          </p>
          <UAlert v-if="priceError" color="error" variant="subtle" :title="priceError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="priceOpen = false" />
          <UButton type="submit" form="price-form" label="حفظ" :loading="savingPrice" />
        </div>
      </template>
    </UModal>

    <PrintSheet v-if="printing" page-size="A4" margin="14mm">
      <UsedDevicesDeclaration :device="device" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { CheckValue, UsedDevice } from '~/types/api'

definePageMeta({ module: 'used_devices', permission: 'used_devices.manage' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const shop = computed(() => ({
  name: store.session?.tenant.name ?? '',
  phone: store.currentBranch?.phone ?? store.session?.tenant.phone ?? null,
  branch: store.currentBranch?.name ?? null,
  address: store.currentBranch?.address ?? null,
}))

const { data, refresh } = await useAsyncData(`used-device-${route.params.id}`, () => api<{ data: UsedDevice }>(`/used-devices/${route.params.id}`))
const device = computed(() => data.value?.data)
const devicePhotos = computed(() => device.value?.photos?.filter(p => p.kind === 'device') ?? [])
const idPhotos = computed(() => device.value?.photos?.filter(p => p.kind !== 'device') ?? [])

const facts = computed(() => {
  const d = device.value
  if (!d) {
    return []
  }
  return [
    { label: 'IMEI', value: d.imei, ltr: true },
    ...(d.imei2 ? [{ label: 'IMEI التاني', value: d.imei2, ltr: true }] : []),
    { label: 'المساحة', value: d.storage ?? '—' },
    { label: 'اللون', value: d.color ?? '—' },
    { label: 'البطارية', value: d.battery_health ? `${d.battery_health}%` : '—' },
    { label: 'اتشرى', value: formatDate(d.bought_at, true) },
    { label: 'اشتراه', value: d.bought_by_name ?? '—' },
    { label: 'الدفع', value: d.payment_method_label },
    { label: d.status === 'in_stock' ? 'في المخزن من' : 'قعد في المخزن', value: `${d.days_in_stock} يوم` },
  ]
})

function checkIcon(v: CheckValue) {
  return v === 'yes' ? 'i-lucide-circle-check' : v === 'no' ? 'i-lucide-circle-x' : 'i-lucide-circle-dashed'
}
function checkClass(v: CheckValue) {
  return v === 'yes' ? 'text-(--ui-success)' : v === 'no' ? 'text-(--ui-error)' : 'text-(--ui-text-dimmed)'
}

const { printing, print } = usePrint()

const priceOpen = ref(false)
const newPrice = ref('')
const savingPrice = ref(false)
const priceError = ref<string | null>(null)
function openPrice() {
  newPrice.value = String(toPounds(device.value?.asking_price) ?? '')
  priceError.value = null
  priceOpen.value = true
}
async function savePrice() {
  const price = toPiasters(newPrice.value)
  if (!price) {
    priceError.value = 'اكتب السعر.'
    return
  }
  savingPrice.value = true
  try {
    await api(`/used-devices/${route.params.id}`, { method: 'PATCH', body: { asking_price: price } })
    priceOpen.value = false
    toast.add({ color: 'success', title: 'السعر اتغير، والكاشير هيبيع بيه.' })
    await refresh()
  }
  catch (e) {
    priceError.value = apiErrorMessage(e)
  }
  finally {
    savingPrice.value = false
  }
}
</script>

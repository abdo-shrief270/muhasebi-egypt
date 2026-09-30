<template>
  <div class="max-w-5xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/used-devices" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="الشخص ده باع لنا قبل كده؟" description="دوّر على البايع بالرقم القومي أو الموبايل أو الاسم، وشوف كل الأجهزة اللي باعهالك." class="flex-1">
        <UButton v-if="store.isOwner" color="neutral" variant="outline" icon="i-lucide-shield-check" label="مدة الاحتفاظ بالبطايق" @click="settingsOpen = true" />
      </PageHeader>
    </div>

    <UInput v-model="q" size="xl" icon="i-lucide-search" placeholder="الرقم القومي (14 رقم)، الموبايل، أو الاسم…" class="w-full" autofocus />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
      <div class="space-y-2">
        <p v-if="!q.trim() && !results.length" class="text-sm text-(--ui-text-muted)">
          اكتب 3 حروف أو أرقام على الأقل.
        </p>
        <p v-else-if="searched && !results.length" class="rounded-(--ui-radius) border border-dashed border-(--ui-border) p-6 text-center text-sm text-(--ui-text-muted)">
          مفيش حد بالبيانات دي باعلك قبل كده.
        </p>
        <button
          v-for="s in results"
          :key="s.id"
          type="button"
          class="w-full rounded-(--ui-radius) border p-3 text-start transition-colors hover:bg-(--ui-bg-elevated)"
          :class="selectedId === s.id ? 'border-primary bg-(--ui-primary)/5' : 'border-(--ui-border)'"
          @click="select(s.id)"
        >
          <p class="font-bold">
            {{ s.name }}
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            <span v-if="s.national_id" class="num" dir="ltr">{{ s.national_id }}</span>
            <span v-if="s.phone"> · <span class="num" dir="ltr">{{ localPhone(s.phone) }}</span></span>
          </p>
          <p class="mt-1 text-xs">
            باع <span class="num">{{ s.devices_count }}</span> {{ s.devices_count === 1 ? 'جهاز' : 'أجهزة' }}<span v-if="s.last_sold_at"> · آخرها <span class="num">{{ formatDate(s.last_sold_at) }}</span></span>
          </p>
        </button>
      </div>

      <UCard v-if="seller">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-lg font-extrabold">
              {{ seller.name }}
            </p>
            <p v-if="seller.phone" class="num text-sm" dir="ltr" style="text-align: right">
              {{ localPhone(seller.phone) }}
            </p>
          </div>
          <UButton v-if="store.isOwner && !seller.erased" color="error" variant="outline" size="sm" icon="i-lucide-user-x" label="امسح بياناته" @click="eraseOpen = true" />
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-xs text-(--ui-text-muted)">
              الرقم القومي
            </dt>
            <dd class="num font-bold" dir="ltr" style="text-align: right">
              {{ seller.national_id ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-(--ui-text-muted)">
              تاريخ الميلاد
            </dt>
            <dd class="num font-bold">
              {{ seller.birth_date ? formatDate(seller.birth_date) : '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-(--ui-text-muted)">
              النوع
            </dt>
            <dd class="font-bold">
              {{ seller.gender === 'male' ? 'ذكر' : seller.gender === 'female' ? 'أنثى' : '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-(--ui-text-muted)">
              المحافظة
            </dt>
            <dd class="font-bold">
              {{ seller.governorate ?? '—' }}
            </dd>
          </div>
        </dl>
        <UAlert v-if="seller.erased" class="mt-3" color="neutral" variant="subtle" icon="i-lucide-user-x" :title="seller.id_purged ? 'البيانات اتمسحت خالص.' : 'الاسم والموبايل اتمسحوا. الرقم القومي وصور البطاقة محفوظين لحد نهاية مدة الاحتفاظ، وبعدين بيتمسحوا لوحدهم.'" />

        <p class="mt-5 mb-2 font-bold">
          الأجهزة اللي باعها
        </p>
        <ul class="divide-y divide-(--ui-border)">
          <li v-for="d in seller.devices" :key="d.id" class="flex items-center justify-between gap-2 py-2 text-sm">
            <div class="min-w-0">
              <NuxtLink :to="`/used-devices/${d.id}`" class="font-bold hover:underline">
                {{ d.title }}
              </NuxtLink>
              <p class="text-xs text-(--ui-text-muted)">
                <span class="num">{{ d.reference }}</span> · <span class="num">{{ formatDate(d.bought_at) }}</span> · <span class="num" dir="ltr">{{ d.imei }}</span>
              </p>
            </div>
            <UBadge :color="usedStatusColor(d.status)" variant="subtle" class="shrink-0">
              {{ d.status_label }}
            </UBadge>
          </li>
        </ul>
      </UCard>
    </div>

    <UModal v-model:open="eraseOpen" title="مسح بيانات البايع" description="قانون حماية البيانات الشخصية (151 لسنة 2020).">
      <template #body>
        <p class="text-sm leading-7">
          اسمه وموبايله هيتمسحوا دلوقتي. الرقم القومي وصور البطاقة هيفضلوا محفوظين ومتشفرين
          <b class="num">{{ settings?.id_retention_years ?? 3 }}</b> سنين من آخر جهاز باعهولك — ده إثبات ملكية الأجهزة لو اتسأل عنها — وبعدين بيتمسحوا لوحدهم. الأجهزة وفلوسها بتفضل.
        </p>
        <UAlert v-if="eraseError" class="mt-3" color="error" variant="subtle" :title="eraseError" />
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="eraseOpen = false" />
          <UButton color="error" label="امسح" :loading="erasing" @click="erase" />
        </div>
      </template>
    </UModal>

    <UModal v-model:open="settingsOpen" title="مدة الاحتفاظ بالبطايق" description="لكل البايعين.">
      <template #body>
        <form id="retention-form" class="space-y-3" @submit.prevent="saveSettings">
          <p class="text-sm leading-7">
            الرقم القومي وصور بطاقة أي بايع بيفضلوا محفوظين ومتشفرين المدة دي من آخر جهاز باعهولك (حماية ليك لو الجهاز طلع مسروق)، وبعدين بيتمسحوا لوحدهم. ولو بايع طلب مسح بياناته، اسمه وموبايله بيتمسحوا على طول.
          </p>
          <UFormField label="المدة">
            <div class="flex items-center gap-2">
              <UInputNumber v-model="years" :min="settings?.min_years ?? 1" :max="settings?.max_years ?? 10" class="w-32" />
              <span class="text-sm">سنة</span>
            </div>
          </UFormField>
          <p v-if="settings?.updated_by_name" class="text-xs text-(--ui-text-muted)">
            آخر تعديل: {{ settings.updated_by_name }}<span v-if="settings.updated_at"> · <span class="num">{{ formatDate(settings.updated_at, true) }}</span></span>
          </p>
          <UAlert v-if="settingsError" color="error" variant="subtle" :title="settingsError" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="settingsOpen = false" />
          <UButton type="submit" form="retention-form" label="حفظ" :loading="savingSettings" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { UsedDeviceSellerSummary, UsedDeviceSettings } from '~/types/api'

definePageMeta({ module: 'used_devices', permission: 'used_devices.view_seller' })

const api = useApi()
const route = useRoute()
const router = useRouter()
const store = useSessionStore()
const toast = useToast()

const q = ref('')
const results = ref<UsedDeviceSellerSummary[]>([])
const searched = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    // A number (national ID / phone) may be typed in Arabic digits or with spaces; a name is kept as typed.
    const term = /^[\d٠-٩\s+-]+$/.test(value.trim()) ? latinDigits(value.trim()) : value.trim()
    if (term.length < 3) {
      results.value = []
      searched.value = false
      return
    }
    const id = ++requestId
    const res = await api<{ data: UsedDeviceSellerSummary[] }>('/used-devices/sellers', { query: { q: term } })
    if (id === requestId) {
      results.value = res.data
      searched.value = true
      if (res.data.length === 1) {
        select(res.data[0]!.id)
      }
    }
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))

const selectedId = ref<string | null>(typeof route.query.id === 'string' ? route.query.id : null)
const seller = ref<UsedDeviceSellerSummary | null>(null)
async function load() {
  seller.value = selectedId.value ? (await api<{ data: UsedDeviceSellerSummary }>(`/used-devices/sellers/${selectedId.value}`)).data : null
}
function select(id: string) {
  selectedId.value = id
  router.replace({ query: { id } })
}
watch(selectedId, load, { immediate: true })

const eraseOpen = ref(false)
const erasing = ref(false)
const eraseError = ref<string | null>(null)
async function erase() {
  if (!seller.value) {
    return
  }
  erasing.value = true
  eraseError.value = null
  try {
    seller.value = (await api<{ data: UsedDeviceSellerSummary }>(`/used-devices/sellers/${seller.value.id}/erase`, { method: 'POST' })).data
    eraseOpen.value = false
    toast.add({ color: 'success', title: 'اسمه وموبايله اتمسحوا.' })
  }
  catch (e) {
    eraseError.value = apiErrorMessage(e)
  }
  finally {
    erasing.value = false
  }
}

const settingsOpen = ref(false)
const settings = ref<UsedDeviceSettings | null>(null)
const years = ref(3)
const savingSettings = ref(false)
const settingsError = ref<string | null>(null)
async function loadSettings() {
  if (!store.isOwner) {
    return
  }
  settings.value = (await api<{ data: UsedDeviceSettings }>('/used-devices/settings')).data
  years.value = settings.value.id_retention_years
}
watch([settingsOpen, eraseOpen], ([a, b]) => {
  if (a || b) {
    settingsError.value = null
    loadSettings()
  }
})
async function saveSettings() {
  savingSettings.value = true
  try {
    settings.value = (await api<{ data: UsedDeviceSettings }>('/used-devices/settings', { method: 'PUT', body: { id_retention_years: years.value } })).data
    settingsOpen.value = false
    toast.add({ color: 'success', title: 'اتحفظ.' })
  }
  catch (e) {
    settingsError.value = apiErrorMessage(e)
  }
  finally {
    savingSettings.value = false
  }
}
</script>

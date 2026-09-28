<template>
  <div class="space-y-6 max-w-4xl">
    <div class="flex items-center gap-3">
      <UButton to="/shop-orders" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square />
      <h1 class="text-2xl font-extrabold">
        طلب جديد من محل شريك
      </h1>
    </div>

    <UAlert
      v-if="!partners.length"
      color="warning"
      variant="subtle"
      icon="i-lucide-users"
      title="لازم يكون عندك شريك الأول"
      description="ضيف محل بالكود بتاعه، ولما يوافق تقدر تطلب منه."
      :actions="[{ label: 'الشركاء', to: '/shop-orders/partners' }]"
    />

    <form v-else class="space-y-6" @submit.prevent="submit">
      <UCard>
        <div class="grid gap-4 md:grid-cols-2">
          <UFormField label="المحل" required>
            <USelect v-model="form.seller_tenant_id" :items="partnerItems" placeholder="اختار المحل" class="w-full" />
          </UFormField>
          <UFormField label="نوع الطلب" required>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="t in types"
                :key="t.value"
                type="button"
                class="flex h-10 items-center justify-center gap-2 rounded-[calc(var(--ui-radius)*1.5)] border font-semibold transition"
                :class="form.type === t.value ? 'border-primary app-soft' : 'border-(--ui-border) hover:bg-(--ui-bg-elevated)'"
                @click="form.type = t.value"
              >
                <UIcon :name="t.icon" class="size-4" /> {{ t.label }}
              </button>
            </div>
          </UFormField>
        </div>
      </UCard>

      <UCard :ui="{ header: 'flex items-center justify-between' }">
        <template #header>
          <span class="font-bold">{{ form.type === 'repair' ? 'الأجهزة' : 'الأصناف' }}</span>
          <UButton size="sm" variant="soft" icon="i-lucide-plus" :label="form.type === 'repair' ? 'جهاز تاني' : 'صنف تاني'" @click="addItem" />
        </template>

        <div class="space-y-3">
          <div v-for="(item, i) in form.items" :key="i" class="grid gap-3 rounded-[calc(var(--ui-radius)*1.5)] bg-(--ui-bg-muted) p-3" :class="form.type === 'repair' ? 'md:grid-cols-[1fr_1fr_1fr_auto]' : 'md:grid-cols-[1fr_120px_auto]'">
            <template v-if="form.type === 'repair'">
              <UInput v-model="item.device_model" placeholder="الموديل (مثلاً iPhone 12)" />
              <UInput v-model="item.imei" placeholder="IMEI (اختياري)" dir="ltr" inputmode="numeric" />
              <UInput v-model="item.description" placeholder="العطل (مثلاً قفلة بوردة)" />
            </template>
            <template v-else>
              <UInput v-model="item.description" placeholder="الصنف (مثلاً شاشة iPhone 11 أصلي سيرفس)" />
              <UInputNumber v-model="item.quantity" :min="1" />
            </template>
            <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" square :disabled="form.items.length === 1" @click="form.items.splice(i, 1)" />
          </div>
        </div>
      </UCard>

      <UCard>
        <div class="grid gap-4 md:grid-cols-[220px_1fr]">
          <UFormField label="محتاجه إمتى؟">
            <UInput v-model="form.needed_by" type="date" class="w-full" />
          </UFormField>
          <UFormField label="ملاحظات">
            <UTextarea v-model="form.notes" :rows="2" placeholder="أي تفاصيل للمحل التاني…" class="w-full" />
          </UFormField>
        </div>
      </UCard>

      <UAlert v-if="error" color="error" variant="subtle" :title="error" />

      <div class="flex justify-end gap-2">
        <UButton to="/shop-orders" color="neutral" variant="ghost" label="إلغاء" />
        <UButton type="submit" size="lg" icon="i-lucide-send" label="إرسال الطلب" :loading="loading" />
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { ShopConnection, ShopOrder } from '~/types/api'

definePageMeta({ module: 'shop_orders' })

interface DraftItem { description: string, quantity: number, device_model: string, imei: string }

const api = useApi()
const route = useRoute()

const types = [
  { value: 'goods', label: 'بضاعة', icon: 'i-lucide-package' },
  { value: 'repair', label: 'شغل صيانة', icon: 'i-lucide-wrench' },
] as const

const { data } = await useAsyncData('shop-connections', () => api<{ data: ShopConnection[] }>('/shop-connections'))
const partners = computed(() => (data.value?.data ?? []).filter(c => c.status === 'accepted' && c.shop))
const partnerItems = computed(() => partners.value.map(c => ({ label: c.shop!.name, value: c.shop!.id })))

const emptyItem = (): DraftItem => ({ description: '', quantity: 1, device_model: '', imei: '' })
const form = reactive({
  seller_tenant_id: typeof route.query.seller === 'string' ? route.query.seller : '',
  type: 'goods' as 'goods' | 'repair',
  items: [emptyItem()],
  needed_by: '',
  notes: '',
})
const loading = ref(false)
const error = ref<string | null>(null)

function addItem() {
  form.items.push(emptyItem())
}

async function submit() {
  loading.value = true
  error.value = null
  try {
    const res = await api<{ data: ShopOrder }>('/shop-orders', {
      method: 'POST',
      body: {
        seller_tenant_id: form.seller_tenant_id,
        type: form.type,
        needed_by: form.needed_by || null,
        notes: form.notes || null,
        items: form.items.map(item => ({
          description: item.description,
          quantity: form.type === 'repair' ? 1 : item.quantity,
          device_model: item.device_model || null,
          imei: item.imei || null,
        })),
      },
    })
    await navigateTo(`/shop-orders/${res.data.id}`)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

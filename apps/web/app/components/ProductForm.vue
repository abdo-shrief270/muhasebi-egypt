<template>
  <form class="space-y-6" @submit.prevent="save">
    <UCard>
      <div class="grid gap-4 md:grid-cols-2">
        <UFormField label="اسم الصنف" required class="md:col-span-2">
          <UInput v-model="form.name" placeholder="مثلاً: سكرينة 9D، جراب سيليكون، شاشة أصلي" class="w-full" autofocus />
        </UFormField>
        <UFormField label="التصنيف" required>
          <USelect v-model="form.category_id" :items="categoryItems" placeholder="اختار التصنيف" class="w-full" />
        </UFormField>
        <UFormField label="الماركة" hint="اختياري">
          <USelect v-model="form.brand_id" :items="brandItems" class="w-full" />
        </UFormField>
        <UFormField label="كود الصنف (SKU)" hint="اختياري">
          <UInput v-model="form.sku" dir="ltr" class="w-full" />
        </UFormField>
        <div class="flex flex-col justify-end gap-3 pb-1">
          <USwitch v-model="form.track_serial" label="كل قطعة ليها IMEI / سيريال" description="للموبايلات والأجهزة: هتسجّل رقم كل قطعة وقت الشراء والبيع." />
          <USwitch v-if="product" v-model="form.is_active" label="الصنف شغال" description="الصنف الموقوف مش بيظهر في البيع." />
        </div>
      </div>
    </UCard>

    <UCard>
      <template #header>
        <p class="font-bold">
          الموديلات اللي بيركب عليها
        </p>
        <p class="text-sm text-(--ui-text-muted)">
          لما حد يدوّر بـ «iPhone 13» هيلاقي كل الأصناف اللي بتركب عليه. سيبها فاضية للأصناف العامة زي الشواحن.
        </p>
      </template>
      <DeviceModelPicker v-model="form.device_model_ids" multiple :known="product?.device_models" placeholder="اختار موديل أو أكتر" />
    </UCard>

    <UCard :ui="{ header: 'flex items-center justify-between gap-3' }">
      <template #header>
        <div>
          <p class="font-bold">
            الأنواع والأسعار
          </p>
          <p class="text-sm text-(--ui-text-muted)">
            نوع لكل لون أو سعة أو جودة ليه سعر أو باركود مختلف. الأسعار بالجنيه.
          </p>
        </div>
        <UButton size="sm" variant="soft" icon="i-lucide-plus" label="نوع تاني" @click="addVariant" />
      </template>

      <div class="space-y-3">
        <div v-for="(v, i) in form.variants" :key="v.key" class="space-y-3 rounded-[calc(var(--ui-radius)*1.5)] bg-(--ui-bg-muted) p-3">
          <div class="grid gap-3 md:grid-cols-[1fr_160px_1fr_auto]">
            <UFormField label="النوع" :hint="form.variants.length === 1 ? 'اختياري' : undefined">
              <UInput v-model="v.name" placeholder="مثلاً: أسود، 128GB، مطفي" class="w-full" />
            </UFormField>
            <UFormField label="الجودة">
              <USelect v-model="v.quality_grade" :items="qualityItems" class="w-full" />
            </UFormField>
            <UFormField label="الباركود">
              <UInput v-model="v.barcode" dir="ltr" icon="i-lucide-scan-barcode" placeholder="امسحه أو اكتبه" class="w-full" />
            </UFormField>
            <div class="flex items-end">
              <UButton color="neutral" variant="ghost" icon="i-lucide-trash-2" square aria-label="حذف النوع" :disabled="form.variants.length === 1" @click="form.variants.splice(i, 1)" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <UFormField label="سعر القطاعي" required>
              <UInput v-model="v.price_retail" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="سعر الجملة">
              <UInput v-model="v.price_wholesale" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="سعر الفني">
              <UInput v-model="v.price_technician" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="سعر الأونلاين">
              <UInput v-model="v.price_online" type="number" min="0" step="any" inputmode="decimal" dir="ltr" class="w-full" />
            </UFormField>
            <UFormField label="حد النواقص" hint="قطعة">
              <UInput v-model="v.min_stock" type="number" min="0" step="1" inputmode="numeric" dir="ltr" class="w-full" />
            </UFormField>
          </div>
        </div>
      </div>
    </UCard>

    <UCard>
      <UFormField label="ملاحظات">
        <UTextarea v-model="form.notes" :rows="2" class="w-full" />
      </UFormField>
    </UCard>

    <UAlert v-if="error" color="error" variant="subtle" :title="error" />

    <div class="flex justify-end gap-2">
      <UButton to="/products" color="neutral" variant="ghost" label="إلغاء" />
      <UButton type="submit" size="lg" icon="i-lucide-check" :label="product ? 'حفظ التعديلات' : 'إضافة الصنف'" :loading="saving" />
    </div>
  </form>
</template>

<script setup lang="ts">
import type { Brand, Category, Product, QualityGrade } from '~/types/api'

const props = defineProps<{ product?: Product }>()
const emit = defineEmits<{ saved: [product: Product] }>()

const NONE = 0
const NO_GRADE = 'none'

interface DraftVariant {
  key: number
  id?: string
  name: string
  quality_grade: QualityGrade | typeof NO_GRADE
  barcode: string
  /** Pounds, as typed. */
  price_retail: string
  price_wholesale: string
  price_technician: string
  price_online: string
  min_stock: string
}

const poundsText = (piasters: number | null | undefined): string => toPounds(piasters)?.toString() ?? ''

const api = useApi()

const [{ data: categoriesData }, { data: brandsData }] = await Promise.all([
  useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories')),
  useAsyncData('catalog-brands', () => api<{ data: Brand[] }>('/catalog/brands')),
])
const categoryItems = computed(() => (categoriesData.value?.data ?? []).map(c => ({ label: `${c.name} · ${c.type_label}`, value: c.id })))
const brandItems = computed(() => [{ label: 'بدون ماركة', value: NONE }, ...(brandsData.value?.data ?? []).map(b => ({ label: b.name, value: b.id }))])
const qualityItems = [{ label: '—', value: NO_GRADE }, ...qualityGrades]

let nextKey = 0
function draftVariant(v?: Product['variants'][number]): DraftVariant {
  return {
    key: nextKey++,
    id: v?.id,
    name: v?.name ?? '',
    quality_grade: v?.quality_grade ?? NO_GRADE,
    barcode: v?.barcode ?? '',
    price_retail: poundsText(v?.price_retail),
    price_wholesale: poundsText(v?.price_wholesale),
    price_technician: poundsText(v?.price_technician),
    price_online: poundsText(v?.price_online),
    min_stock: String(v?.min_stock ?? 0),
  }
}

const p = props.product
const form = reactive({
  name: p?.name ?? '',
  category_id: p?.category.id as number | undefined,
  brand_id: p?.brand?.id ?? NONE,
  sku: p?.sku ?? '',
  track_serial: p?.track_serial ?? false,
  is_active: p?.is_active ?? true,
  notes: p?.notes ?? '',
  device_model_ids: (p?.device_models ?? []).map(m => m.id) as number[] | number | undefined,
  variants: p?.variants.length ? p.variants.map(draftVariant) : [draftVariant()],
})

function addVariant() {
  form.variants.push(draftVariant())
}

const saving = ref(false)
const error = ref<string | null>(null)

async function save() {
  saving.value = true
  error.value = null
  const body = {
    name: form.name,
    category_id: form.category_id,
    brand_id: form.brand_id === NONE ? null : form.brand_id,
    sku: form.sku || null,
    track_serial: form.track_serial,
    is_active: form.is_active,
    notes: form.notes || null,
    device_model_ids: Array.isArray(form.device_model_ids) ? form.device_model_ids : [],
    variants: form.variants.map(v => ({
      id: v.id,
      name: v.name || null,
      quality_grade: v.quality_grade === NO_GRADE ? null : v.quality_grade,
      barcode: v.barcode || null,
      price_retail: toPiasters(v.price_retail),
      price_wholesale: toPiasters(v.price_wholesale),
      price_technician: toPiasters(v.price_technician),
      price_online: toPiasters(v.price_online),
      min_stock: Number(v.min_stock) || 0,
    })),
  }
  try {
    const res = props.product
      ? await api<{ data: Product }>(`/products/${props.product.id}`, { method: 'PATCH', body })
      : await api<{ data: Product }>('/products', { method: 'POST', body })
    emit('saved', res.data)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

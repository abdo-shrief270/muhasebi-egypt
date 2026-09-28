<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/products" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="التصنيفات والماركات" description="القوايم اللي بتنظّم بيها أصنافك. مينفعش تمسح حاجة مربوط بيها أصناف." class="flex-1" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr_3fr]">
      <UCard>
        <template #header>
          <p class="font-bold">
            التصنيفات
          </p>
        </template>

        <form class="mb-4 grid grid-cols-[1fr_140px_auto] gap-2" @submit.prevent="addCategory">
          <UInput v-model="newCategory.name" placeholder="تصنيف جديد" />
          <USelect v-model="newCategory.type" :items="categoryTypes" />
          <UButton type="submit" icon="i-lucide-plus" square aria-label="إضافة تصنيف" :loading="busy === 'category'" />
        </form>

        <ul class="divide-y divide-(--ui-border)">
          <li v-for="c in categories" :key="c.id" class="flex items-center gap-2 py-2">
            <span class="flex-1 font-semibold">{{ c.name }}</span>
            <UBadge color="neutral" variant="subtle" size="sm">
              {{ c.type_label }}
            </UBadge>
            <span class="w-16 text-end text-xs text-(--ui-text-muted) num">{{ c.products_count ?? 0 }} صنف</span>
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" square aria-label="تعديل" @click="rename('category', c)" />
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" square aria-label="حذف" @click="remove(`/catalog/categories/${c.id}`)" />
          </li>
        </ul>
      </UCard>

      <UCard>
        <template #header>
          <p class="font-bold">
            الماركات والموديلات
          </p>
        </template>

        <form class="mb-4 grid grid-cols-[1fr_auto] gap-2" @submit.prevent="addBrand">
          <UInput v-model="newBrand" placeholder="ماركة جديدة" dir="auto" />
          <UButton type="submit" icon="i-lucide-plus" label="إضافة ماركة" :loading="busy === 'brand'" />
        </form>

        <div class="space-y-3">
          <div v-for="b in brands" :key="b.id" class="rounded-[calc(var(--ui-radius)*1.5)] border border-(--ui-border) p-3">
            <div class="mb-2 flex items-center gap-2">
              <span class="flex-1 font-bold" dir="auto">{{ b.name }}</span>
              <span class="text-xs text-(--ui-text-muted) num">{{ b.models?.length ?? 0 }} موديل</span>
              <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" square aria-label="تعديل" @click="rename('brand', b)" />
              <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" square aria-label="حذف" @click="remove(`/catalog/brands/${b.id}`)" />
            </div>
            <div class="flex flex-wrap items-center gap-1.5" dir="ltr">
              <UBadge v-for="m in b.models" :key="m.id" color="neutral" variant="outline" class="gap-1">
                {{ m.name }}
                <button type="button" class="opacity-50 hover:opacity-100" :aria-label="`حذف ${m.name}`" @click="remove(`/catalog/device-models/${m.id}`)">
                  <UIcon name="i-lucide-x" class="size-3" />
                </button>
              </UBadge>
              <form class="inline-flex" @submit.prevent="addModel(b.id)">
                <UInput v-model="newModel[b.id]" size="xs" placeholder="+ موديل" class="w-32" />
              </form>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <UModal v-model:open="renaming.open" title="تعديل الاسم">
      <template #body>
        <form id="rename-form" @submit.prevent="saveRename">
          <UInput v-model="renaming.name" class="w-full" autofocus dir="auto" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="renaming.open = false" />
          <UButton type="submit" form="rename-form" label="حفظ" :loading="busy === 'rename'" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { Brand, Category, CategoryType } from '~/types/api'

definePageMeta({ permission: 'products.manage' })

const api = useApi()
const toast = useToast()

const [{ data: categoriesData, refresh: refreshCategories }, { data: brandsData, refresh: refreshBrands }] = await Promise.all([
  useAsyncData('catalog-categories', () => api<{ data: Category[] }>('/catalog/categories')),
  useAsyncData('catalog-brands', () => api<{ data: Brand[] }>('/catalog/brands')),
])
const categories = computed(() => categoriesData.value?.data ?? [])
const brands = computed(() => brandsData.value?.data ?? [])

const busy = ref<string | null>(null)
const newCategory = reactive({ name: '', type: 'accessory' as CategoryType })
const newBrand = ref('')
const newModel = reactive<Record<number, string>>({})

async function run(key: string, request: () => Promise<unknown>, after: () => Promise<unknown>): Promise<boolean> {
  busy.value = key
  try {
    await request()
    await after()
    return true
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
    return false
  }
  finally {
    busy.value = null
  }
}

const refreshAll = () => Promise.all([refreshCategories(), refreshBrands()])

async function addCategory() {
  if (await run('category', () => api('/catalog/categories', { method: 'POST', body: newCategory }), refreshCategories)) {
    newCategory.name = ''
  }
}

async function addBrand() {
  if (await run('brand', () => api('/catalog/brands', { method: 'POST', body: { name: newBrand.value } }), refreshBrands)) {
    newBrand.value = ''
  }
}

async function addModel(brandId: number) {
  const name = newModel[brandId]?.trim()
  if (!name) {
    return
  }
  if (await run(`model-${brandId}`, () => api(`/catalog/brands/${brandId}/models`, { method: 'POST', body: { name } }), refreshBrands)) {
    newModel[brandId] = ''
  }
}

async function remove(url: string) {
  await run('delete', () => api(url, { method: 'DELETE' }), refreshAll)
}

const renaming = reactive({ open: false, url: '', name: '' })

function rename(kind: 'category' | 'brand', entry: { id: number, name: string }) {
  Object.assign(renaming, {
    open: true,
    url: kind === 'category' ? `/catalog/categories/${entry.id}` : `/catalog/brands/${entry.id}`,
    name: entry.name,
  })
}

async function saveRename() {
  if (await run('rename', () => api(renaming.url, { method: 'PATCH', body: { name: renaming.name } }), refreshAll)) {
    renaming.open = false
  }
}
</script>

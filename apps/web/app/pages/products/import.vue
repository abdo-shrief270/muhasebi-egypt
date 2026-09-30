<template>
  <div class="space-y-6 max-w-5xl">
    <div class="flex items-center gap-3">
      <UButton to="/products" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader title="استيراد أصناف من Excel" description="نزّل النموذج، املاه بأصنافك، وارفعه. هتشوف اللي هيحصل قبل ما يتحفظ أي حاجة." class="flex-1" />
    </div>

    <UCard v-if="result">
      <div class="flex flex-col items-center gap-3 py-6 text-center">
        <div class="grid size-14 place-items-center rounded-full app-soft">
          <UIcon name="i-lucide-check" class="size-7" />
        </div>
        <p class="text-xl font-extrabold">
          اتستوردت الأصناف
        </p>
        <p class="text-(--ui-text-muted)">
          <span class="num">{{ result.new_products }}</span> صنف جديد ·
          <span class="num">{{ result.new_variants }}</span> نوع جديد ·
          <span class="num">{{ result.updated_variants }}</span> نوع اتحدّثت أسعاره
        </p>
        <div class="flex gap-2">
          <UButton to="/products" label="شوف الأصناف" icon="i-lucide-package" />
          <UButton color="neutral" variant="outline" label="استيراد ملف تاني" @click="reset" />
        </div>
      </div>
    </UCard>

    <template v-else>
      <UCard>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
          <div class="space-y-1.5 text-sm">
            <p class="font-bold">
              1. نزّل النموذج واملاه
            </p>
            <p class="text-(--ui-text-muted)">
              كل صف = نوع واحد (لون أو سعة أو جودة). الصفوف اللي ليها نفس الاسم والتصنيف بتبقى صنف واحد.
              لو الباركود موجود عندك، الصف بيحدّث أسعاره بس — ينفع تستخدمه لتعديل الأسعار بالجملة.
              التصنيفات والماركات الجديدة بتتعمل لوحدها.
            </p>
          </div>
          <UButton color="neutral" variant="outline" icon="i-lucide-download" label="نزّل النموذج" :loading="downloading" @click="downloadTemplate" />
        </div>
      </UCard>

      <UCard>
        <p class="mb-3 text-sm font-bold">
          2. ارفع الملف
        </p>
        <UFileUpload
          v-model="file"
          accept=".xlsx,.csv"
          icon="i-lucide-file-spreadsheet"
          label="اسحب الملف هنا أو دوس للاختيار"
          description="Excel (xlsx) أو CSV — لحد 5000 صف"
          class="min-h-40 w-full"
        />
        <p v-if="file" class="mt-3 flex items-center gap-2 text-sm">
          <UIcon name="i-lucide-file-spreadsheet" class="size-4 text-primary" />
          <span class="font-bold">{{ file.name }}</span>
          <span class="text-(--ui-text-muted) num">{{ Math.max(1, Math.round(file.size / 1024)) }} KB</span>
        </p>
      </UCard>

      <UAlert v-if="error" color="error" variant="subtle" icon="i-lucide-circle-alert" :title="error" />

      <div v-if="checking" class="flex items-center justify-center gap-2 py-6 text-(--ui-text-muted)">
        <UIcon name="i-lucide-loader-circle" class="size-5 animate-spin" /> بنراجع الملف…
      </div>

      <template v-if="preview && !checking">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <div v-for="stat in stats" :key="stat.label" class="app-card rounded-[calc(var(--ui-radius)*2)] bg-(--ui-bg) p-4">
            <p class="text-sm text-(--ui-text-muted)">
              {{ stat.label }}
            </p>
            <p class="text-2xl font-extrabold num" :class="stat.class">
              {{ stat.value }}
            </p>
          </div>
        </div>

        <UAlert
          v-if="preview.new_categories.length || preview.new_brands.length"
          color="info"
          variant="subtle"
          icon="i-lucide-info"
          title="هيتعمل تلقائي"
          :description="[
            preview.new_categories.length ? `تصنيفات: ${preview.new_categories.join('، ')}` : '',
            preview.new_brands.length ? `ماركات: ${preview.new_brands.join('، ')}` : '',
          ].filter(Boolean).join(' · ')"
        />

        <UCard v-if="preview.errors.length" :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold text-error">
              صفوف فيها أخطاء ومش هتتستورد
            </p>
          </template>
          <ul class="divide-y divide-(--ui-border) text-sm">
            <li v-for="e in preview.errors" :key="e.row" class="flex gap-3 px-4 py-2">
              <span class="w-16 shrink-0 font-bold">صف <span class="num">{{ e.row }}</span></span>
              <span class="text-error">{{ e.messages.join(' · ') }}</span>
            </li>
          </ul>
        </UCard>

        <UCard v-if="preview.warnings.length" :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <p class="font-bold text-warning">
              ملاحظات (الصفوف دي هتتستورد)
            </p>
          </template>
          <ul class="divide-y divide-(--ui-border) text-sm">
            <li v-for="w in preview.warnings" :key="w.row" class="flex gap-3 px-4 py-2">
              <span class="w-16 shrink-0 font-bold">صف <span class="num">{{ w.row }}</span></span>
              <span class="text-(--ui-text-muted)">{{ w.messages.join(' · ') }}</span>
            </li>
          </ul>
        </UCard>

        <div class="flex flex-wrap justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="reset" />
          <UButton
            v-if="preview.invalid_rows && preview.valid_rows"
            color="neutral"
            variant="outline"
            :label="`استورد الصفوف السليمة بس (${preview.valid_rows})`"
            :loading="importing"
            @click="runImport(true)"
          />
          <UButton
            v-if="!preview.invalid_rows"
            size="lg"
            icon="i-lucide-upload"
            :label="`استورد ${preview.valid_rows} صف`"
            :loading="importing"
            @click="runImport(false)"
          />
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ permission: 'products.manage', feature: 'catalog.excel_import' })

interface RowMessages { row: number, messages: string[] }
interface ImportSummary {
  rows: number
  valid_rows: number
  invalid_rows: number
  new_products: number
  new_variants: number
  updated_variants: number
  new_categories: string[]
  new_brands: string[]
  errors: RowMessages[]
  warnings: RowMessages[]
}

const api = useApi()

const file = ref<File | null | undefined>(null)
const preview = ref<ImportSummary | null>(null)
const result = ref<ImportSummary | null>(null)
const error = ref<string | null>(null)
const checking = ref(false)
const importing = ref(false)
const downloading = ref(false)

const stats = computed(() => preview.value
  ? [
      { label: 'صنف جديد', value: preview.value.new_products, class: '' },
      { label: 'نوع جديد', value: preview.value.new_variants, class: '' },
      { label: 'أسعار هتتحدّث', value: preview.value.updated_variants, class: '' },
      { label: 'صفوف فيها أخطاء', value: preview.value.invalid_rows, class: preview.value.invalid_rows ? 'text-error' : '' },
    ]
  : [])

function body(skipInvalid?: boolean): FormData {
  const form = new FormData()
  form.append('file', file.value as File)
  if (skipInvalid !== undefined) {
    form.append('skip_invalid', skipInvalid ? '1' : '0')
  }
  return form
}

// A new file is checked right away; nothing is saved until the user confirms.
watch(file, async (value) => {
  preview.value = null
  error.value = null
  if (!value) {
    return
  }
  checking.value = true
  try {
    preview.value = (await api<{ data: ImportSummary }>('/products/import/preview', { method: 'POST', body: body() })).data
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    checking.value = false
  }
})

async function runImport(skipInvalid: boolean) {
  importing.value = true
  error.value = null
  try {
    result.value = (await api<{ data: ImportSummary }>('/products/import', { method: 'POST', body: body(skipInvalid) })).data
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    importing.value = false
  }
}

function reset() {
  file.value = null
  preview.value = null
  result.value = null
  error.value = null
}

async function downloadTemplate() {
  downloading.value = true
  try {
    const blob = await api<Blob>('/products/import/template', { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'نموذج-الأصناف.xlsx'
    link.click()
    URL.revokeObjectURL(url)
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    downloading.value = false
  }
}
</script>

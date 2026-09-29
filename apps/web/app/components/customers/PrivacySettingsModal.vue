<template>
  <UModal v-model:open="open" title="خصوصية بيانات العملاء" description="قانون حماية البيانات الشخصية (151 لسنة 2020).">
    <template #body>
      <form id="privacy-form" class="space-y-4" @submit.prevent="save">
        <USwitch v-model="enabled" label="امسح بيانات العملاء اللي مالهمش حركة" description="كل يوم بالليل: العميل اللي مفيش عليه ولا ليه فلوس، ومالوش جهاز في الصيانة، ومالوش حركة من المدة دي، اسمه وموبايله بيتمسحوا. الفلوس والفواتير بتفضل." />
        <UFormField v-if="enabled" label="مالهمش حركة من">
          <div class="flex items-center gap-2">
            <UInputNumber v-model="years" :min="settings?.min_years ?? 1" :max="settings?.max_years ?? 10" class="w-32" />
            <span class="text-sm">سنة</span>
          </div>
        </UFormField>
        <p v-if="settings?.updated_by_name" class="text-xs text-(--ui-text-muted)">
          آخر تعديل: {{ settings.updated_by_name }}<span v-if="settings.updated_at"> · <span class="num">{{ formatDate(settings.updated_at, true) }}</span></span>
        </p>
        <p class="text-xs text-(--ui-text-muted)">
          تقدر كمان تمسح بيانات عميل بعينه من صفحته (البيانات ← مسح بيانات العميل)، أو تنزّلهاله لو طلبها.
          <NuxtLink to="/privacy" target="_blank" class="text-primary hover:underline">سياسة الخصوصية</NuxtLink>
        </p>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="privacy-form" label="حفظ" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CustomerPrivacySettings } from '~/types/api'

/** The shop's customer-data retention period (owner only). */
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const toast = useToast()
const settings = ref<CustomerPrivacySettings | null>(null)
const enabled = ref(false)
const years = ref(3)
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }
  error.value = null
  try {
    settings.value = (await api<{ data: CustomerPrivacySettings }>('/customers/privacy-settings')).data
    enabled.value = settings.value.retention_years !== null
    years.value = settings.value.retention_years ?? 3
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
})

async function save() {
  saving.value = true
  error.value = null
  try {
    settings.value = (await api<{ data: CustomerPrivacySettings }>('/customers/privacy-settings', {
      method: 'PUT',
      body: { retention_years: enabled.value ? years.value : null },
    })).data
    open.value = false
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>

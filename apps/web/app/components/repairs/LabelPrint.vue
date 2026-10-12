<template>
  <UButton v-bind="$attrs" icon="i-lucide-sticker" :label="label" @click="open = true" />

  <UModal v-model:open="open" title="ستيكر الجهاز">
    <template #body>
      <div class="space-y-4">
        <div class="flex justify-center rounded-(--ui-radius) bg-(--ui-bg-elevated) p-4">
          <div class="overflow-hidden rounded-sm bg-white shadow" :style="{ width: `${size.w}mm`, height: `${size.h}mm` }">
            <RepairsDeviceLabel :ticket="ticket" :size="size" :show="settings.show" />
          </div>
        </div>
        <UFormField label="مقاس الرول" :hint="settings.size && settings.size !== shopDefault.key ? 'على الجهاز ده بس' : 'افتراضي المحل'">
          <USelect v-model="sizeKey" :items="REPAIR_LABEL_SIZES.map(s => ({ label: s.label, value: s.key }))" class="w-full" />
          <template v-if="settings.size && settings.size !== shopDefault.key" #help>
            <UButton size="xs" variant="link" class="px-0" :label="`رجّعه لافتراضي المحل (${shopDefault.label})`" @click="settings.size = null" />
          </template>
        </UFormField>
        <div class="grid grid-cols-2 gap-2">
          <UCheckbox v-model="settings.show.technician" label="اسم الفني" />
          <UCheckbox v-model="settings.show.price" label="السعر المتفق والعربون" />
          <UCheckbox v-model="settings.show.faults" label="العطل" />
          <UCheckbox v-model="settings.show.phone" label="موبايل العميل" />
          <UCheckbox v-model="settings.show.barcode" label="باركود التذكرة" />
          <UCheckbox v-if="ticket.unlock_code" v-model="settings.show.code" label="كود فتح الجهاز" />
        </div>
        <USwitch v-model="settings.auto" label="اطبعه لوحده بعد إيصال كل جهاز جديد" description="على الجهاز ده بس (الطابعة بتاعته)." />
      </div>
    </template>
    <template #footer>
      <div class="flex gap-2">
        <UButton icon="i-lucide-printer" label="اطبع" @click="printNow" />
        <UButton color="neutral" variant="ghost" label="إغلاق" @click="open = false" />
      </div>
    </template>
  </UModal>

  <PrintSheet v-if="printing" :page-size="`${size.w}mm ${size.h}mm`">
    <div class="overflow-hidden bg-white" :style="{ width: `${size.w}mm`, height: `${size.h}mm` }">
      <RepairsDeviceLabel :ticket="ticket" :size="size" :show="settings.show" />
    </div>
  </PrintSheet>
</template>

<script setup lang="ts">
import type { RepairTicket } from '~/types/api'

/** «ستيكر الجهاز»: the button, its options and the print. `print()` is exposed for printing right after intake. */
defineOptions({ inheritAttrs: false })
withDefaults(defineProps<{ ticket: RepairTicket, label?: string }>(), { label: 'ستيكر' })

const { settings, size, shopDefault } = useRepairLabel()
// Picking the shop's default again drops this device's own choice.
const sizeKey = computed({
  get: () => size.value.key,
  set: (key: string) => { settings.value.size = key === shopDefault.value.key ? null : key },
})
const { printing, print } = usePrint()
const open = ref(false)

async function printNow() {
  open.value = false
  await nextTick()
  await print()
}

defineExpose({ print: printNow, auto: computed(() => settings.value.auto) })
</script>

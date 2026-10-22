<template>
  <UModal v-model:open="open" title="حالة الجهاز وقت الاستلام" :ui="{ content: 'sm:max-w-2xl' }">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="IMEI">
            <UInput v-model="form.imei" dir="ltr" inputmode="numeric" placeholder="*#06#" class="w-full" />
          </UFormField>
          <UFormField label="اللون">
            <UInput v-model="form.color" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="مع الجهاز">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="o in options.accessories" :key="o.value" size="sm" :color="form.accessories.includes(o.value) ? 'primary' : 'neutral'" :variant="form.accessories.includes(o.value) ? 'soft' : 'outline'" :label="o.label" @click="toggle(form.accessories, o.value)" />
          </div>
        </UFormField>
        <UFormField label="شكله من برّه">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="o in options.condition" :key="o.value" size="sm" :color="form.condition.includes(o.value) ? 'warning' : 'neutral'" :variant="form.condition.includes(o.value) ? 'soft' : 'outline'" :label="o.label" @click="toggle(form.condition, o.value)" />
          </div>
        </UFormField>
        <div>
          <p class="mb-2 text-sm font-medium">
            فحص سريع
          </p>
          <div class="grid gap-2 sm:grid-cols-2">
            <div v-for="c in options.checks" :key="c.value" class="flex items-center justify-between gap-2 rounded-(--ui-radius) border border-(--ui-border) px-3 py-1.5">
              <span class="text-sm">{{ c.label }}</span>
              <UFieldGroup size="xs">
                <UButton v-for="v in checkValues" :key="v.value" :color="form.checks[c.value] === v.value ? v.color : 'neutral'" :variant="form.checks[c.value] === v.value ? 'soft' : 'outline'" :label="v.label" @click="form.checks[c.value] = v.value" />
              </UFieldGroup>
            </div>
          </div>
        </div>
        <UFormField label="قفل الشاشة">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="t in unlockTypes" :key="t.value" size="sm" :color="form.unlock_type === t.value ? 'primary' : 'neutral'" :variant="form.unlock_type === t.value ? 'soft' : 'outline'" :label="t.label" @click="setUnlock(t.value)" />
          </div>
        </UFormField>
        <RepairsPatternInput v-if="form.unlock_type === 'pattern'" v-model="form.unlock_code" large />
        <UInput v-else-if="form.unlock_type !== 'none'" v-model="form.unlock_code" dir="ltr" :placeholder="form.unlock_type === 'pin' ? 'الرقم السري' : 'الباسورد'" class="w-full sm:w-64" />
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" block label="احفظ" :loading="saving" />
      </form>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { RepairOptions, RepairTicket } from '~/types/api'

/** Fills in or corrects how the device came in, after the intake (the quick one doesn't ask). */
const props = defineProps<{ ticket: RepairTicket, options: RepairOptions }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [ticket: RepairTicket] }>()

const api = useApi()
const unlockTypes = [
  { value: 'none', label: 'مفيش' },
  { value: 'pin', label: 'رقم سري' },
  { value: 'pattern', label: 'نمط' },
  { value: 'password', label: 'باسورد' },
] as const
const checkValues = [
  { value: 'yes', label: 'آه', color: 'success' },
  { value: 'no', label: 'لأ', color: 'error' },
  { value: 'unknown', label: 'مش عارف', color: 'neutral' },
] as const

const form = reactive({
  imei: '',
  color: '',
  accessories: [] as string[],
  condition: [] as string[],
  checks: {} as Record<string, 'yes' | 'no' | 'unknown'>,
  unlock_type: 'none' as RepairTicket['unlock_type'],
  unlock_code: '',
})
const saving = ref(false)
const error = ref<string | null>(null)

watch(open, (isOpen) => {
  if (!isOpen) return
  const t = props.ticket
  Object.assign(form, {
    imei: t.imei ?? '',
    color: t.color ?? '',
    accessories: [...t.accessories],
    condition: [...t.condition],
    checks: { ...t.checks },
    unlock_type: t.unlock_type,
    unlock_code: t.unlock_code ?? '',
  })
  error.value = null
})

function toggle(list: string[], value: string) {
  const i = list.indexOf(value)
  if (i === -1) list.push(value)
  else list.splice(i, 1)
}
function setUnlock(type: RepairTicket['unlock_type']) {
  if (form.unlock_type !== type) form.unlock_code = ''
  form.unlock_type = type
}

async function save() {
  saving.value = true
  error.value = null
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${props.ticket.id}`, {
      method: 'PATCH',
      body: {
        imei: form.imei.trim() || null,
        color: form.color.trim() || null,
        accessories: form.accessories,
        condition: form.condition,
        checks: form.checks,
        unlock_type: form.unlock_type,
        unlock_code: form.unlock_type === 'none' ? null : form.unlock_code || null,
      },
    })
    open.value = false
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

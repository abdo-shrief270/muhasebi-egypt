<template>
  <UModal v-model:open="open" title="استعلام جديد">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <template v-if="!customer">
          <UFormField label="رقم التليفون" required>
            <UInput v-model="phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" autofocus />
          </UFormField>
          <div v-if="matches.length" class="space-y-1">
            <p class="text-xs text-(--ui-text-muted)">
              عملاء بالرقم ده:
            </p>
            <button v-for="c in matches" :key="c.id" type="button" class="flex w-full justify-between rounded-(--ui-radius) border border-(--ui-border) p-2 text-sm hover:bg-(--ui-bg-elevated)" @click="picked = c">
              <span class="font-bold">{{ c.name }}</span><span class="num" dir="ltr">{{ localPhone(c.phone) }}</span>
            </button>
          </div>
          <UFormField v-if="!picked" label="الاسم" required>
            <UInput v-model="name" class="w-full" />
          </UFormField>
          <p v-if="picked" class="flex items-center justify-between rounded-(--ui-radius) bg-(--app-primary-soft) p-2 text-sm">
            <span>العميل: <span class="font-bold">{{ picked.name }}</span></span>
            <UButton size="xs" color="neutral" variant="ghost" label="غيّر" @click="picked = null" />
          </p>
          <p v-else-if="phoneOk" class="text-sm text-(--ui-text-muted)">
            عميل جديد: هيتسجّل في العملاء.
          </p>
          <CustomersConsentCheckbox v-if="!picked" v-model="consent" />
        </template>
        <p v-else class="text-sm">
          العميل: <span class="font-bold">{{ customer.name }}</span>
        </p>

        <UFormField label="بيسأل عن">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="k in kinds" :key="k.value" size="sm" :color="kind === k.value ? 'primary' : 'neutral'" :variant="kind === k.value ? 'solid' : 'outline'" :label="k.label" @click="kind = k.value" />
          </div>
        </UFormField>
        <UFormField label="الاستفسار" required>
          <UTextarea v-model="text" :rows="3" autoresize maxlength="1000" placeholder="جراب iPhone 16 Pro شفاف — عايز 2" class="w-full" />
        </UFormField>
        <UFormField label="فكّرني أكلّمه">
          <div class="flex flex-wrap gap-2">
            <UButton v-for="f in followUps" :key="f.label" size="sm" :color="followLabel === f.label ? 'primary' : 'neutral'" :variant="followLabel === f.label ? 'soft' : 'outline'" :label="f.label" @click="followLabel = followLabel === f.label ? '' : f.label" />
          </div>
        </UFormField>
        <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        <UButton type="submit" block size="lg" label="احفظ" :loading="saving" :disabled="!ready" />
      </form>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { Customer, Inquiry } from '~/types/api'

/** Records what a customer asked about: picks them by phone or makes them, like the POS. */
const props = defineProps<{ customer?: { id: string, name: string } | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [inquiry: Inquiry] }>()

const api = useApi()
const kinds = [
  { value: 'item', label: 'صنف مش موجود' },
  { value: 'repair', label: 'سعر تصليح' },
  { value: 'phone', label: 'موبايل' },
  { value: 'other', label: 'حاجة تانية' },
] as const
const at = (days: number, hour: number) => () => {
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setHours(hour, 0, 0, 0)
  return d
}
const followUps = [
  { label: 'بعد ساعة', when: () => new Date(Date.now() + 3600_000) },
  { label: 'بكرة', when: at(1, 12) },
  { label: 'بعد يومين', when: at(2, 12) },
  { label: 'بعد أسبوع', when: at(7, 12) },
]

const phone = ref('')
const name = ref('')
const consent = ref(true)
const kind = ref<Inquiry['kind']>('item')
const text = ref('')
const followLabel = ref('')
const picked = ref<Customer | null>(null)
const matches = ref<Customer[]>([])
const saving = ref(false)
const error = ref<string | null>(null)

const phoneOk = computed(() => /^01[0125]\d{8}$/.test(phone.value))
const ready = computed(() => text.value.trim() !== '' && (props.customer || picked.value || (phoneOk.value && name.value.trim().length >= 2)))

watch(open, (isOpen) => {
  if (isOpen) {
    phone.value = ''
    name.value = ''
    text.value = ''
    kind.value = 'item'
    followLabel.value = ''
    picked.value = null
    matches.value = []
    error.value = null
  }
})

let lookup: ReturnType<typeof setTimeout> | undefined
watch(phone, (value) => {
  clearTimeout(lookup)
  const digits = value.replace(/\D/g, '')
  if (digits.length < 4) {
    matches.value = []
    return
  }
  lookup = setTimeout(async () => {
    matches.value = (await api<{ data: Customer[] }>('/customers', { query: { q: digits, active: 1, per_page: 4 } }).catch(() => ({ data: [] }))).data
  }, 250)
})

async function save() {
  saving.value = true
  error.value = null
  try {
    const who = props.customer?.id ?? picked.value?.id
    const when = followUps.find(f => f.label === followLabel.value)?.when()
    const res = await api<{ data: Inquiry }>('/inquiries', {
      method: 'POST',
      body: {
        ...(who ? { customer_id: who } : { customer_name: name.value.trim(), customer_phone: phone.value, consent: consent.value }),
        kind: kind.value,
        text: text.value.trim(),
        follow_up_at: when ? when.toISOString() : null,
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

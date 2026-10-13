<template>
  <UModal v-model:open="open" :title="opened ? 'اتفتح المحل' : 'افتح محل جديد'">
    <template #body>
      <!-- Done: what to send the owner -->
      <div v-if="opened" class="space-y-4">
        <p class="text-sm text-(--ui-text-muted)">
          ابعت البيانات دي لصاحب المحل. الباسورد مش هيظهر تاني؛ يقدر يغيّره من «الأمان» بعد ما يدخل.
        </p>
        <dl class="space-y-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-4 text-sm">
          <div class="flex justify-between gap-3">
            <dt class="text-(--ui-text-muted)">
              رابط الدخول
            </dt><dd dir="ltr" class="font-mono">
              {{ opened.login_url }}
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-(--ui-text-muted)">
              الموبايل
            </dt><dd dir="ltr" class="font-mono font-bold">
              {{ localPhone(opened.phone) }}
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-(--ui-text-muted)">
              الباسورد
            </dt><dd dir="ltr" class="font-mono text-lg font-extrabold tracking-wider">
              {{ opened.password }}
            </dd>
          </div>
        </dl>
        <div class="flex flex-wrap gap-2">
          <UButton :to="whatsappHref" target="_blank" color="success" icon="i-lucide-message-circle" label="ابعتهم واتساب" />
          <UButton color="neutral" variant="outline" icon="i-lucide-copy" :label="copied ? 'اتنسخ' : 'انسخ الرسالة'" @click="copy" />
        </div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="submit">
        <UFormField label="اسم المحل" :error="errors.shop_name" required>
          <UInput v-model="form.shop_name" class="w-full" />
        </UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="اسم صاحب المحل" :error="errors.owner_name" required>
            <UInput v-model="form.owner_name" class="w-full" />
          </UFormField>
          <UFormField label="موبايله" :error="errors.phone" required>
            <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
          </UFormField>
        </div>
        <UFormField label="نوع المحل" :error="errors.shop_types" required>
          <div class="flex flex-wrap gap-1.5">
            <UButton
              v-for="t in TYPES"
              :key="t.value"
              size="sm"
              :color="form.shop_types.includes(t.value) ? 'primary' : 'neutral'"
              :variant="form.shop_types.includes(t.value) ? 'soft' : 'outline'"
              :label="t.label"
              @click="toggle(t.value)"
            />
          </div>
        </UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField label="جه منين؟" :error="errors.source" required>
            <USelect v-model="form.source" :items="SOURCES" class="w-full" />
          </UFormField>
          <UFormField label="الحملة" hint="اختياري" :error="errors.campaign">
            <UInput v-model="form.campaign" dir="ltr" placeholder="founders" class="w-full" />
          </UFormField>
        </div>
        <p class="text-xs text-(--ui-text-muted)">
          المحل بيبدأ بتجربة 14 يوم بالأقسام اللي تناسب نوعه، وتسجيله بيوصل لميتا كـ CompleteRegistration (برقم الموبايل مشفّر) لو الـ Conversions API متظبط.
        </p>
      </form>
    </template>
    <template #footer>
      <div class="flex gap-2">
        <UButton v-if="!opened" icon="i-lucide-check" label="افتح المحل" :loading="saving" @click="submit" />
        <UButton color="neutral" variant="ghost" :label="opened ? 'تمام' : 'إلغاء'" @click="open = false" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
/** «افتح محل جديد»: the team opens a shop by hand (sign-up closed) and gets the owner's sign-in details once. */
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ opened: [] }>()

const TYPES = [
  { value: 'accessories', label: 'إكسسوارات' },
  { value: 'repair', label: 'صيانة' },
  { value: 'phones', label: 'بيع موبايلات' },
  { value: 'wholesale', label: 'جملة' },
  { value: 'importer', label: 'مستورد' },
]
const SOURCES = [
  { value: 'facebook', label: 'إعلان فيسبوك' },
  { value: 'instagram', label: 'إعلان إنستجرام' },
  { value: 'whatsapp', label: 'واتساب' },
  { value: 'referral', label: 'ترشيح من حد' },
  { value: 'walk_in', label: 'زيارة / معرفة شخصية' },
  { value: 'other', label: 'حاجة تانية' },
]

const api = useAdminApi()
const toast = useToast()
const blank = () => ({ shop_name: '', owner_name: '', phone: '', shop_types: ['accessories'] as string[], source: 'facebook', campaign: '' })
const form = reactive(blank())
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const opened = ref<{ tenant_id: string, phone: string, password: string, login_url: string } | null>(null)
const copied = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(form, blank())
    errors.value = {}
    opened.value = null
  }
})

function toggle(type: string) {
  form.shop_types = form.shop_types.includes(type) ? form.shop_types.filter(t => t !== type) : [...form.shop_types, type]
}

const message = computed(() => opened.value
  ? `أهلاً ${form.owner_name}، حساب «${form.shop_name}» على محاسبي جاهز 🎉\nادخل من هنا: ${opened.value.login_url}\nالموبايل: ${localPhone(opened.value.phone)}\nالباسورد: ${opened.value.password}\nغيّر الباسورد من «الأمان» بعد ما تدخل.`
  : '')
const whatsappHref = computed(() => opened.value ? `https://wa.me/${opened.value.phone.replace(/\D/g, '')}?text=${encodeURIComponent(message.value)}` : undefined)

async function copy() {
  try {
    await navigator.clipboard.writeText(message.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  }
  catch {
    // No clipboard: the details are on screen.
  }
}

async function submit() {
  saving.value = true
  errors.value = {}
  try {
    opened.value = (await api<{ data: NonNullable<typeof opened.value> }>('/shops', { method: 'POST', body: { ...form, campaign: form.campaign.trim() || null } })).data
    emit('opened')
  }
  catch (e) {
    errors.value = apiValidationErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>

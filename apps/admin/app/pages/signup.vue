<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-extrabold">
        التسجيل
      </h1>
      <p class="text-sm text-(--ui-text-muted)">
        المحلات تسجّل لوحدها من صفحة التسجيل، ولا التسجيل يبقى مع الفريق على واتساب.
      </p>
    </div>

    <UCard v-if="form" class="max-w-2xl">
      <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="space-y-1">
            <p class="font-bold">
              التسجيل مفتوح
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ form.open ? 'أي محل يقدر يسجّل ويبدأ التجربة لوحده.' : 'صفحة التسجيل مش بتعرض الفورم: بتعرض رسالتك وزرار واتساب، وأي محاولة تسجيل بتترفض.' }}
            </p>
          </div>
          <USwitch v-model="form.open" :label="form.open ? 'مفتوح' : 'مقفول'" />
        </div>

        <UFormField label="رقم الواتساب" description="اللي صفحة التسجيل بتبعت الناس ليه وهو مقفول." :required="!form.open">
          <UInput v-model="form.whatsapp" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
        </UFormField>

        <UFormField label="الرسالة" :hint="`${form.message.length}/300`" description="اللي بيظهر مكان الفورم وهو مقفول.">
          <UTextarea v-model="form.message" :rows="3" autoresize maxlength="300" class="w-full" />
        </UFormField>

        <div class="flex gap-2">
          <UButton icon="i-lucide-check" label="احفظ" :loading="saving" @click="save" />
          <UButton v-if="whatsappHref" :to="whatsappHref" target="_blank" color="neutral" variant="outline" icon="i-lucide-message-circle" label="جرّب الرقم" />
        </div>
      </div>
    </UCard>
    <USkeleton v-else class="h-64 max-w-2xl" />
  </div>
</template>

<script setup lang="ts">
interface Signup { open: boolean, whatsapp: string | null, message: string }

const api = useAdminApi()
const toast = useToast()
const form = ref<{ open: boolean, whatsapp: string, message: string } | null>(null)
const saving = ref(false)

function fill(s: Signup) {
  form.value = { open: s.open, whatsapp: s.whatsapp ?? '', message: s.message }
}
onMounted(async () => fill((await api<{ data: Signup }>('/signup')).data))

const whatsappHref = computed(() => {
  const digits = form.value?.whatsapp.replace(/\D/g, '') ?? ''
  if (!digits) return null
  return `https://wa.me/${digits.startsWith('0') ? `20${digits.slice(1)}` : digits}`
})

async function save() {
  if (!form.value) return
  saving.value = true
  try {
    fill((await api<{ data: Signup }>('/signup', { method: 'PUT', body: { ...form.value, whatsapp: form.value.whatsapp || null } })).data)
    toast.add({ color: 'success', title: form.value.open ? 'التسجيل مفتوح' : 'التسجيل مقفول: الناس هتكلمك واتساب' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}
</script>

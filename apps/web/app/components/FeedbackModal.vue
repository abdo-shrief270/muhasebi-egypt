<template>
  <UModal v-model:open="open" title="ابعت ملاحظة" description="مشكلة قابلتك، فكرة، أو سؤال؟ فريق محاسبي بيقرا كل رسالة." :ui="{ content: 'sm:max-w-lg' }">
    <template #body>
      <form id="feedback-form" class="space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="النوع">
          <button
            v-for="t in types"
            :key="t.value"
            type="button"
            role="radio"
            :aria-checked="type === t.value"
            class="flex flex-col items-center gap-1 rounded-(--ui-radius) border p-3 text-sm font-bold transition"
            :class="type === t.value ? 'border-primary bg-(--app-primary-soft) text-(--app-primary-strong)' : 'border-(--ui-border) hover:border-(--ui-border-accented)'"
            @click="type = t.value"
          >
            <UIcon :name="t.icon" class="size-5" />
            {{ t.label }}
          </button>
        </div>

        <UFormField :label="placeholderLabel" required>
          <UTextarea v-model="message" :rows="5" autoresize :maxlength="2000" class="w-full" :placeholder="placeholder" autofocus />
        </UFormField>

        <UFormField label="صورة للشاشة" hint="اختياري · لحد 3 ميجا">
          <div class="flex flex-wrap items-center gap-2">
            <UButton color="neutral" variant="outline" icon="i-lucide-image-plus" :label="file ? 'غيّر الصورة' : 'اختار صورة'" @click="picker?.click()" />
            <span v-if="file" class="flex min-w-0 items-center gap-1 text-sm text-(--ui-text-muted)">
              <span class="max-w-48 truncate" dir="ltr">{{ file.name }}</span>
              <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-x" square aria-label="شيل الصورة" @click="file = null" />
            </span>
            <input ref="picker" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pick">
          </div>
        </UFormField>

        <p class="flex items-start gap-1.5 text-xs text-(--ui-text-muted)">
          <UIcon name="i-lucide-info" class="mt-0.5 size-3.5 shrink-0" />
          هيتبعت معاها الصفحة اللي انت فيها ونوع المتصفح ومقاس الشاشة، عشان نوصل للمشكلة أسرع.
        </p>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="ghost" label="إلغاء" @click="open = false" />
        <UButton type="submit" form="feedback-form" icon="i-lucide-send" label="ابعت" :loading="sending" :disabled="message.trim().length < 3" />
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
const { open } = useFeedback()
const api = useApi()
const toast = useToast()
const route = useRoute()

type FeedbackType = 'problem' | 'suggestion' | 'question'
const types: { value: FeedbackType, label: string, icon: string }[] = [
  { value: 'problem', label: 'مشكلة', icon: 'i-lucide-bug' },
  { value: 'suggestion', label: 'اقتراح', icon: 'i-lucide-lightbulb' },
  { value: 'question', label: 'سؤال', icon: 'i-lucide-circle-help' },
]
const type = ref<FeedbackType>('problem')
const message = ref('')
const file = ref<File | null>(null)
const picker = ref<HTMLInputElement | null>(null)
const sending = ref(false)

const placeholderLabel = computed(() => ({ problem: 'إيه اللي حصل؟', suggestion: 'إيه اللي نفسك نضيفه؟', question: 'سؤالك' })[type.value])
const placeholder = computed(() => ({
  problem: 'مثلاً: دوست «ادفع» في الكاشير والشاشة وقفت…',
  suggestion: 'مثلاً: عايز أطبع الباركود على ليبل أصغر…',
  question: 'مثلاً: إزاي أضيف فرع تاني؟',
})[type.value])

const MAX_BYTES = 3 * 1024 * 1024
function pick(e: Event) {
  const input = e.target as HTMLInputElement
  const chosen = input.files?.[0] ?? null
  input.value = ''
  if (chosen && chosen.size > MAX_BYTES) {
    toast.add({ color: 'warning', title: 'الصورة أكبر من 3 ميجا' })
    return
  }
  file.value = chosen
}

watch(open, (isOpen) => {
  if (!isOpen) {
    message.value = ''
    file.value = null
    type.value = 'problem'
  }
})

async function submit() {
  if (message.value.trim().length < 3) {
    return
  }
  const body = new FormData()
  body.append('type', type.value)
  body.append('message', message.value.trim())
  body.append('page', route.path)
  body.append('app_version', appVersion())
  body.append('screen', `${window.screen.width}x${window.screen.height}`)
  if (file.value) {
    body.append('screenshot', file.value)
  }
  sending.value = true
  try {
    await api('/feedback', { method: 'POST', body })
    open.value = false
    toast.add({ color: 'success', icon: 'i-lucide-heart-handshake', title: 'شكراً! ملاحظتك وصلت', description: 'هنقراها ونرد عليك لو محتاجين نعرف أكتر.' })
  }
  catch (e) {
    const tooMany = apiErrorStatus(e) === 429 && apiErrorCode(e) === null
    toast.add({ color: 'error', title: tooMany ? 'بعت ملاحظات كتير ورا بعض. استنى شوية وجرّب تاني.' : apiErrorMessage(e) })
  }
  finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="space-y-2">
    <UInput
      v-model="draft"
      icon="i-lucide-scan-line"
      :placeholder="placeholder"
      dir="ltr"
      :size="size"
      class="w-full"
      :color="error ? 'error' : undefined"
      :aria-label="label"
      @keydown.enter.prevent="commit"
      @blur="commit"
      @paste="onPaste"
    />
    <div v-if="model.length" class="flex flex-wrap gap-1.5">
      <UBadge v-for="(s, i) in model" :key="s" color="neutral" variant="subtle" class="num gap-1" dir="ltr">
        {{ s }}
        <button type="button" class="opacity-60 hover:opacity-100" :aria-label="`شيل ${s}`" @click="removeAt(i)">
          <UIcon name="i-lucide-x" class="size-3" />
        </button>
      </UBadge>
    </div>
    <p v-if="error" class="text-xs text-(--ui-error)">
      {{ error }}
    </p>
    <p v-else-if="required !== undefined" class="text-xs" :class="model.length === required ? 'text-(--ui-text-muted)' : 'text-(--ui-warning)'">
      <span class="num">{{ model.length }}</span> من <span class="num">{{ required }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
/** IMEIs / serials: scan one after another (the scanner presses Enter) or paste a list. */
withDefaults(defineProps<{ required?: number, label?: string, placeholder?: string, size?: 'sm' | 'md' }>(), {
  required: undefined,
  label: 'IMEI / سيريال',
  placeholder: 'امسح أو اكتب الـ IMEI واضغط Enter…',
  size: 'md',
})
const model = defineModel<string[]>({ required: true })
const emit = defineEmits<{ change: [serials: string[]] }>()
const draft = ref('')
const error = ref<string | null>(null)

const normalize = (s: string) => s.replace(/[\s\-/]+/g, '').toUpperCase()

function addMany(values: string[]) {
  error.value = null
  for (const raw of values) {
    const s = normalize(raw)
    if (!s) {
      continue
    }
    if (!/^[A-Z0-9]{4,40}$/.test(s)) {
      error.value = `«${raw.trim()}» مش سيريال صحيح`
      continue
    }
    if (model.value.includes(s)) {
      error.value = `${s} مكتوب قبل كده`
      continue
    }
    model.value.push(s)
  }
  emit('change', model.value)
}

function removeAt(i: number) {
  model.value.splice(i, 1)
  emit('change', model.value)
}

function commit() {
  if (draft.value.trim()) {
    addMany([draft.value])
    draft.value = ''
  }
}

function onPaste(e: ClipboardEvent) {
  const text = e.clipboardData?.getData('text') ?? ''
  if (/[\n,;\t]/.test(text)) {
    e.preventDefault()
    addMany(text.split(/[\n,;\t]+/))
  }
}

</script>

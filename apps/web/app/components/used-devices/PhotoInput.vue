<template>
  <div class="space-y-1.5">
    <p class="text-sm font-medium">
      {{ label }}<span v-if="required" class="text-(--ui-error)"> *</span>
    </p>
    <div class="flex flex-wrap gap-2">
      <div v-for="(f, i) in files" :key="i" class="relative size-24 overflow-hidden rounded-(--ui-radius) border border-(--ui-border)">
        <img :src="previews[i]" :alt="`${label} ${i + 1}`" class="size-full object-cover">
        <button
          type="button"
          class="absolute end-1 top-1 grid size-6 place-items-center rounded-full bg-(--ui-bg)/90 text-(--ui-text) shadow"
          :aria-label="`شيل ${label}`"
          @click="remove(i)"
        >
          <UIcon name="i-lucide-x" class="size-3.5" />
        </button>
      </div>
      <label
        v-if="files.length < max"
        class="grid size-24 cursor-pointer place-items-center rounded-(--ui-radius) border-2 border-dashed text-center text-xs transition-colors hover:bg-(--ui-bg-elevated)"
        :class="invalid ? 'border-(--ui-error) text-(--ui-error)' : 'border-(--ui-border-accented) text-(--ui-text-muted)'"
      >
        <span class="flex flex-col items-center gap-1 px-1">
          <UIcon :name="busy ? 'i-lucide-loader-circle' : 'i-lucide-camera'" class="size-6" :class="busy ? 'animate-spin' : ''" />
          {{ files.length ? 'صورة كمان' : 'صوّر' }}
        </span>
        <input type="file" accept="image/*" capture="environment" :multiple="max > 1" class="sr-only" @change="onPick">
      </label>
    </div>
    <p v-if="hint" class="text-xs text-(--ui-text-muted)">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
/**
 * Takes photos with the phone's camera (or picks files), shrinks them before upload and shows
 * thumbnails. v-model is the list of files (max 1 for a single photo).
 */
const props = withDefaults(defineProps<{ label: string, max?: number, required?: boolean, invalid?: boolean, hint?: string }>(), { max: 1 })
const files = defineModel<File[]>({ default: () => [] })

const busy = ref(false)
const previews = ref<string[]>([])

watch(files, (list) => {
  previews.value.forEach(u => URL.revokeObjectURL(u))
  previews.value = list.map(f => URL.createObjectURL(f))
}, { immediate: true })
onBeforeUnmount(() => previews.value.forEach(u => URL.revokeObjectURL(u)))

async function onPick(event: Event) {
  const input = event.target as HTMLInputElement
  const picked = Array.from(input.files ?? []).slice(0, props.max - files.value.length)
  input.value = ''
  if (!picked.length) {
    return
  }
  busy.value = true
  try {
    const shrunk = await Promise.all(picked.map(f => shrinkPhoto(f)))
    files.value = [...files.value, ...shrunk].slice(0, props.max)
  }
  finally {
    busy.value = false
  }
}

function remove(i: number) {
  files.value = files.value.filter((_, j) => j !== i)
}
</script>

<template>
  <UFormField :label="label" :hint="hint">
    <div class="flex items-center gap-3">
      <div class="flex shrink-0 items-center justify-center overflow-hidden rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-muted)" :class="kind === 'logo' ? 'size-20' : 'h-20 w-40'">
        <img v-if="preview" :src="preview" alt="" class="size-full object-cover">
        <UIcon v-else name="i-lucide-image" class="size-6 text-(--ui-text-dimmed)" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="cursor-pointer">
          <span class="inline-flex items-center gap-1 text-sm font-semibold text-(--ui-primary)">
            <UIcon :name="busy ? 'i-lucide-loader-circle' : 'i-lucide-upload'" :class="busy ? 'animate-spin' : ''" class="size-4" />
            {{ urls ? 'غيّر' : 'ارفع صورة' }}
          </span>
          <input type="file" accept="image/*" class="sr-only" :disabled="busy" @change="upload">
        </label>
        <UButton v-if="urls" size="xs" color="neutral" variant="link" label="شيلها" class="justify-start px-0" :disabled="busy" @click="remove" />
      </div>
    </div>
  </UFormField>
</template>

<script setup lang="ts">
import type { OnlineStoreSettings } from '~/types/api'

/** The store's logo or cover: shrunk here, kept as WebP by the API. */
const props = defineProps<{ kind: 'logo' | 'cover', label: string, hint?: string, urls: Record<string, string> | null }>()
const emit = defineEmits<{ changed: [settings: OnlineStoreSettings] }>()
const api = useApi()
const toast = useToast()
const busy = ref(false)
const preview = computed(() => (props.urls ? Object.values(props.urls).at(-1) : null))

async function upload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) {
    return
  }
  busy.value = true
  try {
    const body = new FormData()
    body.append('image', await shrinkPhoto(file, props.kind === 'logo' ? 1024 : 2000))
    const res = await api<{ data: OnlineStoreSettings }>(`/online-store/media/${props.kind}`, { method: 'POST', body })
    emit('changed', res.data)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = false
  }
}

async function remove() {
  busy.value = true
  try {
    const res = await api<{ data: OnlineStoreSettings }>(`/online-store/media/${props.kind}`, { method: 'DELETE' })
    emit('changed', res.data)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = false
  }
}
</script>

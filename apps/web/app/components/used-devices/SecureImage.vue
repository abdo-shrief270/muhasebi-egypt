<template>
  <button
    type="button"
    class="relative block overflow-hidden rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-elevated)"
    :aria-label="alt"
    @click="url && (zoom = true)"
  >
    <img v-if="url" :src="url" :alt="alt" class="size-full object-cover">
    <span v-else class="grid size-full place-items-center p-2 text-center text-xs text-(--ui-text-muted)">
      <UIcon v-if="!failed" name="i-lucide-loader-circle" class="size-5 animate-spin" />
      <template v-else>{{ failed }}</template>
    </span>
    <UModal v-model:open="zoom" :title="alt" :ui="{ content: 'max-w-3xl' }">
      <template #body>
        <img v-if="url" :src="url" :alt="alt" class="mx-auto max-h-[75vh] w-auto rounded-(--ui-radius)">
      </template>
    </UModal>
  </button>
</template>

<script setup lang="ts">
/**
 * A photo served by the API (it needs the sign-in token, so a plain <img src> can't load it):
 * fetched as a blob into an object URL, released when the component goes away.
 */
const props = defineProps<{ src: string, alt: string }>()

const api = useApi()
const url = ref<string | null>(null)
const failed = ref<string | null>(null)
const zoom = ref(false)

async function load() {
  failed.value = null
  try {
    const blob = await api<Blob>(props.src, { responseType: 'blob' })
    if (url.value) {
      URL.revokeObjectURL(url.value)
    }
    url.value = URL.createObjectURL(blob)
  }
  catch (e) {
    failed.value = apiErrorStatus(e) === 403 ? 'مش مسموحلك تشوفها' : 'الصورة ماتحملتش'
  }
}

watch(() => props.src, load, { immediate: true })
onBeforeUnmount(() => {
  if (url.value) {
    URL.revokeObjectURL(url.value)
  }
})
</script>

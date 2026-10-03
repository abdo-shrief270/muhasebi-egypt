<template>
  <div class="space-y-2">
    <p class="text-sm font-medium">
      الصور <span class="text-(--ui-text-muted)">(لحد {{ MAX }}، الأولى هي الغلاف)</span>
    </p>
    <div class="flex flex-wrap gap-2">
      <div v-for="(img, i) in images" :key="img.id" class="group relative size-24 overflow-hidden rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-muted)">
        <img :src="img.urls['320']" alt="" class="size-full object-contain" loading="lazy">
        <UBadge v-if="i === 0" size="sm" class="absolute top-1 start-1" label="الغلاف" />
        <div class="absolute inset-x-0 bottom-0 flex justify-between bg-(--ui-bg)/90 p-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
          <UButton v-if="i > 0" size="xs" color="neutral" variant="ghost" icon="i-lucide-star" square aria-label="خليها الغلاف" @click="makeCover(img)" />
          <span v-else />
          <UButton size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" square aria-label="شيل الصورة" @click="remove(img)" />
        </div>
      </div>
      <label v-if="images.length < MAX" class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-(--ui-radius) border border-dashed border-(--ui-border-accented) text-xs text-(--ui-text-muted) hover:border-(--ui-primary) hover:text-(--ui-primary)">
        <UIcon :name="uploading ? 'i-lucide-loader-circle' : 'i-lucide-image-plus'" class="size-6" :class="uploading ? 'animate-spin' : ''" />
        {{ uploading ? 'بيترفع…' : 'ضيف صورة' }}
        <input type="file" accept="image/*" multiple class="sr-only" :disabled="uploading" @change="pick">
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProductImage } from '~/types/api'

/** A product's photos: shrunk here before upload; the API keeps three WebP widths. */
const props = defineProps<{ productId: string }>()
const images = defineModel<ProductImage[]>({ required: true })
const MAX = 8
const api = useApi()
const toast = useToast()
const uploading = ref(false)

async function pick(event: Event) {
  const input = event.target as HTMLInputElement
  const files = [...(input.files ?? [])].slice(0, MAX - images.value.length)
  input.value = ''
  uploading.value = true
  try {
    for (const file of files) {
      const body = new FormData()
      body.append('image', await shrinkPhoto(file, 1600))
      const res = await api<{ data: ProductImage }>(`/products/${props.productId}/images`, { method: 'POST', body })
      images.value = [...images.value, res.data]
    }
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    uploading.value = false
  }
}

async function remove(img: ProductImage) {
  try {
    await api(`/products/${props.productId}/images/${img.id}`, { method: 'DELETE' })
    images.value = images.value.filter(i => i.id !== img.id)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function makeCover(img: ProductImage) {
  const ids = [img.id, ...images.value.filter(i => i.id !== img.id).map(i => i.id)]
  try {
    const res = await api<{ data: ProductImage[] }>(`/products/${props.productId}/images/order`, { method: 'PUT', body: { ids } })
    images.value = res.data
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>

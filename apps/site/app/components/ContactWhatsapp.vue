<template>
  <UButton
    v-if="href"
    :to="href"
    target="_blank"
    rel="noopener"
    :size="size"
    color="success"
    :variant="variant"
    icon="i-lucide-message-circle"
    :label="label"
    @click="track('whatsapp_click', { place })"
  />
</template>

<script setup lang="ts">
/** «كلّمنا واتساب»: a chat with the team with a first line already written; hidden when no number is set. */
const props = withDefaults(defineProps<{
  text?: string
  label?: string
  place?: string
  size?: 'md' | 'lg' | 'xl'
  variant?: 'solid' | 'outline' | 'soft'
}>(), { text: 'السلام عليكم، عايز أعرف أكتر عن برنامج محاسبي', label: 'كلّمنا واتساب', place: 'page', size: 'xl', variant: 'outline' })

const { track } = useTracking()
const number = String(useRuntimeConfig().public.whatsapp ?? '').replace(/\D/g, '')
const href = computed(() => number ? `https://wa.me/${number}?text=${encodeURIComponent(props.text)}` : null)
</script>

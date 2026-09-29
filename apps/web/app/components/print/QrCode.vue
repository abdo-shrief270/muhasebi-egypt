<template>
  <!-- eslint-disable-next-line vue/no-v-html -->
  <div class="qr [&>svg]:block [&>svg]:h-full [&>svg]:w-full" :style="{ width: `${size}px`, height: `${size}px` }" v-html="markup" />
</template>

<script setup lang="ts">
import QRCode from 'qrcode'

/** QR code as inline SVG (sharp on thermal printers). */
const props = withDefaults(defineProps<{ value: string, size?: number }>(), { size: 96 })

const markup = ref('')

watchEffect(async () => {
  markup.value = props.value
    ? await QRCode.toString(props.value, { type: 'svg', margin: 0, errorCorrectionLevel: 'M', color: { dark: '#000000', light: '#ffffff' } })
    : ''
})
</script>

<template>
  <svg ref="svg" class="block max-w-full" />
</template>

<script setup lang="ts">
import JsBarcode from 'jsbarcode'

/** CODE128 barcode (EAN-13 when the value is a valid EAN-13) as crisp SVG for screen and print. */
const props = withDefaults(defineProps<{ value: string, height?: number, width?: number, showText?: boolean, fontSize?: number }>(), {
  height: 40,
  width: 1.6,
  showText: true,
  fontSize: 12,
})

const svg = ref<SVGSVGElement | null>(null)

function isEan13(value: string): boolean {
  if (!/^\d{13}$/.test(value)) {
    return false
  }
  const digits = value.split('').map(Number)
  const sum = digits.slice(0, 12).reduce((acc, d, i) => acc + d * (i % 2 === 0 ? 1 : 3), 0)
  return (10 - (sum % 10)) % 10 === digits[12]
}

function draw() {
  if (!svg.value || !props.value) {
    return
  }
  try {
    JsBarcode(svg.value, props.value, {
      format: isEan13(props.value) ? 'EAN13' : 'CODE128',
      height: props.height,
      width: props.width,
      displayValue: props.showText,
      fontSize: props.fontSize,
      margin: 0,
      background: 'transparent',
    })
  }
  catch {
    // Characters CODE128 can't encode: leave it empty rather than break the page.
  }
}

onMounted(draw)
watch(() => [props.value, props.height, props.width, props.showText], draw)
</script>

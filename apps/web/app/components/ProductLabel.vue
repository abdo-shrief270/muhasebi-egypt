<template>
  <div class="flex h-full w-full flex-col items-center justify-center gap-[0.5mm] overflow-hidden px-[1.5mm] py-[1mm] text-center text-black" :style="{ fontSize: `${fontMm}mm` }">
    <p v-if="show.shop" class="w-full truncate font-bold leading-tight" :style="{ fontSize: `${fontMm * 0.85}mm` }">
      {{ shop }}
    </p>
    <p v-if="show.name" class="line-clamp-2 w-full font-bold leading-tight">
      {{ variant.display_name }}
    </p>
    <div class="flex min-h-0 w-full flex-1 items-center justify-center">
      <template v-if="variant.barcode">
        <PrintQrCode v-if="codeType === 'qr'" :value="variant.barcode" :size="qrPx" />
        <PrintBarcode v-else :value="variant.barcode" :height="barHeight" :width="barWidth" :font-size="Math.round(fontMm * 3.2)" />
      </template>
    </div>
    <p v-if="show.price" class="font-extrabold leading-none num" :style="{ fontSize: `${fontMm * 1.35}mm` }">
      {{ formatMoney(variant.price_retail) }}
    </p>
  </div>
</template>

<script setup lang="ts">
export interface LabelSize { w: number, h: number, sheet?: boolean }
export interface LabelVariant { id: string, display_name: string, barcode: string | null, price_retail: number }

/** One barcode label, laid out in millimetres so it prints true to size. */
const props = defineProps<{
  variant: LabelVariant
  size: LabelSize
  codeType: 'barcode' | 'qr'
  show: { shop: boolean, name: boolean, price: boolean }
  shop: string
}>()

const fontMm = computed(() => Math.max(2.2, Math.min(3.4, props.size.h / 9)))
// The code gets whatever height the text lines leave (1mm ≈ 3.78px).
const textLines = computed(() => (props.show.shop ? 1 : 0) + (props.show.name ? 2 : 0) + (props.show.price ? 1.4 : 0))
const codeMm = computed(() => Math.max(6, props.size.h - 2 - textLines.value * fontMm.value * 1.15))
const barHeight = computed(() => Math.round(codeMm.value * 3.78 * 0.62))
const barWidth = computed(() => (props.size.w >= 50 ? 1.5 : 1.1))
const qrPx = computed(() => Math.round(Math.min(codeMm.value, props.size.w - 4) * 3.78))
</script>

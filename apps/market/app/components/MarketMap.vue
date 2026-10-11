<template>
  <div class="relative overflow-hidden bg-sand-dark" :class="rounded">
    <div ref="el" class="absolute inset-0" />
    <p v-if="!pins.length && !me" class="absolute inset-0 grid place-items-center p-6 text-center text-sm text-muted">
      مفيش محلات على الخريطة هنا لسه.
    </p>
    <span v-if="hint" class="absolute bottom-3 start-3 z-[400] rounded-lg bg-white px-3 py-2 text-xs shadow">{{ hint }}</span>
  </div>
</template>

<script setup lang="ts">
import type { LatLng, MapPin } from '~/types'

/** OpenStreetMap with a label per shop (or price) and the visitor's spot; loaded in the browser only. */
const props = withDefaults(defineProps<{ pins: MapPin[], me?: LatLng | null, hint?: string, rounded?: string }>(), { me: null, hint: '', rounded: 'rounded-2xl' })

const el = ref<HTMLElement | null>(null)
let map: import('leaflet').Map | null = null
let layer: import('leaflet').LayerGroup | null = null
let L: typeof import('leaflet') | null = null

function label(text: string, cls: string): HTMLElement {
  const span = document.createElement('span')
  span.className = cls
  span.textContent = text
  return span
}

function draw() {
  if (!map || !layer || !L) {
    return
  }
  layer.clearLayers()
  const points: [number, number][] = []
  for (const p of props.pins) {
    const marker = L.marker([p.lat, p.lng], { icon: L.divIcon({ html: label(p.label, 'market-pin'), className: '', iconSize: undefined }) })
    if (p.to) {
      marker.on('click', () => navigateTo(p.to!))
    }
    marker.addTo(layer)
    points.push([p.lat, p.lng])
  }
  if (props.me) {
    L.marker([props.me.lat, props.me.lng], { icon: L.divIcon({ html: label('', 'market-me block'), className: '', iconSize: [16, 16] }), keyboard: false }).addTo(layer)
    points.push([props.me.lat, props.me.lng])
  }
  if (points.length === 1) {
    map.setView(points[0]!, 14)
  }
  else if (points.length > 1) {
    map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 15 })
  }
}

onMounted(async () => {
  L = await import('leaflet')
  if (!el.value) {
    return
  }
  // Cairo until there's something to show.
  map = L.map(el.value, { zoomControl: true, attributionControl: true, scrollWheelZoom: false }).setView([30.0444, 31.2357], 11)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)
  layer = L.layerGroup().addTo(map)
  draw()
})
watch(() => [props.pins, props.me], draw, { deep: true })
onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>

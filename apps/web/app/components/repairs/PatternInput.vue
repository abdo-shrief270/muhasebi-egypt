<template>
  <div class="inline-flex flex-col items-center gap-2">
    <div
      ref="pad"
      class="relative grid touch-none grid-cols-3 rounded-(--ui-radius) bg-(--ui-bg-elevated) select-none"
      :class="large ? 'size-64 gap-0 p-4' : 'size-36 gap-0 p-2'"
      dir="ltr"
      role="group"
      aria-label="ارسم القفل"
      @pointerdown="start"
      @pointermove="move"
      @pointerup="stop"
      @pointercancel="stop"
      @lostpointercapture="stop"
    >
      <svg class="pointer-events-none absolute inset-0 size-full" aria-hidden="true">
        <polyline :points="line" fill="none" class="stroke-(--ui-primary)" :stroke-width="large ? 8 : 4" stroke-linecap="round" stroke-linejoin="round" opacity=".6" />
      </svg>
      <div v-for="n in 9" :key="n" class="grid place-items-center">
        <button
          :ref="el => (dots[n - 1] = el as HTMLElement | null)"
          type="button"
          class="relative grid place-items-center rounded-full border-2 font-bold transition num"
          :class="[
            large ? 'size-12 text-base' : 'size-8 text-xs',
            order(n) ? 'border-primary bg-primary text-(--ui-bg)' : 'border-(--ui-border-accented) bg-(--ui-bg) text-(--ui-text-muted)',
          ]"
          :disabled="readonly"
          :aria-label="`نقطة ${n}`"
          tabindex="-1"
        >
          {{ order(n) || '' }}
        </button>
      </div>
    </div>
    <UButton v-if="!readonly && model" size="xs" color="neutral" variant="ghost" icon="i-lucide-rotate-ccw" label="امسح وارسم تاني" @click="model = ''" />
  </div>
</template>

<script setup lang="ts">
/**
 * An Android unlock pattern drawn the way it's drawn on the phone: press a dot and drag across the
 * others (finger or mouse); tapping dots one by one works too. Dots 1–9 (left to right, top to
 * bottom) are stored in the order they're joined, "1-5-9-6". Read-only shows the drawn line.
 */
const props = defineProps<{ readonly?: boolean, large?: boolean }>()
const model = defineModel<string>({ default: '' })

const pad = ref<HTMLElement | null>(null)
const dots = ref<(HTMLElement | null)[]>([])
const drawing = ref(false)
const tick = ref(0)

const sequence = computed(() => (model.value ? model.value.split('-').map(Number) : []))
const order = (n: number) => sequence.value.indexOf(n) + 1

function centre(n: number): [number, number] | null {
  const dot = dots.value[n - 1]
  const box = pad.value?.getBoundingClientRect()
  if (!dot || !box) return null
  const r = dot.getBoundingClientRect()
  return [r.left + r.width / 2 - box.left, r.top + r.height / 2 - box.top]
}

const line = computed(() => {
  void tick.value
  return sequence.value.map(centre).filter((p): p is [number, number] => p !== null).map(p => p.join(',')).join(' ')
})

function hit(e: PointerEvent): number | null {
  for (let n = 1; n <= 9; n++) {
    const r = dots.value[n - 1]?.getBoundingClientRect()
    if (!r) continue
    const slack = r.width * 0.35
    if (e.clientX >= r.left - slack && e.clientX <= r.right + slack && e.clientY >= r.top - slack && e.clientY <= r.bottom + slack) {
      return n
    }
  }
  return null
}

function add(n: number | null) {
  if (n !== null && !sequence.value.includes(n)) {
    model.value = [...sequence.value, n].join('-')
  }
}

function start(e: PointerEvent) {
  if (props.readonly) return
  const n = hit(e)
  if (n === null) return
  // Touching a dot already in the pattern (not its end) starts it over.
  if (sequence.value.includes(n) && sequence.value[sequence.value.length - 1] !== n) {
    model.value = ''
  }
  drawing.value = true
  pad.value?.setPointerCapture(e.pointerId)
  add(n)
}

function move(e: PointerEvent) {
  if (drawing.value) add(hit(e))
}

function stop() {
  drawing.value = false
}

// The line is drawn from the dots' places: redraw once they're laid out.
const redraw = () => tick.value++
onMounted(() => {
  nextTick(redraw)
  window.addEventListener('resize', redraw)
})
onBeforeUnmount(() => window.removeEventListener('resize', redraw))
</script>

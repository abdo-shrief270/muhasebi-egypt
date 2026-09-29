<template>
  <div class="inline-flex flex-col items-center gap-2">
    <div class="grid grid-cols-3 gap-3 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3" dir="ltr">
      <button
        v-for="n in 9"
        :key="n"
        type="button"
        class="grid size-10 place-items-center rounded-full border-2 text-sm font-bold transition num"
        :class="order(n) ? 'border-primary bg-primary text-(--ui-bg)' : 'border-(--ui-border-accented) text-(--ui-text-muted)'"
        :disabled="readonly"
        :aria-label="`نقطة ${n}`"
        @click="press(n)"
      >
        {{ order(n) || '' }}
      </button>
    </div>
    <UButton v-if="!readonly && model" size="xs" color="neutral" variant="ghost" icon="i-lucide-rotate-ccw" label="امسح" @click="model = ''" />
  </div>
</template>

<script setup lang="ts">
/**
 * An Android unlock pattern: dots 1–9 (left to right, top to bottom) in the order they're
 * joined, stored as "1-5-9-6". Each dot shows its place in the order.
 */
defineProps<{ readonly?: boolean }>()
const model = defineModel<string>({ default: '' })

const sequence = computed(() => (model.value ? model.value.split('-').map(Number) : []))
const order = (n: number) => sequence.value.indexOf(n) + 1

function press(n: number) {
  if (!sequence.value.includes(n)) {
    model.value = [...sequence.value, n].join('-')
  }
}
</script>

<template>
  <div class="grid grid-cols-3 gap-2" dir="ltr">
    <template v-for="(k, i) in keys" :key="i">
      <span v-if="k === null" />
      <button
        v-else
        type="button"
        class="num h-16 rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) text-2xl font-bold transition select-none active:scale-95 active:bg-(--ui-bg-accented) disabled:opacity-40"
        :disabled="k === '.' && model.includes('.')"
        :aria-label="k === '.' ? 'علامة عشرية' : k"
        @click="press(k)"
      >
        {{ k }}
      </button>
    </template>
    <button
      type="button"
      class="grid h-16 place-items-center rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-elevated) transition select-none active:scale-95"
      aria-label="امسح رقم"
      @click="model = model.slice(0, -1)"
      @contextmenu.prevent="model = ''"
    >
      <UIcon name="i-lucide-delete" class="size-7" />
    </button>
  </div>
</template>

<script setup lang="ts">
/** A big on-screen number pad for touch screens (phone numbers, PINs, prices). Long-press ⌫ clears. */
const props = withDefaults(defineProps<{ maxLength?: number, decimal?: boolean }>(), { maxLength: 15, decimal: false })
const model = defineModel<string>({ default: '' })

const keys = computed(() => ['1', '2', '3', '4', '5', '6', '7', '8', '9', props.decimal ? '.' : null, '0'])

function press(k: string) {
  const next = model.value + k
  if (next.replace('.', '').length <= props.maxLength) {
    model.value = next
  }
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-full border border-line-strong bg-white px-3.5 py-2 text-sm text-ink"
      :aria-expanded="open"
      aria-haspopup="dialog"
      @click="open = !open"
    >
      <MarketIcon name="pin" :size="16" class="text-teal" />
      <span class="max-w-40 truncate">{{ place?.label ?? 'حدد مكانك' }}</span>
      <span class="font-semibold text-teal">{{ place ? 'غيّر' : '' }}</span>
    </button>
    <div v-if="open" role="dialog" aria-label="مكانك" class="card absolute start-0 top-full z-30 mt-2 w-72 space-y-3 p-4 shadow-xl">
      <button type="button" class="btn-teal w-full" :disabled="locating" @click="useMine">
        <MarketIcon name="locate" />
        {{ locating ? 'بنحدد مكانك…' : 'استخدم موقعي' }}
      </button>
      <p v-if="error" class="text-sm text-warn">
        {{ error }}
      </p>
      <label class="block text-sm">
        <span class="mb-1 block text-muted">أو اختار المحافظة</span>
        <select class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" :value="place?.governorate ?? ''" @change="pick(($event.target as HTMLSelectElement).value)">
          <option value="">—</option>
          <option v-for="g in options?.governorates ?? []" :key="g.key" :value="g.key">{{ g.label }}</option>
        </select>
      </label>
      <button v-if="place" type="button" class="text-sm text-muted underline" @click="clearPlace">
        امسح مكاني
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const { place, locate, chooseGovernorate, clear, locating, error } = usePlace()
const { data: options } = useOptions()
const open = ref(false)

async function useMine() {
  await locate()
  if (!error.value) {
    open.value = false
  }
}
function pick(key: string) {
  const g = options.value?.governorates.find(x => x.key === key)
  if (g) {
    chooseGovernorate(g.key, g.label)
    open.value = false
  }
}
function clearPlace() {
  clear()
  open.value = false
}
</script>

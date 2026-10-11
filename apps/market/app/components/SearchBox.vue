<template>
  <form role="search" class="flex w-full gap-2 rounded-2xl border border-line-strong bg-white p-2" :class="big ? '' : 'p-1.5'" @submit.prevent="go">
    <label :for="id" class="sr-only">دوّر</label>
    <input
      :id="id"
      v-model="q"
      type="search"
      enterkeyhint="search"
      :placeholder="placeholder"
      class="min-w-0 flex-1 bg-transparent px-3 text-ink outline-none"
      :class="big ? 'text-[17px]' : 'text-[15px]'"
    >
    <button type="submit" class="btn-teal shrink-0" :class="big ? 'h-12 px-6 text-base' : 'h-10 px-4'">
      {{ big ? 'دوّر حواليا' : 'دوّر' }}
    </button>
  </form>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ initial?: string, big?: boolean, placeholder?: string }>(), { initial: '', big: false, placeholder: 'شاحن سامسونج، اسكرينة A54، جراب ايفون…' })
const id = useId()
const q = ref(props.initial)
watch(() => props.initial, v => (q.value = v))
const route = useRoute()

function go() {
  const query = { ...(route.path === '/search' ? route.query : {}), q: q.value.trim() || undefined, page: undefined }
  navigateTo({ path: '/search', query })
}
</script>

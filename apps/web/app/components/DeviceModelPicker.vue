<template>
  <USelectMenu
    v-model="model"
    v-model:search-term="searchTerm"
    :items="items"
    :multiple="multiple"
    value-key="id"
    label-key="full_name"
    ignore-filter
    :loading="loading"
    :placeholder="placeholder"
    :search-input="{ placeholder: 'اكتب الموديل… (مثلاً iphone 13 أو a54)' }"
    icon="i-lucide-smartphone"
    class="w-full"
  >
    <template #empty>
      {{ searchTerm ? 'مفيش موديل بالاسم ده — ضيفه من «التصنيفات والماركات».' : 'اكتب عشان تدور' }}
    </template>
  </USelectMenu>
</template>

<script setup lang="ts">
import type { DeviceModel } from '~/types/api'

/**
 * Searches the shop's phone models on the server as you type. Selected models stay listed
 * (with their names) even when they are not in the current results.
 */
const props = withDefaults(defineProps<{
  multiple?: boolean
  /** Models already selected, so their names show before any search. */
  known?: DeviceModel[]
  placeholder?: string
}>(), { multiple: false, known: () => [], placeholder: 'الموديلات' })

const model = defineModel<number[] | number | undefined>()
/** The picked model itself (single mode), for screens that also want its name. */
const emit = defineEmits<{ pick: [model: DeviceModel | null] }>()

const api = useApi()
const searchTerm = ref('')
const loading = ref(false)
const results = ref<DeviceModel[]>([])
const remembered = reactive(new Map<number, DeviceModel>())

watch(() => props.known, list => list.forEach(m => remembered.set(m.id, m)), { immediate: true })

const selectedIds = computed(() => (Array.isArray(model.value) ? model.value : model.value ? [model.value] : []))

const items = computed(() => {
  const selected = selectedIds.value.map(id => remembered.get(id)).filter((m): m is DeviceModel => !!m)
  const rest = results.value.filter(m => !selectedIds.value.includes(m.id))
  return [...selected, ...rest]
})

let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

async function search(q: string) {
  const id = ++requestId
  loading.value = true
  try {
    const res = await api<{ data: DeviceModel[] }>('/catalog/device-models', { query: { q } })
    if (id === requestId) {
      results.value = res.data
      res.data.forEach(m => remembered.set(m.id, m))
    }
  }
  finally {
    if (id === requestId) {
      loading.value = false
    }
  }
}

watch(searchTerm, (q) => {
  clearTimeout(timer)
  timer = setTimeout(() => search(q), 250)
})

watch(model, (value) => {
  if (!props.multiple) {
    emit('pick', typeof value === 'number' ? remembered.get(value) ?? null : null)
  }
})

onMounted(() => search(''))
onBeforeUnmount(() => clearTimeout(timer))
</script>

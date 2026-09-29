<template>
  <div class="space-y-6">
    <PageHeader title="فواتير الشراء" description="كل فاتورة بتدخّل البضاعة المخزون وبتتسجل على حساب المورد.">
      <UButton v-if="canManage" to="/purchases/new" icon="i-lucide-plus" label="فاتورة شراء" />
    </PageHeader>
    <UInput v-model="q" icon="i-lucide-search" placeholder="رقم الفاتورة أو رقم فاتورة المورد…" class="w-full sm:max-w-sm" />
    <SuppliersPurchasesTable :q="debouncedQ" />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ permission: 'suppliers.view' })

const store = useSessionStore()
const canManage = computed(() => store.can('suppliers.manage'))

const q = ref('')
const debouncedQ = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(q, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    debouncedQ.value = value.trim()
  }, 300)
})
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div v-if="shift" class="max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
      <UButton to="/cash" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <PageHeader :title="`الوردية ${shift.reference}`" :description="`${shift.user_name} · ${shift.is_open ? 'مفتوحة' : 'مقفولة'}`" class="flex-1">
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع" @click="print" />
        <UButton v-if="shift.is_open && canClose" icon="i-lucide-lock" label="قفل الوردية" @click="closeOpen = true" />
      </PageHeader>
    </div>

    <div class="grid gap-6 md:grid-cols-[auto_1fr]">
      <div class="flex justify-center rounded-(--ui-radius) bg-(--ui-bg-muted) p-3">
        <CashShiftReport :shift="shift" :shop-name="shopName" class="rounded-sm shadow" />
      </div>
      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <table class="w-full text-sm">
          <tbody>
            <tr v-for="m in shift.movements ?? []" :key="m.id" class="border-t border-(--ui-border) first:border-t-0">
              <td class="p-3 text-(--ui-text-muted) num">
                {{ formatDate(m.created_at, true) }}
              </td>
              <td class="p-3">
                <NuxtLink v-if="m.ref_type === 'sale' && m.ref_id" :to="`/sales/${m.ref_id}`" class="font-bold hover:text-primary">
                  {{ m.type_label }}
                </NuxtLink>
                <span v-else class="font-bold">{{ m.category_label ? `${m.type_label} · ${m.category_label}` : m.type_label }}</span>
                <span class="text-(--ui-text-muted)"> · {{ m.method_label }}</span>
                <p v-if="m.note" class="text-xs text-(--ui-text-muted)">
                  {{ m.note }}
                </p>
              </td>
              <td class="p-3 text-end font-bold num" :class="m.amount < 0 ? 'text-error' : ''">
                {{ m.amount < 0 ? '−' : '+' }}{{ formatMoney(Math.abs(m.amount)) }}
              </td>
            </tr>
            <tr v-if="!(shift.movements ?? []).length">
              <td colspan="3" class="p-8 text-center text-(--ui-text-muted)">
                مفيش حركات في الوردية دي.
              </td>
            </tr>
          </tbody>
        </table>
      </UCard>
    </div>

    <CashCloseShiftModal v-model:open="closeOpen" :shift="shift" @closed="() => refresh()" />
    <PrintSheet v-if="printing" page-size="80mm auto">
      <CashShiftReport :shift="shift" :shop-name="shopName" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { CashShift } from '~/types/api'

definePageMeta({ permission: 'cash.shift' })

const api = useApi()
const route = useRoute()
const store = useSessionStore()
const shopName = computed(() => store.session?.tenant.name ?? '')
const id = computed(() => String(route.params.id))

const { data, refresh } = await useAsyncData(`cash-shift-${id.value}`, () => api<{ data: CashShift }>(`/cash/shifts/${id.value}`))
const shift = computed(() => data.value?.data)
const canClose = computed(() => store.can('cash.manage') || shift.value?.user_id === store.session?.user.id)

const closeOpen = ref(false)
const { printing, print } = usePrint()
</script>

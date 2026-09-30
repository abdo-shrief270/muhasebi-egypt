<template>
  <div class="space-y-6">
    <div class="flex justify-center">
      <div class="inline-flex rounded-full border border-(--ui-border) bg-(--ui-bg-muted) p-1" role="radiogroup" aria-label="مدة الاشتراك">
        <button
          v-for="c in cycles"
          :key="c.value"
          type="button"
          role="radio"
          :aria-checked="cycle === c.value"
          class="rounded-full px-5 py-1.5 text-sm font-bold transition"
          :class="cycle === c.value ? 'bg-primary text-white shadow' : 'text-(--ui-text-muted)'"
          @click="cycle = c.value"
        >
          {{ c.label }}
        </button>
      </div>
    </div>

    <div v-if="pending || !data" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <USkeleton v-for="i in 4" :key="i" class="h-96 rounded-2xl" />
    </div>
    <UAlert v-else-if="error" color="warning" variant="subtle" title="مقدرناش نجيب الأسعار دلوقتي" description="جرّب تحدّث الصفحة بعد شوية." />
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="plan in data.data.plans"
        :key="plan.key"
        class="relative flex flex-col rounded-2xl border bg-(--ui-bg) p-6"
        :class="plan.featured ? 'border-primary shadow-[var(--app-shadow)] ring-1 ring-primary' : 'border-(--ui-border)'"
      >
        <UBadge v-if="plan.featured" class="absolute -top-3 start-6" label="الأكتر طلباً" />
        <p class="text-lg font-extrabold">
          {{ plan.name }}
        </p>
        <p class="mt-1 min-h-10 text-sm text-(--ui-text-muted)">
          {{ plan.description }}
        </p>
        <p class="mt-5 flex items-baseline gap-1">
          <span class="num text-4xl font-extrabold">{{ pounds(cycle === 'yearly' ? plan.yearly : plan.monthly) }}</span>
          <span class="text-sm text-(--ui-text-muted)">ج / {{ cycle === 'yearly' ? 'سنة' : 'شهر' }}</span>
        </p>
        <p class="mt-1 h-5 text-xs text-primary">
          <template v-if="cycle === 'yearly'">
            يعني <span class="num">{{ pounds(Math.round(plan.yearly / 12)) }}</span> ج في الشهر
          </template>
        </p>
        <UButton :to="links.register" :variant="plan.featured ? 'solid' : 'outline'" class="mt-5 justify-center" label="ابدأ التجربة ببلاش" />
        <ul class="mt-6 space-y-2 text-sm">
          <li class="flex items-start gap-2">
            <UIcon name="i-lucide-check" class="mt-1 size-4 shrink-0 text-primary" />
            الكاشير والمخزون والمشتريات والعملاء والخزنة والتقارير
          </li>
          <li v-for="m in plan.modules" :key="m.key" class="flex items-start gap-2">
            <UIcon name="i-lucide-check" class="mt-1 size-4 shrink-0 text-primary" />
            <span>{{ m.name }}</span>
            <UBadge v-if="!m.available" size="sm" color="neutral" variant="subtle" label="قريباً" />
          </li>
        </ul>
      </div>
    </div>

    <p v-if="data" class="text-center text-sm text-(--ui-text-muted)">
      الأسعار شاملة ضريبة القيمة المضافة <span class="num">{{ data.data.vat_percent }}%</span> ·
      السنوي بسعر <span class="num">{{ data.data.yearly_months }}</span> شهور ·
      أول <span class="num">{{ data.data.trial_days }}</span> يوم ببلاش ومن غير كارت
    </p>
  </div>
</template>

<script setup lang="ts">
const links = useAppLinks()
const { data, pending, error } = usePlans()
const cycle = ref<'monthly' | 'yearly'>('monthly')
const cycles = computed(() => {
  const free = 12 - (data.value?.data.yearly_months ?? 12)
  return [
    { value: 'monthly' as const, label: 'شهري' },
    { value: 'yearly' as const, label: free > 0 ? `سنوي (${free === 2 ? 'شهرين' : `${free} شهور`} ببلاش)` : 'سنوي' },
  ]
})
</script>

<template>
  <section v-if="state?.visible" class="app-card overflow-hidden" aria-labelledby="onboarding-title">
    <div class="flex flex-wrap items-center gap-3 p-4">
      <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--app-primary-soft) text-(--app-primary-strong)">
        <UIcon name="i-lucide-rocket" class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 id="onboarding-title" class="font-bold">
          ابدأ من هنا
        </h2>
        <p class="text-sm text-(--ui-text-muted)">
          <span class="num">{{ state.done }}</span> من <span class="num">{{ state.total }}</span> خطوات
          <template v-if="state.collapsed && next">
            · الجاية: <NuxtLink :to="next.to" class="font-bold text-(--app-primary-strong) hover:underline">{{ next.title }}</NuxtLink>
          </template>
        </p>
      </div>
      <div class="flex items-center gap-1">
        <UButton
          color="neutral"
          variant="ghost"
          :icon="state.collapsed ? 'i-lucide-chevron-down' : 'i-lucide-chevron-up'"
          square
          :aria-label="state.collapsed ? 'افتح القايمة' : 'صغّر القايمة'"
          :aria-expanded="!state.collapsed"
          @click="save({ collapsed: !state.collapsed })"
        />
        <UTooltip text="اخفيها خالص">
          <UButton color="neutral" variant="ghost" icon="i-lucide-x" square aria-label="اخفي «ابدأ من هنا»" @click="dismiss" />
        </UTooltip>
      </div>
      <div class="h-2 w-full overflow-hidden rounded-full bg-(--ui-bg-elevated)" role="progressbar" :aria-valuenow="state.done" aria-valuemin="0" :aria-valuemax="state.total" aria-label="التقدم">
        <div class="h-2 rounded-full bg-(--app-chart) transition-all" :style="{ width: `${percent}%` }" />
      </div>
    </div>

    <ol v-if="!state.collapsed" class="grid border-t border-(--ui-border) sm:grid-cols-2">
      <li v-for="step in state.steps" :key="step.key" class="border-b border-(--ui-border) sm:odd:border-e">
        <component
          :is="step.done ? 'div' : NuxtLink"
          :to="step.done ? undefined : step.to"
          class="flex h-full items-start gap-3 p-4"
          :class="step.done ? '' : 'transition hover:bg-(--ui-bg-elevated)'"
        >
          <UIcon
            :name="step.done ? 'i-lucide-circle-check' : step.icon"
            class="mt-0.5 size-5 shrink-0"
            :class="step.done ? 'text-success' : 'text-(--ui-text-muted)'"
          />
          <span class="min-w-0 flex-1">
            <span class="block font-bold" :class="step.done ? 'text-(--ui-text-muted) line-through' : ''">{{ step.title }}</span>
            <span v-if="!step.done" class="block text-sm text-(--ui-text-muted)">{{ step.description }}</span>
          </span>
          <UIcon v-if="!step.done" name="i-lucide-chevron-left" class="mt-1 size-4 shrink-0 text-(--ui-text-dimmed)" />
          <span v-else class="sr-only">(اتعملت)</span>
        </component>
      </li>
    </ol>
  </section>
</template>

<script setup lang="ts">
import type { OnboardingState } from '~/types/api'
import { NuxtLink } from '#components'

/** «ابدأ من هنا» on the home page: the shop's first steps, ticked by the server from real data. */
const api = useApi()
const toast = useToast()

const { data: state } = await useAsyncData('onboarding', async () => {
  try {
    return (await api<{ data: OnboardingState }>('/onboarding')).data
  }
  catch {
    return null // offline or an old API: the home page works without it
  }
})

const next = computed(() => state.value?.steps.find(s => !s.done) ?? null)
const percent = computed(() => (state.value?.total ? Math.round(state.value.done / state.value.total * 100) : 0))

async function save(body: { collapsed?: boolean, dismissed?: boolean }) {
  const previous = state.value
  if (state.value && body.collapsed !== undefined) {
    state.value = { ...state.value, collapsed: body.collapsed }
  }
  try {
    state.value = (await api<{ data: OnboardingState }>('/onboarding', { method: 'PATCH', body })).data
  }
  catch (e) {
    state.value = previous
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function dismiss() {
  await save({ dismissed: true })
  toast.add({
    color: 'neutral',
    title: 'اتخفت «ابدأ من هنا»',
    actions: [{ label: 'رجّعها', onClick: () => { save({ dismissed: false }) } }],
  })
}

defineExpose({ state })
</script>

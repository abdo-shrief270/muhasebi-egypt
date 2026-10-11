<template>
  <section id="lifetime" class="mt-16 rounded-3xl border border-(--ui-warning)/40 bg-(--ui-warning)/5 p-6 sm:p-10">
    <div class="flex flex-wrap items-center gap-3">
      <UIcon name="i-lucide-crown" class="size-7 text-(--ui-warning)" />
      <h2 class="text-2xl font-extrabold sm:text-3xl">
        {{ offer.name }}: اشتري السيستم مرة واحدة
      </h2>
      <UBadge v-if="offer.launch" color="warning" variant="subtle" size="lg">
        خصم الإطلاق <span class="num mx-1">{{ offer.launch.percent }}%</span>
        <template v-if="offer.launch.seats_left !== null">
          لأول <span class="num mx-1">{{ offer.launch.seats_left }}</span> محل
        </template>
        <template v-if="offer.launch.ends_at">
          لحد <span class="num ms-1">{{ date(offer.launch.ends_at) }}</span>
        </template>
      </UBadge>
    </div>
    <p v-if="offer.tagline" class="mt-3 max-w-3xl text-lg text-(--ui-text-muted)">
      {{ offer.tagline }}
    </p>
    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
      <ul v-if="offer.perks.length" class="grid gap-2 sm:grid-cols-2">
        <li v-for="perk in offer.perks" :key="perk" class="flex items-start gap-2">
          <UIcon name="i-lucide-badge-check" class="mt-0.5 size-5 shrink-0 text-(--ui-warning)" /> {{ perk }}
        </li>
        <li v-if="installmentsText" class="flex items-start gap-2">
          <UIcon name="i-lucide-badge-check" class="mt-0.5 size-5 shrink-0 text-(--ui-warning)" /> تقدر تقسّطه على {{ installmentsText }}
        </li>
      </ul>
      <div class="text-center">
        <UButton :to="links.register" size="xl" color="warning" label="جرّب ببلاش واشتريه بعدها" />
        <p class="mt-2 text-xs text-(--ui-text-muted)">
          أسعار كل باقة مدى الحياة في الجدول فوق.
        </p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { Lifetime } from '~/composables/usePlans'

const props = defineProps<{ offer: Lifetime }>()
const links = useAppLinks()

const installmentsText = computed(() => props.offer.installments.filter(n => n > 1).map(n => `${n} أقساط`).join(' أو '))
const date = (iso: string) => new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

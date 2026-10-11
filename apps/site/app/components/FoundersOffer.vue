<template>
  <section id="founders" class="mt-16 rounded-3xl border border-(--ui-warning)/40 bg-(--ui-warning)/5 p-6 sm:p-10">
    <div class="flex flex-wrap items-center gap-3">
      <UIcon name="i-lucide-crown" class="size-7 text-(--ui-warning)" />
      <h2 class="text-2xl font-extrabold sm:text-3xl">
        {{ offer.name }}
      </h2>
      <UBadge v-if="offer.seats_left !== null" color="warning" variant="subtle" size="lg">
        فاضل <span class="num mx-1">{{ offer.seats_left }}</span> مكان
      </UBadge>
      <UBadge v-if="offer.ends_at" color="neutral" variant="subtle" size="lg">
        العرض لحد <span class="num ms-1">{{ date(offer.ends_at) }}</span>
      </UBadge>
    </div>
    <p v-if="offer.tagline" class="mt-3 max-w-3xl text-lg text-(--ui-text-muted)">
      {{ offer.tagline }}
    </p>

    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <div v-for="p in offer.plans" :key="p.key" class="flex flex-col rounded-2xl border border-(--ui-border) bg-(--ui-bg) p-5" :class="p.featured ? 'ring-2 ring-(--ui-warning)' : ''">
        <p class="text-lg font-extrabold">
          {{ p.name }}
        </p>
        <p class="mt-3">
          <span class="num text-3xl font-extrabold">{{ pounds(p.price) }}</span> ج
          <span class="text-sm text-(--ui-text-muted)">مرة واحدة</span>
        </p>
        <p class="mt-1 text-sm text-(--ui-text-muted)">
          شامل أول سنة، وبعدها <b class="num">{{ pounds(p.annual_fee) }}</b> ج في السنة بس
          <span v-if="p.monthly">(بدل <span class="num">{{ pounds(p.monthly * yearlyMonths) }}</span> ج)</span>
        </p>
        <p v-if="offer.installments.length > 1" class="mt-2 text-xs text-(--ui-text-muted)">
          أو على {{ installmentsText }}
        </p>
        <ul class="mt-4 flex-1 space-y-1 text-sm">
          <li v-for="m in p.modules" :key="m.key" class="flex items-center gap-1.5">
            <UIcon name="i-lucide-check" class="size-4 shrink-0 text-(--ui-success)" /> {{ m.name }}
          </li>
        </ul>
      </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
      <ul v-if="offer.perks.length" class="grid gap-2 sm:grid-cols-2">
        <li v-for="perk in offer.perks" :key="perk" class="flex items-start gap-2">
          <UIcon name="i-lucide-badge-check" class="mt-0.5 size-5 shrink-0 text-(--ui-warning)" /> {{ perk }}
        </li>
      </ul>
      <div class="text-center">
        <UButton :to="links.register" size="xl" color="warning" label="ابدأ التجربة واحجز مكانك" />
        <p class="mt-2 text-xs text-(--ui-text-muted)">
          جرّب ببلاش، وادفع باقة المؤسسين من صفحة الاشتراك جوه البرنامج.
        </p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { Founders } from '~/composables/usePlans'

const props = defineProps<{ offer: Founders, yearlyMonths: number }>()
const links = useAppLinks()

const installmentsText = computed(() => props.offer.installments.filter(n => n > 1).map(n => `${n} أقساط`).join(' أو '))
const date = (iso: string) => new Date(iso).toLocaleDateString('ar-EG-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

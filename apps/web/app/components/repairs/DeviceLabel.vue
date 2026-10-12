<template>
  <div class="flex h-full w-full flex-col gap-[0.4mm] overflow-hidden px-[1.5mm] py-[1mm] text-black" :style="{ fontSize: `${fontMm}mm`, lineHeight: 1.2 }">
    <div class="flex items-baseline justify-between gap-[1mm]">
      <span class="num font-extrabold" :style="{ fontSize: `${fontMm * 1.35}mm` }">{{ ticket.reference }}</span>
      <span class="shrink-0" :class="{ 'font-bold': ticket.expected_at }">{{ ticket.expected_at ? `تسليم ${short(ticket.expected_at)}` : short(ticket.received_at) }}</span>
    </div>
    <p class="line-clamp-2 font-bold">
      {{ ticket.device_name }}<template v-if="ticket.color"> · {{ ticket.color }}</template>
    </p>
    <p class="truncate">
      {{ ticket.customer_name }}<template v-if="show.phone && ticket.customer_phone"> · <bdi class="num">{{ localPhone(ticket.customer_phone) }}</bdi></template>
    </p>
    <p v-if="show.faults && faults" class="line-clamp-2">
      {{ faults }}
    </p>
    <p v-if="show.technician && ticket.technician_name" class="truncate">
      الفني <span class="font-bold">{{ ticket.technician_name }}</span>
    </p>
    <p v-if="money.length" class="truncate font-bold">
      <template v-for="(m, i) in money" :key="m.label">
        <template v-if="i"> · </template>{{ m.label }} <span class="num">{{ m.value }}</span>
      </template>
    </p>
    <p v-if="show.code && ticket.unlock_code" class="truncate">
      {{ lockLabel }} <bdi class="num font-bold">{{ ticket.unlock_code }}</bdi>
    </p>
    <div v-if="show.barcode" class="flex min-h-0 flex-1 items-end justify-center overflow-hidden">
      <PrintBarcode :value="ticket.reference" :height="barHeight" :width="size.w >= 58 ? 1.3 : 1" :show-text="false" />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RepairTicket } from '~/types/api'
import type { RepairLabelSettings, RepairLabelSize } from '~/composables/useRepairLabel'

/**
 * The sticker stuck on a device taken in: ticket number (and its barcode, which the repairs
 * search finds), device, customer, faults, technician, the agreed price and deposit, when it's
 * due. Laid out in millimetres so it prints true to size on a label roll.
 */
const props = defineProps<{ ticket: RepairTicket, size: RepairLabelSize, show: RepairLabelSettings['show'] }>()

const fontMm = computed(() => Math.max(2.1, Math.min(3.2, props.size.h / 13)))
const faults = computed(() => (props.ticket.diagnosed_faults ?? props.ticket.reported_faults).map(f => f.name).join('، ') || props.ticket.reported_note || '')

// The agreed price: the bill once it's priced, else the estimate at intake; and what was paid.
const money = computed(() => {
  const t = props.ticket
  const list: { label: string, value: string }[] = []
  if (!props.show.price) return list
  const agreed = t.total > 0 ? t.total : t.estimate
  if (agreed) list.push({ label: 'المتفق', value: formatMoney(agreed) })
  if (t.paid > 0) list.push({ label: 'عربون', value: formatMoney(t.paid) })
  return list
})

// «14 أكتوبر 3:00 م»: short enough for a sticker's top row.
const short = (iso: string) => new Date(iso).toLocaleString('ar-EG-u-nu-latn', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })

const lockLabel = computed(() => ({ pin: 'PIN', pattern: 'باترن', password: 'باسورد', none: '' })[props.ticket.unlock_type])

// The barcode takes what the lines leave (1mm ≈ 3.78px), at least 5mm.
const barHeight = computed(() => {
  const lines = 3 + (props.show.faults && faults.value ? 1.5 : 0) + (props.show.technician && props.ticket.technician_name ? 1 : 0)
    + (money.value.length ? 1 : 0) + (props.show.code && props.ticket.unlock_code ? 1 : 0)
  return Math.round(Math.max(5, props.size.h - 2.5 - lines * fontMm.value * 1.25) * 3.78)
})
</script>

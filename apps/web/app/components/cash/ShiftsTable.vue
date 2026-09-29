<template>
  <UCard :ui="{ body: 'p-0 sm:p-0' }">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              الوردية
            </th>
            <th class="p-3 text-start font-bold">
              من
            </th>
            <th class="hidden p-3 text-start font-bold sm:table-cell">
              لـ
            </th>
            <th class="p-3 text-start font-bold">
              الكاش
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in shifts" :key="s.id" class="cursor-pointer border-t border-(--ui-border) hover:bg-(--ui-bg-elevated)" @click="navigateTo(`/cash/${s.id}`)">
            <td class="p-3">
              <p class="font-bold num">
                {{ s.reference }}
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ s.user_name }}
              </p>
            </td>
            <td class="p-3 text-(--ui-text-muted)">
              {{ formatDate(s.opened_at, true) }}
            </td>
            <td class="hidden p-3 text-(--ui-text-muted) sm:table-cell">
              {{ s.closed_at ? formatDate(s.closed_at, true) : '—' }}
            </td>
            <td class="p-3">
              <UBadge v-if="s.is_open" color="primary" variant="subtle">
                مفتوحة
              </UBadge>
              <UBadge v-else-if="s.cash_difference === 0" color="success" variant="subtle" icon="i-lucide-check">
                مظبوط
              </UBadge>
              <UBadge v-else :color="(s.cash_difference ?? 0) < 0 ? 'error' : 'warning'" variant="subtle" class="num">
                {{ (s.cash_difference ?? 0) < 0 ? 'عجز' : 'زيادة' }} {{ formatMoney(Math.abs(s.cash_difference ?? 0)) }}
              </UBadge>
            </td>
          </tr>
          <tr v-if="!shifts.length">
            <td colspan="4" class="p-8 text-center text-(--ui-text-muted)">
              لسه مفيش ورديات.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </UCard>
</template>

<script setup lang="ts">
import type { CashShift } from '~/types/api'

defineProps<{ shifts: CashShift[] }>()
</script>

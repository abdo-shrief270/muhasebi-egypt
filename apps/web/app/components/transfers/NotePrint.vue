<template>
  <div class="bg-white text-black">
    <div class="mb-4 flex items-end justify-between border-b-2 border-black pb-2">
      <div>
        <p class="text-xl font-extrabold">
          إذن تحويل <span class="num">{{ transfer.reference }}</span>
        </p>
        <p class="text-xs">
          من: <span class="font-bold">{{ transfer.from.name }}</span> · إلى: <span class="font-bold">{{ transfer.to.name }}</span>
        </p>
      </div>
      <div class="text-end text-xs">
        <p class="font-bold">
          {{ shopName }}
        </p>
        <p class="num">
          {{ formatDate(transfer.shipped_at ?? transfer.created_at, true) }}
        </p>
      </div>
    </div>

    <table class="w-full border-collapse text-[11px]">
      <thead>
        <tr>
          <th class="border border-black/40 bg-black/5 p-1 text-start">
            #
          </th>
          <th class="border border-black/40 bg-black/5 p-1 text-start">
            الصنف
          </th>
          <th class="border border-black/40 bg-black/5 p-1 text-end">
            {{ transfer.status === 'requested' ? 'المطلوب' : 'اتبعت' }}
          </th>
          <th class="border border-black/40 bg-black/5 p-1 text-end">
            وصل
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, i) in transfer.items ?? []" :key="item.id" class="break-inside-avoid">
          <td class="border border-black/40 p-1 num">
            {{ i + 1 }}
          </td>
          <td class="border border-black/40 p-1">
            <p class="font-bold">
              {{ item.name }}
            </p>
            <p v-if="item.serials.length" class="num text-[10px]" dir="ltr">
              {{ item.serials.join(' · ') }}
            </p>
          </td>
          <td class="border border-black/40 p-1 text-end num">
            {{ transfer.status === 'requested' ? item.qty_requested : item.qty_shipped }}
          </td>
          <td class="border border-black/40 p-1 text-end num">
            {{ transfer.status === 'received' ? item.qty_received : '' }}
          </td>
        </tr>
      </tbody>
    </table>

    <p v-if="transfer.notes" class="mt-3 text-xs">
      ملاحظات: {{ transfer.notes }}
    </p>
    <div class="mt-10 grid grid-cols-3 gap-6 text-center text-xs">
      <div>
        <p class="border-t border-black pt-1">
          المُرسِل{{ transfer.shipped_by_name ? ` (${transfer.shipped_by_name})` : '' }}
        </p>
      </div>
      <div>
        <p class="border-t border-black pt-1">
          المندوب / الناقل
        </p>
      </div>
      <div>
        <p class="border-t border-black pt-1">
          المُستلِم{{ transfer.received_by_name ? ` (${transfer.received_by_name})` : '' }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { StockTransfer } from '~/types/api'

/** «إذن تحويل»: A4 sheet that travels with the goods, signed by sender, carrier and receiver. */
defineProps<{ transfer: StockTransfer, shopName: string }>()
</script>

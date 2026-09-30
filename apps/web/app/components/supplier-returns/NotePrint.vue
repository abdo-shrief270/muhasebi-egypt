<template>
  <div class="note-print bg-white text-black">
    <div class="mb-4 flex items-end justify-between border-b-2 border-black pb-2">
      <div>
        <p class="text-xl font-extrabold">
          إذن مرتجع <span class="num">{{ note.reference }}</span>
        </p>
        <p class="text-xs">
          إلى: <span class="font-bold">{{ note.source.name }}</span> ({{ note.source.type === 'shop' ? 'محل شريك' : 'مورد' }})
          <span v-if="note.source.phone" class="num"> · {{ localPhone(note.source.phone) }}</span>
        </p>
      </div>
      <div class="text-end text-xs">
        <p class="font-bold">
          {{ shopName }}
        </p>
        <p class="num">
          {{ formatDate(note.created_at) }}
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
            الكمية
          </th>
          <th class="border border-black/40 bg-black/5 p-1 text-start">
            السبب
          </th>
          <th v-if="withCost" class="border border-black/40 bg-black/5 p-1 text-end">
            التكلفة
          </th>
          <th v-if="withCost" class="border border-black/40 bg-black/5 p-1 text-end">
            الإجمالي
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(line, i) in lines" :key="line.key" class="break-inside-avoid">
          <td class="border border-black/40 p-1 num">
            {{ i + 1 }}
          </td>
          <td class="border border-black/40 p-1">
            <p class="font-bold">
              {{ line.name }}
            </p>
            <p v-if="line.serials.length" class="num text-[10px]" dir="ltr">
              {{ line.serials.join(' · ') }}
            </p>
            <p v-if="line.docs.length" class="text-[10px]">
              من <span class="num">{{ line.docs.join('، ') }}</span>
            </p>
          </td>
          <td class="border border-black/40 p-1 text-end num">
            {{ line.qty }}
          </td>
          <td class="border border-black/40 p-1">
            {{ line.reasons.join('، ') }}
          </td>
          <td v-if="withCost" class="border border-black/40 p-1 text-end num">
            {{ formatMoney(line.unitCost) }}
          </td>
          <td v-if="withCost" class="border border-black/40 p-1 text-end num">
            {{ formatMoney(line.total) }}
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="font-extrabold">
          <td class="border border-black/40 bg-black/5 p-1" colspan="2">
            الإجمالي
          </td>
          <td class="border border-black/40 bg-black/5 p-1 text-end num">
            {{ note.units }}
          </td>
          <td class="border border-black/40 bg-black/5 p-1" />
          <td v-if="withCost" class="border border-black/40 bg-black/5 p-1" />
          <td v-if="withCost" class="border border-black/40 bg-black/5 p-1 text-end num">
            {{ formatMoney(note.total_cost) }}
          </td>
        </tr>
      </tfoot>
    </table>

    <p v-if="note.notes" class="mt-2 text-xs">
      ملاحظات: {{ note.notes }}
    </p>

    <div class="mt-12 grid grid-cols-2 gap-8 text-xs">
      <div>
        <p class="font-bold">
          المسلّم ({{ shopName }})
        </p>
        <p class="mt-8 border-t border-black pt-1">
          الاسم / التوقيع
        </p>
      </div>
      <div>
        <p class="font-bold">
          المستلم ({{ note.source.name }})
        </p>
        <p class="mt-8 border-t border-black pt-1">
          الاسم / التوقيع / التاريخ
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ReturnNote } from '~/utils/supplierReturns'

/** A return note on A4: items (grouped per variant, cost and reason), serials, totals and signatures. */
const props = defineProps<{ note: ReturnNote, shopName: string, withCost: boolean }>()

const lines = computed(() => {
  const map = new Map<string, { key: string, name: string, qty: number, unitCost: number, total: number, serials: string[], reasons: string[], docs: string[] }>()
  for (const i of props.note.items ?? []) {
    const key = `${i.variant_id}:${i.unit_cost ?? ''}`
    const line = map.get(key) ?? { key, name: i.name, qty: 0, unitCost: i.unit_cost ?? 0, total: 0, serials: [], reasons: [], docs: [] }
    line.qty += i.qty
    line.total += i.value ?? 0
    if (i.serial) {
      line.serials.push(i.serial)
    }
    if (!line.reasons.includes(i.reason_label)) {
      line.reasons.push(i.reason_label)
    }
    if (i.source_doc && !line.docs.includes(i.source_doc)) {
      line.docs.push(i.source_doc)
    }
    map.set(key, line)
  }
  return [...map.values()]
})
</script>

<style scoped>
.note-print {
  font-family: 'Cairo', system-ui, sans-serif;
  line-height: 1.4;
}
</style>

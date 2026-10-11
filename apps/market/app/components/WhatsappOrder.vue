<template>
  <form class="space-y-3" @submit.prevent="send">
    <p class="text-sm text-muted">
      المحل ده بياخد الطلبات على واتساب: اكتب بياناتك ونبعتله الطلب رسالة جاهزة.
    </p>
    <input v-model="name" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" autocomplete="name" placeholder="اسمك" maxlength="80" required>
    <div class="flex gap-2">
      <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'border-line-strong bg-white' : 'border-ink bg-ink text-white'" @click="delivery = false">
        هستلم من المحل
      </button>
      <button type="button" class="chip flex-1 justify-center" :class="delivery ? 'border-ink bg-ink text-white' : 'border-line-strong bg-white'" @click="delivery = true">
        توصيل
      </button>
    </div>
    <input v-if="delivery" v-model="address" class="w-full rounded-xl border border-line-strong bg-white px-3 py-2" autocomplete="street-address" placeholder="العنوان (المنطقة، الشارع، علامة مميزة)" maxlength="200" required>
    <button type="submit" class="btn-teal w-full py-3">
      <MarketIcon name="whatsapp" /> ابعت الطلب لـ {{ group.shop.name }} على واتساب
    </button>
  </form>
</template>

<script setup lang="ts">
import type { CartLine } from '~/composables/useCart'

const props = defineProps<{ group: { shop: { name: string }, lines: CartLine[], total: number }, phone: string }>()
const name = ref('')
const address = ref('')
const delivery = ref(false)

function send() {
  const text = [
    `السلام عليكم، طلب من سوق محاسبي لـ ${props.group.shop.name}:`,
    ...props.group.lines.map(l => `• ${l.qty} × ${l.title} — ${formatPrice(l.price * l.qty)}`),
    `الإجمالي: ${formatPrice(props.group.total)}`,
    '',
    `الاسم: ${name.value.trim()}`,
    delivery.value ? `توصيل إلى: ${address.value.trim()}` : 'هستلم من المحل',
  ].join('\n')
  window.open(whatsappLink(props.phone, text), '_blank', 'noopener')
}
</script>

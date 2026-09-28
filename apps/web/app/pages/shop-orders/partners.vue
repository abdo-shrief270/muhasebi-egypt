<template>
  <div class="space-y-6 max-w-5xl">
    <div class="flex items-center gap-3">
      <UButton to="/shop-orders" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square />
      <div>
        <h1 class="text-2xl font-extrabold">
          الشركاء
        </h1>
        <p class="text-(--ui-text-muted)">
          المحلات اللي تقدر تطلب منها أو تستقبل منها طلبات.
        </p>
      </div>
    </div>

    <div v-if="store.can('shop_orders.partners')" class="grid gap-4 md:grid-cols-2">
      <UCard>
        <p class="text-sm text-(--ui-text-muted)">
          كود محلك
        </p>
        <div class="mt-1 flex items-center justify-between gap-3">
          <span class="num text-3xl font-extrabold tracking-widest">{{ myCode }}</span>
          <UButton color="neutral" variant="outline" icon="i-lucide-copy" label="نسخ" @click="copyCode" />
        </div>
        <p class="mt-2 text-sm text-(--ui-text-muted)">
          ادّي الكود ده لأي محل عايز يبقى شريكك، أو ابعته على WhatsApp.
        </p>
      </UCard>

      <UCard>
        <form class="space-y-3" @submit.prevent="request">
          <UFormField label="إضافة شريك بالكود">
            <div class="flex gap-2">
              <UInput v-model="code" dir="ltr" placeholder="مثلاً K7M2QX" class="flex-1 num" :ui="{ base: 'uppercase tracking-widest' }" />
              <UButton type="submit" icon="i-lucide-user-plus" label="إرسال طلب" :loading="busy === 'request'" :disabled="!code" />
            </div>
          </UFormField>
          <p class="text-sm text-(--ui-text-muted)">
            المحل التاني بيوافق، وبعدها تقدروا تطلبوا من بعض.
          </p>
        </form>
      </UCard>
    </div>

    <UCard v-if="incoming.length && store.can('shop_orders.partners')" :ui="{ header: 'font-bold' }">
      <template #header>
        طلبات شراكة مستنية ردّك
      </template>
      <div class="divide-y divide-(--ui-border)">
        <div v-for="c in incoming" :key="c.id" class="flex items-center justify-between gap-3 py-3">
          <ShopLine :shop="c.shop" />
          <div class="flex gap-2">
            <UButton size="sm" icon="i-lucide-check" label="موافقة" :loading="busy === c.id" @click="respond(c.id, 'accept')" />
            <UButton size="sm" color="neutral" variant="ghost" label="رفض" @click="respond(c.id, 'decline')" />
          </div>
        </div>
      </div>
    </UCard>

    <UCard :ui="{ header: 'font-bold', body: 'p-0 sm:p-0' }">
      <template #header>
        شركاؤك
      </template>
      <div v-if="!partners.length" class="py-10 text-center text-(--ui-text-muted)">
        لسه مفيش شركاء.
      </div>
      <div class="divide-y divide-(--ui-border)">
        <div v-for="c in partners" :key="c.id" class="flex items-center justify-between gap-3 px-6 py-3">
          <ShopLine :shop="c.shop" />
          <UButton size="sm" variant="soft" icon="i-lucide-plus" label="طلب جديد" :to="`/shop-orders/new?seller=${c.shop?.id}`" />
        </div>
        <div v-for="c in outgoing" :key="c.id" class="flex items-center justify-between gap-3 px-6 py-3">
          <ShopLine :shop="c.shop" />
          <UBadge color="warning" variant="subtle">
            {{ c.status_label }}
          </UBadge>
        </div>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { ShopConnection } from '~/types/api'

definePageMeta({ module: 'shop_orders', permission: 'shop_orders.view' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()

const myCode = computed(() => store.session?.tenant.code ?? '')
const code = ref('')
const busy = ref<string | null>(null)

const { data, refresh } = await useAsyncData('shop-connections', () => api<{ data: ShopConnection[] }>('/shop-connections'))
const connections = computed(() => data.value?.data ?? [])
const incoming = computed(() => connections.value.filter(c => c.status === 'pending' && c.direction === 'incoming'))
const outgoing = computed(() => connections.value.filter(c => c.status === 'pending' && c.direction === 'outgoing'))
const partners = computed(() => connections.value.filter(c => c.status === 'accepted'))

async function request() {
  busy.value = 'request'
  try {
    const res = await api<{ data: ShopConnection }>('/shop-connections', { method: 'POST', body: { code: code.value.trim() } })
    toast.add({
      color: 'success',
      title: res.data.status === 'accepted' ? `بقيتوا شركاء مع ${res.data.shop?.name}` : `اتبعت طلب شراكة لـ ${res.data.shop?.name}`,
    })
    code.value = ''
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

async function respond(id: string, action: 'accept' | 'decline') {
  busy.value = id
  try {
    await api(`/shop-connections/${id}/${action}`, { method: 'POST' })
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    busy.value = null
  }
}

async function copyCode() {
  await navigator.clipboard?.writeText(myCode.value)
  toast.add({ color: 'success', title: 'الكود اتنسخ' })
}
</script>

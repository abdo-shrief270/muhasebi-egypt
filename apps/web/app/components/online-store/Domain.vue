<template>
  <div class="space-y-3 text-sm">
    <template v-if="!state?.domain || editing">
      <p class="text-(--ui-text-muted)">
        عندك دومين (زي www.elnour-mobile.com)؟ اربطه بالمتجر والزباين يفتحوه عليه، واللينك القديم بيوديهم عليه لوحده.
      </p>
      <form class="flex gap-2" @submit.prevent="save">
        <UInput v-model="domain" dir="ltr" class="min-w-0 flex-1" placeholder="www.elnour-mobile.com" aria-label="الدومين" />
        <UButton type="submit" label="اربط" :loading="busy" :disabled="domain.trim().length < 4" />
      </form>
      <UButton v-if="editing" size="xs" color="neutral" variant="ghost" label="رجوع" @click="editing = false" />
    </template>

    <template v-else>
      <div class="flex items-center gap-2">
        <span class="num min-w-0 flex-1 truncate font-bold" dir="ltr">{{ state.domain }}</span>
        <UBadge :color="state.verified ? 'success' : 'warning'" variant="subtle" :label="state.verified ? 'شغال' : 'مستني الـ DNS'" />
      </div>

      <template v-if="!state.verified || points === false">
        <p class="text-(--ui-text-muted)">
          ضيف السجلات دي في إعدادات الـ DNS عند الشركة اللي حاجز منها الدومين (Hostinger، GoDaddy، Namecheap…)، وبعدين دوس «اتأكد». ممكن تاخد من دقايق لساعات.
        </p>
        <div v-for="r in state.records" :key="r.type" class="space-y-1 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-2">
          <p class="font-bold">
            {{ r.type === 'TXT' ? '1. سجل TXT (يثبت إن الدومين بتاعك)' : '2. سجل CNAME (يوصّل الدومين بالمتجر)' }}
          </p>
          <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-1 text-xs">
            <span class="text-(--ui-text-muted)">Name</span>
            <span class="num truncate" dir="ltr">{{ r.name }}</span>
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-copy" aria-label="انسخ الاسم" @click="copy(r.name)" />
            <span class="text-(--ui-text-muted)">Value</span>
            <span class="num truncate" dir="ltr">{{ r.value }}</span>
            <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-copy" aria-label="انسخ القيمة" @click="copy(r.value)" />
          </div>
        </div>
        <p v-if="state.records.some(r => r.type === 'CNAME')" class="text-xs text-(--ui-text-muted)">
          لو الدومين من غير www (elnour-mobile.com)، شركات كتير مش بتسمح بـ CNAME عليه: استخدم ALIAS / ANAME لو موجود، أو اربط www وخلّي الدومين من غير www يحوّل عليه.
        </p>
      </template>
      <UAlert v-if="state.verified && points === false" color="warning" variant="subtle" title="الدومين بتاعك، بس لسه مش موصّل بالمتجر" description="ضيف سجل الـ CNAME (أو استنى لو لسه مضيفه) ودوس «اتأكد» تاني." />
      <p v-if="state.verified && points !== false" class="text-(--ui-text-muted)">
        المتجر بيفتح على <a :href="state.url" target="_blank" rel="noopener" class="num font-bold text-primary" dir="ltr">{{ state.url }}</a>. أول فتحة ممكن تاخد دقيقة لحد ما شهادة الأمان (HTTPS) تطلع.
      </p>

      <div class="flex flex-wrap gap-2">
        <UButton size="sm" icon="i-lucide-refresh-cw" label="اتأكد" :loading="busy" @click="verify" />
        <UButton size="sm" color="neutral" variant="ghost" label="غيّر الدومين" @click="editing = true; domain = state.domain ?? ''" />
        <UButton size="sm" color="error" variant="ghost" label="شيل الدومين" @click="remove" />
      </div>
    </template>
    <p v-if="message" class="text-sm" :class="ok ? 'text-(--ui-success)' : 'text-(--ui-error)'">
      {{ message }}
    </p>
  </div>
</template>

<script setup lang="ts">
import type { StoreDomain } from '~/types/api'

/** The shop's own domain for its store (online_store.manage). */
const api = useApi()
const toast = useToast()

const { data } = await useAsyncData('online-store-domain', () => api<{ data: StoreDomain }>('/online-store/domain'))
const state = ref<StoreDomain | null>(data.value?.data ?? null)
const domain = ref('')
const editing = ref(false)
const busy = ref(false)
const points = ref<boolean | null>(null)
const message = ref<string | null>(null)
const ok = ref(false)

async function run(fn: () => Promise<{ data: StoreDomain & { points?: boolean, owned?: boolean } }>) {
  busy.value = true
  message.value = null
  try {
    const res = (await fn()).data
    state.value = res
    return res
  }
  catch (e) {
    message.value = apiErrorMessage(e)
    ok.value = false
    return null
  }
  finally {
    busy.value = false
  }
}

async function save() {
  if (await run(() => api('/online-store/domain', { method: 'PUT', body: { domain: domain.value.trim() } }))) {
    editing.value = false
    points.value = null
  }
}

async function verify() {
  const res = await run(() => api('/online-store/domain/verify', { method: 'POST' }))
  if (res) {
    points.value = res.points ?? null
    ok.value = !!res.owned
    message.value = res.owned
      ? (res.points ? 'تمام، الدومين اتربط بالمتجر.' : 'الدومين بتاعك اتأكد. فاضل يتوصّل بالمتجر.')
      : 'لسه سجل الـ TXT مش ظاهر. اتأكد إنك كتبته صح، واستنى شوية وجرّب تاني.'
  }
}

async function remove() {
  if (await run(() => api('/online-store/domain', { method: 'DELETE' }))) {
    domain.value = ''
    points.value = null
  }
}

async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ color: 'success', title: 'اتنسخ' })
  }
  catch {
    toast.add({ color: 'error', title: 'مقدرناش ننسخ؛ انسخه بإيدك.' })
  }
}
</script>

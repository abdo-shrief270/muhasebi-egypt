<template>
  <div class="max-w-3xl space-y-6">
    <PageHeader title="التطبيق على الجهاز" description="نزّل محاسبي على الموبايل أو الكمبيوتر: يفتح بدوسة زي أي برنامج، والكاشير شغال حتى لو النت فصل." />

    <UCard>
      <div class="flex flex-wrap items-center gap-4 sm:flex-nowrap">
        <img src="/icons/icon-192.png?v=1" alt="" class="size-14 shrink-0 rounded-2xl">
        <div class="min-w-0 flex-1">
          <template v-if="state.installed">
            <p class="flex items-center gap-1.5 font-bold text-success">
              <UIcon name="i-lucide-circle-check" class="size-5" /> محاسبي متنزّل على الجهاز ده
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              افتحه من {{ state.platform === 'ios' || state.platform === 'android' ? 'الشاشة الرئيسية' : 'قايمة البرامج أو شريط المهام' }}. التحديثات بتنزل لوحدها.
            </p>
          </template>
          <template v-else>
            <p class="font-bold">
              محاسبي مش متنزّل على الجهاز ده
            </p>
            <p class="text-sm text-(--ui-text-muted)">
              {{ state.canPrompt ? 'دوس «نزّل التطبيق» وبعدين «تثبيت».' : 'اتبع الخطوات اللي تحت حسب جهازك.' }}
            </p>
          </template>
        </div>
        <UButton v-if="!state.installed && state.canPrompt" icon="i-lucide-download" label="نزّل التطبيق" size="lg" @click="install()" />
      </div>
    </UCard>

    <UCard>
      <template #header>
        <h2 class="text-lg font-bold">
          إزاي تنزّله
        </h2>
      </template>
      <UTabs v-model="tab" :items="tabs" :content="false" variant="link" class="mb-4" />
      <InstallSteps :platform="tab" />
    </UCard>

    <UCard>
      <template #header>
        <h2 class="text-lg font-bold">
          الفرق لما يبقى متنزّل
        </h2>
      </template>
      <ul class="space-y-2 text-sm">
        <li v-for="point in points" :key="point.text" class="flex gap-2">
          <UIcon :name="point.icon" class="mt-0.5 size-4 shrink-0 text-primary" />
          <span>{{ point.text }}</span>
        </li>
      </ul>
    </UCard>

    <UCard>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="font-bold">
            النسخة
          </p>
          <p class="text-sm text-(--ui-text-muted) num" dir="ltr">
            {{ version || '—' }}
          </p>
        </div>
        <UButton color="neutral" variant="outline" icon="i-lucide-refresh-cw" label="دوّر على تحديث" :loading="checking" @click="checkUpdate" />
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import type { InstallPlatform } from '~/composables/useInstallApp'

const { state, install } = useInstallApp()
const toast = useToast()
const version = appVersion()

const tab = ref<InstallPlatform>(state.value.platform)
const tabs: { label: string, value: InstallPlatform, icon: string }[] = [
  { label: 'آيفون وآيباد', value: 'ios', icon: 'i-lucide-smartphone' },
  { label: 'أندرويد', value: 'android', icon: 'i-lucide-smartphone' },
  { label: 'ويندوز ولينكس وكروم بوك', value: 'desktop', icon: 'i-lucide-monitor' },
  { label: 'ماك (سفاري)', value: 'mac-safari', icon: 'i-lucide-laptop' },
]

const points = [
  { icon: 'i-lucide-mouse-pointer-click', text: 'يفتح بدوسة من أيقونته، في شباك لوحده من غير شريط المتصفح.' },
  { icon: 'i-lucide-wifi-off', text: 'الكاشير يفتح ويبيع حتى لو النت فصل، والفواتير تتبعت أول ما يرجع.' },
  { icon: 'i-lucide-history', text: 'يرجع للصفحة اللي كنت سايبها.' },
  { icon: 'i-lucide-zap', text: 'اختصارات من الأيقونة نفسها (دوسة مطوّلة أو كليك يمين): بيع جديد، استلام جهاز، استعلام سعر، الوردية.' },
  { icon: 'i-lucide-refresh-cw', text: 'التحديثات بتنزل لوحدها؛ لما تبقى فيه نسخة جديدة هيظهرلك «حدّث».' },
]

const checking = ref(false)
async function checkUpdate() {
  const registration = 'serviceWorker' in navigator ? await navigator.serviceWorker.getRegistration() : undefined
  if (!registration) {
    toast.add({ color: 'neutral', title: 'التحديث التلقائي شغال في النسخة المنشورة بس' })
    return
  }
  checking.value = true
  try {
    await registration.update()
    if (!registration.installing && !registration.waiting) {
      toast.add({ color: 'success', icon: 'i-lucide-circle-check', title: 'إنت على آخر نسخة' })
    }
  }
  catch {
    toast.add({ color: 'warning', title: 'مقدرناش نوصل للسيرفر، جرّب تاني لما النت يرجع' })
  }
  finally {
    checking.value = false
  }
}
</script>

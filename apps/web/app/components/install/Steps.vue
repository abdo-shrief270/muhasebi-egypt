<template>
  <ol class="space-y-3">
    <li v-for="(step, i) in steps" :key="i" class="flex gap-3">
      <span class="grid size-7 shrink-0 place-items-center rounded-full bg-primary text-sm font-bold text-(--ui-bg) num">{{ i + 1 }}</span>
      <div class="min-w-0 flex-1 space-y-2">
        <p class="font-bold leading-7">
          {{ step.title }}
        </p>
        <p v-if="step.hint" class="text-sm text-(--ui-text-muted)">
          {{ step.hint }}
        </p>

        <!-- Small drawings of what to look for on the screen. -->
        <div v-if="step.art === 'ios-share'" class="flex w-full max-w-72 items-center justify-between rounded-2xl border border-(--ui-border) bg-(--ui-bg-elevated) px-4 py-2 text-(--ui-text-muted)" dir="ltr" aria-hidden="true">
          <UIcon name="i-lucide-chevron-left" class="size-5" />
          <UIcon name="i-lucide-chevron-right" class="size-5" />
          <span class="grid size-9 place-items-center rounded-full text-primary ring-2 ring-primary"><UIcon name="i-lucide-share" class="size-5" /></span>
          <UIcon name="i-lucide-book-open" class="size-5" />
          <UIcon name="i-lucide-copy" class="size-5" />
        </div>
        <div v-else-if="step.art === 'ios-add'" class="w-full max-w-72 overflow-hidden rounded-xl border border-(--ui-border) bg-(--ui-bg-elevated) text-sm" aria-hidden="true">
          <div class="flex items-center justify-between px-3 py-2 text-(--ui-text-muted)">
            <span>إضافة إلى المفضلة</span><UIcon name="i-lucide-star" class="size-4" />
          </div>
          <div class="flex items-center justify-between border-y border-(--ui-border) bg-(--app-primary-soft) px-3 py-2 font-bold text-(--app-primary-strong)">
            <span>إضافة إلى الشاشة الرئيسية</span><UIcon name="i-lucide-square-plus" class="size-4" />
          </div>
          <div class="flex items-center justify-between px-3 py-2 text-(--ui-text-muted)">
            <span>طباعة</span><UIcon name="i-lucide-printer" class="size-4" />
          </div>
        </div>
        <div v-else-if="step.art === 'ios-confirm'" class="w-full max-w-72 rounded-xl border border-(--ui-border) bg-(--ui-bg-elevated) p-3 text-sm" aria-hidden="true">
          <div class="mb-3 flex items-center justify-between">
            <span class="text-(--ui-text-muted)">إلغاء</span>
            <span class="rounded-md px-2 py-0.5 font-bold text-primary ring-2 ring-primary">إضافة</span>
          </div>
          <div class="flex items-center gap-2">
            <img src="/apple-touch-icon.png?v=1" alt="" class="size-10 rounded-[22%]">
            <span class="font-bold">محاسبي</span>
          </div>
        </div>
        <div v-else-if="step.art === 'android-menu'" class="flex w-full max-w-72 items-center gap-2 rounded-full border border-(--ui-border) bg-(--ui-bg-elevated) px-3 py-1.5 text-sm text-(--ui-text-muted)" dir="ltr" aria-hidden="true">
          <UIcon name="i-lucide-lock" class="size-4" />
          <span class="flex-1 truncate">{{ host }}</span>
          <span class="grid size-8 place-items-center rounded-full text-primary ring-2 ring-primary"><UIcon name="i-lucide-ellipsis-vertical" class="size-5" /></span>
        </div>
        <div v-else-if="step.art === 'desktop-bar'" class="flex w-full max-w-sm items-center gap-2 rounded-full border border-(--ui-border) bg-(--ui-bg-elevated) px-3 py-1.5 text-sm text-(--ui-text-muted)" dir="ltr" aria-hidden="true">
          <UIcon name="i-lucide-lock" class="size-4" />
          <span class="flex-1 truncate">{{ host }}</span>
          <span class="grid size-8 place-items-center rounded-full text-primary ring-2 ring-primary"><UIcon name="i-lucide-monitor-down" class="size-5" /></span>
          <UIcon name="i-lucide-star" class="size-4" />
        </div>
        <div v-else-if="step.art === 'app-window'" class="w-full max-w-sm overflow-hidden rounded-lg border border-(--ui-border) bg-(--ui-bg) text-xs" aria-hidden="true">
          <div class="flex items-center gap-2 border-b border-(--ui-border) bg-(--ui-bg-elevated) px-2 py-1">
            <img src="/favicon.svg?v=1" alt="" class="size-4">
            <span class="flex-1 font-bold">محاسبي</span>
            <span class="flex gap-1" dir="ltr"><span class="size-2.5 rounded-full bg-(--ui-border-accented)" /><span class="size-2.5 rounded-full bg-(--ui-border-accented)" /><span class="size-2.5 rounded-full bg-(--ui-border-accented)" /></span>
          </div>
          <div class="grid grid-cols-3 gap-1.5 p-2">
            <span class="h-6 rounded bg-(--app-primary-soft)" /><span class="h-6 rounded bg-(--ui-bg-elevated)" /><span class="h-6 rounded bg-(--ui-bg-elevated)" />
          </div>
        </div>
      </div>
    </li>
  </ol>
</template>

<script setup lang="ts">
import type { InstallPlatform } from '~/composables/useInstallApp'

type Art = 'ios-share' | 'ios-add' | 'ios-confirm' | 'android-menu' | 'desktop-bar' | 'app-window'
interface Step {
  title: string
  hint?: string
  art?: Art
}

const props = defineProps<{ platform: InstallPlatform }>()

const host = location.host

const steps = computed<Step[]>(() => {
  switch (props.platform) {
    case 'ios':
      return [
        { title: 'دوس على زرار المشاركة', hint: 'في سفاري: المربع اللي طالع منه سهم، تحت في الآيفون وفوق في الآيباد.', art: 'ios-share' },
        { title: 'انزل في القايمة واختار «إضافة إلى الشاشة الرئيسية»', hint: 'لو الآيفون بالإنجليزي: «Add to Home Screen».', art: 'ios-add' },
        { title: 'دوس «إضافة» فوق', hint: 'هتلاقي أيقونة محاسبي على الشاشة الرئيسية، افتحه منها على طول.', art: 'ios-confirm' },
      ]
    case 'android':
      return [
        { title: 'افتح قايمة كروم (⋮) فوق', art: 'android-menu' },
        { title: 'اختار «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية»', hint: 'لو الموبايل بالإنجليزي: «Install app» أو «Add to Home screen».' },
        { title: 'دوس «تثبيت»', hint: 'هتلاقي محاسبي مع باقي البرامج، ويفتح من غير شريط المتصفح.' },
      ]
    case 'mac-safari':
      return [
        { title: 'من قايمة «ملف» (File) فوق اختار «إضافة إلى Dock»', hint: 'محتاج macOS Sonoma أو أحدث. أو افتح الموقع في كروم أو إيدج ونزّله من هناك.' },
        { title: 'دوس «إضافة»', hint: 'محاسبي هيظهر في الـ Dock وفي Launchpad ويفتح في شباك لوحده.', art: 'app-window' },
      ]
    default:
      return [
        { title: 'في كروم أو إيدج: دوس أيقونة التثبيت في آخر شريط العنوان', hint: 'أو من القايمة: كروم ⋮ ← «حفظ ومشاركة» ← «تثبيت الصفحة كتطبيق»، إيدج … ← «التطبيقات» ← «تثبيت هذا الموقع كتطبيق».', art: 'desktop-bar' },
        { title: 'دوس «تثبيت»', hint: 'فايرفوكس ما بيدعمش التثبيت: افتح محاسبي في كروم أو إيدج.' },
        { title: 'محاسبي يفتح في شباك لوحده', hint: 'هتلاقيه في قايمة ابدأ (ويندوز) أو Launchpad (ماك) أو قايمة البرامج (كروم بوك ولينكس)، وتقدر تثبته في شريط المهام.', art: 'app-window' },
      ]
  }
})
</script>

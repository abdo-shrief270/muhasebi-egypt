<template>
  <div class="app-shell min-h-dvh flex bg-(--ui-bg-muted)">
    <aside class="app-sidebar sticky top-0 hidden h-dvh w-64 shrink-0 border-e border-(--ui-border) bg-(--ui-bg) px-3 py-4 lg:block">
      <AppSidebar />
    </aside>

    <USlideover v-model:open="menuOpen" side="right" title="القائمة" :ui="{ content: 'max-w-72', body: 'p-3 sm:p-3' }">
      <template #content>
        <div class="app-safe-y h-full bg-(--ui-bg) px-3 py-4">
          <AppSidebar @navigate="menuOpen = false" />
        </div>
      </template>
    </USlideover>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="app-titlebar sticky top-0 z-20 flex items-center gap-2 border-b border-(--ui-border) bg-(--ui-bg)/90 px-3 backdrop-blur sm:gap-3 lg:px-8">
        <UButton class="lg:hidden" color="neutral" variant="ghost" icon="i-lucide-menu" square aria-label="القائمة" @click="menuOpen = true" />

        <button
          type="button"
          class="flex h-10 min-w-0 flex-1 items-center gap-2 rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg-muted) px-3 text-start text-sm text-(--ui-text-muted) transition hover:border-(--ui-border-accented) sm:max-w-md"
          aria-label="بحث سريع"
          @click="searchOpen = true"
        >
          <UIcon name="i-lucide-search" class="size-4 shrink-0" />
          <span class="flex-1 truncate">دوّر على صنف أو صفحة…</span>
          <span class="hidden items-center gap-0.5 sm:flex" dir="ltr">
            <UKbd value="meta" /><UKbd value="K" />
          </span>
        </button>

        <div class="ms-auto flex items-center gap-1 sm:gap-2">
          <OfflineStatus />
          <UTooltip v-if="priceCheck.available.value" text="استعلام عن سعر (F8)">
            <UButton color="neutral" variant="outline" icon="i-lucide-tag" aria-label="استعلام عن سعر" @click="priceCheck.show()">
              <span class="hidden sm:inline">سعر</span>
            </UButton>
          </UTooltip>
          <UTooltip text="كود محلك — ادّيه لأي محل عايز يبقى شريكك. دوس عشان تنسخه.">
            <UButton color="neutral" variant="soft" class="hidden font-bold sm:inline-flex" :icon="copied ? 'i-lucide-check' : 'i-lucide-hash'" @click="copyCode">
              <span class="num">{{ store.session?.tenant.code }}</span>
            </UButton>
          </UTooltip>

          <NotificationBell />

          <UDropdownMenu :items="colorModeItems" :content="{ align: 'end' }">
            <UButton color="neutral" variant="ghost" :icon="colorModeIcon" square aria-label="شكل الألوان" />
          </UDropdownMenu>

          <UDropdownMenu :items="userItems" :content="{ align: 'end' }" :ui="{ content: 'w-60' }">
            <button type="button" class="flex items-center gap-2 rounded-(--ui-radius) p-1 transition hover:bg-(--ui-bg-elevated)" aria-label="حسابك">
              <UAvatar :alt="store.session?.user.name" size="md" />
              <span class="hidden text-start leading-tight md:block">
                <span class="block max-w-32 truncate text-sm font-bold">{{ store.session?.user.name }}</span>
                <span class="block max-w-32 truncate text-xs text-(--ui-text-muted)">{{ roleLabel }}</span>
              </span>
              <UIcon name="i-lucide-chevron-down" class="hidden size-4 text-(--ui-text-muted) md:block" />
            </button>
          </UDropdownMenu>
        </div>
      </header>

      <main class="app-main flex-1 p-4 lg:p-8">
        <BillingSubscriptionBanner />
        <slot />
      </main>
    </div>

    <GlobalSearch v-model:open="searchOpen" />
    <PriceCheck v-if="store.can('products.view')" />
    <PosOutboxPanel />
    <FeedbackModal />
    <InstallAppModal />
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'

const store = useSessionStore()
const toast = useToast()
const colorMode = useColorMode()

const menuOpen = ref(false)
const searchOpen = ref(false)
const priceCheck = usePriceCheck()
const feedback = useFeedback()
const installApp = useInstallApp()

defineShortcuts({
  meta_k: () => {
    searchOpen.value = !searchOpen.value
  },
  '/': () => {
    searchOpen.value = true
  },
  // Works while typing too: the POS keeps the cursor in its search box.
  f8: {
    usingInput: true,
    handler: () => {
      if (priceCheck.available.value) {
        searchOpen.value = false
        priceCheck.show()
      }
    },
  },
})

const roleLabel = computed(() => (store.isOwner ? 'صاحب المحل' : store.session?.user.role?.name ?? 'موظف'))

const colorModes = [
  { value: 'light', label: 'فاتح', icon: 'i-lucide-sun' },
  { value: 'dark', label: 'غامق', icon: 'i-lucide-moon' },
  { value: 'system', label: 'زي الجهاز', icon: 'i-lucide-monitor' },
] as const

const colorModeIcon = computed(() => colorModes.find(m => m.value === colorMode.preference)?.icon ?? 'i-lucide-sun')
const colorModeItems = computed<DropdownMenuItem[][]>(() => [
  [{ label: 'شكل الألوان', type: 'label' }],
  colorModes.map(mode => ({
    label: mode.label,
    icon: mode.icon,
    type: 'checkbox' as const,
    checked: colorMode.preference === mode.value,
    onSelect: (e: Event) => {
      e.preventDefault()
      colorMode.preference = mode.value
    },
  })),
])

const userItems = computed<DropdownMenuItem[][]>(() => [
  [{
    label: store.session?.user.name ?? '',
    description: [roleLabel.value, store.currentBranch?.name].filter(Boolean).join(' · '),
    avatar: { alt: store.session?.user.name },
    type: 'label',
  }],
  [
    ...(store.can('users.manage') ? [{ label: 'الموظفين', icon: 'i-lucide-users-round', to: '/settings/users' }] : []),
    ...(store.isOwner ? [{ label: 'بيانات المحل والإيصال', icon: 'i-lucide-receipt-text', to: '/settings/shop' }, { label: 'الأقسام', icon: 'i-lucide-blocks', to: '/settings/modules' }, { label: 'المميزات', icon: 'i-lucide-toggle-right', to: '/settings/features' }, { label: 'الاشتراك والفواتير', icon: 'i-lucide-credit-card', to: '/settings/billing' }] : []),
    { label: 'الأمان وتسجيل الدخول', icon: 'i-lucide-lock-keyhole', to: '/settings/security' },
    { label: 'بحث سريع', icon: 'i-lucide-search', kbds: ['meta', 'K'], onSelect: () => { searchOpen.value = true } },
    ...(priceCheck.available.value ? [{ label: 'استعلام عن سعر', icon: 'i-lucide-tag', kbds: ['F8'], onSelect: () => priceCheck.show() }] : []),
    { label: `نسخ كود المحل (${store.session?.tenant.code ?? ''})`, icon: 'i-lucide-hash', onSelect: copyCode },
  ],
  ...(installApp.installable.value ? [[{ label: 'نزّل التطبيق', description: 'افتح محاسبي من أيقونة زي أي برنامج', icon: 'i-lucide-download', onSelect: () => installApp.install() }]] : []),
  [{ label: 'ابعت ملاحظة', description: 'مشكلة، اقتراح، أو سؤال لفريق محاسبي', icon: 'i-lucide-message-square-heart', onSelect: () => feedback.show() }],
  [{ label: 'تسجيل الخروج', icon: 'i-lucide-log-out', color: 'error' as const, onSelect: () => store.logout() }],
])

const copied = ref(false)
async function copyCode() {
  const code = store.session?.tenant.code
  if (!code) {
    return
  }
  try {
    await navigator.clipboard.writeText(code)
    copied.value = true
    toast.add({ color: 'success', title: 'اتنسخ كود المحل', description: code })
    setTimeout(() => {
      copied.value = false
    }, 2000)
  }
  catch {
    toast.add({ color: 'neutral', title: `كود المحل: ${code}` })
  }
}
</script>

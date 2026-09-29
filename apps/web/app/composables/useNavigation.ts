import type { MenuGroup } from '~/types/api'

/**
 * The sidebar: home, then the modules' menu entries grouped by section, with the
 * app's own settings screens appended to the settings section.
 */
export function useNavigation() {
  const store = useSessionStore()

  const home: NavItem = { to: '/', label: 'الرئيسية', icon: 'i-lucide-layout-dashboard' }

  const settings = computed<NavItem[]>(() => [
    { to: '/settings/users', label: 'الموظفين', icon: 'i-lucide-users-round', show: store.can('users.manage') },
    { to: '/settings/roles', label: 'الأدوار والصلاحيات', icon: 'i-lucide-shield-check', show: store.can('roles.manage') },
    { to: '/settings/branches', label: 'الفروع', icon: 'i-lucide-store', show: store.can('branches.manage') },
    { to: '/settings/modules', label: 'الأقسام', icon: 'i-lucide-blocks', show: store.isOwner },
    { to: '/settings/billing', label: 'الاشتراك والفواتير', icon: 'i-lucide-credit-card', show: store.isOwner },
    { to: '/settings/audit', label: 'سجل العمليات', icon: 'i-lucide-history', show: store.can('audit.view') },
  ].filter(item => item.show).map(({ to, label, icon }) => ({ to, label, icon })))

  const groups = computed<NavGroup[]>(() => (Object.keys(NAV_GROUP_TITLES) as MenuGroup[])
    .map(key => ({
      key,
      title: NAV_GROUP_TITLES[key],
      items: [
        ...store.menu.filter(entry => entry.group === key).map(({ to, label, icon }) => ({ to, label, icon })),
        ...(key === 'settings' ? settings.value : []),
      ],
    }))
    .filter(group => group.items.length > 0))

  /** Every page the user can open, for the quick search. */
  const allItems = computed<NavItem[]>(() => [home, ...groups.value.flatMap(group => group.items)])

  return { home, groups, allItems }
}

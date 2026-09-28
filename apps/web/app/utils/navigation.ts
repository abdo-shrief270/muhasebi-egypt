import type { MenuGroup } from '~/types/api'

export interface NavItem {
  to: string
  label: string
  icon: string
}

export interface NavGroup {
  key: MenuGroup
  title: string
  items: NavItem[]
}

/** Sidebar sections in display order (the API tags each menu entry with one). */
export const NAV_GROUP_TITLES: Record<MenuGroup, string> = {
  sales: 'البيع والحسابات',
  stock: 'الأصناف والمخزون',
  services: 'الصيانة والخدمات',
  reports: 'التقارير',
  settings: 'الإعدادات',
}

export function isActivePath(currentPath: string, to: string): boolean {
  return to === '/' ? currentPath === '/' : currentPath === to || currentPath.startsWith(`${to}/`)
}

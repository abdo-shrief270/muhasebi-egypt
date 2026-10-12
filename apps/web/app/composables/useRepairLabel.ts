/**
 * The device sticker printed at intake (`RepairsDeviceLabel`): roll size and what it shows,
 * remembered on this device (a label printer belongs to one counter). The roll is the shop's
 * default (/settings/shop) until this device picks another.
 */
export type RepairLabelSize = LabelRoll

export interface RepairLabelSettings {
  /** this device's roll; null = the shop's default */
  size: string | null
  /** print the sticker right after the intake receipt */
  auto: boolean
  show: { phone: boolean, faults: boolean, technician: boolean, price: boolean, code: boolean, barcode: boolean }
}

const KEY = 'muhasebi:repair-label'
const DEFAULTS: RepairLabelSettings = {
  size: null,
  auto: false,
  show: { phone: true, faults: true, technician: true, price: true, code: false, barcode: true },
}

export function useRepairLabel() {
  const store = useSessionStore()
  const settings = useState<RepairLabelSettings>('repair-label', () => {
    try {
      const saved = JSON.parse(localStorage.getItem(KEY) ?? 'null') as Partial<RepairLabelSettings> | null
      return { ...DEFAULTS, ...saved, show: { ...DEFAULTS.show, ...saved?.show } }
    }
    catch {
      return structuredClone(DEFAULTS)
    }
  })

  watch(settings, (value) => {
    try {
      localStorage.setItem(KEY, JSON.stringify(value))
    }
    catch {
      // Private window: the choice lasts this visit only.
    }
  }, { deep: true })

  const shopDefault = computed(() => REPAIR_LABEL_SIZES.find(s => s.key === store.session?.tenant.receipt?.repair_label) ?? REPAIR_LABEL_SIZES[1]!)
  const size = computed(() => REPAIR_LABEL_SIZES.find(s => s.key === settings.value.size) ?? shopDefault.value)

  return { settings, size, shopDefault }
}

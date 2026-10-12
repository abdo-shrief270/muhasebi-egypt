/**
 * The device sticker printed at intake (`RepairsDeviceLabel`): roll size and what it shows,
 * remembered on this device (a label printer belongs to one counter).
 */
export interface RepairLabelSize { key: string, label: string, w: number, h: number }

export interface RepairLabelSettings {
  size: string
  /** print the sticker right after the intake receipt */
  auto: boolean
  show: { phone: boolean, faults: boolean, technician: boolean, price: boolean, code: boolean, barcode: boolean }
}

export const REPAIR_LABEL_SIZES: RepairLabelSize[] = [
  { key: '50x30', label: 'رول 50 × 30 مم', w: 50, h: 30 },
  { key: '58x40', label: 'رول 58 × 40 مم', w: 58, h: 40 },
  { key: '60x50', label: 'رول 60 × 50 مم', w: 60, h: 50 },
  { key: '80x50', label: 'رول 80 × 50 مم', w: 80, h: 50 },
]

const KEY = 'muhasebi:repair-label'
const DEFAULTS: RepairLabelSettings = {
  size: '58x40',
  auto: false,
  show: { phone: true, faults: true, technician: true, price: true, code: false, barcode: true },
}

export function useRepairLabel() {
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

  const size = computed(() => REPAIR_LABEL_SIZES.find(s => s.key === settings.value.size) ?? REPAIR_LABEL_SIZES[1]!)

  return { settings, size }
}

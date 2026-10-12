/**
 * Label rolls the shop prints on (mm). The owner picks the default of each kind on /settings/shop
 * (`tenant.receipt.product_label` / `repair_label`, the API's `ReceiptSettings`); same keys there.
 */
export interface LabelRoll { key: string, label: string, w: number, h: number, sheet?: boolean }

/** Product barcode labels: thermal rolls, and a 3×8 A4 sticker sheet. */
export const PRODUCT_LABEL_SIZES: LabelRoll[] = [
  { key: '38x25', label: 'رول 38 × 25 مم', w: 38, h: 25 },
  { key: '50x30', label: 'رول 50 × 30 مم', w: 50, h: 30 },
  { key: '58x40', label: 'رول 58 × 40 مم', w: 58, h: 40 },
  { key: 'a4', label: 'ورقة A4 (24 ليبل 64 × 34 مم)', w: 64, h: 34, sheet: true },
]

/** The device sticker printed at repair intake. */
export const REPAIR_LABEL_SIZES: LabelRoll[] = [
  { key: '50x30', label: 'رول 50 × 30 مم', w: 50, h: 30 },
  { key: '58x40', label: 'رول 58 × 40 مم', w: 58, h: 40 },
  { key: '60x50', label: 'رول 60 × 50 مم', w: 60, h: 50 },
  { key: '80x50', label: 'رول 80 × 50 مم', w: 80, h: 50 },
]

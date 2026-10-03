const money = new Intl.NumberFormat('ar-EG-u-nu-latn', { maximumFractionDigits: 2 })

/** Piasters → "1,250 ج" (pounds only when whole). */
export function formatPrice(piasters: number): string {
  return `${money.format(piasters / 100)} ج`
}

/** "01012345678" from +201012345678. */
export function localPhone(e164: string | null | undefined): string {
  return e164 ? e164.replace(/^\+20/, '0') : ''
}

/** A wa.me link with the message ready. */
export function whatsappLink(e164: string, text: string): string {
  return `https://wa.me/${e164.replace(/^\+/, '')}?text=${encodeURIComponent(text)}`
}

export const AVAILABILITY: Record<string, { label: string, class: string }> = {
  in: { label: 'متوفر', class: 'text-ok' },
  low: { label: 'قرّب يخلص', class: 'text-warn' },
  out: { label: 'خلص', class: 'text-bad' },
}

/** The image closest above the width wanted, for srcset. */
export function srcset(urls: Record<string, string>): string {
  return Object.entries(urls).map(([w, u]) => `${u} ${w}w`).join(', ')
}

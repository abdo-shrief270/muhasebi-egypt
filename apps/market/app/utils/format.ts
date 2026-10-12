const money = new Intl.NumberFormat('ar-EG-u-nu-latn', { maximumFractionDigits: 2 })

/** Piasters → "1,250 ج". */
export function formatPrice(piasters: number): string {
  return `${money.format(piasters / 100)} ج`
}

/** 0.35 → "350 م", 2.4 → "2.4 كم". */
export function formatKm(km: number | null | undefined): string {
  if (km === null || km === undefined) {
    return ''
  }
  return km < 1 ? `${Math.max(50, Math.round(km * 1000 / 50) * 50)} م` : `${money.format(Math.round(km * 10) / 10)} كم`
}

/** A rough trip time: walking under 1.5 km, else by car in the city. */
export function tripTime(km: number | null | undefined): string {
  if (km === null || km === undefined) {
    return ''
  }
  return km <= 1.5 ? `${minutes(Math.max(1, Math.round(km * 12)))} مشي` : `${minutes(Math.max(3, Math.round(km * 3)))} بالعربية`
}

/** 1 دقيقة، 2 دقيقتين، 3–10 دقايق، 11+ دقيقة. */
function minutes(n: number): string {
  return n === 1 ? 'دقيقة' : n === 2 ? 'دقيقتين' : n <= 10 ? `${n} دقايق` : `${n} دقيقة`
}

/** "01012345678" from +201012345678. */
export function localPhone(e164: string | null | undefined): string {
  return e164 ? e164.replace(/^\+20/, '0') : ''
}

/** A wa.me link with the message ready. */
export function whatsappLink(e164: string, text: string): string {
  return `https://wa.me/${e164.replace(/^\+/, '')}?text=${encodeURIComponent(text)}`
}

/** Directions to a place in Google Maps. */
export function directionsLink(p: { lat: number, lng: number }): string {
  return `https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}`
}

export const AVAILABILITY: Record<string, { label: string, class: string }> = {
  in: { label: 'متاح', class: 'text-ok' },
  low: { label: 'قرّب يخلص', class: 'text-warn' },
}

export const CONDITION: Record<string, string> = { new: 'جديد', used: 'مستعمل' }

export const SORT_LABELS: Record<string, string> = {
  nearest: 'الأقرب',
  price_asc: 'الأرخص',
  relevance: 'الأنسب',
  new: 'الأحدث',
  price_desc: 'الأغلى',
}

/** The first letter of a shop's name, for its badge. */
export function initial(name: string | null | undefined): string {
  const n = (name ?? '').trim().replace(/^ال(?=\S{2})/, '')
  return n.charAt(0) || 'م'
}

/** The used devices' grades as buyers read them. */
export const GRADE_LABELS: Record<string, string> = { A: 'زي الجديد', B: 'حالة كويسة', C: 'فيه عيوب' }

/** 128 → "128GB", 1024 → "1TB". */
export function storageLabel(gb: number): string {
  return gb >= 1024 ? `${gb / 1024}TB` : `${gb}GB`
}

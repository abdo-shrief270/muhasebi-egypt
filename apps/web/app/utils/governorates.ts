/** Egypt's governorates, as `Identity\Enums\Governorate` (same keys and names). */
export const GOVERNORATES: { value: string, label: string }[] = [
  { value: 'cairo', label: 'القاهرة' },
  { value: 'giza', label: 'الجيزة' },
  { value: 'alexandria', label: 'الإسكندرية' },
  { value: 'qalyubia', label: 'القليوبية' },
  { value: 'sharqia', label: 'الشرقية' },
  { value: 'dakahlia', label: 'الدقهلية' },
  { value: 'gharbia', label: 'الغربية' },
  { value: 'monufia', label: 'المنوفية' },
  { value: 'beheira', label: 'البحيرة' },
  { value: 'kafr_el_sheikh', label: 'كفر الشيخ' },
  { value: 'damietta', label: 'دمياط' },
  { value: 'port_said', label: 'بورسعيد' },
  { value: 'ismailia', label: 'الإسماعيلية' },
  { value: 'suez', label: 'السويس' },
  { value: 'faiyum', label: 'الفيوم' },
  { value: 'beni_suef', label: 'بني سويف' },
  { value: 'minya', label: 'المنيا' },
  { value: 'asyut', label: 'أسيوط' },
  { value: 'sohag', label: 'سوهاج' },
  { value: 'qena', label: 'قنا' },
  { value: 'luxor', label: 'الأقصر' },
  { value: 'aswan', label: 'أسوان' },
  { value: 'red_sea', label: 'البحر الأحمر' },
  { value: 'new_valley', label: 'الوادي الجديد' },
  { value: 'matrouh', label: 'مطروح' },
  { value: 'north_sinai', label: 'شمال سيناء' },
  { value: 'south_sinai', label: 'جنوب سيناء' },
]

/**
 * Coordinates from what an owner pastes: "30.0444, 31.2357" or a Google Maps link
 * (…/@30.04,31.23,17z, ?q=30.04,31.23, !3d30.04!4d31.23). Short links (maps.app.goo.gl) carry none.
 */
export function parseLatLng(text: string): { lat: number, lng: number } | null {
  const t = text.trim()
  const patterns = [
    /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
    /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
    /[?&](?:q|ll|query|destination)=(-?\d+(?:\.\d+)?)(?:,|%2C)\s*(-?\d+(?:\.\d+)?)/i,
    /^(-?\d+(?:\.\d+)?)\s*[,،\s]\s*(-?\d+(?:\.\d+)?)$/,
  ]
  for (const re of patterns) {
    const m = t.match(re)
    if (m) {
      const lat = Number(m[1])
      const lng = Number(m[2])
      if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
        return { lat: Math.round(lat * 1e6) / 1e6, lng: Math.round(lng * 1e6) / 1e6 }
      }
    }
  }
  return null
}

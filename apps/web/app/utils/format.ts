/** Piasters → "1,234.50 ج" (drops .00). */
export function formatMoney(piasters: number | null | undefined): string {
  if (piasters === null || piasters === undefined) {
    return '—'
  }
  const pounds = piasters / 100
  return `${pounds.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ج`
}

export function formatDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) {
    return '—'
  }
  return new Date(iso).toLocaleString('ar-EG-u-nu-latn', withTime
    ? { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }
    : { day: 'numeric', month: 'short', year: 'numeric' })
}

/** 01xxxxxxxxx / +201xxxxxxxxx → wa.me link with a prefilled message. */
export function whatsappLink(phone: string, text: string): string {
  const digits = phone.replace(/\D/g, '').replace(/^0/, '20')
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
}

/** +201012345678 → 01012345678, the way people read and type it. */
export function localPhone(phone: string | null | undefined): string {
  return phone ? phone.replace(/^\+20/, '0') : ''
}

/** "دلوقتي" / "من 5 دقايق" / "من ساعتين" / the time today / the date — for live feeds. */
export function timeAgo(iso: string, now = Date.now()): string {
  const seconds = Math.max(0, Math.round((now - Date.parse(iso)) / 1000))
  if (seconds < 45) {
    return 'دلوقتي'
  }
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) {
    return minutes === 1 ? 'من دقيقة' : minutes === 2 ? 'من دقيقتين' : `من ${minutes} ${minutes <= 10 ? 'دقايق' : 'دقيقة'}`
  }
  const hours = Math.round(minutes / 60)
  if (hours < 6) {
    return hours === 1 ? 'من ساعة' : hours === 2 ? 'من ساعتين' : `من ${hours} ساعات`
  }
  const date = new Date(iso)
  const today = new Date(now)
  return date.toDateString() === today.toDateString()
    ? date.toLocaleTimeString('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit' })
    : formatDate(iso, true)
}

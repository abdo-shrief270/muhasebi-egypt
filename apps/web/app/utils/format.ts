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

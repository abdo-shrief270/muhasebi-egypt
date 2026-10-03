import type { UsedDeviceGrade, UsedDeviceStatus } from '~/types/api'

/** Egyptian national ID governorate codes (digits 8–9), as the API checks them. */
const GOVERNORATES: Record<string, string> = {
  '01': 'القاهرة', '02': 'الإسكندرية', '03': 'بورسعيد', '04': 'السويس',
  '11': 'دمياط', '12': 'الدقهلية', '13': 'الشرقية', '14': 'القليوبية', '15': 'كفر الشيخ',
  '16': 'الغربية', '17': 'المنوفية', '18': 'البحيرة', '19': 'الإسماعيلية',
  '21': 'الجيزة', '22': 'بني سويف', '23': 'الفيوم', '24': 'المنيا', '25': 'أسيوط',
  '26': 'سوهاج', '27': 'قنا', '28': 'أسوان', '29': 'الأقصر',
  '31': 'البحر الأحمر', '32': 'الوادي الجديد', '33': 'مطروح', '34': 'شمال سيناء', '35': 'جنوب سيناء',
  '88': 'مولود بره مصر',
}

/** Arabic-Indic digits → Latin, spaces and dashes dropped. */
export function latinDigits(raw: string): string {
  return raw.replace(/[٠-٩]/g, d => String(d.charCodeAt(0) - 0x0660)).replace(/[\s-]+/g, '')
}

export type ParsedNationalId =
  | { valid: true, birthDate: string, age: number, gender: 'male' | 'female', governorate: string }
  | { valid: false, error: string }

/** Same rules as the API's NationalId: century 2/3, a real birth date not in the future, a known governorate. */
export function parseNationalId(raw: string, today = new Date()): ParsedNationalId {
  const n = latinDigits(raw)
  if (!/^\d{14}$/.test(n)) {
    return { valid: false, error: 'الرقم القومي لازم يبقى 14 رقم.' }
  }
  const century = n[0] === '2' ? 1900 : n[0] === '3' ? 2000 : null
  if (century === null) {
    return { valid: false, error: 'أول رقم في الرقم القومي لازم يبقى 2 أو 3.' }
  }
  const year = century + Number(n.slice(1, 3))
  const month = Number(n.slice(3, 5))
  const day = Number(n.slice(5, 7))
  const birth = new Date(year, month - 1, day)
  if (birth.getFullYear() !== year || birth.getMonth() !== month - 1 || birth.getDate() !== day) {
    return { valid: false, error: 'تاريخ الميلاد اللي في الرقم القومي مش صحيح.' }
  }
  if (birth > today) {
    return { valid: false, error: 'تاريخ الميلاد اللي في الرقم القومي لسه ماجاش.' }
  }
  const governorate = GOVERNORATES[n.slice(7, 9)]
  if (!governorate) {
    return { valid: false, error: 'كود المحافظة اللي في الرقم القومي مش صحيح.' }
  }
  let age = today.getFullYear() - year
  if (today.getMonth() < month - 1 || (today.getMonth() === month - 1 && today.getDate() < day)) {
    age--
  }
  const pad = (x: number) => String(x).padStart(2, '0')
  return { valid: true, birthDate: `${year}-${pad(month)}-${pad(day)}`, age, gender: Number(n[12]) % 2 === 1 ? 'male' : 'female', governorate }
}

/** 15 digits with a valid Luhn check digit. */
export function isValidImei(raw: string): boolean {
  const imei = latinDigits(raw).replace(/\D/g, '')
  if (!/^\d{15}$/.test(imei)) {
    return false
  }
  let sum = 0
  for (let i = 0; i < 15; i++) {
    let d = Number(imei[i])
    if (i % 2 === 1) {
      d *= 2
      if (d > 9) {
        d -= 9
      }
    }
    sum += d
  }
  return sum % 10 === 0
}

export function gradeColor(grade: UsedDeviceGrade): 'success' | 'info' | 'warning' {
  return grade === 'A' ? 'success' : grade === 'B' ? 'info' : 'warning'
}

export function usedStatusColor(status: UsedDeviceStatus): 'primary' | 'success' | 'neutral' {
  return status === 'in_stock' ? 'primary' : status === 'sold' ? 'success' : 'neutral'
}

/**
 * A phone photo shrunk before upload (longest side ≤ maxSide, JPEG): a 4–10 MB camera shot becomes
 * a few hundred KB, which uploads fast on a shop's connection and stays well under the API limit.
 */
export async function shrinkPhoto(file: File, maxSide = 1600, quality = 0.82): Promise<File> {
  if (!file.type.startsWith('image/')) {
    return file
  }
  try {
    const bitmap = await createImageBitmap(file)
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * scale)
    canvas.height = Math.round(bitmap.height * scale)
    const ctx = canvas.getContext('2d')
    // JPEG has no transparency: a cut-out product photo gets white behind it, not black.
    if (ctx) {
      ctx.fillStyle = '#ffffff'
      ctx.fillRect(0, 0, canvas.width, canvas.height)
      ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    }
    bitmap.close()
    const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))
    if (!blob || blob.size >= file.size) {
      return file
    }
    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })
  }
  catch {
    return file
  }
}
